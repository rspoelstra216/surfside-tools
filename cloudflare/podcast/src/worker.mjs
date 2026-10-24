import { Container, ContainerProxy, getContainer } from "@cloudflare/containers";
export { ContainerProxy };
import { validId, sourceKey, audioKey, statusKey, validStorageAccess, verifiedRequest } from "./validation.mjs";
import { uploadAudioToR2 } from "./audio-upload.mjs";
import { validDraftMetadata, spreakerConfigured, canRetryKnownRedirectFailure, processSpreakerDraft, readDraftState, saveDraftState } from "./spreaker.mjs";

const reply = (data, status = 200) => Response.json(data, {
  status,
  headers: { "cache-control": "no-store" },
});
const state = async (env, id, status, detail = "", test = false) => {
  await env.SERMONS_BUCKET.put(statusKey(id, test), JSON.stringify({
    video_id: id, status, detail, updated_at: new Date().toISOString()
  }), { httpMetadata: { contentType: "application/json" } });
};
const readState = async (env, id, test = false) => {
  const stored = await env.SERMONS_BUCKET.get(statusKey(id, test));
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
  if (!["/jobs", "/status", "/retry-test-spreaker"].includes(route) || request.method !== "POST") {
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
  const test = data?.test === true;
  if (data?.test !== undefined && typeof data.test !== "boolean") return reply({ error: "invalid_test_flag" }, 400);
  if (route === "/status") {
    const [audio, spreaker] = await Promise.all([readState(env, id, test), readDraftState(env, id, test)]);
    return reply(spreaker ? { ...audio, spreaker } : audio);
  }
  if (route === "/retry-test-spreaker") {
    // Strictly a manually confirmed, test-only recovery of the historical
    // unsupported redirect:"error" preflight. Does not re-run FFmpeg.
    if (!test || data?.confirmed_no_draft !== true || !validDraftMetadata(data?.spreaker))
      return reply({ error: "test_retry_requires_confirmation" }, 400);
    if (!spreakerConfigured(env)) return reply({ error: "test_requires_spreaker" }, 409);
    const [audio, previous, spreaker] = await Promise.all([
      readState(env, id, true),
      env.SERMONS_BUCKET.head(audioKey(id, true)),
      readDraftState(env, id, true),
    ]);
    if (audio.status !== "ready" || !previous || !canRetryKnownRedirectFailure(spreaker)) {
      return reply({ error: "retry_not_eligible" }, 409);
    }
    // No episode ID was received, and the verified old failure was thrown
    // before fetch executed. Reset only the test record for a single attempt.
    await saveDraftState(env, id, "queued", null, "", true);
    try {
      await env.PODCAST_QUEUE.send({ kind: "spreaker", video_id: id, test: true,
        spreaker: { title: data.spreaker.title, description: data.spreaker.description } });
      return reply({ video_id: id, status: "queued", test: true }, 202);
    } catch (error) {
      console.error("Unable to queue recovered Spreaker test draft", id, String(error));
      await saveDraftState(env, id, "queue_unavailable", null, "", true);
      return reply({ error: "queue_unavailable" }, 503);
    }
  }
  if (test && (!validDraftMetadata(data?.spreaker) || !spreakerConfigured(env))) return reply({ error: "test_requires_spreaker" }, 409);
  if (!await env.SERMONS_BUCKET.head(sourceKey(id, test))) return reply({ error: "sermon_missing" }, 409);
  if (!await env.SERMONS_BUCKET.head("podcast-assets/intro.mp3") ||
      !await env.SERMONS_BUCKET.head("podcast-assets/outro.mp3")) {
    return reply({ error: "bumper_assets_missing" }, 409);
  }
  if (await env.SERMONS_BUCKET.head(audioKey(id, test))) {
    await state(env, id, "ready", "", test);
    return reply({ video_id: id, status: "ready", already_exists: true });
  }
  const previous = await readState(env, id, test);
  if (previous.status === "queued" || previous.status === "processing") {
    return reply({ video_id: id, status: previous.status, already_exists: true });
  }
  // Spreaker is never implicit: only server-signed, complete metadata may opt in.
  const spreaker = validDraftMetadata(data?.spreaker) ? {
    title: test ? ("[TEST - DO NOT PUBLISH] " + data.spreaker.title).slice(0, 140) : data.spreaker.title,
    description: test ? ("TEST DRAFT ONLY — delete after integration verification.\n\n" +
      data.spreaker.description).slice(0, 8000) : data.spreaker.description,
  } : null;
  await state(env, id, "queued", "", test);
  try {
    await env.PODCAST_QUEUE.send({ video_id: id, ...(test ? { test: true } : {}), ...(spreaker ? { spreaker } : {}) });
    return reply({ video_id: id, status: "queued" }, 202);
  } catch {
    await state(env, id, "failed", "queue_unavailable", test);
    return reply({ error: "queue_unavailable" }, 503);
  }
}

export default {
  fetch(request, env) { return incoming(request, env); },
  async queue(batch, env) {
    for (const message of batch.messages) {
      const id = message.body?.video_id;
      if (!validId(id)) { message.ack(); continue; }
      const test = message.body?.test === true;
      if (message.body?.kind === "spreaker") {
        try {
          const outcome = await processSpreakerDraft(env, id, message.body.spreaker, undefined, test);
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
        if (await env.SERMONS_BUCKET.head(audioKey(id, test))) {
          await state(env, id, "ready", "", test);
          message.ack();
          audioReady = true;
        } else {
          if (!await env.SERMONS_BUCKET.head(sourceKey(id, test))) throw Error("source_missing");
          await state(env, id, "processing", "", test);
          const result = await getContainer(env.PODCAST_CONTAINER, id).fetch(
            new Request("http://container/process", {
              method: "POST",
              headers: { "content-type": "application/json" },
              body: JSON.stringify({ video_id: id, ...(test ? { test: true } : {}) }),
            })
          );
          if (!result.ok) throw Error("container_processing_failed");
          if (!await env.SERMONS_BUCKET.head(audioKey(id, test))) throw Error("audio_not_saved");
          await state(env, id, "ready", "", test);
          message.ack();
          audioReady = true;
        }
      } catch (error) {
        console.error("Podcast processing failed", id, String(error));
        await state(env, id, "failed", "processing_failed", test);
        message.retry({ delaySeconds: 120 });
      }
      // A separate Queue job can ONLY create an unpublished Spreaker draft.
      // Keep its entire failure path outside the audio job's try/catch.
      const metadata = message.body?.spreaker;
      if (audioReady && validDraftMetadata(metadata)) {
        try {
          const previous = await readDraftState(env, id, test);
          if (!previous || previous.status === "queue_unavailable") {
            await saveDraftState(env, id, "queued", null, "", test);
            await env.PODCAST_QUEUE.send({ kind: "spreaker", video_id: id, ...(test ? { test: true } : {}),
              spreaker: { title: metadata.title, description: metadata.description } });
          }
        } catch (error) {
          console.error("Unable to queue Spreaker draft", id, String(error));
          try { await saveDraftState(env, id, "queue_unavailable", null, "", test); }
          catch (saveError) { console.error("Unable to save Spreaker queue status", id, String(saveError)); }
        }
      }
    }
  },
};
