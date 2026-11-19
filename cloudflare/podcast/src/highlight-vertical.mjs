import { getContainer } from "@cloudflare/containers";
import { PROCESSOR_CONTAINER_ID, sourceKey, validId } from "./validation.mjs";
import { resolveApprovedExports } from "./highlight-video.mjs";
import { verticalKey, validVerticalClip, sameVerticalJob } from "./highlight-vertical-video.mjs";
import { requestedByteRange } from "./highlight-video.mjs";

const jobKey = (id) => `sermon-highlights/vertical-jobs/${id}.json`;
const resultKey = (id) => `sermon-highlights/results/${id}.json`;
const ttlSeconds = 600;
const pathPattern = /^\/highlights\/vertical\/(view|download)\/([A-Za-z0-9_-]{11})\/((?:[1-9]|10)-(\d+)-(\d+)-a(\d{1,3})\.mp4)$/;
const reply = (value, status = 200) => Response.json(value, {
  status, headers: { "cache-control": "no-store" },
});

async function getJson(bucket, key) {
  const stored = await bucket.get(key);
  if (!stored) return null;
  try { return await stored.json(); } catch { return null; }
}
async function save(env, id, status, clip, anchor, extra = {}) {
  await env.SERMONS_BUCKET.put(jobKey(id), JSON.stringify({
    video_id: id, status, clip, anchor,
    ...extra, updated_at: new Date().toISOString(),
  }), { httpMetadata: { contentType: "application/json" } });
}
async function verifiedClip(env, id, requestClip, anchor) {
  if (!validVerticalClip(requestClip, anchor))
    throw Error("invalid_vertical_clip");
  const analysis = await getJson(env.SERMONS_BUCKET, resultKey(id));
  if (!Array.isArray(analysis?.highlights)) throw Error("highlight_analysis_required");
  const clips = resolveApprovedExports(analysis.highlights, [requestClip]);
  return { number: clips[0].number, start: clips[0].start, end: clips[0].end };
}

export async function queueVertical(env, id, requestClip, anchor) {
  if (!validId(id)) return reply({ error: "invalid_video_id" }, 400);
  if (!await env.SERMONS_BUCKET.head(sourceKey(id)))
    return reply({ error: "sermon_missing" }, 409);
  let clip;
  try { clip = await verifiedClip(env, id, requestClip, anchor); }
  catch (error) { return reply({ error: error.message }, 400); }
  const prior = await getJson(env.SERMONS_BUCKET, jobKey(id));
  if (["queued", "processing"].includes(prior?.status)) {
    if (sameVerticalJob(prior, clip, anchor))
      return reply({ video_id: id, status: prior.status, already_exists: true });
    return reply({ error: "vertical_job_in_progress" }, 409);
  }
  if (prior?.status === "ready" && sameVerticalJob(prior, clip, anchor) &&
      await env.SERMONS_BUCKET.head(verticalKey(id, clip, anchor)))
    return reply({ video_id: id, status: "ready", already_exists: true });
  await save(env, id, "queued", clip, anchor);
  try {
    await env.PODCAST_QUEUE.send({
      kind: "highlight_vertical", video_id: id, clip, anchor,
    });
    return reply({ video_id: id, status: "queued" }, 202);
  } catch (error) {
    console.error("Cannot queue experimental portrait job", id, String(error));
    await save(env, id, "failed", clip, anchor, { detail: "queue_unavailable" });
    return reply({ error: "queue_unavailable" }, 503);
  }
}

export async function processVertical(env, id, requestClip, anchor) {
  if (!validId(id)) throw Error("invalid_video_id");
  let clip;
  try {
    clip = await verifiedClip(env, id, requestClip, anchor);
    const current = await getJson(env.SERMONS_BUCKET, jobKey(id));
    // A newer manual request must not be overwritten by a stale Queue retry.
    if (!sameVerticalJob(current, clip, anchor)) return;
    await save(env, id, "processing", clip, anchor);
    const response = await getContainer(env.PODCAST_CONTAINER, PROCESSOR_CONTAINER_ID)
      .fetch(new Request("http://container/highlights/vertical/render", {
        method: "POST", headers: { "content-type": "application/json" },
        body: JSON.stringify({ video_id: id, clip, anchor }),
      }));
    if (!response.ok) throw Error("vertical_container_failed");
    const info = await response.json();
    if (!await env.SERMONS_BUCKET.head(verticalKey(id, clip, anchor)))
      throw Error("vertical_output_missing");
    const currentAfter = await getJson(env.SERMONS_BUCKET, jobKey(id));
    if (sameVerticalJob(currentAfter, clip, anchor))
      await save(env, id, "ready", clip, anchor, {
        mode: info.mode, confidence: info.confidence,
        face_samples: info.face_samples, samples: info.samples,
      });
  } catch (error) {
    console.error("Experimental portrait conversion failed", id, String(error));
    const current = await getJson(env.SERMONS_BUCKET, jobKey(id));
    if (clip && sameVerticalJob(current, clip, anchor))
      await save(env, id, "failed", clip, anchor, {
        detail: "vertical_render_failed",
      });
    throw error;
  }
}

