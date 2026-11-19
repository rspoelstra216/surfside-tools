import { validId } from "./validation.mjs";
import { enqueueHighlights } from "./highlights.mjs";
import { enqueueClips } from "./highlight-clips.mjs";
import { paddedHighlightPicks } from "./highlight-automation.mjs";

const resultKey = (id) => `sermon-highlights/results/${id}.json`;

async function getHighlightResult(env, id) {
  const object = await env.SERMONS_BUCKET.get(resultKey(id));
  if (!object) return null;
  try { return await object.json(); } catch { return null; }
}

export async function queueAutomaticPreviews(env, id) {
  if (!validId(id)) throw Error("invalid_highlight_id");
  const analysis = await getHighlightResult(env, id);
  const clips = paddedHighlightPicks(analysis?.highlights);
  if (!clips.length) throw Error("no_valid_highlight_previews");
  // Reuses existing private, staff-scoped renderer and its queue status. The
  // result is idempotent for matching completed or in-flight previews.
  const result = await enqueueClips(env, id, clips);
  if (!result.ok) throw Error("automatic_previews_not_queued_" + result.status);
  return result;
}

/** Handles existing analysis+missing previews as well as fresh AI analysis. */
export async function requestHighlightPreparation(env, id) {
  if (!validId(id)) return Response.json({ error: "invalid_video_id" }, { status: 400 });
  const existing = await getHighlightResult(env, id);
  if (!existing) return enqueueHighlights(env, id);
  const result = await queueAutomaticPreviews(env, id);
  const state = await result.json();
  return Response.json({
    video_id: id, status: "ready", already_analyzed: true,
    previews: state.status, preview_already_exists: state.already_exists === true,
  }, { status: result.status, headers: { "cache-control": "no-store" } });
}
