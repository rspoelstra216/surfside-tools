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
import { clipVideoArgs, previewKey, exportKey } from "./highlight-video.mjs";
import {
  verticalExtractArgs, verticalFilter, verticalRenderArgs,
  verticalTrackingProxyArgs, parseVerticalDimensions, VERTICAL_RENDER_VERSION,
} from "./vertical-video.mjs";

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
async function upload(key, file, contentType = "audio/mpeg") {
  const bytes = (await stat(file)).size;
  if (bytes < 1024 || bytes > 300 * 1024 * 1024) throw Error("output_size_invalid");
  const response = await fetch(STORAGE + key, {
    method: "PUT",
    headers: { "content-type": contentType, "content-length": String(bytes) },
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
    let stdout = "", stderr = "", settled = false, timedOut = false;
    const timer = setTimeout(() => {
      timedOut = true;
      child.kill("SIGKILL");
    }, timeoutMs);
    child.stdout.on("data", (data) => { stdout = (stdout + data.toString()).slice(-2048); });
    child.stderr.on("data", (data) => { stderr = (stderr + data.toString()).slice(-2048); });
    child.once("error", (error) => {
      if (!settled) { settled = true; clearTimeout(timer); reject(error); }
    });
    child.once("close", (code) => {
      if (settled) return;
      settled = true; clearTimeout(timer);
      if (code === 0) resolve(stdout);
      else reject(Error(command + (timedOut ? "_timed_out" : "_failed") +
        ": exit=" + code + " " + stderr.slice(-500)));
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


// Manually approved ranges are re-validated inside the Container. Download the
// original MP4 once for the batch and render each private review clip accurately.
async function renderHighlightClips(id, clips, kind = "preview") {
  if (!["preview", "export"].includes(kind)) throw Error("invalid_clip_type");
  if (!Array.isArray(clips) || clips.length < 1 ||
      clips.length > (kind === "export" ? 10 : 5))
    throw Error("invalid_clip_batch");
  const seen = new Set();
  let totalSeconds = 0;
  for (const clip of clips) {
    if (!clip || !Number.isInteger(clip.number) || clip.number < 1 ||
        clip.number > 10 || seen.has(clip.number) ||
        !Number.isFinite(clip.start) || !Number.isFinite(clip.end) ||
        clip.start < 0 || clip.end > 72 * 60 ||
        clip.end - clip.start < 15 || clip.end - clip.start > 120)
      throw Error("invalid_clip_selection");
    seen.add(clip.number);
    totalSeconds += clip.end - clip.start;
  }
  if (totalSeconds > 420) throw Error("clip_batch_too_long");
  const usingPreviews = kind === "export" && clips.some((c) => c.source_preview);
  for (const clip of clips) {
    if (clip.source_preview !== undefined) {
      const source = clip.source_preview;
      if (kind !== "export" || !source || Array.isArray(source) ||
          typeof source !== "object" ||
          Object.keys(source).some((key) => !["start", "end"].includes(key)) ||
          !Number.isFinite(source.start) || !Number.isFinite(source.end) ||
          source.start < 0 || source.end > 4320 || source.end <= source.start ||
          source.end - source.start > 120 ||
          source.start > clip.start + 0.001 || source.end < clip.end - 0.001)
        throw Error("invalid_preview_source");
    }
  }
  const dir = await mkdtemp(path.join(tmpdir(), "surfside-clips-"));
  try {
    const needsOriginal = kind === "preview" ||
      clips.some((clip) => !clip.source_preview);
    const video = path.join(dir, "sermon.mp4");
    if (needsOriginal) {
      await download("sermons/" + id + ".mp4", video);
      const duration = await durationOf(video);
      if (duration > 72 * 60 + 5 ||
          clips.some((clip) => !clip.source_preview &&
            clip.end > duration + 0.25))
        throw Error("clip_outside_sermon");
    }
    for (const clip of clips) {
      let sourceFile = video;
      let start = clip.start, end = clip.end;
      if (kind === "export" && clip.source_preview) {
        const padded = clip.source_preview;
        sourceFile = path.join(dir, "preview-" + clip.number + ".mp4");
        // Only fetch a deterministic, validated clip key; never a URL or
        // arbitrary file path supplied by a browser.
        await download(previewKey(id, {
          number: clip.number, start: padded.start, end: padded.end,
        }), sourceFile);
        const duration = await durationOf(sourceFile);
        start = Math.max(0, Math.round((clip.start - padded.start) * 100) / 100);
        end = Math.round((clip.end - padded.start) * 100) / 100;
        if (end > duration + 0.25) throw Error("preview_source_too_short");
      }
      const file = path.join(dir, "clip-" + clip.number + ".mp4");
      await runCommand("ffmpeg", clipVideoArgs(sourceFile, file, start, end),
        11 * 60 * 1000);
      const key = kind === "export" ? exportKey(id, clip) : previewKey(id, clip);
      await upload(key, file, "video/mp4");
      await rm(file, { force: true });
      if (sourceFile !== video) await rm(sourceFile, { force: true });
    }
    if (usingPreviews) console.log("Private padded preview source reused for export", id);
    return clips.map((clip) => ({
      number: clip.number,
      key: kind === "export" ? exportKey(id, clip) : previewKey(id, clip),
    }));
  } finally {
    await rm(dir, { recursive: true, force: true });
  }
}

// Experimental, manual-only portrait conversion. The subject detector is
// offline and never uploads frames, metadata about faces, or source images.
async function renderVertical(id, clip, anchor) {
  if (!clip || Object.keys(clip).some((key) =>
        !["number", "start", "end"].includes(key)) ||
      !Number.isInteger(clip.number) || clip.number < 1 || clip.number > 10 ||
      !Number.isFinite(clip.start) || !Number.isFinite(clip.end) ||
      clip.start < 0 || clip.end > 4320 ||
      clip.end - clip.start < 15 || clip.end - clip.start > 120 ||
      !Number.isInteger(anchor) || anchor < 0 || anchor > 100)
    throw Error("invalid_vertical_request");
  const key = `sermon-highlights/vertical/${id}/${clip.number}-${Math.round(clip.start * 100)}-${Math.round(clip.end * 100)}-a${anchor}.mp4`;
  const dir = await mkdtemp(path.join(tmpdir(), "surfside-vertical-"));
  let stage = "source";
  try {
    const original = path.join(dir, "sermon.mp4");
    const short = path.join(dir, "approved.mp4");
    const commands = path.join(dir, "tracking.txt");
    const output = path.join(dir, "portrait.mp4");
    // Use original MP4, not compressed 720p review footage; portrait cropping
    // only retains about one third of a wide image's horizontal pixels.
    await download("sermons/" + id + ".mp4", original);
    const length = await durationOf(original);
    if (clip.end > length + 0.25) throw Error("vertical_cut_outside_sermon");
    stage = "extraction";
    await runCommand("ffmpeg",
      verticalExtractArgs(original, short, clip.start, clip.end), 11 * 60 * 1000);
    // Full source is no longer needed after the accurate short extraction.
    await rm(original, { force: true });
    stage = "tracking";
    // Track a tiny, 2-fps copy of the approved segment. Never send video
    // frames elsewhere or sacrifice quality of the final original-source
    // render. A failed detector is not a failed MP4: explicitly return a
    // full-width vertical layout instead of retrying costly analysis.
    const proxy = path.join(dir, "tracking-proxy.mp4");
    let tracking = {
      mode: "wide_fallback", confidence: 0,
      face_samples: 0, samples: 0,
      fallback_reason: "tracking_unavailable",
    };
    try {
      const sourceSize = parseVerticalDimensions(await runCommand("ffprobe", [
        "-v", "error", "-select_streams", "v:0",
        "-show_entries", "stream=width,height",
        "-of", "csv=p=0:s=x", short,
      ], 30_000));
      await runCommand("ffmpeg",
        verticalTrackingProxyArgs(short, proxy), 120_000);
      const outputText = await runCommand("/usr/bin/python3", [
        "/app/track-subject.py", proxy, commands, String(anchor),
        String(sourceSize.width), String(sourceSize.height),
      ], 120_000);
      tracking = JSON.parse(outputText.trim());
      if (!["tracked", "wide_fallback"].includes(tracking.mode))
        throw Error("vertical_tracking_mode_invalid");
      if (tracking.mode === "wide_fallback")
        tracking.fallback_reason = "insufficient_face_detections";
    } catch (error) {
      console.error("Private vertical tracking unavailable: switching to full-width portrait fallback",
        id, String(error));
      tracking = {
        mode: "wide_fallback", confidence: 0,
        face_samples: 0, samples: 0,
        fallback_reason: "tracking_unavailable",
      };
    }
    const filter = verticalFilter(tracking.mode, commands,
      tracking.crop_width, tracking.height);
    stage = "encoding";
    await runCommand("ffmpeg", verticalRenderArgs(short, output, filter),
      11 * 60 * 1000);
    stage = "upload";
    const bytes = await upload(key, output, "video/mp4");
    return { key, mode: tracking.mode, confidence: tracking.confidence,
      face_samples: tracking.face_samples, samples: tracking.samples,
      fallback_reason: tracking.fallback_reason || null,
      render_version: VERTICAL_RENDER_VERSION, bytes };
  } catch (error) {
    // Detailed stderr stays in Cloudflare logs. The UI receives only a safe
    // bounded stage code, never internal file paths, command lines or secrets.
    console.error("Experimental vertical pipeline failed", id, stage, String(error));
    const wrapped = Error("vertical_render_stage_failed");
    wrapped.verticalStage = stage;
    throw wrapped;
  } finally {
    await rm(dir, { recursive: true, force: true });
  }
}

http.createServer(async (request, response) => {
  if (request.url === "/health" && request.method === "GET") {
    response.writeHead(200).end("ok"); return;
  }
  if (!["/process", "/highlights/chunks", "/highlights/render", "/highlights/export", "/highlights/vertical/render"].includes(request.url) || request.method !== "POST") {
    response.writeHead(404).end("not_found"); return;
  }
  if (busy) { response.writeHead(409).end("busy"); return; }
  busy = true;
  try {
    let body = "";
    for await (const chunk of request) {
      body += chunk.toString();
      if (body.length > (["/highlights/render", "/highlights/export", "/highlights/vertical/render"].includes(request.url) ? 4096 : 256))
        throw Error("request_too_large");
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
    } else if (request.url === "/highlights/vertical/render") {
      if (test || payload?.test !== undefined) {
        response.writeHead(400).end("highlight_test_not_supported"); return;
      }
      const result = await renderVertical(id, payload.clip, payload.anchor);
      response.writeHead(200, { "content-type": "application/json" })
        .end(JSON.stringify({ ok: true, ...result }));
    } else if (["/highlights/render", "/highlights/export"].includes(request.url)) {
      if (test || payload?.test !== undefined) {
        response.writeHead(400).end("highlight_test_not_supported"); return;
      }
      const kind = request.url === "/highlights/export" ? "export" : "preview";
      const clips = await renderHighlightClips(id, payload.clips, kind);
      response.writeHead(200, { "content-type": "application/json" })
        .end(JSON.stringify({ ok: true, clips }));
    } else {
      const bytes = await produce(id, test);
      response.writeHead(200, { "content-type": "application/json" })
        .end(JSON.stringify({ ok: true, bytes }));
    }
  } catch (error) {
    console.error("Podcast container job failed", String(error));
    if (request.url === "/highlights/vertical/render") {
      const stage = ["source", "extraction", "tracking", "encoding", "upload"]
        .includes(error?.verticalStage) ? error.verticalStage : "unknown";
      response.writeHead(500, { "content-type": "application/json" })
        .end(JSON.stringify({ error: "vertical_render_failed", stage }));
    } else {
      response.writeHead(500).end("processing_failed");
    }
  } finally {
    busy = false;
  }
}).listen(8080, "0.0.0.0");
