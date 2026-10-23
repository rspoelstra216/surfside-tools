import { Container, ContainerProxy, getContainer } from "@cloudflare/containers";
export { ContainerProxy };
import { validId, sourceKey, audioKey, statusKey, validStorageAccess, verifiedRequest } from "./validation.mjs";
import { uploadAudioToR2 } from "./audio-upload.mjs";
import { validDraftMetadata, testDraftMetadata, spreakerConfigured, processSpreakerDraft, readDraftState, saveDraftState } from "./spreaker.mjs";

const reply = (data, status = 200) => Response.json(data, {
  status,
  headers: { "cache-control": "no-store" },
});
const state = async (env, id, status, detail = "") => {
  await env.SERMONS_BUCKET.put(statusKey(id), JSON.stringify({
    video_id: id, status, detail, updated_at: new Date().toISOString()
  }), { httpMetadata: { contentType: "application/json" } });
};
const readState = async (env, id) => {
  const stored = await env.SERMONS_BUCKET.get(statusKey(id));
  if (!stored) return { video_id: id, status: "not_queued" };
  try { return await stored.json(); }
  catch { return { video_id: id, status: "unknown" }; }
};

export class PodcastContainer extends Container {
  defaultPort = 8080;
  sleepAfter = "1m";
  enableInternet = false;
}

// Only the processor can read sermon MP4 and the two fixed bumper files.
// No R2 credentials enter the container and only an MP3 output PUT is allowed.
PodcastContainer.outboundByHost = {
  "storage.internal": async (request, env) => {
    const pathname = new URL(request.url).pathname;
    const key = decodeURIComponent(pathname.slice(1));
    if (!validStorageAccess(key, request.method)) return new Response("Forbidden", { status: 403 });
    if (request.method === "GET") {
      const object = await env.SERMONS_BUCKET.get(key);
      return object ? new Response(object.body, { status: 200 }) : new Response("Missing", { status: 404 });
    }
    if (await env.SERMONS_BUCKET.head(key)) return new Response("Already exists", { status: 409 });
    // A generic ReadableStream has no known size and R2 rejects it.
    // Use the validated byte count to create a FixedLengthStream without
    // buffering full sermon MP3s inside the memory-limited Worker.
    return uploadAudioToR2(request, env.SERMONS_BUCKET, key);
  },
};

async function incoming(request, env) {
  const route = new URL(request.url).pathname;
  if (!["/jobs", "/status", "/draft-test"].includes(route) || request.method !== "POST") {
    return reply({ error: "not_found" }, 404);
  }
  if (!await verifiedRequest(request, env.SURFSIDE_PODCAST_SHARED_SECRET)) {
    return reply({ error: "unauthorized" }, 401);
  }
  let data;
  try { data = await request.json(); }
  catch { return reply({ error: "invalid_json" }, 400); }
  const id = data?.video_id;
  if (!validId(id)) return reply({ error: "invalid_video_id" }, 400);
  if (new URL(request.url).pathname === "/status") {
    const [audio, spreaker] = await Promise.all([readState(env, id), readDraftState(env, id)]);
    return reply(spreaker ? { ...audio, spreaker } : audio);
  }
  if (route === "/draft-test") {
    // Explicit one-time operator test. Never modifies existing MP3s or invokes FFmpeg.
    // The title visibly distinguishes this unpublished test from manually scheduled episodes.
    if (data?.test !== true || !validDraftMetadata(data?.spreaker)) {
      return reply({ error: "invalid_test_request" }, 400);
    }
    if (!spreakerConfigured(env)) return reply({ error: "spreaker_not_configured" }, 503);
    const audio = await env.SERMONS_BUCKET.head(audioKey(id));
    if (!audio || audio.size < 1024 || audio.size > 300 * 1024 * 1024) {
      return reply({ error: "prepared_audio_missing" }, 409);
    }
    const existing = await readDraftState(env, id);
    if (existing) {
      return reply({ video_id: id, status: existing.status, already_exists: true,
        episode_id: existing.episode_id ?? null }, 409);
    }
    const metadata = testDraftMetadata(data.spreaker);
    try {
      await saveDraftState(env, id, "queued");
      await env.PODCAST_QUEUE.send({ kind: "spreaker", video_id: id, spreaker: metadata });
      return reply({ video_id: id, status: "queued", test: true }, 202);
    } catch (error) {
      console.error("Unable to queue Spreaker test draft", id, String(error));
      await saveDraftState(env, id, "queue_unavailable");
      return reply({ error: "queue_unavailable" }, 503);
    }
  }
  if (!await env.SERMONS_BUCKET.head(sourceKey(id))) return reply({ error: "sermon_missing" }, 409);
  if (!await env.SERMONS_BUCKET.head("podcast-assets/intro.mp3") ||
      !await env.SERMONS_BUCKET.head("podcast-assets/outro.mp3")) {
    return reply({ error: "bumper_assets_missing" }, 409);
  }
  if (await env.SERMONS_BUCKET.head(audioKey(id))) {
    await state(env, id, "ready");
    return reply({ video_id: id, status: "ready", already_exists: true });
  }
  const previous = await readState(env, id);
  if (previous.status === "queued" || previous.status === "processing") {
    return reply({ video_id: id, status: previous.status, already_exists: true });
  }
  // Spreaker is never implicit: only server-signed, complete metadata may opt in.
  const spreaker = validDraftMetadata(data?.spreaker) ? {
    title: data.spreaker.title, description: data.spreaker.description,
  } : null;
  await state(env, id, "queued");
  try {
    await env.PODCAST_QUEUE.send({ video_id: id, ...(spreaker ? { spreaker } : {}) });
    return reply({ video_id: id, status: "queued" }, 202);
  } catch {
    await state(env, id, "failed", "queue_unavailable");
    return reply({ error: "queue_unavailable" }, 503);
  }
}

