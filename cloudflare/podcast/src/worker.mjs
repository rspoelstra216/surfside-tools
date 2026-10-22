import { Container, ContainerProxy, getContainer } from "@cloudflare/containers";
export { ContainerProxy };
import { validId, sourceKey, audioKey, statusKey, validStorageAccess, verifiedRequest } from "./validation.mjs";

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
    await env.SERMONS_BUCKET.put(key, request.body, {
      httpMetadata: { contentType: "audio/mpeg" }
    });
    return new Response("Stored", { status: 201 });
  },
};

async function incoming(request, env) {
  if (!["/jobs", "/status"].includes(new URL(request.url).pathname) || request.method !== "POST") {
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
  if (new URL(request.url).pathname === "/status") return reply(await readState(env, id));
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
  await state(env, id, "queued");
  try {
    await env.PODCAST_QUEUE.send({ video_id: id });
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
      try {
        if (await env.SERMONS_BUCKET.head(audioKey(id))) {
          await state(env, id, "ready");
          message.ack();
          continue;
        }
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
      } catch (error) {
        console.error("Podcast processing failed", id, String(error));
        await state(env, id, "failed", "processing_failed");
        message.retry({ delaySeconds: 120 });
      }
    }
  },
};
