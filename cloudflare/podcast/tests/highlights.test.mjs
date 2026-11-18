import { previewKey, resolveClipPicks, requestedByteRange } from "../src/highlight-video.mjs";
import test from "node:test";
import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { validStorageAccess } from "../src/validation.mjs";
import {
  normalizeSegments, transcriptWindows, formatTime, highlightPrompt,
  parseHighlightCandidates, selectHighlights,
} from "../src/highlight-selection.mjs";
import { refineHighlightCandidates } from "../src/highlight-refinement.mjs";
import { validateChunkManifest } from "../src/highlight-chunk-manifest.mjs";
import { highlightChunkResponse } from "../container/sermon-audio.mjs";

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

test("transcript refinement includes nearby fragments but never invents words", () => {
  const transcript = [
    { start: 10, end: 18, text: "The challenge is that" },
    { start: 18, end: 45, text: "we need God's perspective" },
    { start: 45, end: 51, text: "as we read Scripture." },
    { start: 51, end: 61, text: "Here is a new point." },
  ];
  const [candidate] = refineHighlightCandidates([{
    start: 18, end: 45, title: "Perspective", reason: "Whole thought",
    score: 5, excerpt: "we need God's perspective",
  }], transcript);
  assert.equal(candidate.start, 10);
  assert.equal(candidate.end, 51);
  assert.equal(candidate.excerpt, transcript.slice(0, 3).map((s) => s.text).join(" "));
  assert.equal(candidate.boundary_review.transcript_sentence_complete, true);
  assert.equal(candidate.boundary_review.manual_review_required, true);
});

test("refinement chooses a following speech break without including the next phrase", () => {
  const transcript = [
    { start: 0, end: 30, text: "We can trust God." },
    { start: 35, end: 52, text: "Now a different thought." },
  ];
  const [candidate] = refineHighlightCandidates([{
    start: 0, end: 30, title: "Trust", reason: "Encouragement",
    score: 5, excerpt: "We can trust God.",
  }], transcript, [{ start: 31.3, end: 31.85 }, { start: 36, end: 36.5 }]);
  assert.equal(candidate.end, 31.3);
  assert.equal(candidate.boundary_review.original_end, 30);
  assert.equal(candidate.boundary_review.used_audio_pauses, true);
});

test("strong clips are spread across sermon regions rather than clustered at the beginning", () => {
  const candidates = [
    { start: 20, end: 70, score: 5, title: "God's Perspective" },
    { start: 85, end: 135, score: 5, title: "Need for God's Perspective" },
    { start: 600, end: 650, score: 4, title: "Scripture" },
    { start: 1100, end: 1150, score: 4, title: "Mercy" },
    { start: 1700, end: 1750, score: 4, title: "Serving Others" },
    { start: 2160, end: 2220, score: 4, title: "Love of Christ" },
    { start: 2200, end: 2250, score: 2, title: "Weak Closing" },
  ];
  const result = selectHighlights(candidates, 5, { duration: 2400 });
  assert.equal(result.length, 5);
  assert.equal(result.filter((c) => c.start < 140).length, 1);
  assert.ok(result.some((c) => c.start >= 1700));
  assert.ok(result.every((c) => c.score >= 3));
  assert.deepEqual(result.map((c) => c.number), [1, 2, 3, 4, 5]);
});

test("realistic eleven-chunk private manifest passes unchanged", () => {
  const id = "AjkoDHgFdeA";
  const chunks = Array.from({ length: 11 }, (_, i) => ({
    key: "sermon-highlights/chunks/" + id + "/" + String(i).padStart(3, "0") + ".mp3",
    offset: i * 180,
    duration: i === 10 ? 75.33 : 180.02,
  }));
  const pauses = [{ start: 31.4, end: 31.9 }, { start: -1, end: 2 }];
  const parsed = validateChunkManifest({ ok: true, chunks, pauses }, id);
  assert.equal(parsed.chunks.length, 11);
  assert.deepEqual(parsed.pauses, [{ start: 31.4, end: 31.9 }]);
});

