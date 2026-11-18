import { getContainer } from "@cloudflare/containers";
import { PROCESSOR_CONTAINER_ID, sourceKey, validId } from "./validation.mjs";
import { previewKey, requestedByteRange, resolveClipPicks } from "./highlight-video.mjs";

const statusKey = (id) => `sermon-highlights/clip-jobs/${id}.json`;
const highlightResultKey = (id) => `sermon-highlights/results/${id}.json`;
const reply = (data, status = 200) => Response.json(data, {
  status, headers: { "cache-control": "no-store" },
});
async function readJson(bucket, key) {
  const object = await bucket.get(key);
  if (!object) return null;
  try { return await object.json(); } catch { return null; }
}
async function writeStatus(env, id, state, clips, detail = "") {
  await env.SERMONS_BUCKET.put(statusKey(id), JSON.stringify({
    video_id: id, status: state, detail, clips,
    updated_at: new Date().toISOString(),
  }), { httpMetadata: { contentType: "application/json" } });
}

const ttlSeconds = 600;
async function hmac(secret, message) {
  if (typeof secret !== "string" || secret.length < 32) throw Error("clip_signing_unavailable");
  const encoder = new TextEncoder();
  const key = await crypto.subtle.importKey("raw", encoder.encode(secret),
    { name: "HMAC", hash: "SHA-256" }, false, ["sign"]);
  const digest = await crypto.subtle.sign("HMAC", key, encoder.encode(message));
  return [...new Uint8Array(digest)].map((x) => x.toString(16).padStart(2, "0")).join("");
}
const signedMessage = (key, exp) => `surfside-private-clip\n${exp}\n${key}`;
async function signedPreviewLink(request, env, key) {
  const exp = Math.floor(Date.now() / 1000) + ttlSeconds;
  const sig = await hmac(env.SURFSIDE_PODCAST_SHARED_SECRET, signedMessage(key, exp));
  const parts = key.match(/^sermon-highlights\/previews\/([A-Za-z0-9_-]{11})\/([1-5]-\d+-\d+\.mp4)$/);
  if (!parts) throw Error("invalid_clip_preview_key");
  const url = new URL(request.url);
  url.pathname = `/highlights/clips/view/${parts[1]}/${parts[2]}`;
  url.search = new URLSearchParams({ exp: String(exp), sig }).toString();
  return url.toString();
}
export async function statusClips(request, env, id) {
  const status = await readJson(env.SERMONS_BUCKET, statusKey(id)) ||
    { video_id: id, status: "not_queued" };
  if (status.status !== "ready") return reply(status);
  const clips = await Promise.all((status.clips || []).map(async (clip) => ({
    ...clip, preview_url: await signedPreviewLink(request, env, previewKey(id, clip)),
  })));
  return reply({ ...status, clips, preview_links_expire_seconds: ttlSeconds });
}
export async function enqueueClips(env, id, requested) {
  const prior = await readJson(env.SERMONS_BUCKET, statusKey(id));
  if (prior && ["queued", "processing"].includes(prior.status))
    return reply({ video_id: id, status: prior.status, already_exists: true });
  if (!await env.SERMONS_BUCKET.head(sourceKey(id)))
    return reply({ error: "sermon_missing" }, 409);
  const highlights = await readJson(env.SERMONS_BUCKET, highlightResultKey(id));
  if (!Array.isArray(highlights?.highlights))
    return reply({ error: "highlight_analysis_required" }, 409);
  let clips;
  try { clips = resolveClipPicks(highlights.highlights, requested); }
  catch (error) { return reply({ error: error.message }, 400); }
  await writeStatus(env, id, "queued", clips);
  try {
    await env.PODCAST_QUEUE.send({ kind: "highlight_clips", video_id: id, clips });
    return reply({ video_id: id, status: "queued", clips }, 202);
  } catch (error) {
    console.error("Unable to queue manual clip previews", id, String(error));
    await writeStatus(env, id, "failed", clips, "queue_unavailable");
    return reply({ error: "queue_unavailable" }, 503);
  }
}
export async function processClipJob(env, id, clips) {
  if (!validId(id) || !Array.isArray(clips) || !clips.length) throw Error("invalid_clip_job");
  // Canonical object names are derived from validated selection, not user paths.
  const clean = clips.map((c) => ({
    number: c.number, title: c.title, start: c.start, end: c.end,
  }));
  try {
    await writeStatus(env, id, "processing", clean);
    const response = await getContainer(env.PODCAST_CONTAINER, PROCESSOR_CONTAINER_ID)
      .fetch(new Request("http://container/highlights/render", {
        method: "POST", headers: { "content-type": "application/json" },
        body: JSON.stringify({ video_id: id, clips: clean }),
      }));
    if (!response.ok) throw Error("clip_container_failed");
    for (const clip of clean) {
      if (!await env.SERMONS_BUCKET.head(previewKey(id, clip)))
        throw Error("clip_preview_missing");
    }
    await writeStatus(env, id, "ready", clean);
  } catch (error) {
    console.error("Manual highlight clip rendering failed", id, String(error));
    await writeStatus(env, id, "failed", clean, "clip_render_failed");
    throw error;
  }
}

// Short-lived, clip-scoped GET URLs allow native browser video review,
// including seeking, without making the sermon R2 bucket public.
export async function servePreview(request, env) {
  const url = new URL(request.url);
  const match = url.pathname.match(/^\/highlights\/clips\/view\/([A-Za-z0-9_-]{11})\/([1-5]-\d+-\d+\.mp4)$/);
  if (!match || request.method !== "GET")
    return reply({ error: "not_found" }, 404);
  const expText = url.searchParams.get("exp") || "";
  const received = url.searchParams.get("sig") || "";
  if (!/^\d{10}$/.test(expText) || !/^[a-f0-9]{64}$/.test(received))
    return reply({ error: "unauthorized" }, 401);
  const exp = Number(expText), now = Math.floor(Date.now() / 1000);
  if (exp <= now || exp > now + ttlSeconds) return reply({ error: "link_expired" }, 403);
  const key = `sermon-highlights/previews/${match[1]}/${match[2]}`;
  const expected = await hmac(env.SURFSIDE_PODCAST_SHARED_SECRET, signedMessage(key, exp));
  let mismatch = 0;
  for (let i = 0; i < 64; i++) mismatch |= expected.charCodeAt(i) ^ received.charCodeAt(i);
  if (mismatch) return reply({ error: "unauthorized" }, 401);
  const head = await env.SERMONS_BUCKET.head(key);
  if (!head) return reply({ error: "clip_missing" }, 404);
  const range = requestedByteRange(request.headers.get("range"), head.size);
  const headers = new Headers({
    "content-type": "video/mp4",
    "accept-ranges": "bytes",
    "cache-control": "private, no-store",
    "referrer-policy": "no-referrer",
    "x-content-type-options": "nosniff",
    "content-disposition": `inline; filename="${match[2]}"`,
  });
  if (range === false) {
    headers.set("content-range", `bytes */${head.size}`);
    return new Response(null, { status: 416, headers });
  }
  const body = await env.SERMONS_BUCKET.get(key,
    range ? { range: { offset: range.offset, length: range.length } } : {});
  if (!body?.body) return reply({ error: "clip_missing" }, 404);
  headers.set("content-length", String(range ? range.length : head.size));
  if (range) headers.set("content-range", `bytes ${range.offset}-${range.end}/${head.size}`);
  return new Response(body.body, { status: range ? 206 : 200, headers });
}
