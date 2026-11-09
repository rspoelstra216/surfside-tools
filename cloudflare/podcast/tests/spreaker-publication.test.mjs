import test from "node:test";
import assert from "node:assert/strict";
import { publishReadyEpisode, MAX_PUBLISH_CHECKS } from "../src/spreaker-publication.mjs";
import { readDraftState, saveDraftState } from "../src/spreaker.mjs";

const id = "e0NnCrrshM4";
const episodeId = "75976979";
const draft = (status = "READY", extra = {}) => ({
  episode_id: Number(episodeId), show_id: 10240, type: "DRAFT",
  encoding_status: status, visibility: "PUBLIC", media_id: 123,
  ...extra,
});
const published = () => ({
  episode_id: Number(episodeId), show_id: 10240, type: "RECORDED",
  published_at: "2026-11-09 12:00:00", visibility: "PUBLIC",
});
const json = (episode) => Response.json({ response: { episode } });

const env = () => {
  const records = new Map();
  const bucket = {
    async get(key) { return records.has(key) ? { async json() { return JSON.parse(records.get(key)); } } : null; },
    async put(key, value) { records.set(key, value); },
    async head(key) { return key === `podcast-audio/${id}.mp3` ? { size: 123 } : null; },
  };
  return { SERMONS_BUCKET: bucket, SPREAKER_OAUTH_ACCESS_TOKEN: "t".repeat(40), SPREAKER_SHOW_ID: "10240" };
};
const setUploaded = (binding) => saveDraftState(binding, id, "uploaded", episodeId);

test("publish request only after Spreaker media reaches READY", async () => {
  const binding = env();
  await setUploaded(binding);
  let getCount = 0, postCount = 0;
  const client = async (url, options) => {
    assert.equal(url, `https://api.spreaker.com/v2/episodes/${episodeId}`);
    assert.equal(options.redirect, "manual");
    assert.equal(options.headers.authorization, `Bearer ${binding.SPREAKER_OAUTH_ACCESS_TOKEN}`);
    if (options.method === "GET") {
      getCount++;
      return json(draft(getCount === 1 ? "PENDING" : "READY"));
    }
    postCount++;
    const data = new URLSearchParams(options.body);
    assert.match(data.get("published_at"), /^\d{4}-\d\d-\d\d \d\d:\d\d:\d\d$/);
    assert.equal(data.get("visibility"), "PUBLIC");
    return json(published());
  };
  const waiting = await publishReadyEpisode(binding, id, 0, client);
  assert.equal(waiting.status, "check_again");
  assert.equal((await readDraftState(binding, id)).status, "encoding_wait");
  assert.equal(postCount, 0);
  const done = await publishReadyEpisode(binding, id, 1, client);
  assert.equal(done.status, "published");
  assert.equal(postCount, 1);
  await publishReadyEpisode(binding, id, 2, client);
  assert.equal(postCount, 1, "never republish an already published episode");
});

test("a manually published episode is accepted without editing its date", async () => {
  const binding = env();
  await setUploaded(binding);
  let count = 0;
  const outcome = await publishReadyEpisode(binding, id, 0, (_url, opts) => {
    count++;
    assert.equal(opts.method, "GET");
    return json(published());
  });
  assert.equal(outcome.status, "published");
  assert.equal(count, 1);
});

test("network-ambiguous POST does not trigger a second POST", async () => {
  const binding = env();
  await setUploaded(binding);
  let posts = 0, gets = 0;
  const client = async (_url, opts) => {
    if (opts.method === "GET") {
      gets++;
      return json(draft());
    }
    posts++;
    throw new Error("lost response after publication request");
  };
  assert.equal((await publishReadyEpisode(binding, id, 0, client)).status, "check_again");
  assert.equal((await readDraftState(binding, id)).status, "publication_uncertain");
  const result = await publishReadyEpisode(binding, id, 1, client);
  assert.equal(result.status, "publish_review_required");
  assert.equal(result.detail, "publish_response_uncertain");
  assert.equal(posts, 1);
  assert.equal(gets, 2);
});

test("uncertain POST can be reconciled if subsequent GET reports RECORDED", async () => {
  const binding = env();
  await setUploaded(binding);
  await saveDraftState(binding, id, "publication_uncertain", episodeId);
  let posts = 0;
  const outcome = await publishReadyEpisode(binding, id, 1, (_url, opts) => {
    if (opts.method === "POST") posts++;
    return json(published());
  });
  assert.equal(outcome.status, "published");
  assert.equal(posts, 0);
});

test("no status, wrong show, failed encoding and test-only state never publish", async () => {
  const missing = env();
  let calls = 0;
  assert.equal((await publishReadyEpisode(missing, id, 0, () => { calls++; })).status, "not_eligible");
  await saveDraftState(missing, id, "uploaded", episodeId, "", true);
  assert.equal((await publishReadyEpisode(missing, id, 0, () => { calls++; })).status, "not_eligible");
  assert.equal(calls, 0, "test jobs are never looked up by the production publisher");

  const binding = env();
  await setUploaded(binding);
  const mismatch = await publishReadyEpisode(binding, id, 0,
    () => json(draft("READY", { show_id: 1 })));
  assert.equal(mismatch.status, "publish_review_required");
  assert.equal(mismatch.detail, "episode_identity_mismatch");

  const failed = env();
  await setUploaded(failed);
  const outcome = await publishReadyEpisode(failed, id, 0, () => json(draft("ERROR")));
  assert.equal(outcome.status, "publish_review_required");
  assert.equal(outcome.detail, "spreaker_encoding_error");
});

test("encoding timeout keeps draft unpublished instead of retrying forever", async () => {
  const binding = env();
  await setUploaded(binding);
  let postCount = 0;
  const result = await publishReadyEpisode(binding, id, MAX_PUBLISH_CHECKS, (_url, opts) => {
    if (opts.method === "POST") postCount++;
    return json(draft("PROCESSING"));
  });
  assert.equal(result.status, "publish_review_required");
  assert.equal(result.detail, "encoding_timeout");
  assert.equal(postCount, 0);
});

test("does not publish a draft without verified podcast MP3 or media identity", async () => {
  const binding = env();
  await setUploaded(binding);
  binding.SERMONS_BUCKET.head = async () => null;
  assert.equal((await publishReadyEpisode(binding, id, 0, () => {
    throw Error("should not fetch");
  })).detail, "podcast_audio_missing");
  const second = env();
  await setUploaded(second);
  let posts = 0;
  const result = await publishReadyEpisode(second, id, 0, (_url, opts) => {
    if (opts.method === "POST") posts++;
    return json(draft("READY", { media_id: null, media_url: null }));
  });
  assert.equal(result.detail, "audio_not_confirmed_ready");
  assert.equal(posts, 0);
});
