import { exportKey, requestedByteRange, exportJobStalled } from "./highlight-video.mjs";

// Download-only short-lived URLs. The R2 bucket and final exports remain private.
const statusKey = (id) => `sermon-highlights/export-jobs/${id}.json`;
const ttlSeconds = 600;
const reply = (data, status = 200) =>
  Response.json(data, { status, headers: { "cache-control": "no-store" } });
const expectedPath = /^\/highlights\/exports\/download\/([A-Za-z0-9_-]{11})\/((?:[1-9]|10)-\d+-\d+\.mp4)$/;

async function signature(secret, data) {
  if (typeof secret !== "string" || secret.length < 32)
    throw Error("export_signing_unavailable");
  const enc = new TextEncoder();
  const key = await crypto.subtle.importKey("raw", enc.encode(secret),
    { name: "HMAC", hash: "SHA-256" }, false, ["sign"]);
  const result = await crypto.subtle.sign("HMAC", key, enc.encode(data));
  return [...new Uint8Array(result)].map((x) => x.toString(16).padStart(2, "0")).join("");
}
const signingInput = (key, exp) => `surfside-approved-export\n${exp}\n${key}`;
async function makeLink(request, env, key) {
  const match = key.match(/^sermon-highlights\/exports\/([A-Za-z0-9_-]{11})\/((?:[1-9]|10)-\d+-\d+\.mp4)$/);
  if (!match) throw Error("invalid_export_key");
  const exp = Math.floor(Date.now() / 1000) + ttlSeconds;
  const sig = await signature(env.SURFSIDE_PODCAST_SHARED_SECRET, signingInput(key, exp));
  const url = new URL(request.url);
  url.pathname = `/highlights/exports/download/${match[1]}/${match[2]}`;
  url.search = new URLSearchParams({ exp: String(exp), sig }).toString();
  return url.toString();
}
export async function statusExports(request, env, id) {
  const object = await env.SERMONS_BUCKET.get(statusKey(id));
  const stored = object ? await object.json().catch(() => null) : null;
  const job = stored || { video_id: id, status: "not_queued" };
  if (exportJobStalled(job))
    return reply({ ...job, status: "stalled", detail: "old_export_attempt_stalled" });
  if (job.status !== "ready") return reply(job);
  const clips = [];
  for (const clip of job.clips || []) {
    const key = exportKey(id, clip);
    if (!await env.SERMONS_BUCKET.head(key))
      return reply({ ...job, status: "incomplete", detail: "export_missing" });
    clips.push({ ...clip, download_url: await makeLink(request, env, key) });
  }
  return reply({ ...job, clips, download_links_expire_seconds: ttlSeconds });
}
export async function serveExportDownload(request, env) {
  const url = new URL(request.url);
  const match = expectedPath.exec(url.pathname);
  if (!match || request.method !== "GET")
    return reply({ error: "not_found" }, 404);
  const expText = url.searchParams.get("exp") || "";
  const provided = url.searchParams.get("sig") || "";
  if (!/^\d{10}$/.test(expText) || !/^[a-f0-9]{64}$/.test(provided))
    return reply({ error: "unauthorized" }, 401);
  const exp = Number(expText), now = Math.floor(Date.now() / 1000);
  if (exp <= now || exp > now + ttlSeconds)
    return reply({ error: "link_expired" }, 403);
  const key = `sermon-highlights/exports/${match[1]}/${match[2]}`;
  const correct = await signature(env.SURFSIDE_PODCAST_SHARED_SECRET, signingInput(key, exp));
  let mismatch = 0;
  for (let i = 0; i < 64; i++) mismatch |= correct.charCodeAt(i) ^ provided.charCodeAt(i);
  if (mismatch) return reply({ error: "unauthorized" }, 401);
  const head = await env.SERMONS_BUCKET.head(key);
  if (!head) return reply({ error: "export_missing" }, 404);
  const range = requestedByteRange(request.headers.get("range"), head.size);
  const headers = new Headers({
    "content-type": "video/mp4",
    "accept-ranges": "bytes",
    "cache-control": "private, no-store",
    "referrer-policy": "no-referrer",
    "x-content-type-options": "nosniff",
    "content-disposition": `attachment; filename="surfside-sermon-${match[1]}-highlight-${match[2].split("-")[0]}.mp4"`,
  });
  if (range === false) {
    headers.set("content-range", `bytes */${head.size}`);
    return new Response(null, { status: 416, headers });
  }
  const result = await env.SERMONS_BUCKET.get(key,
    range ? { range: { offset: range.offset, length: range.length } } : {});
  if (!result?.body) return reply({ error: "export_missing" }, 404);
  headers.set("content-length", String(range ? range.length : head.size));
  if (range) headers.set("content-range",
    `bytes ${range.offset}-${range.end}/${head.size}`);
  return new Response(result.body, { status: range ? 206 : 200, headers });
}