async function sign(secret, message) {
  if (typeof secret !== "string" || secret.length < 32)
    throw Error("vertical_signing_unavailable");
  const enc = new TextEncoder();
  const key = await crypto.subtle.importKey("raw", enc.encode(secret),
    { name: "HMAC", hash: "SHA-256" }, false, ["sign"]);
  const bytes = await crypto.subtle.sign("HMAC", key, enc.encode(message));
  return [...new Uint8Array(bytes)].map((n) => n.toString(16).padStart(2, "0")).join("");
}
const signMessage = (mode, key, exp) => `surfside-vertical-${mode}\n${exp}\n${key}`;
async function link(request, env, id, clip, anchor, mode) {
  const key = verticalKey(id, clip, anchor);
  const name = key.split("/").at(-1);
  const exp = Math.floor(Date.now() / 1000) + ttlSeconds;
  const sig = await sign(env.SURFSIDE_PODCAST_SHARED_SECRET,
    signMessage(mode, key, exp));
  const url = new URL(request.url);
  url.pathname = `/highlights/vertical/${mode}/${id}/${name}`;
  url.search = new URLSearchParams({ exp: String(exp), sig }).toString();
  return url.toString();
}
export async function verticalStatus(request, env, id) {
  const state = await getJson(env.SERMONS_BUCKET, jobKey(id)) ||
    { video_id: id, status: "not_queued" };
  if (state.status !== "ready") return reply(state);
  const clip = state.clip, anchor = state.anchor;
  const object = await env.SERMONS_BUCKET.head(verticalKey(id, clip, anchor));
  if (!object) return reply({ ...state, status: "incomplete" });
  return reply({
    ...state,
    preview_url: await link(request, env, id, clip, anchor, "view"),
    download_url: await link(request, env, id, clip, anchor, "download"),
    link_expires_seconds: ttlSeconds,
  });
}
export async function serveVertical(request, env) {
  const url = new URL(request.url);
  const match = pathPattern.exec(url.pathname);
  if (!match || request.method !== "GET")
    return reply({ error: "not_found" }, 404);
  const [, mode, id, filename, numberText, startText, endText, anchorText] = match;
  const clip = {
    number: Number(filename.split("-")[0]),
    start: Number(startText) / 100, end: Number(endText) / 100,
  };
  const anchor = Number(anchorText);
  let key;
  try { key = verticalKey(id, clip, anchor); }
  catch { return reply({ error: "not_found" }, 404); }
  if (key.split("/").at(-1) !== filename)
    return reply({ error: "not_found" }, 404);
  const expText = url.searchParams.get("exp") || "";
  const received = url.searchParams.get("sig") || "";
  if (!/^\d{10}$/.test(expText) || !/^[a-f0-9]{64}$/.test(received))
    return reply({ error: "unauthorized" }, 401);
  const exp = Number(expText), now = Math.floor(Date.now() / 1000);
  if (exp <= now || exp > now + ttlSeconds)
    return reply({ error: "link_expired" }, 403);
  const expected = await sign(env.SURFSIDE_PODCAST_SHARED_SECRET,
    signMessage(mode, key, exp));
  let diff = 0;
  for (let i = 0; i < 64; i++)
    diff |= expected.charCodeAt(i) ^ received.charCodeAt(i);
  if (diff) return reply({ error: "unauthorized" }, 401);
  const head = await env.SERMONS_BUCKET.head(key);
  if (!head) return reply({ error: "vertical_missing" }, 404);
  const range = requestedByteRange(request.headers.get("range"), head.size);
  const headers = new Headers({
    "content-type": "video/mp4",
    "accept-ranges": "bytes",
    "cache-control": "private, no-store",
    "referrer-policy": "no-referrer",
    "x-content-type-options": "nosniff",
    "content-disposition": `${mode === "download" ? "attachment" : "inline"}; filename="surfside-${id}-vertical-${clip.number}.mp4"`,
  });
  if (range === false) {
    headers.set("content-range", `bytes */${head.size}`);
    return new Response(null, { status: 416, headers });
  }
  const object = await env.SERMONS_BUCKET.get(key,
    range ? { range: { offset: range.offset, length: range.length } } : {});
  if (!object?.body) return reply({ error: "vertical_missing" }, 404);
  headers.set("content-length", String(range ? range.length : head.size));
  if (range) headers.set("content-range",
    `bytes ${range.offset}-${range.end}/${head.size}`);
  return new Response(object.body, { status: range ? 206 : 200, headers });
}