export default {
  fetch(request, env) { return incoming(request, env); },
  async queue(batch, env) {
    for (const message of batch.messages) {
      const id = message.body?.video_id;
      if (!validId(id)) { message.ack(); continue; }
      if (message.body?.kind === "spreaker") {
        try {
          const outcome = await processSpreakerDraft(env, id, message.body.spreaker);
          if (outcome.status === "upload_retry") throw Error("spreaker_upload_retry");
          message.ack();
        } catch (error) {
          console.error("Spreaker draft upload failed", id, String(error));
          message.retry({ delaySeconds: 120 });
        }
        continue;
      }
      let audioReady = false;
      try {
        if (await env.SERMONS_BUCKET.head(audioKey(id))) {
          await state(env, id, "ready");
          message.ack();
          audioReady = true;
        } else {
          if (!await env.SERMONS_BUCKET.head(sourceKey(id))) throw Error("source_missing");
          await state(env, id, "processing");
          const result = await getContainer(env.PODCAST_CONTAINER, id).fetch(
            new Request("http://container/process", {
              method: "POST",
              headers: { "content-type": "application/json" },
              body: JSON.stringify({ video_id: id }),
            })
          );
          if (!result.ok) throw Error("container_processing_failed");
          if (!await env.SERMONS_BUCKET.head(audioKey(id))) throw Error("audio_not_saved");
          await state(env, id, "ready");
          message.ack();
          audioReady = true;
        }
      } catch (error) {
        console.error("Podcast processing failed", id, String(error));
        await state(env, id, "failed", "processing_failed");
        message.retry({ delaySeconds: 120 });
      }
      // A separate Queue job can ONLY create an unpublished Spreaker draft.
      // Keep its entire failure path outside the audio job's try/catch.
      const metadata = message.body?.spreaker;
      if (audioReady && validDraftMetadata(metadata)) {
        try {
          const previous = await readDraftState(env, id);
          if (!previous || previous.status === "queue_unavailable") {
            await saveDraftState(env, id, "queued");
            await env.PODCAST_QUEUE.send({ kind: "spreaker", video_id: id,
              spreaker: { title: metadata.title, description: metadata.description } });
          }
        } catch (error) {
          console.error("Unable to queue Spreaker draft", id, String(error));
          try { await saveDraftState(env, id, "queue_unavailable"); }
          catch (saveError) { console.error("Unable to save Spreaker queue status", id, String(saveError)); }
        }
      }
    }
  },
};
