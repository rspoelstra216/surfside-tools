// Automatic publication is opt-in per signed new weekly job. Existing drafts
// and isolated test jobs are never promoted automatically.
import { audioKey } from "./validation.mjs";
import { readDraftState, saveDraftState, spreakerConfigured } from "./spreaker.mjs";

export const PUBLISH_CHECK_DELAY_SECONDS = 90;
export const MAX_PUBLISH_CHECKS = 20;
const API = "https://api.spreaker.com/v2";
const validId = (id) => typeof id === "string" && /^[A-Za-z0-9_-]{11}$/.test(id);
const validEpisodeId = (id) => /^[1-9][0-9]*$/.test(String(id ?? ""));
const retryLater = (reason) => ({ status: "check_again", reason });

// Re-queue ONLY the historical, pre-publication audio readiness false-negative.
// An existing episode ID is required and upload/MP3 must already be complete.
// Unknown publishing outcomes and previous publication attempts cannot use it.
export const canRetryAudioReadinessReview = (audioState, mp3, mp4, previous) =>
  audioState?.status === "ready" && Boolean(mp3) && Boolean(mp4) &&
  previous?.status === "publish_review_required" &&
  ["audio_not_confirmed_ready", "encoding_status_unknown"].includes(previous?.detail) &&
  validEpisodeId(previous.episode_id);

export async function publishReadyEpisode(env, id, attempt = 0, fetcher = fetch) {
  if (!validId(id) || !Number.isInteger(attempt) || attempt < 0 || attempt > MAX_PUBLISH_CHECKS)
    return { status: "invalid_job" };

  const previous = await readDraftState(env, id, false);
  if (previous?.status === "published") return previous;
  if (!previous || !["uploaded", "encoding_wait", "publishing", "publication_uncertain"].includes(previous.status) ||
      !validEpisodeId(previous.episode_id)) return { status: "not_eligible" };

  const episodeId = String(previous.episode_id);
  const save = (status, detail = "") => saveDraftState(env, id, status, episodeId, detail);
  if (!spreakerConfigured(env)) return save("publish_review_required", "spreaker_not_configured");
  // Never publish if our R2 audio has disappeared after the upload succeeded.
  if (!await env.SERMONS_BUCKET.head(audioKey(id))) {
    return save("publish_review_required", "podcast_audio_missing");
  }

  const url = `${API}/episodes/${episodeId}`;
  const headers = { authorization: `Bearer ${env.SPREAKER_OAUTH_ACCESS_TOKEN}` };
  let response;
  try {
    response = await fetcher(url, { method: "GET", headers, redirect: "manual" });
  } catch (error) {
    console.error("Spreaker publication lookup failed", id, String(error));
    if (attempt >= MAX_PUBLISH_CHECKS) return save("publish_review_required", "lookup_timeout");
    // If a publish POST may have succeeded, never blindly POST it again.
    if (previous.status !== "publishing" && previous.status !== "publication_uncertain")
      await save("encoding_wait", "lookup_unavailable");
    return retryLater("lookup_unavailable");
  }
  if (!response.ok) {
    if (attempt < MAX_PUBLISH_CHECKS && [429, 500, 502, 503, 504].includes(response.status)) {
      if (previous.status !== "publishing" && previous.status !== "publication_uncertain")
        await save("encoding_wait", `lookup_http_${response.status}`);
      return retryLater("lookup_unavailable");
    }
    return save("publish_review_required", `lookup_http_${response.status}`);
  }

  const episode = (await response.json().catch(() => null))?.response?.episode;
  if (!episode || String(episode.episode_id) !== episodeId ||
      String(episode.show_id) !== String(env.SPREAKER_SHOW_ID)) {
    return save("publish_review_required", "episode_identity_mismatch");
  }
  if (episode.type === "RECORDED") {
    // Already published manually or by an earlier ambiguous API call.
    return episode.published_at && episode.visibility === "PUBLIC"
      ? save("published")
      : save("publish_review_required", "recorded_not_public");
  }
  if (episode.type !== "DRAFT") return save("publish_review_required", "unexpected_episode_type");

  // A publish request may have succeeded even if the response was lost.
  // If the next GET still says DRAFT, leave it for manual review, never POST twice.
  if (previous.status === "publishing" || previous.status === "publication_uncertain") {
    return save("publish_review_required", "publish_response_uncertain");
  }

  if (episode.encoding_status === "ERROR")
    return save("publish_review_required", "spreaker_encoding_error");

  // Spreaker's published draft workflow explicitly permits publishing after
  // a successful media_file upload *before* encoding completes. Only once
  // encoding reaches READY will listeners be able to play it. Our R2 MP3 and
  // Spreaker's successful draft upload acknowledgment have both been checked,
  // so an absent encoding_status in GET is not evidence of missing audio.
  // A known encoding failure or an undocumented non-empty state still fails
  // closed. Spreaker itself rejects publishing if an audio file is absent.
  if (typeof episode.encoding_status === "string" &&
      !["READY", "PENDING", "PROCESSING"].includes(episode.encoding_status)) {
    return save("publish_review_required", "unexpected_encoding_status");
  }

  // Spreaker documents POST /v2/episodes/{id} with published_at in UTC to
  // publish an existing DRAFT after an acknowledged audio upload. Spreaker
  // may still be encoding; public playback becomes available when READY.
  await save("publishing");
  const publishedAt = new Date().toISOString().slice(0, 19).replace("T", " ");
  let publishedResponse;
  try {
    publishedResponse = await fetcher(url, {
      method: "POST",
      headers: { ...headers, "content-type": "application/x-www-form-urlencoded" },
      body: new URLSearchParams({ published_at: publishedAt, visibility: "PUBLIC" }).toString(),
      redirect: "manual",
    });
  } catch (error) {
    console.error("Spreaker publication response uncertain", id, String(error));
    await save("publication_uncertain", "publish_request_uncertain");
    return retryLater("verify_publish_result");
  }
  if (!publishedResponse.ok) {
    if ([429, 500, 502, 503, 504].includes(publishedResponse.status) && attempt < MAX_PUBLISH_CHECKS) {
      await save("publication_uncertain", `publish_http_${publishedResponse.status}`);
      return retryLater("verify_publish_result");
    }
    return save("publish_review_required", `publish_http_${publishedResponse.status}`);
  }
  const updated = (await publishedResponse.json().catch(() => null))?.response?.episode;
  if (updated && String(updated.episode_id) === episodeId &&
      updated.type === "RECORDED" && updated.visibility === "PUBLIC" && updated.published_at) {
    return save("published");
  }
  await save("publication_uncertain", "unexpected_publish_response");
  return retryLater("verify_publish_result");
}
