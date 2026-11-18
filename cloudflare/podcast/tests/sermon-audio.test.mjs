import test from "node:test";
import assert from "node:assert/strict";
import { readFileSync, mkdtempSync, rmSync, statSync } from "node:fs";
import { tmpdir } from "node:os";
import path from "node:path";
import { spawnSync } from "node:child_process";
import { sermonAudioKey, podcastAudioArgs, extractSermonAudioArgs, chunkSermonAudioArgs } from "../container/sermon-audio.mjs";
import { sermonAudioKey as workerSermonAudioKey, validStorageAccess } from "../src/validation.mjs";

test("source audio sidecar paths are deterministic and test-isolated", () => {
  const id = "OTt1dfYhlDY";
  assert.equal(sermonAudioKey(id), "sermon-audio/OTt1dfYhlDY.mp3");
  assert.equal(sermonAudioKey(id, true), "podcast-test-sermon-audio/OTt1dfYhlDY.mp3");
  assert.equal(workerSermonAudioKey(id), sermonAudioKey(id));
  assert.equal(workerSermonAudioKey(id, true), sermonAudioKey(id, true));
  assert.equal(validStorageAccess(sermonAudioKey(id), "PUT"), true);
  assert.equal(validStorageAccess(sermonAudioKey(id), "GET"), true);
  assert.equal(validStorageAccess(sermonAudioKey(id, true), "PUT"), true);
  assert.equal(validStorageAccess(sermonAudioKey(id, true), "GET"), true);
  assert.equal(validStorageAccess("sermon-audio/../../secret.mp3", "GET"), false);
  assert.equal(validStorageAccess("sermon-audio/../../secret.mp3", "PUT"), false);
  assert.equal(validStorageAccess("sermons/" + id + ".mp4", "PUT"), false);
});

test("podcast audio chain remains intact with optional sidecar from original audio input", () => {
  const plain = podcastAudioArgs("intro", "source.mp4", "outro", "podcast.mp3");
  const both = podcastAudioArgs("intro", "source.mp4", "outro", "podcast.mp3", "raw.mp3");
  assert.match(plain.join(" "), /concat=n=3:v=0:a=1,loudnorm=I=-16:TP=-1.5:LRA=11/);
  assert.match(both.join(" "), /concat=n=3:v=0:a=1,loudnorm=I=-16:TP=-1.5:LRA=11/);
  assert.match(both.join(" "), /\[1:a:0\]asplit=2\[sermon_podcast\]\[sermon_raw\]/);
  assert.match(both.join(" "), /aresample=async=1:first_pts=0/);
  assert.equal(both.at(-1), "raw.mp3");
  assert.equal(plain.at(-1), "podcast.mp3");
  const extracted = extractSermonAudioArgs("old.mp4", "raw.mp3");
  assert.ok(extracted.includes("old.mp4"));
  assert.ok(!extracted.includes("intro.mp3"));
  assert.ok(chunkSermonAudioArgs("raw.mp3", "chunk-%03d.mp3").includes("copy"));
});

test("container loads bundled audio helper and retains original podcast fallback", () => {
  const server = readFileSync(new URL("../container/server.mjs", import.meta.url), "utf8");
  const docker = readFileSync(new URL("../container/Dockerfile", import.meta.url), "utf8");
  assert.match(docker, /COPY server\.mjs sermon-audio\.mjs/);
  assert.match(server, /await processAudio\(intro, sermon, outro, output, sermonOnly\)/);
  assert.match(server, /await processAudio\(intro, sermon, outro, output\)/);
  assert.match(server, /await downloadIfPresent\(sermonAudioKey\(id\), audio\)/);
  assert.match(server, /await upload\(sermonAudioKey\(id\), audio\)/);
  assert.match(server, /chunkSermonAudioArgs\(audio, pattern\)/);
});

const hasFFmpeg = spawnSync("ffmpeg", ["-version"], { stdio: "ignore" }).status === 0 &&
  spawnSync("ffprobe", ["-version"], { stdio: "ignore" }).status === 0;
if (process.env.CI && !hasFFmpeg) throw Error("FFmpeg and ffprobe are required in CI");
const run = (args) => {
  const result = spawnSync("ffmpeg", args, { encoding: "utf8", timeout: 30_000 });
  assert.equal(result.status, 0, result.stderr?.slice(-3000));
};
const probe = (file) => {
  const result = spawnSync("ffprobe",
    ["-v", "error", "-show_entries", "format=duration",
      "-of", "default=noprint_wrappers=1:nokey=1", file],
    { encoding: "utf8", timeout: 10_000 });
  assert.equal(result.status, 0, result.stderr);
  return Number(result.stdout.trim());
};

test("FFmpeg produces bumper-inclusive podcast and bumper-free sermon audio in one pass",
  { skip: !hasFFmpeg }, () => {
    const dir = mkdtempSync(path.join(tmpdir(), "surfside-dual-audio-"));
    try {
      const intro = path.join(dir, "intro.mp3");
      const sermon = path.join(dir, "sermon.mp4");
      const outro = path.join(dir, "outro.mp3");
      const episode = path.join(dir, "episode.mp3");
      const raw = path.join(dir, "raw.mp3");
      const chunkPattern = path.join(dir, "chunk-%03d.mp3");
      const generate = (file, frequency, seconds, mp4 = false) => run([
        "-hide_banner", "-loglevel", "error", "-y", "-f", "lavfi",
        "-i", "sine=frequency=" + frequency + ":duration=" + seconds,
        "-vn", "-c:a", mp4 ? "aac" : "libmp3lame", file,
      ]);
      generate(intro, 700, 1);
      generate(sermon, 440, 8, true);
      generate(outro, 900, 1);
      run(podcastAudioArgs(intro, sermon, outro, episode, raw));
      assert.ok(statSync(episode).size > 1024);
      assert.ok(statSync(raw).size > 1024);
      const podcastSeconds = probe(episode), sermonSeconds = probe(raw);
      assert.ok(podcastSeconds > sermonSeconds + 1.5,
        "bumper-inclusive podcast is longer than sermon-only audio");
      assert.ok(Math.abs(sermonSeconds - 8) < 0.5,
        "sermon-only audio is aligned to original MP4 audio");
      run(chunkSermonAudioArgs(raw, chunkPattern));
      assert.ok(statSync(path.join(dir, "chunk-000.mp3")).size > 1024);
    } finally {
      rmSync(dir, { recursive: true, force: true });
    }
  });
