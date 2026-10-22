# Cloudflare weekly sermon audio processor (first stage)

## Purpose and boundaries

A successful **new weekly MP4 upload from Weekly Media Publishing** now makes one optional, authenticated HTTP call to a Cloudflare Worker to enqueue audio production. This call is invoked **only** from the established browser upload-completion handler, *after* the existing R2 HEAD, size and Content-Type checks mark Roku playback ready. It is not invoked by the historical import script, reconciliation, YouTube playlist scans, or Roku public APIs. The existing YouTube video ID remains the job ID. **A processing failure never undoes Roku readiness.**

This stage does **not** touch Spreaker, publish a podcast or require a NAS. Its output is an MP3 in R2, for a separate Spreaker draft-upload PR after live processing has been verified. R2 continues to hold the Roku video under its canonical key.

## Cloudflare side

Code lives under \`cloudflare/podcast/\`. It includes:

- An HTTP Worker endpoint \`POST /jobs\`, authenticated by timestamped HMAC, for the server-side WordPress integration; \`POST /status\` uses the same authentication.
- A Cloudflare Queue consumer to do the slow processing outside the WordPress request. It writes job statuses to R2.
- A Cloudflare Container running Node and FFmpeg (no externally accessible container route). It retrieves the sermon and fixed intro/outro MP3s through an **R2 binding outbound handler** with restricted read/write paths, then writes the finished MP3 into R2.
- 128 kbps, stereo 44.1 kHz MP3 with intro → sermon → outro and a loudness-normalization pass.
- Validation, duplicate-output prevention, queue retries, idempotent deterministic paths and no credentials in client-side JavaScript.

### R2 object layout

| Key | Purpose |
| --- | --- |
| \`sermons/<youtube-id>.mp4\` | **Existing** verified Roku copy; unchanged |
| \`podcast-assets/intro.mp3\` | Static intro recording, uploaded once |
| \`podcast-assets/outro.mp3\` | Static outro recording, uploaded once |
| \`podcast-audio/<youtube-id>.mp3\` | Resulting **private** MP3, not automatically posted to Spreaker |
| \`podcast-jobs/<youtube-id>.json\` | Processing state (\`queued\`, \`processing\`, \`ready\`, \`failed\`) |

Upload the intro and outro files to the **existing** R2 bucket using the Cloudflare R2 object browser. Both must be real playable MP3s. If your saved bumpers are WAVs, convert each once to MP3 first. The program does **not** alter the originals or the sermon video.

## Deployment (requires the Cloudflare account owner)

1. Confirm the **Workers Paid ($5/month)** plan is enabled. Containers are billed by usage above its included allowances; this is not a $5 spending cap. For ordinary 1.6–2 GB sermon originals, the configuration uses **standard-1** (8 GB ephemeral disk) instead of **basic** (4 GB), to leave room for temporary MP4 + output. Verify actual CPU/disk usage in the first run and set a billing alert. A Queue and R2 operations also count toward their usage meters.
2. On a development computer with Docker and Node installed, run \`cd cloudflare/podcast && npm install\` and \`npm run check && npm test\`.
3. Authenticate: \`npx wrangler login\`. Create the queue: \`npx wrangler queues create surfside-podcast-jobs\`. If your bucket is not actually named \`surfside-sermons\`, correct the \`bucket_name\` in \`wrangler.jsonc\` first.
4. Generate a **long, random shared secret** (at least 32 characters). Run \`npx wrangler secret put SURFSIDE_PODCAST_SHARED_SECRET\` inside \`cloudflare/podcast\` and enter it. Store the same value securely for the WordPress configuration; never commit it, put it in a screenshot or paste it into chat.
5. Run \`npx wrangler deploy\` with Docker running. Note the assigned HTTPS \`.workers.dev\` URL, then confirm provisioning with \`npx wrangler containers list\`. No container needs to stay running between sermons.
6. **Only after deployment and validating the bumper MP3 objects**, add these constants to production \`wp-config.php\`, alongside the other Surfside R2 constants:

       define('SURFSIDE_PODCAST_WORKER_URL', 'https://surfside-sermon-podcast.YOUR_SUBDOMAIN.workers.dev');
       define('SURFSIDE_PODCAST_SHARED_SECRET', 'SAME_LONG_RANDOM_SECRET_AS_WRANGLER');

   If either constant is missing, the WordPress plugin does **not** dispatch a job; its existing video upload remains functional. Do not enable until the Cloudflare processor is tested.

7. For the next real weekly sermon, publish the YouTube video to the Messages playlist, paste its link into Weekly Media, and upload the Roku-ready MP4 as before. The video should still show **verified** even if Cloudflare is unreachable. When queued, the uploader will say **Podcast audio processing queued**.
8. Inspect R2 for \`podcast-jobs/<video-id>.json\`, eventually \`ready\`, and \`podcast-audio/<video-id>.mp3\`. Download the MP3 and listen to the transitions and volume. Do **not** treat an MP3's existence as authorization to publish it.
9. The worker accepts an authenticated \`POST /status\` with \`{"video_id":"..."}\`, signed the same way as \`/jobs\`. A future PR can expose this read-only status and retry within Weekly Media without doing broad work on public requests.

## Failure behavior

- Missing worker configuration: MP4 upload succeeds; message says podcast processing is not configured.
- Missing intro/outro or missing source: the Cloudflare endpoint responds with a controlled error; MP4 upload remains ready. Nothing is sent to Spreaker.
- Network/queue failure: MP4 remains ready, job status may show failed. An authorized retry can enqueue again when the underlying issue is repaired. The enqueue endpoint refuses duplicates with an existing queued/processing job or finished output.
- If FFmpeg crashes, times out after eleven minutes, or output is empty/over 300 MB, the Queue retries (bounded). No partial file is published as an episode.
- The processing job is constrained by Cloudflare Queues' **15-minute consumer limit**. Measure actual duration during the first trial; if it exceeds the limit, we should split orchestration into a longer-lived Workflow without changing the R2 object contract.
- Never blindly enable R2 bucket-wide event notifications for this feature. Those would reprocess historical bulk imports and unrelated files.

## Security and architecture notes

- WordPress signs the raw JSON with HMAC-SHA256 over \`timestamp + "." + body\`, and the Worker rejects signatures outside ±5 minutes. The secret must match between WordPress and the Worker.
- The Worker only accepts \`POST /jobs\` and \`POST /status\` with valid signatures. The processor Container is not public. Container outbound R2 access is scoped to known media paths and object types; no R2 secret key is present inside its environment.
- The podcast assets and MP3 output are not exposed through the Roku message catalog; only the canonical sermon MP4 key remains authoritative for Roku.
- Spreaker drafts, authorization, publishing controls and persistent podcast IDs will be added in a separate subsystem PR after the audio output is verified. Spreaker drafts should remain unpublished pending staff review.
- Do not merge this PR until GitHub CI and a code review pass. A live test still requires deployment/configuration actions on the Cloudflare account.

## First processing test: R2 MP3 stream length fix

In the October 22 first live test, FFmpeg completed far enough to send its output
to `storage.internal`, but R2 rejected the PUT with "Provided readable stream
must have a known length". The October 22 fix sends the actual MP3 file size
as `Content-Length` from the Container. The Worker validates the length,
wraps the incoming body in a `FixedLengthStream`, and streams it directly into
the R2 binding without buffering a whole podcast in Worker memory. Both
missing/invalid lengths and truncated bodies are rejected.

Once this fix is merged and Cloudflare's GitHub build completes successfully,
retry the **same** video ID via a newly signed `POST /jobs` request. The failed
job may have retried previously; inspect `podcast-jobs/<id>.json` and
`podcast-audio/<id>.mp3` first. If MP3 is already present, verify it and
do not request an unnecessary re-encode. If status is `failed` with no MP3,
one explicit retry is safe. The verified Roku MP4 and Spreaker remain untouched.
