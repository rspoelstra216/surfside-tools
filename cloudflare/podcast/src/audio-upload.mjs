// Cloudflare R2 requires a stream with a known byte length.
// FixedLengthStream avoids loading the whole finished episode into Worker memory.
export const MAX_PODCAST_BYTES = 300 * 1024 * 1024;

export function parseAudioContentLength(headers) {
  const value = headers.get("content-length");
  if (!value || !/^[1-9][0-9]*$/.test(value)) return null;
  const bytes = Number(value);
  return Number.isSafeInteger(bytes) && bytes >= 1024 && bytes <= MAX_PODCAST_BYTES
    ? bytes : null;
}

// Injectable FixedLengthStream constructor permits Node-based regression tests.
export async function uploadAudioToR2(
  request, bucket, key, FixedStream = globalThis.FixedLengthStream
) {
  const bytes = parseAudioContentLength(request.headers);
  if (bytes === null || !request.body) {
    return new Response("Valid Content-Length required", { status: 411 });
  }
  const { readable, writable } = new FixedStream(bytes);
  // Start both sides together: waiting for pipeTo first would deadlock because
  // the R2 binding has not yet begun reading from the fixed-length stream.
  const piping = request.body.pipeTo(writable);
  const saving = bucket.put(key, readable, {
    httpMetadata: { contentType: "audio/mpeg" },
  });
  const [object] = await Promise.all([saving, piping]);
  if (!object) throw Error("audio_object_not_stored");
  return new Response("Stored", { status: 201 });
}
