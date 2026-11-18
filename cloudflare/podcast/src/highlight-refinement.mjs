// Editorial boundary suggestions grounded in ASR segments and optional audio pauses.
// AI transcription can be inaccurate; human approval is still required.
const sentenceEnd = (text) => /[.!?]["'’)]?\s*$/.test(text.trim());
const fragmentStart = (text) =>
  /^[a-z]/.test(text.trim()) || /^(?:and|but|because|which|although)\b/i.test(text.trim());
const round = (n) => Math.round(n * 100) / 100;

export function refineHighlightCandidates(candidates, segments, pauses = []) {
  const transcript = [...segments].sort((a, b) => a.start - b.start);
  const safePauses = pauses.filter((p) => Number.isFinite(p?.start) &&
    Number.isFinite(p?.end) && p.start >= 0 &&
    p.end - p.start >= 0.3 && p.end - p.start <= 12);
  return candidates.map((candidate) => {
    let first = transcript.findIndex((s) => Math.abs(s.start - candidate.start) < 0.06);
    let last = transcript.findIndex((s) => Math.abs(s.end - candidate.end) < 0.06);
    if (first < 0 || last < first) return candidate;
    const originalStart = candidate.start, originalEnd = candidate.end;

    // An initial lowercase fragment often belongs with the preceding segment.
    if (first > 0 && fragmentStart(transcript[first].text) &&
        transcript[first].start - transcript[first - 1].end <= 2 &&
        transcript[first].start - transcript[first - 1].start <= 9 &&
        transcript[last].end - transcript[first - 1].start <= 105) first--;

    // End on a transcript sentence boundary when a nearby continuation exists.
    if (!sentenceEnd(transcript[last].text)) {
      const priorEnd = transcript[last].end;
      while (last + 1 < transcript.length &&
          transcript[last + 1].end - priorEnd <= 12 &&
          transcript[last + 1].start - transcript[last].end <= 2 &&
          transcript[last + 1].end - transcript[first].start <= 105) {
        last++;
        if (sentenceEnd(transcript[last].text)) break;
      }
    }

    let start = transcript[first].start, end = transcript[last].end;
    const entryPause = safePauses
      .filter((p) => p.end <= start + 0.1 && start - p.end <= 1.5)
      .sort((a, b) => b.end - a.end)[0];
    if (entryPause) start = Math.min(start, entryPause.end);

    // A modest speech-break extension prevents abrupt cuts at an ASR boundary.
    // Do not include speech belonging to a subsequent transcript segment.
    const nextStart = transcript[last + 1]?.start ?? Infinity;
    const exitPause = safePauses
      .filter((p) => p.start >= end + 0.35 && p.start <= end + 4.5 &&
        p.start < nextStart && p.start - start <= 105)
      .sort((a, b) => a.start - b.start)[0];
    if (exitPause) end = exitPause.start;
    if (end - start < 25 || end - start > 105) return candidate;
    return {
      ...candidate,
      start: round(start),
      end: round(end),
      excerpt: transcript.slice(first, last + 1).map((s) => s.text).join(" "),
      boundary_review: {
        original_start: originalStart,
        original_end: originalEnd,
        used_audio_pauses: Boolean(entryPause || exitPause),
        transcript_sentence_complete: sentenceEnd(transcript[last].text),
        manual_review_required: true,
      },
    };
  });
}
