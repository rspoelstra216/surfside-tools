import {
  transcriptWindows, highlightPrompt, HIGHLIGHT_SCHEMA,
  parseHighlightCandidates, selectHighlights,
} from "./highlight-selection.mjs";
import { refineHighlightCandidates } from "./highlight-refinement.mjs";
import { paddedHighlightPicks } from "./highlight-automation.mjs";
import { validId } from "./validation.mjs";

const prefix = "sermon-highlights/";
const jobKey = (id) => prefix + "more-jobs/" + id + ".json";
const resultKey = (id) => prefix + "results/" + id + ".json";
const transcriptKey = (id) => prefix + "transcripts/" + id + ".json";
const reply = (value, status = 200) => Response.json(value, {
  status, headers: { "cache-control": "no-store" },
});

async function readJson(bucket, key) {
  const object = await bucket.get(key);
  if (!object) return null;
  try { return await object.json(); } catch { return null; }
}
async function writeJson(bucket, key, value) {
  await bucket.put(key, JSON.stringify(value), {
    httpMetadata: { contentType: "application/json" },
  });
}
async function writeStatus(env, id, data) {
  await writeJson(env.SERMONS_BUCKET, jobKey(id), {
    video_id: id, ...data, updated_at: new Date().toISOString(),
  });
}

// Existing suggestions, including rejected ones, remain immutable with stable
// numbers. New suggestions must not cover the same speech or duplicate topics.
export function selectAdditionalHighlights(candidates, existing, duration, maxNew = 3) {
  if (!Array.isArray(existing) || !Array.isArray(candidates)) return [];
  const textTokens = (value) => new Set(String(value || "").toLowerCase()
    .replace(/[^a-z0-9 ]/g, " ").split(/\s+/).filter((w) =>
      w.length >= 4 && !["that", "this", "with", "from", "your", "have", "what",
        "while", "when", "will", "they", "them", "just"].includes(w)));
  const similar = (a, b) => {
    const left = textTokens(a), right = textTokens(b);
    if (!left.size || !right.size) return false;
    const overlap = [...left].filter((w) => right.has(w)).length;
    return overlap / (left.size + right.size - overlap) >= 0.6;
  };
  const unique = candidates.filter((candidate) =>
    Number.isFinite(candidate.start) && Number.isFinite(candidate.end) &&
    candidate.end - candidate.start >= 25 &&
    candidate.end - candidate.start <= 105 &&
    Number(candidate.score) >= 4 &&
    !existing.some((previous) =>
      Math.min(previous.end, candidate.end) -
        Math.max(previous.start, candidate.start) > 10 ||
      similar(previous.title, candidate.title)));
  const ranked = selectHighlights(unique, Math.max(0, Math.min(3, maxNew)),
    { duration });
  const firstNumber = Math.max(0, ...existing.map((h) => Number(h.number) || 0)) + 1;
  return ranked.map((highlight, i) => ({
    ...highlight, number: firstNumber + i,
  }));
}

export async function moreHighlightStatus(env, id) {
  return await readJson(env.SERMONS_BUCKET, jobKey(id)) ||
    { video_id: id, status: "not_queued" };
}

export async function queueMoreHighlights(env, id) {
  if (!validId(id)) return reply({ error: "invalid_video_id" }, 400);
  const result = await readJson(env.SERMONS_BUCKET, resultKey(id));
  if (!Array.isArray(result?.highlights))
    return reply({ error: "highlight_analysis_required" }, 409);
  if (!await env.SERMONS_BUCKET.head(transcriptKey(id)))
    return reply({ error: "highlight_transcript_missing" }, 409);
  const existingCount = result.highlights.length;
  if (existingCount >= 10)
    return reply({ error: "suggestion_limit_reached" }, 409);
  const prior = await moreHighlightStatus(env, id);
  if (["queued", "processing"].includes(prior.status))
    return reply({ video_id: id, status: prior.status, already_exists: true });
  const round = (Number(prior.round) || 0) + 1;
  if (round > 4)
    return reply({ error: "additional_search_limit_reached" }, 409);
  await writeStatus(env, id, {
    status: "queued", round, base_count: existingCount, added: 0,
  });
  try {
    await env.PODCAST_QUEUE.send({
      kind: "highlight_more", video_id: id, base_count: existingCount, round,
    });
    return reply({ video_id: id, status: "queued", round }, 202);
  } catch (error) {
    console.error("Could not queue additional private highlight search", id, String(error));
    await writeStatus(env, id, {
      status: "failed", round, base_count: existingCount, detail: "queue_unavailable",
    });
    return reply({ error: "queue_unavailable" }, 503);
  }
}

