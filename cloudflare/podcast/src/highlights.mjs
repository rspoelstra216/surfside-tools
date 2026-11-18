import { getContainer } from "@cloudflare/containers";
import { PROCESSOR_CONTAINER_ID, validId, sourceKey } from "./validation.mjs";
import {
  normalizeSegments, transcriptWindows, highlightPrompt, HIGHLIGHT_SCHEMA,
  parseHighlightCandidates, selectHighlights,
} from "./highlight-selection.mjs";
import { refineHighlightCandidates } from "./highlight-refinement.mjs";

// Opt-in, private analysis only. Never schedules a podcast or video publication.
const prefix = "sermon-highlights/";
const statusKey = (id) => prefix + "jobs/" + id + ".json";
const resultKey = (id) => prefix + "results/" + id + ".json";
const transcriptKey = (id) => prefix + "transcripts/" + id + ".json";
const reply = (data, status = 200) => Response.json(data, {
  status, headers: { "cache-control": "no-store" },
});

async function storeJson(bucket, key, data) {
  await bucket.put(key, JSON.stringify(data),
    { httpMetadata: { contentType: "application/json" } });
}
async function readJson(bucket, key) {
  const object = await bucket.get(key);
  if (!object) return null;
  try { return await object.json(); }
  catch { return null; }
}
async function saveStatus(env, id, status, detail = "") {
  await storeJson(env.SERMONS_BUCKET, statusKey(id),
    { video_id: id, status, detail, updated_at: new Date().toISOString() });
}

export async function highlightStatus(env, id) {
  if (!validId(id)) return reply({ error: "invalid_video_id" }, 400);
  const job = await readJson(env.SERMONS_BUCKET, statusKey(id)) ||
    { video_id: id, status: "not_queued" };
  if (job.status !== "ready") return reply(job);
  const result = await readJson(env.SERMONS_BUCKET, resultKey(id));
  return reply(result ? { ...job, result } : { ...job, status: "result_missing" });
}

export async function enqueueHighlights(env, id) {
  if (!validId(id)) return reply({ error: "invalid_video_id" }, 400);
  if (!await env.SERMONS_BUCKET.head(sourceKey(id)))
    return reply({ error: "sermon_missing" }, 409);
  if (await env.SERMONS_BUCKET.head(resultKey(id))) {
    await saveStatus(env, id, "ready");
    return reply({ video_id: id, status: "ready", already_exists: true });
  }
  const prior = await readJson(env.SERMONS_BUCKET, statusKey(id));
  if (prior?.status === "queued" || prior?.status === "processing")
    return reply({ video_id: id, status: prior.status, already_exists: true });
  await saveStatus(env, id, "queued");
  try {
    await env.PODCAST_QUEUE.send({ kind: "highlights", video_id: id });
    return reply({ video_id: id, status: "queued" }, 202);
  } catch (error) {
    console.error("Unable to queue highlight pilot", id, String(error));
    await saveStatus(env, id, "failed", "queue_unavailable");
    return reply({ error: "queue_unavailable" }, 503);
  }
}

function base64Of(buffer) {
  const bytes = new Uint8Array(buffer);
  // Split to avoid exceeding the JS call argument limit.
  let binary = "";
  for (let i = 0; i < bytes.length; i += 8192)
    binary += String.fromCharCode(...bytes.subarray(i, i + 8192));
  return btoa(binary);
}

