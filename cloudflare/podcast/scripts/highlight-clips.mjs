#!/usr/bin/env node
// GitHub Codespaces manual review helper: render selected MP4 clips or get
// short-lived private browser preview links. No publishing is performed.
import { createHmac } from "node:crypto";

const [id, action = "status", ...selections] = process.argv.slice(2);
const base = process.env.SURFSIDE_PODCAST_WORKER_URL?.replace(/\/$/, "");
const secret = process.env.SURFSIDE_PODCAST_SHARED_SECRET;
const usage = "Usage: node scripts/highlight-clips.mjs <11-char-video-id> [status|render [2=456-491] [4]]";
if (!/^[A-Za-z0-9_-]{11}$/.test(id || "") ||
    !["status", "render"].includes(action)) {
  console.error(usage); process.exit(2);
}
if (!base || !/^https:\/\//.test(base) || !secret || secret.length < 32) {
  console.error("Set SURFSIDE_PODCAST_WORKER_URL and SURFSIDE_PODCAST_SHARED_SECRET locally.");
  process.exit(2);
}
let clips;
if (action === "render") {
  if (!selections.length || selections.length > 5) {
    console.error(usage); process.exit(2);
  }
  clips = selections.map((arg) => {
    const matched = /^([1-5])(?:=(\d+(?:\.\d{1,2})?)-(\d+(?:\.\d{1,2})?))?$/.exec(arg);
    if (!matched) { console.error("Invalid clip: " + arg); process.exit(2); }
    return matched[2]
      ? { number: Number(matched[1]), start: Number(matched[2]), end: Number(matched[3]) }
      : { number: Number(matched[1]) };
  });
  if (new Set(clips.map((c) => c.number)).size !== clips.length) {
    console.error("Duplicate highlight number"); process.exit(2);
  }
}
const body = JSON.stringify({ video_id: id, ...(clips ? { clips } : {}) });
const timestamp = String(Math.floor(Date.now() / 1000));
const signature = createHmac("sha256", secret).update(timestamp + "." + body).digest("hex");
const response = await fetch(base + "/highlights/clips/" +
  (action === "render" ? "jobs" : "status"), {
  method: "POST",
  headers: {
    "content-type": "application/json",
    "x-surfside-time": timestamp,
    "x-surfside-signature": signature,
  },
  body,
});
const data = await response.json().catch(() => ({ error: "invalid_json_response" }));
console.log("HTTP", response.status);
console.log(JSON.stringify(data, null, 2));
if (data?.status === "ready" && Array.isArray(data.clips)) {
  console.log("\nPrivate preview links (expire in 10 minutes):");
  for (const clip of data.clips)
    console.log(`#${clip.number} ${clip.title}: ${clip.preview_url}`);
  console.log("Open in your browser to watch or download; do not post these links publicly.");
}
if (!response.ok) process.exitCode = 1;
