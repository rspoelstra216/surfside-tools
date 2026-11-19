import test from "node:test";
import assert from "node:assert/strict";
import {
  exportKey, resolveApprovedExports, sameExports,
  exportQueueClips, exportJobStalled,
} from "../src/highlight-video.mjs";
import { exportKey as containerExportKey } from "../container/highlight-video.mjs";
import { validStorageAccess } from "../src/validation.mjs";
import { statusExports, serveExportDownload } from "../src/highlight-export-downloads.mjs";

const id = "AjkoDHgFdeA";
const highlights = [
  { number: 2, title: "Celebrating While Imperfect", start: 457.24, end: 495.24 },
  { number: 4, title: "Celebrating Faith Steps", start: 952.78, end: 1010.52 },
];

test("final exports use exact approved staff cuts, different from padded previews", () => {
  const requested = [
    { number: 4, start: 952.78, end: 1010.52 },
    { number: 2, start: 456.74, end: 491.24 },
  ];
  const clips = resolveApprovedExports(highlights, requested);
  assert.deepEqual(clips.map((c) => c.number), [2, 4]);
  assert.equal(clips[0].start, 456.74);
  assert.equal(clips[0].end, 491.24);
  assert.equal(exportKey(id, clips[0]),
    "sermon-highlights/exports/AjkoDHgFdeA/2-45674-49124.mp4");
  assert.equal(containerExportKey(id, clips[0]), exportKey(id, clips[0]));
  assert.equal(sameExports(clips, clips), true);
  assert.equal(sameExports(clips, clips.slice(0, 1)), false);
  assert.equal(sameExports(clips, [
    { ...clips[0], start: 456.24 }, clips[1],
  ]), false);
});

test("export Queue round trip strips title metadata before strict revalidation", () => {
  // PR #524 bug: resolveApprovedExports adds a title, but
  // resolveClipPicks rejects title on revalidation in processExportJob.
  const requested = [{ number: 2, start: 456.74, end: 491.24 }];
  const resolved = resolveApprovedExports(highlights, requested);
  assert.equal(resolved[0].title, "Celebrating While Imperfect");
  assert.throws(() => resolveApprovedExports(highlights, resolved), /invalid_clip_times/);
  const serializedQueueClips = JSON.parse(JSON.stringify(exportQueueClips(resolved)));
  assert.deepEqual(serializedQueueClips, requested);
  assert.deepEqual(resolveApprovedExports(highlights, serializedQueueClips), resolved);
  // Previously queued jobs already contain a title; the consumer strips
  // only that known display field without weakening numeric validation.
  assert.deepEqual(resolveApprovedExports(highlights, exportQueueClips(resolved)), resolved);
  for (const extra of [
    { ...resolved[0], admin: true },
    { ...resolved[0], title: { text: "unsafe" } },
  ]) assert.throws(() => exportQueueClips([extra]), /invalid_clip_times/);
  assert.throws(() => resolveApprovedExports(highlights,
    exportQueueClips([{ ...resolved[0], start: 450 }])), /export_outside_review_adjustment/);
});

test("stale queued exports may be retried; active processing never duplicates", () => {
  const now = Date.parse("2026-11-19T15:00:00Z");
  const old = { updated_at: "2026-11-19T14:35:00Z" };
  assert.equal(exportJobStalled({ ...old, status: "queued" }, now), true);
  assert.equal(exportJobStalled({ ...old, status: "processing" }, now), false);
  assert.equal(exportJobStalled({ ...old, status: "failed" }, now), false);
  assert.equal(exportJobStalled({ ...old, status: "ready" }, now), false);
  assert.equal(exportJobStalled({ ...old, status: "queued",
    updated_at: "2026-11-19T14:50:00Z" }, now), false);
  assert.equal(exportJobStalled({ status: "queued" }, now), false);
});

test("only bounded private final export keys are permitted to Container", () => {
  assert.equal(validStorageAccess(
    "sermon-highlights/exports/AjkoDHgFdeA/4-95278-101052.mp4", "PUT"), true);
  assert.equal(validStorageAccess(
    "sermon-highlights/exports/AjkoDHgFdeA/4-95278-101052.mp4", "GET"), false);
  for (const key of ["sermons/AjkoDHgFdeA.mp4",
    "sermon-highlights/exports/../../private.mp4",
    "sermon-highlights/exports/AjkoDHgFdeA/6-95278-101052.mp4"])
    assert.equal(validStorageAccess(key, "PUT"), false);
});

test("approved exact export rejects fabricated, missing, or out-of-window times", () => {
  const valid = { number: 2, start: 456.74, end: 491.24 };
  for (const invalid of [
    [{ number: 2 }],
    [{ ...valid, start: 452.23 }],
    [{ ...valid, end: 500.25 }],
    [{ ...valid, end: valid.start }],
    [{ ...valid, start: -1 }],
    [{ number: 3, start: 456.74, end: 491.24 }],
  ]) assert.throws(() => resolveApprovedExports(highlights, invalid));
});

test("final MP4 download links are scoped, short-lived, private and attachment-only", async () => {
  const clips = resolveApprovedExports(highlights, [{
    number: 4, start: 953.28, end: 1009.52,
  }]);
  const bucket = {
    async get(key, options) {
      if (key === "sermon-highlights/export-jobs/" + id)
        return null;
      if (key === "sermon-highlights/export-jobs/" + id + ".json")
        return { async json() { return {
          video_id: id, status: "ready", clips,
        }; } };
      if (key === exportKey(id, clips[0])) {
        const length = options?.range?.length ?? 250;
        return { body: new ReadableStream({
          start(controller) { controller.enqueue(new Uint8Array(length)); controller.close(); },
        }) };
      }
      return null;
    },
    async head(key) {
      return key === exportKey(id, clips[0]) ? { size: 250 } : null;
    },
  };
  const env = {
    SERMONS_BUCKET: bucket,
    SURFSIDE_PODCAST_SHARED_SECRET: "z".repeat(64), // Test-only key.
  };
  const result = await statusExports(
    new Request("https://example.workers.dev/highlights/exports/status"), env, id);
  assert.equal(result.status, 200);
  const data = await result.json();
  assert.equal(data.status, "ready");
  assert.equal(data.download_links_expire_seconds, 600);
  const link = data.clips[0].download_url;
  assert.match(link, /\/highlights\/exports\/download\/AjkoDHgFdeA\/4-95328-100952\.mp4/);
  const video = await serveExportDownload(
    new Request(link, { headers: { range: "bytes=10-29" } }), env);
  assert.equal(video.status, 206);
  assert.equal(video.headers.get("content-type"), "video/mp4");
  assert.equal(video.headers.get("content-range"), "bytes 10-29/250");
  assert.match(video.headers.get("content-disposition"), /^attachment; filename=/);
  assert.equal(video.headers.get("cache-control"), "private, no-store");
  assert.equal((await video.arrayBuffer()).byteLength, 20);

  const tampered = new URL(link);
  tampered.pathname = tampered.pathname.replace("4-95328", "4-95329");
  assert.equal((await serveExportDownload(new Request(tampered), env)).status, 401);
  const expired = new URL(link);
  expired.searchParams.set("exp", String(Math.floor(Date.now() / 1000) - 1));
  assert.equal((await serveExportDownload(new Request(expired), env)).status, 403);
  const unsigned = new URL(link);
  unsigned.search = "";
  assert.equal((await serveExportDownload(new Request(unsigned), env)).status, 401);
});