export async function processMoreHighlights(env, id, baseCount, round, enqueuePreviews) {
  if (typeof enqueuePreviews !== "function")
    throw Error("additional_preview_queue_unavailable");
  if (!validId(id) || !Number.isInteger(baseCount) || baseCount < 1 ||
      baseCount > 9 || !Number.isInteger(round) || round < 1 || round > 4)
    throw Error("invalid_more_highlights_job");
  const currentJob = await moreHighlightStatus(env, id);
  if (currentJob.round !== round) return; // stale Queue retry, not a new search
  try {
    let result = await readJson(env.SERMONS_BUCKET, resultKey(id));
    if (!Array.isArray(result?.highlights)) throw Error("analysis_missing");
    if (result.highlights.length < baseCount) throw Error("analysis_changed");
    let added = [];
    if (result.highlights.length > baseCount) {
      // A previous retry already stored new candidates. Do not re-bill AI.
      added = result.highlights.slice(baseCount);
    } else {
      await writeStatus(env, id, {
        status: "processing", base_count: baseCount, round, added: 0,
      });
      if (!env.AI?.run) throw Error("workers_ai_binding_missing");
      const transcript = await readJson(env.SERMONS_BUCKET, transcriptKey(id));
      const segments = transcript?.segments;
      if (!Array.isArray(segments) || segments.length < 10)
        throw Error("highlight_transcript_missing");
      const existing = result.highlights;
      const avoided = existing.map((h) =>
        `#${h.number} ${h.title} (${Math.floor(h.start)}-${Math.ceil(h.end)} seconds)`)
        .join("; ").slice(0, 1100);
      const candidates = [];
      for (const window of transcriptWindows(segments)) {
        const response = await env.AI.run("@cf/meta/llama-3.3-70b-instruct-fp8-fast", {
          messages: [
            {
              role: "system",
              content: "You are finding ALTERNATIVE sermon-only social clips after the first " +
                "suggestions were unusable. Select meaningful standalone Scripture teaching, " +
                "concrete applications, insights or encouraging calls to action. Avoid " +
                "opening announcements, prayers for logistics, repeated phrases, filler, " +
                "half sentences and anything requiring earlier context. Do not invent words, " +
                "timestamps or doctrines. A genuine complete thought is more important " +
                "than reaching a quota; return zero if none qualify.",
            },
            {
              role: "user",
              content: `Previously suggested excerpts must NOT be repeated or overlapped: ${avoided}.\n` +
                `Search for DIFFERENT moments and complete ideas elsewhere in this passage.\n` +
                highlightPrompt(window),
            },
          ],
          response_format: { type: "json_schema", json_schema: HIGHLIGHT_SCHEMA },
          max_tokens: 800,
        });
        candidates.push(...parseHighlightCandidates(response, window));
      }
      const duration = segments.at(-1).end;
      added = selectAdditionalHighlights(
        refineHighlightCandidates(candidates, segments),
        existing, duration, Math.min(3, 10 - existing.length));
      if (added.length) {
        result = { ...result, highlights: [...existing, ...added],
          additional_searches: round, updated_at: new Date().toISOString() };
        await writeJson(env.SERMONS_BUCKET, resultKey(id), result);
      }
    }
    let previewState = "not_needed";
    if (added.length) {
      const picks = paddedHighlightPicks(added);
      if (picks.length) {
        const queued = await enqueuePreviews(env, id, picks);
        previewState = queued.ok ? (await queued.json()).status : "pending_retry";
        if (!queued.ok)
          console.error("Additional clip previews not queued", id, queued.status);
      }
    }
    await writeStatus(env, id, {
      status: "ready", base_count: baseCount, round, added: added.length,
      previews: previewState,
    });
  } catch (error) {
    console.error("Additional sermon highlight search failed", id, String(error));
    await writeStatus(env, id, {
      status: "failed", base_count: baseCount, round, detail: "more_analysis_failed",
    });
    throw error;
  }
}
