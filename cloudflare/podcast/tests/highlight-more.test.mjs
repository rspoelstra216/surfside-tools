import test from "node:test";
import assert from "node:assert/strict";
import {
  selectAdditionalHighlights, queueMoreHighlights, processMoreHighlights,
} from "../src/highlight-more.mjs";
import { previewKey } from "../src/highlight-video.mjs";

const id = "AjkoDHgFdeA";
const first = [
  { number: 1, start: 60, end: 100, score: 4,
    title: "Love God and Neighbor", excerpt: "Love others" },
  { number: 2, start: 150, end: 200, score: 4,
    title: "Jesus Example of Prayer", excerpt: "How we pray" },
];
const candidate = (start, end, title, score = 5) =>
  ({ start, end, title, score, reason: "Complete sermon takeaway", excerpt: title });

test("more selects meaningful nonoverlapping alternatives with stable numbers", () => {
  const extra = selectAdditionalHighlights([
    candidate(65, 98, "Love God Again"), // overlapping
    candidate(260, 310, "Jesus Example of Prayer"), // title duplicate
    candidate(305, 350, "Trust God During Hardship"),
    candidate(365, 400, "Hope Through the Storm"),
    candidate(410, 445, "Faithful Community"),
  ], first, 500);
  assert.deepEqual(extra.map((x) => x.number), [3, 4, 5]);
  assert.ok(extra.every((x) => x.start >= 300));
  assert.deepEqual(first.map((x) => x.number), [1, 2],
    "first pass remains immutable");
  assert.deepEqual(selectAdditionalHighlights([], first, 500), []);
});

test("find-more reuses existing transcript and renders only new previews", async () => {
  const transcript = {
    video_id: id,
    segments: Array.from({ length: 30 }, (_, i) => ({
      start: i * 15, end: (i + 1) * 15,
      text: `A coherent idea for the sermon number ${i}.`,
    })),
  };
  const saved = new Map([
    [`sermon-highlights/results/${id}.json`, {
      video_id: id, status: "ready", highlights: first,
    }],
    [`sermon-highlights/transcripts/${id}.json`, transcript],
    [`sermon-highlights/clip-jobs/${id}.json`, {
      status: "ready", clips: [
        { number: 1, start: 55, end: 105, title: "Love God and Neighbor" },
        { number: 2, start: 145, end: 205, title: "Jesus Example of Prayer" },
      ],
    }],
  ]);
  const previews = [
    { number: 1, start: 55, end: 105 },
    { number: 2, start: 145, end: 205 },
  ];
  for (const clip of previews) saved.set(previewKey(id, clip), { exists: true });
  const sent = [], aiCalls = [];
  const env = {
    SERMONS_BUCKET: {
      async get(key) {
        const val = saved.get(key);
        return val ? { async json() { return structuredClone(val); } } : null;
      },
      async head(key) {
        return saved.has(key) || key === `sermons/${id}.mp4`
          ? { size: 200000 } : null;
      },
      async put(key, data) { saved.set(key, JSON.parse(data)); },
    },
    PODCAST_QUEUE: {
      async send(msg) { sent.push(structuredClone(msg)); },
    },
    AI: {
      async run(model, params) {
        aiCalls.push(model);
        assert.equal(model, "@cf/meta/llama-3.3-70b-instruct-fp8-fast",
          "must not rerun Whisper or regenerate the transcript");
        assert.match(params.messages[1].content, /Love God and Neighbor/);
        return { response: { highlights: [
          { start_segment: 20, end_segment: 22,
            title: "Faith During Difficult Times",
            reason: "A useful standalone encouragement", score: 5 },
        ] } };
      },
    },
  };
  const queued = await queueMoreHighlights(env, id);
  assert.equal(queued.status, 202);
  assert.equal((await queued.json()).status, "queued");
  assert.deepEqual(sent[0], {
    kind: "highlight_more", video_id: id, base_count: 2, round: 1,
  });

  await processMoreHighlights(env, id, 2, 1);
  const updated = saved.get(`sermon-highlights/results/${id}.json`);
  assert.equal(updated.highlights.length, 3);
  assert.deepEqual(updated.highlights.slice(0, 2), first);
  assert.equal(updated.highlights[2].number, 3);
  assert.equal(updated.highlights[2].title, "Faith During Difficult Times");
  assert.equal(aiCalls.length, 1);
  const mediaJob = sent.find((x) => x.kind === "highlight_clips");
  assert.ok(mediaJob);
  assert.deepEqual(mediaJob.clips.map((x) => x.number), [3]);
  assert.deepEqual(mediaJob.manifest.map((x) => x.number), [1, 2, 3]);
  assert.equal(saved.get(`sermon-highlights/more-jobs/${id}.json`).status, "ready");
  assert.equal(saved.get(`sermon-highlights/clip-jobs/${id}.json`).status, "queued");

  // If a Queue retry arrives, the first result was already saved; do not
  // charge Llama a second time or append another copy of #3.
  await processMoreHighlights(env, id, 2, 1);
  assert.equal(aiCalls.length, 1);
  assert.equal(saved.get(`sermon-highlights/results/${id}.json`).highlights.length, 3);
  assert.equal(sent.length, 2);
});

test("additional search must have an existing result and transcript", async () => {
  const env = {
    SERMONS_BUCKET: {
      async get() { return null; },
      async head() { return null; },
    },
  };
  assert.equal((await queueMoreHighlights(env, "bad")).status, 400);
  const response = await queueMoreHighlights(env, id);
  assert.equal(response.status, 409);
  assert.equal((await response.json()).error, "highlight_analysis_required");
});
