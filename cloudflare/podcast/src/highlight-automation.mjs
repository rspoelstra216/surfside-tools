// Shared pure orchestration helpers for automatic weekly highlight preparation.
// These cannot publish, change staff approvals, or touch podcast/Spreaker state.
export function paddedHighlightPicks(highlights) {
  if (!Array.isArray(highlights)) return [];
  const selected = [], seen = new Set();
  for (const h of highlights.slice(0, 5)) {
    if (!Number.isInteger(h?.number) || h.number < 1 || h.number > 5 ||
        seen.has(h.number) || !Number.isFinite(h.start) || !Number.isFinite(h.end))
      continue;
    seen.add(h.number);
    const start = Math.round(Math.max(0, h.start - 5) * 100) / 100;
    const end = Math.round(Math.min(4320, h.end + 5) * 100) / 100;
    if (end - start < 15 || end - start > 120) continue;
    selected.push({ number: h.number, start, end });
  }
  return selected;
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
