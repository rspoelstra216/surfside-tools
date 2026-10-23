import test from "node:test";
import assert from "node:assert/strict";
import { validDraftMetadata, testDraftMetadata, multipartAudioStream, processSpreakerDraft, readDraftState } from "../src/spreaker.mjs";

const id = "0_QXD1k4fcI";
const meta = { title: "Made to Serve | Made for More Series (Week 6)",
  description: "Sermon date: October 18, 2026\\nWatch on YouTube: https://www.youtube.com/watch?v=0_QXD1k4fcI" };
const audioBytes = new TextEncoder().encode("FAKE_MP3_STREAM");
const env = (configured = true) => {
  const records = new Map();
  return {
    SPREAKER_OAUTH_ACCESS_TOKEN: configured ? "a".repeat(40) : undefined,
    SPREAKER_SHOW_ID: "10240",
    SERMONS_BUCKET: {
      async get(key) {
        if (key === `podcast-audio/${id}.mp3`) return {
          size: audioBytes.byteLength,
          body: new ReadableStream({ start(c) { c.enqueue(audioBytes); c.close(); } }),
        };
        if (!records.has(key)) return null;
        return { async json() { return JSON.parse(records.get(key)); } };
      },
      async put(key, content) { records.set(key, content); },
    },
  };
};

test("draft metadata rejects incomplete or overlong episode titles", () => {
  assert.equal(validDraftMetadata(meta), true);
  assert.equal(validDraftMetadata({ ...meta, title: "x".repeat(141) }), false);
  assert.equal(validDraftMetadata({ ...meta, description: "" }), false);
});

test("MP3 streams as correct multipart request and Spreaker stays unpublished", async () => {
  const binding = env();
  const calls = [];
  const send = async (url, options) => {
    calls.push(url);
    assert.match(options.headers.authorization, /^Bearer /);
    assert.equal(options.redirect, "error");
    if (url.endsWith("/episodes/drafts")) {
      const fields = new URLSearchParams(options.body);
      assert.equal(fields.get("title"), meta.title);
      assert.equal(fields.get("show_id"), "10240");
      assert.equal(fields.get("description"), meta.description);
      return Response.json({ response: { episode: { episode_id: 909, type: "DRAFT" } } });
    }
    assert.equal(url, "https://api.spreaker.com/v2/episodes/909");
    const body = new Uint8Array(await new Response(options.body).arrayBuffer());
    assert.equal(body.length, Number(options.headers["content-length"]));
    const bodyText = new TextDecoder().decode(body);
    assert.match(bodyText, /name="media_file"; filename="0_QXD1k4fcI.mp3"/);
    assert.match(bodyText, /FAKE_MP3_STREAM/);
    assert.match(options.headers["content-type"], /^multipart\/form-data; boundary=surfside-/);
    return Response.json({ response: { episode: { episode_id: 909, type: "DRAFT" } } });
  };
  const outcome = await processSpreakerDraft(binding, id, meta, send);
  assert.equal(outcome.status, "uploaded");
  assert.equal(outcome.episode_id, "909");
  assert.equal((await readDraftState(binding, id)).status, "uploaded");
  await processSpreakerDraft(binding, id, meta, send);
  assert.equal(calls.length, 2, "retry must not create a second episode or upload");
});

test("ambiguous Spreaker draft response stops duplicate creation", async () => {
  const binding = env();
  let attempts = 0;
  const send = async () => { attempts++; throw Error("network interrupted after POST"); };
  const first = await processSpreakerDraft(binding, id, meta, send);
  assert.equal(first.status, "review_required");
  assert.equal(first.detail, "draft_response_uncertain");
  const second = await processSpreakerDraft(binding, id, meta, send);
  assert.equal(second.status, "review_required");
  assert.equal(attempts, 1);
});

test("audio upload retries reuse existing draft ID and never publish", async () => {
  const binding = env();
  let draftCount = 0, uploadCount = 0;
  const send = async (url, options) => {
    if (url.endsWith("/episodes/drafts")) {
      draftCount++;
      return Response.json({ response: { episode: { episode_id: 123, type: "DRAFT" } } });
    }
    uploadCount++;
    await new Response(options.body).arrayBuffer();
    if (uploadCount === 1) return new Response("temporary error", { status: 503 });
    return Response.json({ response: { episode: { episode_id: 123, type: "DRAFT" } } });
  };
  await assert.rejects(processSpreakerDraft(binding, id, meta, send), /spreaker_audio_http_503/);
  const first = await readDraftState(binding, id);
  assert.equal(first.status, "upload_retry");
  assert.equal(first.episode_id, "123");
  const final = await processSpreakerDraft(binding, id, meta, send);
  assert.equal(final.status, "uploaded");
  assert.equal(draftCount, 1);
  assert.equal(uploadCount, 2);
});

test("missing secrets fail closed without reaching Spreaker", async () => {
  const binding = env(false);
  let requests = 0;
  const state = await processSpreakerDraft(binding, id, meta, () => {requests++;});
  assert.equal(state.status, "not_configured");
  assert.equal(requests, 0);
});

test("test drafts are unmistakably labeled, bounded, and cannot become published automatically", () => {
  const data = testDraftMetadata(meta);
  assert.match(data.title, /^\[TEST - DO NOT PUBLISH\] /);
  assert.match(data.description, /^TEST DRAFT ONLY\. Do not publish/);
  assert.match(data.description, /Sermon date:/);
  assert.equal(validDraftMetadata(data), true);
  const lengthy = testDraftMetadata({ ...meta, title: "A".repeat(140), description: "D".repeat(8000) });
  assert.equal(lengthy.title.length, 140);
  assert.equal(lengthy.description.length, 8000);
  assert.equal(validDraftMetadata(lengthy), true);
  assert.equal(testDraftMetadata({ ...meta, title: "" }), null);
});

