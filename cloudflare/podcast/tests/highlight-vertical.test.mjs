import test from "node:test";
import assert from "node:assert/strict";
import { mkdtempSync, rmSync, statSync, writeFileSync } from "node:fs";
import { tmpdir } from "node:os";
import path from "node:path";
import { spawnSync } from "node:child_process";
import { verticalKey, validVerticalClip, sameVerticalJob }
  from "../src/highlight-vertical-video.mjs";
import { validStorageAccess } from "../src/validation.mjs";
import { verticalStatus, serveVertical } from "../src/highlight-vertical-downloads.mjs";
import { verticalFilter, verticalRenderArgs } from "../container/vertical-video.mjs";

const id = "AjkoDHgFdeA", clip = { number: 4, start: 952.78, end: 1010.52 };
const key = verticalKey(id, clip, 50);
const exists = spawnSync("ffmpeg", ["-version"], { stdio: "ignore" }).status === 0;

test("private portrait paths and approved clip limits are strict", () => {
  assert.equal(validVerticalClip(clip, 50), true);
  assert.equal(key,
    "sermon-highlights/vertical/AjkoDHgFdeA/4-95278-101052-a50.mp4");
  assert.equal(validStorageAccess(key, "PUT"), true);
  assert.equal(validStorageAccess(key, "GET"), false);
  assert.equal(sameVerticalJob({ clip, anchor: 50 }, clip, 50), true);
  assert.equal(sameVerticalJob({ clip, anchor: 60 }, clip, 50), false);
  for (const bad of [
    { ...clip, number: 11 },
    { ...clip, end: 1200 },
    { ...clip, start: 0 },
    { ...clip, random: 1 },
  ]) assert.equal(validVerticalClip(bad, 50), false);
  for (const a of [-1, 101, 1.5, "50"]) {
    assert.equal(validVerticalClip(clip, a), false);
  }
  for (const badKey of [
    "sermon-highlights/vertical/AjkoDHgFdeA/11-95278-101052-a50.mp4",
    "sermon-highlights/vertical/../../sensitive.mp4",
    "sermon-highlights/vertical/AjkoDHgFdeA/4-95278-101052-a200.mp4",
  ]) assert.equal(validStorageAccess(badKey, "PUT"), false);
});

test("vertical preview and download links are private, scoped and expiring", async () => {
  const state = { video_id: id, status: "ready", clip, anchor: 50,
    mode: "tracked", confidence: 0.7 };
  const env = {
    SURFSIDE_PODCAST_SHARED_SECRET: "z".repeat(64),
    SERMONS_BUCKET: {
      async get(objectKey, options) {
        if (objectKey === `sermon-highlights/vertical-jobs/${id}.json`)
          return { async json() { return state; } };
        if (objectKey === key)
          return { body: new ReadableStream({
            start(controller) {
              controller.enqueue(new Uint8Array(options?.range?.length ?? 512));
              controller.close();
            },
          }) };
        return null;
      },
      async head(objectKey) {
        return objectKey === key ? { size: 512 } : null;
      },
    },
  };
  const status = await verticalStatus(
    new Request("https://example.workers.dev/highlights/vertical/status"), env, id);
  assert.equal(status.status, 200);
  const data = await status.json();
  assert.equal(data.mode, "tracked");
  assert.equal(data.link_expires_seconds, 600);
  assert.ok(data.preview_url.includes("/vertical/view/"));
  assert.ok(data.download_url.includes("/vertical/download/"));
  const preview = await serveVertical(new Request(data.preview_url, {
    headers: { range: "bytes=10-39" },
  }), env);
  assert.equal(preview.status, 206);
  assert.equal(preview.headers.get("content-range"), "bytes 10-39/512");
  assert.match(preview.headers.get("content-disposition"), /^inline;/);
  assert.equal((await preview.arrayBuffer()).byteLength, 30);
  const download = await serveVertical(new Request(data.download_url), env);
  assert.equal(download.status, 200);
  assert.match(download.headers.get("content-disposition"), /^attachment;/);
  const wrongMode = new URL(data.preview_url);
  wrongMode.pathname = wrongMode.pathname.replace("/view/", "/download/");
  assert.equal((await serveVertical(new Request(wrongMode), env)).status, 401);
  const badSig = new URL(data.preview_url);
  badSig.searchParams.set("sig", "a".repeat(64));
  assert.equal((await serveVertical(new Request(badSig), env)).status, 401);
  const expired = new URL(data.preview_url);
  expired.searchParams.set("exp", String(Math.floor(Date.now() / 1000) - 1));
  assert.equal((await serveVertical(new Request(expired), env)).status, 403);
  const forged = new URL(data.preview_url);
  forged.pathname = forged.pathname.replace("a50", "a60");
  assert.equal((await serveVertical(new Request(forged), env)).status, 401);
});

function ffmpeg(args) {
  const r = spawnSync("ffmpeg", args, { encoding: "utf8", timeout: 45000 });
  assert.equal(r.status, 0, (r.stderr || "").slice(-1000));
}
test("actual FFmpeg renders moving 9:16 crop and safe wide fallback with audio",
  { skip: !exists }, () => {
    const dir = mkdtempSync(path.join(tmpdir(), "surfside-portrait-"));
    try {
      const input = path.join(dir, "original.mp4");
      ffmpeg(["-hide_banner", "-loglevel", "error", "-y",
        "-f", "lavfi", "-i", "testsrc2=size=640x360:rate=24:duration=3",
        "-f", "lavfi", "-i", "sine=frequency=440:duration=3",
        "-c:v", "libx264", "-preset", "ultrafast",
        "-c:a", "aac", "-shortest", input]);
      const commands = path.join(dir, "position.txt");
      writeFileSync(commands,
        "0.000 crop@subject x 60;\n1.000 crop@subject x 180;\n2.000 crop@subject x 300;\n");
      for (const [mode, filter] of [
        ["tracked", verticalFilter("tracked", commands, 202, 360)],
        ["wide_fallback", verticalFilter("wide_fallback", commands, 202, 360)],
      ]) {
        const output = path.join(dir, mode + ".mp4");
        ffmpeg(verticalRenderArgs(input, output, filter));
        assert.ok(statSync(output).size > 4096);
        const probe = spawnSync("ffprobe", ["-v", "error",
          "-show_entries", "stream=codec_name,width,height",
          "-of", "json", output], { encoding: "utf8" });
        assert.equal(probe.status, 0);
        const streams = JSON.parse(probe.stdout).streams;
        const video = streams.find((stream) => stream.codec_name === "h264");
        const audio = streams.find((stream) => stream.codec_name === "aac");
        assert.ok(audio, mode + " must preserve sermon audio");
        assert.equal(video?.width, 720);
        assert.equal(video?.height, 1280);
      }
    } finally { rmSync(dir, { recursive: true, force: true }); }
  });