test("manifest validation identifies missing or incorrect fields without exposing source data", () => {
  const id = "AjkoDHgFdeA";
  const valid = { key: "sermon-highlights/chunks/" + id + "/000.mp3", offset: 0, duration: 180.02 };
  const invalid = [
    [{ chunks: null }, "chunks_not_array"],
    [{ chunks: [] }, "chunk_count"],
    [{ chunks: [null] }, "chunk_not_object_at_0"],
    [{ chunks: [{ ...valid, key: "sermons/" + id + ".mp4" }] }, "chunk_key_at_0"],
    [{ chunks: [{ ...valid, offset: null }] }, "chunk_offset_at_0"],
    [{ chunks: [{ ...valid, duration: 191 }] }, "chunk_duration_at_0"],
  ];
  for (const [data, code] of invalid)
    assert.throws(() => validateChunkManifest(data, id),
      (error) => error.message === "invalid_chunk_manifest:" + code);
});

test("container response keeps chunks as top-level array when pauses are included", () => {
  const id = "AjkoDHgFdeA";
  const chunks = [{
    key: "sermon-highlights/chunks/" + id + "/000.mp3",
    offset: 0, duration: 180.01,
  }];
  const response = highlightChunkResponse({
    chunks, pauses: [{ start: 45, end: 45.5 }],
  });
  assert.equal(response.ok, true);
  assert.ok(Array.isArray(response.chunks), "must not double-wrap chunks");
  assert.deepEqual(validateChunkManifest(JSON.parse(JSON.stringify(response)), id),
    { chunks, pauses: [{ start: 45, end: 45.5 }] });
  const server = readFileSync(new URL("../container/server.mjs", import.meta.url), "utf8");
  assert.match(server, /JSON\.stringify\(highlightChunkResponse\(manifest\)\)/);
});

test("staff select an existing highlight and override exactly the preview boundaries", () => {
  const highlights = [
    { number: 2, title: "Celebrating While Imperfect", start: 457.24, end: 495.24 },
    { number: 4, title: "Celebrating Faith Steps", start: 952.78, end: 1010.52 },
  ];
  const clips = resolveClipPicks(highlights, [
    { number: 2, start: 456, end: 491 }, { number: 4 },
  ]);
  assert.equal(clips[0].start, 456);
  assert.equal(clips[0].end, 491);
  assert.equal(clips[1].start, 952.78);
  assert.equal(previewKey("AjkoDHgFdeA", clips[0]),
    "sermon-highlights/previews/AjkoDHgFdeA/2-45600-49100.mp4");
  assert.equal(validStorageAccess(previewKey("AjkoDHgFdeA", clips[0]), "PUT"), true);
  assert.equal(validStorageAccess("sermons/AjkoDHgFdeA.mp4", "PUT"), false);
  assert.equal(validStorageAccess("sermon-highlights/previews/../../secret.mp4", "PUT"), false);
});
test("manual previews refuse nonexistent clips, overlong requests and duplicate selections", () => {
  const highlights = [{ number: 2, title: "Test", start: 456, end: 491 }];
  assert.throws(() => resolveClipPicks(highlights, [{ number: 3 }]), /unknown_highlight_number/);
  assert.throws(() => resolveClipPicks(highlights,
    [{ number: 2 }, { number: 2 }]), /invalid_clip_selection/);
  assert.throws(() => resolveClipPicks(highlights,
    [{ number: 2, start: 0, end: 1000 }]), /invalid_clip_times/);
  assert.throws(() => resolveClipPicks(highlights,
    [{ number: 2, start: 451 }]), /invalid_clip_times/);
});
test("video preview range requests support HTML5 seeking and reject malformed ranges", () => {
  assert.deepEqual(requestedByteRange(null, 1000), null);
  assert.deepEqual(requestedByteRange("bytes=100-199", 1000),
    { offset: 100, length: 100, end: 199 });
  assert.deepEqual(requestedByteRange("bytes=980-", 1000),
    { offset: 980, length: 20, end: 999 });
  assert.deepEqual(requestedByteRange("bytes=-50", 1000),
    { offset: 950, length: 50, end: 999 });
  assert.equal(requestedByteRange("bytes=1000-1001", 1000), false);
  assert.equal(requestedByteRange("bytes=1-2,3-4", 1000), false);
});
