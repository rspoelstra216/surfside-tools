// Spreaker integration is opt-in and never publishes: only DRAFT episodes are made.
// Store a durable per-video episode ID before uploading the audio so a Queue retry
// reuses the same draft instead of creating a second episode.
const enc = new TextEncoder();
const validId = (id) => typeof id === "string" && /^[A-Za-z0-9_-]{11}$/.test(id);
export const draftKey = (id, test = false) => `${test ? "podcast-test-spreaker" : "podcast-spreaker"}/${id}.json`;

export const validDraftMetadata = (data) =>
  data && typeof data === "object" && !Array.isArray(data) &&
  typeof data.title === "string" && data.title.trim().length > 0 &&
  data.title.length <= 140 && typeof data.description === "string" &&
  data.description.trim().length > 0 && data.description.length <= 8000;

export const spreakerConfigured = (env) =>
  typeof env.SPREAKER_OAUTH_ACCESS_TOKEN === "string" &&
  env.SPREAKER_OAUTH_ACCESS_TOKEN.length > 20 &&
  /^[1-9][0-9]*$/.test(String(env.SPREAKER_SHOW_ID ?? ""));

const read = async (env, id, test = false) => {
  const object = await env.SERMONS_BUCKET.get(draftKey(id, test));
  if (!object) return null;
  try { return await object.json(); } catch { return { status: "review_required" }; }
};
export const readDraftState = read;

// Explicit recovery is restricted to the known Cloudflare redirect-mode
// preflight failure. This historical failure occurred before any HTTP
// request reached Spreaker, so no draft episode was created by that attempt.
// Other uncertain responses must remain review-only to prevent duplicates.
export const canRetryKnownRedirectFailure = (state) =>
  state?.status === "review_required" &&
  state?.detail === "draft_response_uncertain" &&
  state?.episode_id == null;


export async function saveDraftState(env, id, status, episodeId = null, detail = "", test = false) {
  const state = { video_id: id, status, episode_id: episodeId, detail,
    updated_at: new Date().toISOString() };
  await env.SERMONS_BUCKET.put(draftKey(id, test), JSON.stringify(state), {
    httpMetadata: { contentType: "application/json" },
  });
  return state;
}

const endpoint = "https://api.spreaker.com/v2";
const tokenHeaders = (env) => ({ authorization: `Bearer ${env.SPREAKER_OAUTH_ACCESS_TOKEN}` });
const getEpisode = (data) => data?.response?.episode;

// Stream the R2 body directly to Spreaker; no full MP3 buffering in Worker memory.
export function multipartAudioStream(audio, videoId, boundary) {
  const header = enc.encode(`--${boundary}\r\nContent-Disposition: form-data; name="media_file"; filename="${videoId}.mp3"\r\nContent-Type: audio/mpeg\r\n\r\n`);
  const trailer = enc.encode(`\r\n--${boundary}--\r\n`);
  const reader = audio.body.getReader();
  let phase = 0;
  const body = new ReadableStream({
    async pull(controller) {
      try {
        if (phase === 0) {
          phase = 1;
          controller.enqueue(header);
          return;
        }
        if (phase === 1) {
          const { done, value } = await reader.read();
          if (!done) { controller.enqueue(value); return; }
          phase = 2;
        }
        controller.enqueue(trailer);
        controller.close();
        phase = 3;
      } catch (error) {
        controller.error(error);
      }
    },
    cancel(reason) { return reader.cancel(reason); },
  });
  return { body, contentLength: header.byteLength + audio.size + trailer.byteLength };
}

export async function processSpreakerDraft(env, id, metadata, fetcher = fetch, test = false) {
  const save = (status, episodeId = null, detail = "") => saveDraftState(env, id, status, episodeId, detail, test);
  if (!validId(id) || !validDraftMetadata(metadata)) {
    return save("review_required", null, "invalid_metadata");
  }
  const previous = await read(env, id, test);
  if (previous?.status === "uploaded") return previous;
  if (previous?.status === "review_required" || previous?.status === "creating") return previous;
  if (!spreakerConfigured(env)) {
    return save("not_configured", previous?.episode_id ?? null);
  }
  const audio = await env.SERMONS_BUCKET.get(`${test ? "podcast-test-audio" : "podcast-audio"}/${id}.mp3`);
  if (!audio || !audio.size || audio.size > 300 * 1024 * 1024 || !audio.body) {
    return save("review_required", previous?.episode_id ?? null, "audio_missing_or_invalid");
  }

  let episodeId = previous?.episode_id;
  if (!episodeId) {
    // This marker prevents a blind duplicate POST after an ambiguous network failure.
    await save("creating");
    try {
      const params = new URLSearchParams({
        title: metadata.title,
        show_id: String(env.SPREAKER_SHOW_ID),
        description: metadata.description,
      });
      const response = await fetcher(`${endpoint}/episodes/drafts`, {
        method: "POST", headers: { ...tokenHeaders(env), "content-type": "application/x-www-form-urlencoded" },
        body: params.toString(), redirect: "manual",
      });
      if (!response.ok) {
        return save("review_required", null, `draft_http_${response.status}`);
      }
      const episode = getEpisode(await response.json());
      if (!Number.isSafeInteger(Number(episode?.episode_id)) || Number(episode.episode_id) <= 0 ||
          episode?.type !== "DRAFT") {
        return save("review_required", null, "unexpected_draft_response");
      }
      episodeId = String(episode.episode_id);
      await save("draft_created", episodeId);
    } catch (error) {
      console.error("Spreaker draft creation ambiguous; manual review required", id, String(error));
      return save("review_required", null, "draft_response_uncertain");
    }
  }

  const boundary = "surfside-" + crypto.randomUUID().replace(/-/g, "");
  const { body, contentLength } = multipartAudioStream(audio, id, boundary);
  let response;
  try {
    response = await fetcher(`${endpoint}/episodes/${episodeId}`, {
      method: "POST",
      headers: { ...tokenHeaders(env), "content-type": `multipart/form-data; boundary=${boundary}`,
        "content-length": String(contentLength) },
      body, duplex: "half", redirect: "manual",
    });
  } catch (error) {
    await save("upload_retry", episodeId, "audio_transfer_error");
    throw error;
  }
  if (!response.ok) {
    await save(response.status === 401 || response.status === 403
      ? "review_required" : "upload_retry", episodeId, `audio_http_${response.status}`);
    if (response.status === 401 || response.status === 403) return read(env, id, test);
    throw Error(`spreaker_audio_http_${response.status}`);
  }
  const uploaded = getEpisode(await response.json().catch(() => null));
  if (!uploaded || String(uploaded.episode_id) !== episodeId || uploaded.type !== "DRAFT") {
    return save("review_required", episodeId, "unexpected_audio_response");
  }
  // Uploaded means attached to an UNPUBLISHED draft; encoding may still be running.
  return save("uploaded", episodeId);
}
