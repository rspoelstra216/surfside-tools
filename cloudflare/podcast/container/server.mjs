import http from "node:http";
import { spawn } from "node:child_process";
import { createReadStream, createWriteStream } from "node:fs";
import { mkdtemp, rm, stat, readdir } from "node:fs/promises";
import { tmpdir } from "node:os";
import path from "node:path";
import { Readable } from "node:stream";
import { pipeline } from "node:stream/promises";

const STORAGE = "http://storage.internal/";
const idPattern = /^[A-Za-z0-9_-]{11}$/;
let busy = false;

async function download(key, dest) {
  const response = await fetch(STORAGE + key);
  if (!response.ok || !response.body) throw Error("input_not_available");
  await pipeline(Readable.fromWeb(response.body), createWriteStream(dest));
  const size = (await stat(dest)).size;
  if (!size || size > 5 * 1024 ** 3) throw Error("invalid_input_size");
}

function processAudio(intro, sermon, outro, output) {
  return new Promise((resolve, reject) => {
    const args = [
      "-hide_banner", "-nostdin", "-loglevel", "error", "-y",
      "-i", intro, "-i", sermon, "-i", outro,
      "-filter_complex",
      "[0:a:0]aresample=44100,aformat=sample_fmts=fltp:channel_layouts=stereo,asetpts=PTS-STARTPTS[a0];" +
      "[1:a:0]aresample=44100,aformat=sample_fmts=fltp:channel_layouts=stereo,asetpts=PTS-STARTPTS[a1];" +
      "[2:a:0]aresample=44100,aformat=sample_fmts=fltp:channel_layouts=stereo,asetpts=PTS-STARTPTS[a2];" +
      "[a0][a1][a2]concat=n=3:v=0:a=1,loudnorm=I=-16:TP=-1.5:LRA=11[out]",
      "-map", "[out]", "-vn", "-c:a", "libmp3lame", "-b:a", "128k",
      "-ar", "44100", "-ac", "2", output
    ];
    const child = spawn("ffmpeg", args, { stdio: ["ignore", "ignore", "pipe"] });
    let stderr = "";
    child.stderr.on("data", (data) => { stderr = (stderr + data.toString()).slice(-2000); });
    const timeout = setTimeout(() => child.kill("SIGKILL"), 11 * 60 * 1000);
    child.once("error", (error) => { clearTimeout(timeout); reject(error); });
    child.once("close", (code) => {
      clearTimeout(timeout);
      if (code === 0) resolve();
      else reject(Error("ffmpeg_failed: " + stderr.slice(-160)));
    });
  });
}

async function produce(id, test = false) {
  const dir = await mkdtemp(path.join(tmpdir(), "surfside-podcast-"));
  try {
    const sermon = path.join(dir, "sermon.mp4");
    const intro = path.join(dir, "intro.mp3");
    const outro = path.join(dir, "outro.mp3");
    const output = path.join(dir, "episode.mp3");
    await download((test ? "podcast-test-input/" : "sermons/") + id + ".mp4", sermon);
    await download("podcast-assets/intro.mp3", intro);
    await download("podcast-assets/outro.mp3", outro);
    await processAudio(intro, sermon, outro, output);
    const bytes = (await stat(output)).size;
    if (bytes < 1024 || bytes > 300 * 1024 * 1024) throw Error("output_size_invalid");
    const response = await fetch(STORAGE + (test ? "podcast-test-audio/" : "podcast-audio/") + id + ".mp3", {
      method: "PUT",
      // The Worker needs this exact byte count for R2's fixed-length stream.
      headers: { "content-type": "audio/mpeg", "content-length": String(bytes) },
      body: Readable.toWeb(createReadStream(output)),
      duplex: "half",
    });
    if (!response.ok && response.status !== 409) throw Error("r2_output_failed_" + response.status);
    return bytes;
  } finally {
    await rm(dir, { recursive: true, force: true });
  }
}


function runCommand(command, args, timeoutMs = 11 * 60 * 1000) {
  return new Promise((resolve, reject) => {
    const child = spawn(command, args, { stdio: ["ignore", "pipe", "pipe"] });
    let stdout = "", stderr = "", settled = false;
    const timer = setTimeout(() => child.kill("SIGKILL"), timeoutMs);
    child.stdout.on("data", (data) => { stdout = (stdout + data.toString()).slice(-2048); });
    child.stderr.on("data", (data) => { stderr = (stderr + data.toString()).slice(-2048); });
    child.once("error", (error) => {
      if (!settled) { settled = true; clearTimeout(timer); reject(error); }
    });
    child.once("close", (code) => {
      if (settled) return;
      settled = true; clearTimeout(timer);
      if (code === 0) resolve(stdout);
      else reject(Error(command + "_failed: " + stderr.slice(-160)));
    });
  });
}

