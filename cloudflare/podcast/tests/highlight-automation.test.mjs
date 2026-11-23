import test from "node:test";
import assert from "node:assert/strict";
import {
  paddedHighlightPicks, paddedHighlightRange, nextAutomaticPreviewBatch,
  exportPreviewPlan,
} from "../src/highlight-automation.mjs";
import { previewKey } from "../src/highlight-video.mjs";
import { validStorageAccess } from "../src/validation.mjs";

test("weekly automatic preview padding matches the WordPress editor ±10 seconds", () => {
  const highlights = [
    { number: 1, start: 3.20, end: 47.11 },
    { number: 2, start: 457.24, end: 495.24 },
    { number: 4, start: 952.78, end: 1010.52 },
  ];
  assert.deepEqual(paddedHighlightPicks(highlights), [
    { number: 1, start: 0, end: 57.11 },
    { number: 2, start: 447.24, end: 505.24 },
    { number: 4, start: 942.78, end: 1020.52 },
  ]);
  assert.equal(paddedHighlightPicks(null).length, 0);
  assert.equal(paddedHighlightPicks([
    { number: 1, start: 15, end: 19 },
    { number: 1, start: 19, end: 27 },
    { number: 2, start: -12, end: 3 },
  ]).length, 0);
});

test("ten-second preview padding stays within 120s while retaining long AI suggestions", () => {
  assert.deepEqual(paddedHighlightRange(200, 305), { start: 192.5, end: 312.5 });
  assert.deepEqual(paddedHighlightRange(3, 108), { start: 0, end: 118 });
  assert.deepEqual(paddedHighlightRange(4215, 4320), { start: 4205, end: 4320 });
  assert.deepEqual(paddedHighlightRange(100, 200), { start: 90, end: 210 });
  assert.equal(paddedHighlightRange(100, 221), null);
  const results = paddedHighlightPicks([
    { number: 1, start: 200, end: 305 },
    { number: 2, start: 100, end: 221 }, // Invalid >120s AI cut.
  ]);
  assert.deepEqual(results, [{ number: 1, start: 192.5, end: 312.5 }]);
});

test("five longer previews are split into bounded automatic batches", () => {
  const picks = Array.from({ length: 5 }, (_, index) => ({
    number: index + 1, start: index * 200, end: index * 200 + 90,
  }));
  const first = nextAutomaticPreviewBatch(picks);
  assert.deepEqual(first.map((clip) => clip.number), [1, 2, 3, 4]);
  assert.deepEqual(nextAutomaticPreviewBatch(picks.slice(first.length))
    .map((clip) => clip.number), [5]);
  assert.equal(nextAutomaticPreviewBatch(null).length, 0);
  const veryLong = picks.map((p) => ({ ...p, end: p.start + 120 }));
  assert.deepEqual(nextAutomaticPreviewBatch(veryLong)
    .map((clip) => clip.number), [1, 2, 3]);
});

test("private padded video input covers exact approved trim; fallback for any mismatch", () => {
  const approved = [
    { number: 2, start: 456.74, end: 491.24 },
    { number: 4, start: 952.78, end: 1010.52 },
  ];
  const available = [
    { number: 2, start: 447.24, end: 505.24 },
    { number: 4, start: 953.00, end: 1020.52 },
  ];
  const plan = exportPreviewPlan(approved, available);
  assert.deepEqual(plan[0], {
    ...approved[0], source_preview: { start: 447.24, end: 505.24 },
  });
  assert.deepEqual(plan[1], approved[1]); // Clip #4 starts before the preview.
  assert.deepEqual(exportPreviewPlan(approved, []), approved);
  assert.deepEqual(exportPreviewPlan(approved, [
    { number: 3, start: 0, end: 200 },
  ]), approved);
});

test("Container may read strictly scoped private previews, never other files", () => {
  const key = previewKey("AjkoDHgFdeA",
    { number: 2, start: 452.24, end: 500.24 });
  assert.equal(key, "sermon-highlights/previews/AjkoDHgFdeA/2-45224-50024.mp4");
  assert.equal(validStorageAccess(key, "GET"), true);
  assert.equal(validStorageAccess(key, "PUT"), true);
  assert.equal(validStorageAccess(
    "sermon-highlights/previews/../../secret.mp4", "GET"), false);
  assert.equal(validStorageAccess(
    "sermon-highlights/exports/AjkoDHgFdeA/2-45674-49124.mp4", "GET"), false);
});
