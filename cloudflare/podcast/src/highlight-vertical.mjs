import { getContainer } from "@cloudflare/containers";
import { PROCESSOR_CONTAINER_ID, sourceKey, validId } from "./validation.mjs";
import { resolveApprovedExports } from "./highlight-video.mjs";
import { verticalKey, validVerticalClip, sameVerticalJob } from "./highlight-vertical-video.mjs";

const jobKey = (id) => `sermon-highlights/vertical-jobs/${id}.json`;
const resultKey = (id) => `sermon-highlights/results/${id}.json`;
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

