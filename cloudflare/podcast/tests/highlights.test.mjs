import test from "node:test";
import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { validStorageAccess } from "../src/validation.mjs";
import {
  normalizeSegments, transcriptWindows, formatTime, highlightPrompt,
  parseHighlightCandidates, selectHighlights,
} from "../src/highlight-selection.mjs";

const segments = [
  { start: 0, end: 15, text: "We face uncertainty." },
  { start: 15, end: 30, text: "But we can trust God." },
  { start: 30, end: 45, text: "We are not alone." },
  { start: 45, end: 60, text: "Let us pray." },
];

test("transcription times are shifted to original MP4 coordinates and invalid segments ignored", () => {
  const normalized = normalizeSegments([
    { start: 3, end: 8, text: "  Trust   God  " },
    { start: 8, end: 5, text: "invalid" },
    { start: 40, end: 42, text: "outside chunk" },
  ], 180, 30);
  assert.deepEqual(normalized, [{ start: 183, end: 188, text: "Trust God" }]);
});

test("transcript windows retain stable global segment IDs", () => {
  const windows = transcriptWindows(segments, 30, 5);
  assert.ok(windows.length >= 2);
  assert.equal(windows[0][0].id, 0);
  assert.match(highlightPrompt(windows[0]), /\[0\] 00:00-00:15/);
  assert.equal(formatTime(3723.8), "62:03");
});

test("only actual transcript segment IDs yield valid highlight times and exact excerpts", () => {
  const window = segments.map((s, id) => ({ ...s, id }));
  const proposals = parseHighlightCandidates({ response: { highlights: [
    { start_segment: 0, end_segment: 2, title: "Trusting God", reason: "Standalone application", score: 5 },
    { start_segment: 5, end_segment: 9, title: "Fabricated IDs", reason: "Invalid", score: 5 },
    { start_segment: 3, end_segment: 3, title: "Too short", reason: "Invalid", score: 5 },
  ] } }, window);
  assert.equal(proposals.length, 1);
  assert.equal(proposals[0].start, 0);
  assert.equal(proposals[0].end, 45);
  assert.equal(proposals[0].excerpt, segments.slice(0, 3).map((x) => x.text).join(" "));
});

test("duplicate and overlapping proposals are excluded", () => {
  const candidates = [
    { start: 0, end: 45, title: "A", score: 5 },
    { start: 8, end: 48, title: "B", score: 4 },
    { start: 120, end: 180, title: "C", score: 5 },
  ];
  const selected = selectHighlights(candidates);
  assert.deepEqual(selected.map((x) => x.title), ["A", "C"]);
  assert.deepEqual(selected.map((x) => x.number), [1, 2]);
});

test("container may put isolated audio chunks but cannot write original sermons or results", () => {
  const id = "OTt1dfYhlDY";
  assert.equal(validStorageAccess(`sermon-highlights/chunks/${id}/000.mp3`, "PUT"), true);
  assert.equal(validStorageAccess(`sermon-highlights/chunks/${id}/000.mp3`, "GET"), false);
  assert.equal(validStorageAccess(`sermon-highlights/results/${id}.json`, "PUT"), false);
  assert.equal(validStorageAccess(`sermons/${id}.mp4`, "PUT"), false);
  assert.equal(validStorageAccess("sermon-highlights/chunks/../secrets/000.mp3", "PUT"), false);
});

test("highlights remain manual and the podcast worker retains its existing processor", () => {
  const worker = readFileSync(new URL("../src/worker.mjs", import.meta.url), "utf8");
  const container = readFileSync(new URL("../container/server.mjs", import.meta.url), "utf8");
  const config = JSON.parse(readFileSync(new URL("../wrangler.jsonc", import.meta.url), "utf8"));
  assert.equal(config.ai.binding, "AI");
  assert.match(worker, /verifiedRequest\(request, env\.SURFSIDE_PODCAST_SHARED_SECRET\)/);
  assert.match(worker, /kind === "highlights"/);
  assert.match(container, /"\/highlights\/chunks"/);
  assert.match(container, /await produce\(id, test\)/);
});
