# Sermon highlights — manual AI pilot

This is a **manual, analysis-only experiment**, not an automated social posting or clip-generation feature. It reuses the existing Cloudflare podcast Worker, Queue, R2 bucket, and FFmpeg Container. No WordPress or mobile app changes are needed to test the first sermon.

## Reuse the podcast's original audio

The existing podcast processor uses a single FFmpeg filter to concatenate the intro + sermon + outro; previously, it did not save the bare sermon audio. This PR branches the **original MP4 audio stream within that existing FFmpeg operation**, creating two outputs:

- **Unchanged finished podcast**: `podcast-audio/<id>.mp3` — intro + sermon + outro, stereo 44.1 kHz, 128 kbps, loudness-normalized. Spreaker behavior stays unchanged.
- **New reusable sermon-only audio**: `sermon-audio/<id>.mp3` — no bumpers, mono 16 kHz, 48 kbps, starting on the original source timeline.

Test podcast jobs use the isolated `podcast-test-sermon-audio/<id>.mp3` path. The podcast MP3 is uploaded first. If combined encoding fails, the processor retries **the original podcast-only encode**; it never uploads a partial sidecar. If the optional sermon-only upload fails, podcast delivery still succeeds.

For highlights of **new sermons**, the Container downloads the small sermon-only MP3, not the video. **Historical sermons** lacking this sidecar trigger a one-time on-demand extract from the original MP4 and cache the result in `sermon-audio/`. The MP4 and podcast MP3 remain untouched. A sermon-only MP3 may need an occasional manual timestamp correction due to codec/A-V sync behavior, so staff should verify clip boundaries.

## Manual processing flow

1. A signed `POST /highlights/jobs` for one YouTube ID checks that its canonical MP4 exists and queues a new `kind: "highlights"` job. Existing `/jobs`, `/status`, and Spreaker behavior is unchanged.
2. The existing Container reads the cached `sermon-audio/<id>.mp3` (or creates it from the MP4 once for historical recordings) and **splits it without re-encoding** into ~3-minute MP3 chunks. Each temporary chunk is placed under `sermon-highlights/chunks/<id>/NNN.mp3`. Maximum source duration: 72 minutes.
3. The Queue consumer uses Cloudflare Workers AI (`@cf/openai/whisper-large-v3-turbo`) to transcribe every chunk. Returned ASR segment timestamps are converted back into full-MP4 seconds.
4. The transcript is split into overlapping ~10-minute editorial windows. Workers AI (`@cf/meta/llama-3.3-70b-instruct-fp8-fast`) recommends candidates by **existing transcript segment IDs**, not invented start/end timestamps.
5. The pilot validates candidate segment IDs, durations (25–105 seconds), transcript excerpts, and overlapping suggestions. It chooses **up to five** review candidates and saves the result privately in R2.
6. Temporary analysis MP3 chunks are deleted on the success/failure path; the reusable sermon-only audio is retained in R2. Original MP4s and finished podcasts remain unchanged.

Outputs in the same R2 bucket:

- `sermon-audio/<id>.mp3` — reusable sermon-only audio captured during future podcast jobs, or created on demand for historical sermons; never published.
- `sermon-highlights/jobs/<id>.json` — queued / processing / ready / failed.
- `sermon-highlights/transcripts/<id>.json` — full timestamped ASR segments.
- `sermon-highlights/results/<id>.json` — selected highlight titles, start/end seconds, exact transcript excerpts, and AI-generated editorial reasons.
- `sermon-highlights/chunks/<id>/NNN.mp3` — temporary audio, deleted at the end of a job (may require manual cleanup after an interrupted Container upload).

The result is **not published**, returned to a public endpoint, or visible in the website/app. The API requires the same timestamped HMAC secret as the existing podcast endpoints.

## Before deploying

- Review the pull request, run `cd cloudflare/podcast && npm run check && npm test`, and confirm GitHub checks are green.
- Confirm the existing Cloudflare account has Workers AI enabled, and review Workers AI and Container usage billing. The Worker gains an `AI` binding; no new API key is committed.
- Cloudflare may deploy automatically when `main` changes if a GitHub build is connected. Verify the new Cloudflare deployment before issuing a job.
- Normal new podcast jobs will now **also save a sermon-only audio sidecar** as a best-effort additional output. **AI transcription and highlight analysis remain manual**; no existing historical recordings are bulk processed.
- Do not share the existing `SURFSIDE_PODCAST_SHARED_SECRET` in chat, a screenshot, terminal history, or GitHub.

## Run one sermon

On a trusted development machine with Node 22+, set these two environment variables locally (same existing Worker URL and HMAC secret used by the WordPress integration):

- `SURFSIDE_PODCAST_WORKER_URL`
- `SURFSIDE_PODCAST_SHARED_SECRET`

Then run:

```bash
cd cloudflare/podcast
node scripts/highlights-pilot.mjs e0NnCrrshM4 jobs
node scripts/highlights-pilot.mjs e0NnCrrshM4 status
```

`e0NnCrrshM4` is the sermon ID mentioned in the existing November 8 podcast-processor notes as having been uploaded to R2; confirm the source MP4 is still present before using it. The first command returns `queued` or an existing status. The second reports the current job state and, when ready, five or fewer highlight recommendations. If an error occurs, resolve it before explicitly re-triggering a failed job.

You can use **any verified canonical sermon YouTube ID** instead. Do not use a full URL. Never run a large historical backfill for this pilot.

## What success looks like

After `status` becomes `ready`, inspect suggested `start` and `end` seconds against the **original MP4**, not the podcast MP3. Look for complete sentences, meaningful standalone teaching, faithful context, and ideally five worthwhile moments across the sermon.

An approximate time can be displayed as `mm:ss`. ASR and codec framing may introduce small timestamp offsets, so staff must verify the boundaries before cutting clips. AI transcription can also mishear names, Scripture references, or numbers. The title/reason/score are editorial *suggestions*, not verified sermon facts.

A zero-highlight result is possible and should be evaluated rather than silently replaced with low-quality recommendations.

## Out of scope

- Automatic running after weekly uploads.
- Editing video, captions, reframing, waveform previews, or FFmpeg clip renders.
- WordPress dashboard review UX.
- Spreaker publication or changes to Roku playback.
- Automatic Facebook / Instagram / YouTube posting.

If manual review proves the selected moments useful, a later PR can add a staff review interface and a separately approved video rendering step.
