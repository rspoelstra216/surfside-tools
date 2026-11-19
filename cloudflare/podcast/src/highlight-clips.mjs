import { getContainer } from "@cloudflare/containers";
import { PROCESSOR_CONTAINER_ID, sourceKey, validId } from "./validation.mjs";
import { previewKey, resolveClipPicks } from "./highlight-video.mjs";

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

// Keep previously generated preview links available when more suggestions are
// requested. A later batch renders only the new/changed clips but publishes a
// combined manifest of completed private preview files.
const sameClip = (a, b) =>
  a.number === b.number && a.start === b.start && a.end === b.end;
const mergeClips = (prior, next) => {
  const merged = Array.isArray(prior) ? [...prior] : [];
  for (const clip of next) {
    const index = merged.findIndex((c) => c.number === clip.number);
    if (index >= 0) merged[index] = clip;
    else merged.push(clip);
  }
  return merged.sort((a, b) => a.number - b.number);
};

export async function enqueueClips(env, id, requested) {
  if (!await env.SERMONS_BUCKET.head(sourceKey(id)))
    return reply({ error: "sermon_missing" }, 409);
  const highlights = await readJson(env.SERMONS_BUCKET, highlightResultKey(id));
  if (!Array.isArray(highlights?.highlights))
    return reply({ error: "highlight_analysis_required" }, 409);
  let clips;
  try { clips = resolveClipPicks(highlights.highlights, requested); }
  catch (error) { return reply({ error: error.message }, 400); }
  const prior = await readJson(env.SERMONS_BUCKET, statusKey(id));
  if (prior && ["queued", "processing"].includes(prior.status)) {
    if (clips.every((c) => (prior.clips || []).some((p) => sameClip(p, c))))
      return reply({ video_id: id, status: prior.status, already_exists: true });
    return reply({ error: "preview_in_progress" }, 409);
  }
  const previous = prior?.status === "ready" ? (prior.clips || []) : [];
  const available = await Promise.all(clips.map(async (clip) =>
    previous.some((p) => sameClip(p, clip)) &&
    !!await env.SERMONS_BUCKET.head(previewKey(id, clip))));
  if (available.every(Boolean))
    return reply({ video_id: id, status: "ready", already_exists: true });
  const pending = clips.filter((_, i) => !available[i]);
  // A bounded batch always contains <=5 clips, even after a second pass.
  const retained = await Promise.all(previous.map(async (clip) =>
    !!await env.SERMONS_BUCKET.head(previewKey(id, clip))));
  const priorReady = previous.filter((_, i) => retained[i]);
  const combined = mergeClips(priorReady, clips);
  await writeStatus(env, id, "queued", combined);
  try {
    await env.PODCAST_QUEUE.send({
      kind: "highlight_clips", video_id: id,
      clips: pending, manifest: combined,
    });
    return reply({ video_id: id, status: "queued", clips: combined }, 202);
  } catch (error) {
    console.error("Unable to queue manual clip previews", id, String(error));
    await writeStatus(env, id, "failed", clips, "queue_unavailable");
    return reply({ error: "queue_unavailable" }, 503);
  }
}
export async function processClipJob(env, id, clips, manifest = clips) {
  if (!validId(id) || !Array.isArray(clips) || !clips.length ||
      clips.length > 5 || !Array.isArray(manifest) ||
      manifest.length > 10) throw Error("invalid_clip_job");
  // Canonical object names are derived from validated selection, not user paths.
  const clean = clips.map((c) => ({
    number: c.number, title: c.title, start: c.start, end: c.end,
  }));
  const combined = mergeClips(manifest, clean);
  try {
    await writeStatus(env, id, "processing", combined);
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
    await writeStatus(env, id, "ready", combined);
  } catch (error) {
    console.error("Manual highlight clip rendering failed", id, String(error));
    await writeStatus(env, id, "failed", combined, "clip_render_failed");
    throw error;
  }
}

