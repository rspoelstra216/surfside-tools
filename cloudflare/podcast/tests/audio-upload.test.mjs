import test from "node:test";
import assert from "node:assert/strict";
import { parseAudioContentLength, uploadAudioToR2, MAX_PODCAST_BYTES } from "../src/audio-upload.mjs";

// Mirrors FixedLengthStream's exact-byte contract for tests in Node 22.
class TestFixedLengthStream extends TransformStream {
  constructor(size) {
    let seen = 0;
    super({
      transform(chunk, controller) {
        seen += chunk.byteLength;
        if (seen > size) throw Error("length_mismatch");
        controller.enqueue(chunk);
      },
      flush() {
        if (seen !== size) throw Error("length_mismatch");
      },
    });
  }
}

const request = (data, length) => new Request(
  "https://storage.internal/podcast-audio/OTt1dfYhlDY.mp3",
  { method: "PUT",
    headers: length === undefined ? {} : { "content-length": String(length) },
    body: data },
);

function fakeBucket() {
  const writes = [];
  return {
    writes,
    async put(key, stream, options) {
      const reader = stream.getReader();
      const parts = [];
      while (true) {
        const { value, done } = await reader.read();
        if (done) break;
        parts.push(value);
      }
      const bytes = parts.reduce((n, part) => n + part.byteLength, 0);
      writes.push({ key, bytes, options });
      return { key };
    },
  };
}

test("R2 requires an explicit bounded audio byte length", () => {
  assert.equal(parseAudioContentLength(new Headers()), null);
  assert.equal(parseAudioContentLength(new Headers({ "content-length": "chunked" })), null);
  assert.equal(parseAudioContentLength(new Headers({ "content-length": "1023" })), null);
  assert.equal(parseAudioContentLength(new Headers({ "content-length": String(MAX_PODCAST_BYTES + 1) })), null);
  assert.equal(parseAudioContentLength(new Headers({ "content-length": "2048" })), 2048);
});

test("known-length MP3 bytes stream directly to R2", async () => {
  const bytes = new Uint8Array(2048).fill(42);
  const bucket = fakeBucket();
  const result = await uploadAudioToR2(
    request(bytes, bytes.length), bucket, "podcast-audio/OTt1dfYhlDY.mp3", TestFixedLengthStream
  );
  assert.equal(result.status, 201);
  assert.deepEqual(bucket.writes, [{
    key: "podcast-audio/OTt1dfYhlDY.mp3",
    bytes: 2048,
    options: { httpMetadata: { contentType: "audio/mpeg" } },
  }]);
});

test("unknown-length MP3 stream does not reach R2", async () => {
  const bucket = fakeBucket();
  const result = await uploadAudioToR2(
    request(new Uint8Array(2048)), bucket, "podcast-audio/OTt1dfYhlDY.mp3", TestFixedLengthStream
  );
  assert.equal(result.status, 411);
  assert.equal(bucket.writes.length, 0);
});

test("truncated MP3 is rejected rather than stored", async () => {
  const bucket = fakeBucket();
  await assert.rejects(
    uploadAudioToR2(
      request(new Uint8Array(1024), 2048), bucket,
      "podcast-audio/OTt1dfYhlDY.mp3", TestFixedLengthStream
    ),
    /length_mismatch/,
  );
  assert.equal(bucket.writes.length, 0);
});
