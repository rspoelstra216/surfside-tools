import { verticalKey } from "./highlight-vertical-video.mjs";
import { requestedByteRange } from "./highlight-video.mjs";

// Pure link handlers; separable from the Cloudflare Container Queue.
const jobKey = (id) => `sermon-highlights/vertical-jobs/${id}.json`;
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
  const [, mode, id, filename, startText, endText, anchorText] = match;
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
