import http from "node:http";
import { spawn } from "node:child_process";
import { createReadStream, createWriteStream } from "node:fs";
import { mkdtemp, rm, stat } from "node:fs/promises";
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

async function produce(id) {
  const dir = await mkdtemp(path.join(tmpdir(), "surfside-podcast-"));
  try {
    const sermon = path.join(dir, "sermon.mp4");
    const intro = path.join(dir, "intro.mp3");
    const outro = path.join(dir, "outro.mp3");
    const output = path.join(dir, "episode.mp3");
    await download("sermons/" + id + ".mp4", sermon);
    await download("podcast-assets/intro.mp3", intro);
    await download("podcast-assets/outro.mp3", outro);
    await processAudio(intro, sermon, outro, output);
    const bytes = (await stat(output)).size;
    if (bytes < 1024 || bytes > 300 * 1024 * 1024) throw Error("output_size_invalid");
    const response = await fetch(STORAGE + "podcast-audio/" + id + ".mp3", {
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

http.createServer(async (request, response) => {
  if (request.url === "/health" && request.method === "GET") {
    response.writeHead(200).end("ok"); return;
  }
  if (request.url !== "/process" || request.method !== "POST") {
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
    const id = JSON.parse(body)?.video_id;
    if (!idPattern.test(id)) { response.writeHead(400).end("invalid_id"); return; }
    const bytes = await produce(id);
    response.writeHead(200, { "content-type": "application/json" })
      .end(JSON.stringify({ ok: true, bytes }));
  } catch (error) {
    console.error("Podcast container job failed", String(error));
    response.writeHead(500).end("processing_failed");
  } finally {
    busy = false;
  }
}).listen(8080, "0.0.0.0");
