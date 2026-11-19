import { getContainer } from "@cloudflare/containers";
import { PROCESSOR_CONTAINER_ID, sourceKey, validId } from "./validation.mjs";
import { exportKey, resolveApprovedExports, sameExports } from "./highlight-video.mjs";

// Final export jobs are strictly separate from 5-second-padded review previews.
// Output keys are immutable and contain exact reviewed source-MP4 timestamps.
const jobKey = (id) => `sermon-highlights/export-jobs/${id}.json`;
const resultKey = (id) => `sermon-highlights/results/${id}.json`;
const reply = (data, status = 200) =>
  Response.json(data, { status, headers: { "cache-control": "no-store" } });

async function getJson(bucket, key) {
  const object = await bucket.get(key);
  if (!object) return null;
  try { return await object.json(); } catch { return null; }
}
async function save(env, id, status, clips, detail = "") {
  await env.SERMONS_BUCKET.put(jobKey(id), JSON.stringify({
    video_id: id, status, detail, clips, updated_at: new Date().toISOString(),
  }), { httpMetadata: { contentType: "application/json" } });
}

export async function queueExports(env, id, requested) {
  if (!validId(id)) return reply({ error: "invalid_video_id" }, 400);
  if (!await env.SERMONS_BUCKET.head(sourceKey(id)))
    return reply({ error: "sermon_missing" }, 409);
  const analysis = await getJson(env.SERMONS_BUCKET, resultKey(id));
  if (!Array.isArray(analysis?.highlights))
    return reply({ error: "highlight_analysis_required" }, 409);
  let clips;
  try { clips = resolveApprovedExports(analysis.highlights, requested); }
  catch (error) { return reply({ error: error.message }, 400); }

  const prior = await getJson(env.SERMONS_BUCKET, jobKey(id));
  if (prior && ["queued", "processing"].includes(prior.status)) {
    if (sameExports(prior.clips, clips))
      return reply({ video_id: id, status: prior.status, already_exists: true });
    return reply({ error: "export_in_progress" }, 409);
  }
  if (prior?.status === "ready" && sameExports(prior.clips, clips) &&
      (await Promise.all(clips.map((clip) =>
        env.SERMONS_BUCKET.head(exportKey(id, clip))))).every(Boolean))
    return reply({ video_id: id, status: "ready", already_exists: true });

  await save(env, id, "queued", clips);
  try {
    await env.PODCAST_QUEUE.send({ kind: "highlight_exports", video_id: id, clips });
    return reply({ video_id: id, status: "queued", clips }, 202);
  } catch (error) {
    console.error("Unable to queue private highlight exports", id, String(error));
    await save(env, id, "failed", clips, "queue_unavailable");
    return reply({ error: "queue_unavailable" }, 503);
  }
}

export async function processExportJob(env, id, requested) {
  if (!validId(id)) throw Error("invalid_export_id");
  const analysis = await getJson(env.SERMONS_BUCKET, resultKey(id));
  const clips = resolveApprovedExports(analysis?.highlights, requested);
  try {
    await save(env, id, "processing", clips);
    const response = await getContainer(env.PODCAST_CONTAINER, PROCESSOR_CONTAINER_ID)
      .fetch(new Request("http://container/highlights/export", {
        method: "POST", headers: { "content-type": "application/json" },
        body: JSON.stringify({ video_id: id, clips }),
      }));
    if (!response.ok) throw Error("export_container_failed");
    for (const clip of clips) {
      if (!await env.SERMONS_BUCKET.head(exportKey(id, clip)))
        throw Error("approved_export_missing");
    }
    await save(env, id, "ready", clips);
  } catch (error) {
    console.error("Approved highlight export failed", id, String(error));
    await save(env, id, "failed", clips, "export_failed");
    throw error;
  }
}
