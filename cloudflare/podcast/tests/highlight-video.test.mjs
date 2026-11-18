import test from "node:test";
import assert from "node:assert/strict";
import { statusClips, servePreview } from "../src/highlight-clip-preview.mjs";

test("private preview links are signed for one clip and support browser byte ranges", async () => {
  const id = "AjkoDHgFdeA";
  const secret = "a".repeat(64); // Test-only value, not a production credential.
  const bucket = {
    async get(key, options) {
      if (key === "sermon-highlights/clip-jobs/" + id + ".json")
        return { async json() {
          return { video_id: id, status: "ready", clips: [
            { number: 2, title: "Celebrating While Imperfect", start: 456, end: 491 },
          ] };
        } };
      if (key.startsWith("sermon-highlights/previews/" + id + "/")) {
        const length = options?.range?.length ?? 100;
        return { body: new ReadableStream({
          start(controller) { controller.enqueue(new Uint8Array(length)); controller.close(); },
        }) };
      }
      return null;
    },
    async head(key) {
      return key.startsWith("sermon-highlights/previews/" + id + "/")
        ? { size: 100 } : null;
    },
  };
  const env = { SERMONS_BUCKET: bucket, SURFSIDE_PODCAST_SHARED_SECRET: secret };
  const status = await statusClips(new Request("https://example.workers.dev/highlights/clips/status"), env, id);
  assert.equal(status.status, 200);
  const data = await status.json();
  assert.equal(data.status, "ready");
  assert.equal(data.preview_links_expire_seconds, 600);
  const link = data.clips[0].preview_url;
  assert.match(link, /\/highlights\/clips\/view\/AjkoDHgFdeA\/2-45600-49100\.mp4/);

  const video = await servePreview(new Request(link, {
    headers: { range: "bytes=10-19" },
  }), env);
  assert.equal(video.status, 206);
  assert.equal(video.headers.get("content-type"), "video/mp4");
  assert.equal(video.headers.get("content-range"), "bytes 10-19/100");
  assert.equal((await video.arrayBuffer()).byteLength, 10);
  assert.equal(video.headers.get("cache-control"), "private, no-store");

  const altered = new URL(link);
  altered.pathname = altered.pathname.replace("2-45600-49100", "2-45000-49100");
  const bad = await servePreview(new Request(altered), env);
  assert.equal(bad.status, 401);
  const expired = new URL(link);
  expired.searchParams.set("exp", String(Math.floor(Date.now() / 1000) - 1));
  const forbidden = await servePreview(new Request(expired), env);
  assert.equal(forbidden.status, 403);
});