async function prepareChunks(env, id) {
  const response = await getContainer(env.PODCAST_CONTAINER, PROCESSOR_CONTAINER_ID)
    .fetch(new Request("http://container/highlights/chunks", {
      method: "POST", headers: { "content-type": "application/json" },
      body: JSON.stringify({ video_id: id }),
    }));
  if (!response.ok) throw Error("chunk_preparation_failed");
  const manifest = await response.json();
  if (!Array.isArray(manifest?.chunks) || manifest.chunks.length < 1 ||
      manifest.chunks.length > 24) throw Error("invalid_chunk_manifest");
  for (const chunk of manifest.chunks) {
    if (typeof chunk.key !== "string" ||
        !new RegExp("^sermon-highlights/chunks/" + id + "/[0-9]{3}\\.mp3$").test(chunk.key) ||
        !Number.isFinite(chunk.offset) || chunk.offset < 0 ||
        !Number.isFinite(chunk.duration) || chunk.duration <= 0 || chunk.duration > 185)
      throw Error("invalid_chunk_manifest");
  }
  // Pause metadata is optional; transcriptions still work without it.
  const pauses = Array.isArray(manifest.pauses) ? manifest.pauses.filter((p) =>
    Number.isFinite(p?.start) && Number.isFinite(p?.end) &&
    p.start >= 0 && p.end > p.start && p.end <= 72 * 60 + 5).slice(0, 3000) : [];
  return { chunks: manifest.chunks, pauses };
}

export async function processHighlights(env, id) {
  if (!validId(id)) throw Error("invalid_video_id");
  if (await env.SERMONS_BUCKET.head(resultKey(id))) {
    await saveStatus(env, id, "ready");
    return;
  }
  let chunks = [];
  try {
    await saveStatus(env, id, "processing");
    if (!env.AI?.run) throw Error("workers_ai_binding_missing");
    const prepared = await prepareChunks(env, id);
    chunks = prepared.chunks;
    const pauses = prepared.pauses;
    const segments = [];
    for (const chunk of chunks) {
      const object = await env.SERMONS_BUCKET.get(chunk.key);
      if (!object || object.size > 3 * 1024 * 1024) throw Error("chunk_missing_or_oversized");
      const transcription = await env.AI.run("@cf/openai/whisper-large-v3-turbo", {
        audio: base64Of(await object.arrayBuffer()),
        task: "transcribe", language: "en",
      });
      segments.push(...normalizeSegments(transcription?.segments, chunk.offset, chunk.duration));
    }
    segments.sort((a, b) => a.start - b.start);
    if (segments.length < 10) throw Error("transcript_too_short");
    await storeJson(env.SERMONS_BUCKET, transcriptKey(id), {
      video_id: id, source: sourceKey(id), model: "@cf/openai/whisper-large-v3-turbo",
      segments,
    });

    const candidates = [];
    for (const window of transcriptWindows(segments)) {
      const response = await env.AI.run("@cf/meta/llama-3.3-70b-instruct-fp8-fast", {
        messages: [
          { role: "system", content: "Return grounded, useful sermon clip suggestions in JSON. Never invent transcript quotes or timestamps." },
          { role: "user", content: highlightPrompt(window) },
        ],
        response_format: { type: "json_schema", json_schema: HIGHLIGHT_SCHEMA },
        max_tokens: 800,
      });
      candidates.push(...parseHighlightCandidates(response, window));
    }
    const result = {
      video_id: id,
      status: "ready",
      source: sourceKey(id),
      generated_at: new Date().toISOString(),
      model: "@cf/meta/llama-3.3-70b-instruct-fp8-fast",
      transcript_key: transcriptKey(id),
      highlights: selectHighlights(
        refineHighlightCandidates(candidates, segments, pauses), 5,
        { duration: segments.at(-1).end }),
      note: "AI-generated suggestions for staff review; times refer to the original sermon MP4.",
    };
    await storeJson(env.SERMONS_BUCKET, resultKey(id), result);
    await saveStatus(env, id, "ready");
  } catch (error) {
    console.error("Sermon highlight pilot failed", id, String(error));
    await saveStatus(env, id, "failed", "analysis_failed");
    throw error;
  } finally {
    // Ephemeral ASR source chunks are not needed once transcription completes.
    // Failure to delete them must not turn a valid analysis into a failed job.
    try {
      if (chunks.length) await env.SERMONS_BUCKET.delete(chunks.map((chunk) => chunk.key));
    } catch (error) {
      console.error("Unable to clean highlight chunks", id, String(error));
    }
  }
}