async function durationOf(file) {
  const output = await runCommand("ffprobe", [
    "-v", "error", "-show_entries", "format=duration",
    "-of", "default=noprint_wrappers=1:nokey=1", file
  ], 30_000);
  const seconds = Number(output.trim());
  if (!Number.isFinite(seconds) || seconds <= 0) throw Error("invalid_audio_duration");
  return seconds;
}

// Manual highlights pilot: create short, playable audio chunks from the SOURCE MP4.
// The podcast MP3 is deliberately not used because its intro shifts timestamps.
async function prepareHighlightChunks(id) {
  const dir = await mkdtemp(path.join(tmpdir(), "surfside-highlights-"));
  try {
    const source = path.join(dir, "sermon.mp4");
    await download("sermons/" + id + ".mp4", source);
    const duration = await durationOf(source);
    if (duration > 72 * 60) throw Error("highlight_source_too_long");
    const pattern = path.join(dir, "chunk-%03d.mp3");
    await runCommand("ffmpeg", [
      "-hide_banner", "-nostdin", "-loglevel", "error", "-y",
      "-i", source, "-map", "0:a:0", "-vn", "-ac", "1", "-ar", "16000",
      "-af", "aresample=async=1:first_pts=0",
      "-c:a", "libmp3lame", "-b:a", "48k",
      "-f", "segment", "-segment_time", "180", "-reset_timestamps", "1",
      pattern,
    ]);
    const names = (await readdir(dir)).filter((name) => /^chunk-[0-9]{3}\.mp3$/.test(name)).sort();
    if (!names.length || names.length > 24) throw Error("invalid_highlight_chunk_count");
    const chunks = [];
    let offset = 0;
    for (let i = 0; i < names.length; i++) {
      const file = path.join(dir, names[i]);
      const bytes = (await stat(file)).size;
      if (bytes < 1024 || bytes > 3 * 1024 * 1024) throw Error("highlight_chunk_size_invalid");
      const seconds = await durationOf(file);
      if (seconds > 185) throw Error("highlight_chunk_duration_invalid");
      const key = "sermon-highlights/chunks/" + id + "/" + String(i).padStart(3, "0") + ".mp3";
      const put = await fetch(STORAGE + key, {
        method: "PUT",
        headers: { "content-type": "audio/mpeg", "content-length": String(bytes) },
        body: Readable.toWeb(createReadStream(file)),
        duplex: "half",
      });
      if (!put.ok && put.status !== 409) throw Error("highlight_chunk_upload_failed");
      chunks.push({
        key,
        offset: Math.round(offset * 100) / 100,
        duration: Math.round(seconds * 100) / 100,
      });
      offset += seconds;
    }
    return chunks;
  } finally {
    await rm(dir, { recursive: true, force: true });
  }
}

http.createServer(async (request, response) => {
  if (request.url === "/health" && request.method === "GET") {
    response.writeHead(200).end("ok"); return;
  }
  if (!["/process", "/highlights/chunks"].includes(request.url) || request.method !== "POST") {
    response.writeHead(404).end("not_found"); return;
  }
  if (busy) { response.writeHead(409).end("busy"); return; }
  busy = true;
  try {
    let body = "";
    for await (const chunk of request) {
      body += chunk.toString();
      if (body.length > 256) throw Error("request_too_large");
    }
    const payload = JSON.parse(body);
    const id = payload?.video_id;
    const test = payload?.test === true;
    if (payload?.test !== undefined && typeof payload.test !== "boolean") {
      response.writeHead(400).end("invalid_test_flag"); return;
    }
    if (!idPattern.test(id)) { response.writeHead(400).end("invalid_id"); return; }
    if (request.url === "/highlights/chunks") {
      if (test || payload?.test !== undefined) {
        response.writeHead(400).end("highlight_test_not_supported"); return;
      }
      const chunks = await prepareHighlightChunks(id);
      response.writeHead(200, { "content-type": "application/json" })
        .end(JSON.stringify({ ok: true, chunks }));
    } else {
      const bytes = await produce(id, test);
      response.writeHead(200, { "content-type": "application/json" })
        .end(JSON.stringify({ ok: true, bytes }));
    }
  } catch (error) {
    console.error("Podcast container job failed", String(error));
    response.writeHead(500).end("processing_failed");
  } finally {
    busy = false;
  }
}).listen(8080, "0.0.0.0");
