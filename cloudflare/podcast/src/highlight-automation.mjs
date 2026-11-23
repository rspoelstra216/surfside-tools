// Shared pure orchestration helpers for automatic weekly highlight preparation.
// These cannot publish, change staff approvals, or touch podcast/Spreaker state.
export const HIGHLIGHT_EDIT_WINDOW_SECONDS = 10;

// Extend previews to cover each edge's ±10s editor range. Since the existing
// renderer caps a private preview at 120 seconds, split any smaller available
// padding budget between the two sides, never cutting the AI-suggested clip.
// Work in centiseconds so R2 keys, WordPress and FFmpeg all agree exactly.
export function paddedHighlightRange(start, end) {
  if (!Number.isFinite(start) || !Number.isFinite(end)) return null;
  const from = Math.round(start * 100), to = Math.round(end * 100);
  const duration = to - from;
  if (from < 0 || to > 432000 || duration < 1500 || duration > 12000) return null;
  const remaining = 12000 - duration;
  const before = Math.min(HIGHLIGHT_EDIT_WINDOW_SECONDS * 100, from);
  const after = Math.min(HIGHLIGHT_EDIT_WINDOW_SECONDS * 100, 432000 - to);
  let left = Math.min(before, Math.floor(remaining / 2));
  const right = Math.min(after, remaining - left);
  left = Math.min(before, remaining - right);
  return { start: (from - left) / 100, end: (to + right) / 100 };
}

export function paddedHighlightPicks(highlights) {
  if (!Array.isArray(highlights)) return [];
  const selected = [], seen = new Set();
  for (const h of highlights.slice(0, 10)) {
    if (!Number.isInteger(h?.number) || h.number < 1 || h.number > 10 ||
        seen.has(h.number)) continue;
    const range = paddedHighlightRange(h.start, h.end);
    if (!range) continue;
    seen.add(h.number);
    selected.push({ number: h.number, ...range });
  }
  return selected;
}

// Keep each FFmpeg batch within the existing 420s aggregate cap.
// Automatic processing continues with the remaining clips after completion.
export function nextAutomaticPreviewBatch(pending) {
  if (!Array.isArray(pending)) return [];
  const batch = [];
  let totalCentis = 0;
  for (const clip of pending) {
    const duration = Math.round(clip.end * 100) - Math.round(clip.start * 100);
    if (duration < 1500 || duration > 12000 || batch.length >= 5 ||
        totalCentis + duration > 42000) break;
    batch.push(clip);
    totalCentis += duration;
  }
  return batch;
}

// A private, already-rendered padded clip can serve as the input to an
// accurate final cut, avoiding another full-sermon MP4 download.
// Only the matching numbered clip containing the ENTIRE approved range qualifies.
export function exportPreviewPlan(approved, available) {
  if (!Array.isArray(approved)) throw Error("invalid_export_plan");
  return approved.map((clip) => {
    const valid = Array.isArray(available) && available.find((candidate) =>
      candidate?.number === clip.number &&
      Number.isFinite(candidate.start) && Number.isFinite(candidate.end) &&
      candidate.start <= clip.start + 0.001 &&
      candidate.end >= clip.end - 0.001 &&
      candidate.start >= 0 && candidate.end <= 4320 &&
      candidate.end > candidate.start);
    return {
      number: clip.number, start: clip.start, end: clip.end,
      ...(valid ? { source_preview: {
        start: valid.start, end: valid.end,
      }} : {}),
    };
  });
}
