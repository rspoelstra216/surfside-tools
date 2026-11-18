// Validate the FFmpeg Container's private manifest before reading chunk objects.
// Emit a precise, bounded reason without exposing media, transcripts or credentials.
const failure = (reason, index = null) => {
  const suffix = index === null ? reason : reason + "_at_" + index;
  throw new Error("invalid_chunk_manifest:" + suffix);
};

export function validateChunkManifest(manifest, id) {
  const chunks = manifest?.chunks;
  if (!Array.isArray(chunks)) failure("chunks_not_array");
  if (chunks.length < 1 || chunks.length > 24) failure("chunk_count");

  const keyPattern = new RegExp(
    "^sermon-highlights/chunks/" + id + "/[0-9]{3}\\.mp3$");
  for (let i = 0; i < chunks.length; i++) {
    const chunk = chunks[i];
    if (!chunk || typeof chunk !== "object") failure("chunk_not_object", i);
    if (typeof chunk.key !== "string" || !keyPattern.test(chunk.key))
      failure("chunk_key", i);
    if (!Number.isFinite(chunk.offset) || chunk.offset < 0)
      failure("chunk_offset", i);
    if (!Number.isFinite(chunk.duration) || chunk.duration <= 0 ||
        chunk.duration > 185)
      failure("chunk_duration", i);
  }

  const pauses = Array.isArray(manifest.pauses) ? manifest.pauses.filter((p) =>
    Number.isFinite(p?.start) && Number.isFinite(p?.end) &&
    p.start >= 0 && p.end > p.start && p.end <= 72 * 60 + 5).slice(0, 3000) : [];
  return { chunks, pauses };
}
