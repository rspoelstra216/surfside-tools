import http from "node:http";
import { spawn } from "node:child_process";
import { createReadStream, createWriteStream } from "node:fs";
import { mkdtemp, rm, stat, readdir } from "node:fs/promises";
import { tmpdir } from "node:os";
import path from "node:path";
import { Readable } from "node:stream";
import { pipeline } from "node:stream/promises";
import { sermonAudioKey, podcastAudioArgs, extractSermonAudioArgs, chunkSermonAudioArgs, highlightChunkResponse } from "./sermon-audio.mjs";
import { detectSpeechPauses } from "./pause-detection.mjs";

const STORAGE = "http://storage.internal/";
const idPattern = /^[A-Za-z0-9_-]{11}$/;
let busy = false;

async function downloadIfPresent(key, dest) {
  const response = await fetch(STORAGE + key);
  if (response.status === 404) return false;
  if (!response.ok || !response.body) throw Error("input_not_available");
  await pipeline(Readable.fromWeb(response.body), createWriteStream(dest));
  const size = (await stat(dest)).size;
  if (!size || size > 5 * 1024 ** 3) throw Error("invalid_input_size");
  return true;
}

async function download(key, dest) {
  if (!await downloadIfPresent(key, dest)) throw Error("input_not_available");
}

// Sends a file with a known length; R2 is immutable for existing keys.
async function upload(key, file) {
  const bytes = (await stat(file)).size;
  if (bytes < 1024 || bytes > 300 * 1024 * 1024) throw Error("output_size_invalid");
  const response = await fetch(STORAGE + key, {
    method: "PUT",
    headers: { "content-type": "audio/mpeg", "content-length": String(bytes) },
    body: Readable.toWeb(createReadStream(file)),
    duplex: "half",
  });
  if (!response.ok && response.status !== 409)
    throw Error("r2_output_failed_" + response.status);
  return bytes;
}

function processAudio(intro, sermon, outro, output, sermonOnly = null) {
  return new Promise((resolve, reject) => {
    const args = podcastAudioArgs(intro, sermon, outro, output, sermonOnly);
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
    const sermonOnly = path.join(dir, "sermon-only.mp3");
    await download((test ? "podcast-test-input/" : "sermons/") + id + ".mp4", sermon);
    await download("podcast-assets/intro.mp3", intro);
    await download("podcast-assets/outro.mp3", outro);
    let sidecarReady = false;
    try {
      // One FFmpeg read of the source MP4 creates the original podcast mix
      // and a small, source-time-aligned sermon-only audio sidecar.
      await processAudio(intro, sermon, outro, output, sermonOnly);
      sidecarReady = true;
    } catch (error) {
      // A sidecar encoding error must never prevent a playable podcast.
      console.error("Combined podcast/sermon audio processing failed; using original podcast path", String(error));
      await rm(sermonOnly, { force: true });
      await processAudio(intro, sermon, outro, output);
    }
    // Podcast output is authoritative; a sidecar upload is best effort.
    const bytes = await upload((test ? "podcast-test-audio/" : "podcast-audio/") + id + ".mp3", output);
    if (sidecarReady) {
      try {
        await upload(sermonAudioKey(id, test), sermonOnly);
      } catch (error) {
        console.error("Unable to save optional sermon-only audio", id, String(error));
      }
    }
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

// Manual highlights pilot: split the reusable sermon-only audio into short chunks.
// Never use the bumper-inclusive podcast MP3 because it shifts video timestamps.
async function prepareHighlightChunks(id) {
  const dir = await mkdtemp(path.join(tmpdir(), "surfside-highlights-"));
  try {
    const audio = path.join(dir, "sermon-only.mp3");
    // New sermons already have this sidecar from normal podcast production.
    // Existing sermons are backfilled once, and later runs reuse that MP3.
    if (!await downloadIfPresent(sermonAudioKey(id), audio)) {
      const source = path.join(dir, "sermon.mp4");
      await download("sermons/" + id + ".mp4", source);
      const sourceDuration = await durationOf(source);
      if (sourceDuration > 72 * 60) throw Error("highlight_source_too_long");
      await runCommand("ffmpeg", extractSermonAudioArgs(source, audio));
      try {
        await upload(sermonAudioKey(id), audio);
      } catch (error) {
        // Transcript analysis can still proceed using this local copy.
        console.error("Could not cache extracted historical sermon audio", id, String(error));
      }
    }
    const duration = await durationOf(audio);
    if (duration > 72 * 60 + 5) throw Error("highlight_audio_too_long");
    const pattern = path.join(dir, "chunk-%03d.mp3");
    await runCommand("ffmpeg", chunkSermonAudioArgs(audio, pattern));
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
      await upload(key, file);
      chunks.push({
        key,
        offset: Math.round(offset * 100) / 100,
        duration: Math.round(seconds * 100) / 100,
      });
      offset += seconds;
    }
    // Best-effort silence scan runs on sermon-only audio (not published podcasts).
    // A failed scan cannot prevent transcription or highlight selection.
    let pauses = [];
    try { pauses = await detectSpeechPauses(audio); }
    catch (error) { console.error("Optional sermon pause scan failed", id, String(error)); }
    return { chunks, pauses };
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
      const manifest = await prepareHighlightChunks(id);
      response.writeHead(200, { "content-type": "application/json" })
        .end(JSON.stringify(highlightChunkResponse(manifest)));
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
