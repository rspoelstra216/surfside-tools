import test from "node:test";
import assert from "node:assert/strict";
import { webcrypto } from "node:crypto";
import { validId, sourceKey, audioKey, statusKey, validStorageAccess, verifiedRequest } from "../src/validation.mjs";

globalThis.crypto ??= webcrypto;
test("YouTube IDs and fixed keys are strict", () => {
  assert.equal(validId("OTt1dfYhlDY"), true);
  assert.equal(validId("../traversal"), false);
  assert.equal(sourceKey("OTt1dfYhlDY"), "sermons/OTt1dfYhlDY.mp4");
  assert.equal(audioKey("OTt1dfYhlDY"), "podcast-audio/OTt1dfYhlDY.mp3");
});
test("container outbound R2 access is restricted", () => {
  assert.equal(validStorageAccess("sermons/OTt1dfYhlDY.mp4", "GET"), true);
  assert.equal(validStorageAccess("podcast-assets/intro.mp3", "GET"), true);
  assert.equal(validStorageAccess("podcast-assets/outro.mp3", "GET"), true);
  assert.equal(validStorageAccess("podcast-audio/OTt1dfYhlDY.mp3", "PUT"), true);
  assert.equal(validStorageAccess("podcast-jobs/OTt1dfYhlDY.json", "PUT"), false);
  assert.equal(validStorageAccess("sermons/OTt1dfYhlDY.mp4", "PUT"), false);
  assert.equal(validStorageAccess("../secrets", "GET"), false);
});
test("HMAC rejects missing, invalid and replayed timestamps", async () => {
  const secret = "A".repeat(48);
  const timestamp = "1792682400";
  const payload = JSON.stringify({ video_id: "OTt1dfYhlDY" });
  const enc = new TextEncoder();
  const key = await crypto.subtle.importKey("raw", enc.encode(secret), { name: "HMAC", hash: "SHA-256" }, false, ["sign"]);
  const sig = Array.from(new Uint8Array(await crypto.subtle.sign("HMAC", key, enc.encode(timestamp + "." + payload))))
    .map((v) => v.toString(16).padStart(2, "0")).join("");
  const req = (s, time = timestamp) => new Request("https://processor.example/jobs", {
    method: "POST", headers: { "x-surfside-time": time, "x-surfside-signature": s }, body: payload
  });
  assert.equal(await verifiedRequest(req(sig), secret, Number(timestamp) * 1000), true);
  assert.equal(await verifiedRequest(req("0".repeat(64)), secret, Number(timestamp) * 1000), false);
  assert.equal(await verifiedRequest(req(sig), secret, (Number(timestamp) + 1000) * 1000), false);
  assert.equal(await verifiedRequest(req(sig), "short", Number(timestamp) * 1000), false);
});

test("isolated end-to-end test paths cannot overwrite canonical Roku or podcast objects", () => {
  const id = "0_QXD1k4fcI";
  assert.equal(sourceKey(id), "sermons/0_QXD1k4fcI.mp4");
  assert.equal(audioKey(id), "podcast-audio/0_QXD1k4fcI.mp3");
  assert.equal(statusKey(id), "podcast-jobs/0_QXD1k4fcI.json");
  assert.equal(sourceKey(id, true), "podcast-test-input/0_QXD1k4fcI.mp4");
  assert.equal(audioKey(id, true), "podcast-test-audio/0_QXD1k4fcI.mp3");
  assert.equal(statusKey(id, true), "podcast-test-jobs/0_QXD1k4fcI.json");
  assert.equal(validStorageAccess(sourceKey(id, true), "GET"), true);
  assert.equal(validStorageAccess(audioKey(id, true), "PUT"), true);
  assert.equal(validStorageAccess(sourceKey(id, true), "PUT"), false);
  assert.equal(validStorageAccess(audioKey(id, true), "GET"), false);
  assert.equal(validStorageAccess("podcast-test-spreaker/0_QXD1k4fcI.json", "PUT"), false);
  assert.equal(validStorageAccess("podcast-test-input/../../secrets.mp4", "GET"), false);
});
