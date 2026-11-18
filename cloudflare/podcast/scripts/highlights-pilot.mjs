#!/usr/bin/env node
// Run a single, explicitly authorized analysis job. No podcast/video publishing.
import { createHmac } from "node:crypto";

const [id, action = "jobs"] = process.argv.slice(2);
const url = process.env.SURFSIDE_PODCAST_WORKER_URL?.replace(/\/$/, "");
const secret = process.env.SURFSIDE_PODCAST_SHARED_SECRET;
if (!/^[A-Za-z0-9_-]{11}$/.test(id || "") || !["jobs", "status"].includes(action)) {
  console.error("Usage: node scripts/highlights-pilot.mjs <youtube-video-id> [jobs|status]");
  process.exit(2);
}
if (!url || !/^https:\/\//.test(url) || !secret || secret.length < 32) {
  console.error("Set SURFSIDE_PODCAST_WORKER_URL and SURFSIDE_PODCAST_SHARED_SECRET in your local environment.");
  process.exit(2);
}
const body = JSON.stringify({ video_id: id });
const timestamp = String(Math.floor(Date.now() / 1000));
const signature = createHmac("sha256", secret).update(timestamp + "." + body).digest("hex");
const response = await fetch(url + "/highlights/" + action, {
  method: "POST",
  headers: {
    "content-type": "application/json",
    "x-surfside-time": timestamp,
    "x-surfside-signature": signature,
  },
  body,
});
console.log("HTTP", response.status);
const payload = await response.json().catch(() => ({ error: "invalid_json_response" }));
console.log(JSON.stringify(payload, null, 2));
if (!response.ok) process.exitCode = 1;
