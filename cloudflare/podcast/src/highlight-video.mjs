// Pure input validation for unpublished manual video previews.
export const previewKey = (id, clip) =>
  `sermon-highlights/previews/${id}/${clip.number}-${Math.round(clip.start * 100)}-${Math.round(clip.end * 100)}.mp4`;

// Immutable final export key, distinct from the padded review previews.
export const exportKey = (id, clip) =>
  `sermon-highlights/exports/${id}/${clip.number}-${Math.round(clip.start * 100)}-${Math.round(clip.end * 100)}.mp4`;

export function resolveClipPicks(highlights, requested) {
  if (!Array.isArray(highlights) || !Array.isArray(requested) ||
      requested.length < 1 || requested.length > 5) throw Error("invalid_clip_selection");
  const seen = new Set();
  const resolved = requested.map((item) => {
    if (!item || !Number.isInteger(item.number) || item.number < 1 || item.number > 10 ||
        seen.has(item.number)) throw Error("invalid_clip_selection");
    seen.add(item.number);
    const original = highlights.find((h) => h.number === item.number);
    if (!original) throw Error("unknown_highlight_number");
    const hasStart = Object.hasOwn(item, "start"), hasEnd = Object.hasOwn(item, "end");
    if (hasStart !== hasEnd ||
        Object.keys(item).some((key) => !["number", "start", "end"].includes(key)))
      throw Error("invalid_clip_times");
    const start = hasStart ? item.start : original.start;
    const end = hasEnd ? item.end : original.end;
    if (typeof start !== "number" || typeof end !== "number" ||
        !Number.isFinite(start) || !Number.isFinite(end) ||
        start < 0 || end > 72 * 60 || end - start < 15 || end - start > 120)
      throw Error("invalid_clip_times");
    return {
      number: item.number,
      title: String(original.title || "Highlight").slice(0, 100),
      start: Math.round(start * 100) / 100,
      end: Math.round(end * 100) / 100,
    };
  });
  if (resolved.reduce((sum, c) => sum + (c.end - c.start), 0) > 420)
    throw Error("clip_batch_too_long");
  return resolved;
}

// Strict support for a single HTTP byte range, used by browser HTML5 video.
export function requestedByteRange(header, size) {
  if (!Number.isSafeInteger(size) || size < 1) throw Error("invalid_media_size");
  if (header == null) return null;
  const m = /^bytes=(\d*)-(\d*)$/.exec(header);
  if (!m || (!m[1] && !m[2])) return false;
  let start, end;
  if (!m[1]) {
    const suffix = Number(m[2]);
    if (!Number.isSafeInteger(suffix) || suffix <= 0) return false;
    start = Math.max(0, size - suffix);
    end = size - 1;
  } else {
    start = Number(m[1]); end = m[2] ? Number(m[2]) : size - 1;
    if (!Number.isSafeInteger(start) || !Number.isSafeInteger(end) ||
        start >= size || start > end) return false;
    end = Math.min(end, size - 1);
  }
  return { offset: start, length: end - start + 1, end };
}

/** Strict final-clip export validation against immutable AI suggestions. */
export function resolveApprovedExports(highlights, requested) {
  const clips = resolveClipPicks(highlights, requested);
  if (requested.some((item) => !Object.hasOwn(item, "start") ||
        !Object.hasOwn(item, "end")))
    throw Error("export_requires_exact_times");
  for (const clip of clips) {
    const source = highlights.find((h) => h.number === clip.number);
    if (!Number.isFinite(source?.start) || !Number.isFinite(source?.end) ||
        Math.abs(clip.start - source.start) > 5.001 ||
        Math.abs(clip.end - source.end) > 5.001)
      throw Error("export_outside_review_adjustment");
  }
  // Sort for a stable same-selection comparison across requests.
  return clips.sort((a, b) => a.number - b.number);
}

export function sameExports(a, b) {
  return Array.isArray(a) && Array.isArray(b) &&
    a.length === b.length && a.every((x, i) => {
      const y = b[i];
      return y && x.number === y.number && x.start === y.start && x.end === y.end;
    });
}

/**
 * Exports selected in the Worker include the AI title for staff display, but
 * the Queue's revalidation contract intentionally accepts ONLY numeric cuts.
 * Strip exactly the known display-only field before queuing or revalidating.
 * Strictly reject any other unknown fields rather than weakening validation.
 */
export function exportQueueClips(clips) {
  if (!Array.isArray(clips)) throw Error("invalid_clip_selection");
  return clips.map((clip) => {
    if (!clip || typeof clip !== "object" || Array.isArray(clip) ||
        Object.keys(clip).some((key) =>
          !["number", "start", "end", "title"].includes(key)) ||
        (Object.hasOwn(clip, "title") && typeof clip.title !== "string"))
      throw Error("invalid_clip_times");
    return { number: clip.number, start: clip.start, end: clip.end };
  });
}

// All configured Queue retries have elapsed by this point. An old deployment
// could leave the status queued forever when validation threw before its catch.
export function exportJobStalled(job, now = Date.now()) {
  // A running FFmpeg batch may legitimately last longer than the timeout.
  // Only queued jobs are recoverable automatically; never duplicate active renders.
  if (!job || job.status !== "queued") return false;
  const timestamp = Date.parse(job.updated_at || "");
  return Number.isFinite(timestamp) && now - timestamp > 20 * 60 * 1000;
}
