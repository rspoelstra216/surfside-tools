# Spreaker draft integration — weekly publishing only

## Safeguards

This optional stage runs **only after a new weekly MP4 is uploaded and verified through
Surfside Tools** and the Cloudflare processor has generated its R2 MP3 successfully.
Existing manually scheduled Spreaker backlog episodes are untouched. YouTube IDs,
titles, series/week, and sermon dates come from the existing WordPress YouTube
catalog/review data. Ambiguous titles/dates or excluded videos do **not** request
a Spreaker draft.

The integration **never publishes or schedules** an episode. It uses Spreaker's
`POST /v2/episodes/drafts` endpoint and uploads audio to the returned draft via
`POST /v2/episodes/<episode-id>`. Review and publish manually in Spreaker.

A second message on the existing Cloudflare Queue uploads audio to Spreaker;
it does not hold the FFmpeg processing job open. Spreaker errors never change
a completed Roku MP4 or R2 podcast MP3 to failed.

## One-time setup

1. Confirm `SURFSIDE_PODCAST_WORKER_URL` and `SURFSIDE_PODCAST_SHARED_SECRET`
   are configured in production `wp-config.php`, matching the Cloudflare
   worker deployed at `cloudflare/podcast/`. The MP4 weekly-upload queue
   stays disabled until both are valid.
2. Register an OAuth2 app through the [Spreaker developer portal](https://developers.spreaker.com/guides/authentication/).
   The podcast owner must authorize the application with the `basic` scope.
   Use the **server-side authorization-code flow** to obtain an access token
   using Spreaker's `https://api.spreaker.com/oauth2/token` endpoint.
   **Never paste an access token, client secret, or refresh token into chat,
   GitHub, screenshots, or the WordPress page.**
3. Find the numeric **Spreaker show ID** of the existing Surfside podcast.
   Do not create a new podcast. Spreaker's API provides the owner's shows
   through the authenticated shows listing.
4. In Cloudflare, open **Workers & Pages → surfside-sermon-podcast →
   Settings → Variables and Secrets**. Create two encrypted Worker secrets:
   - `SPREAKER_OAUTH_ACCESS_TOKEN` — the access token for the podcast owner;
   - `SPREAKER_SHOW_ID` — the numeric ID of the existing podcast.
   
   Alternatively, from `cloudflare/podcast`, use
   `npx wrangler secret put SPREAKER_OAUTH_ACCESS_TOKEN` and
   `npx wrangler secret put SPREAKER_SHOW_ID`.
   Do not place these values in `wrangler.jsonc` or any source file.
5. After this PR is merged and Cloudflare reports **Ready**, enable this
   explicit server-side switch in production `wp-config.php`:
   
   ```php
   define('SURFSIDE_SPREAKER_DRAFTS_ENABLED', true);
   ```
   
   Leave this setting **unset/false** until the Cloudflare secrets exist
   and you're ready for a controlled **new weekly upload** test.

## Weekly flow

1. Staff select a YouTube message and upload the MP4 using
   **Weekly Media Publishing**. Only the existing staff-authorized completion
   handler can send the signed job, after R2 HEAD and size/content-type
   verification. Historical reconciliation and playlist sync never dispatch.
2. When the explicit switch is on and the existing metadata parser recognizes
   a complete valid sermon title/date, WordPress includes `spreaker.title`
   and `spreaker.description` in the signed `POST /jobs` request. No token is
   sent from WordPress or exposed to browser JavaScript.
3. Cloudflare's existing processor creates `podcast-audio/<video-id>.mp3`
   and marks the audio job `ready`.
4. The Queue receives a **separate** `kind: "spreaker"` message. Cloudflare
   creates a Spreaker `DRAFT`, records its episode ID in R2, and streams the
   finished MP3 directly from R2 to Spreaker as multipart/form-data.
5. Once Spreaker accepts the upload, the R2 record is marked `uploaded`.
   Spreaker may still be encoding the audio. Staff must open the Spreaker CMS,
   confirm audio/metadata, and **publish manually**.

## Status and recovery

- `podcast-jobs/<video-id>.json`: existing audio job state:
  `queued`, `processing`, `ready`, or `failed`. This remains independent
  of Spreaker.
- `podcast-spreaker/<video-id>.json`: draft state:
  `queued`, `creating`, `draft_created`, `uploaded`,
  `not_configured`, `upload_retry`, `queue_unavailable`, or
  `review_required`. The record includes the Spreaker episode ID once known;
  it contains **no secret/token**.
- The authenticated Worker `POST /status` also includes a `spreaker`
  object if there is a Spreaker record.
- If creating the draft yields an ambiguous response or a token authorization
  error, the state becomes `review_required`; the system **does not
  automatically create another draft**, because the earlier POST may have
  succeeded. Inspect Spreaker and Cloudflare logs before resolving it.
- If upload fails after an episode ID is known, bounded Queue retries reuse
  that same unpublished draft. After retries are exhausted, staff must
  investigate and reconcile manually.
- OAuth access tokens can expire. This first integration does **not**
  implement a token-refresh service; renew the Worker secret before expiry or
  investigate any `401` state. A subsequent dedicated PR can implement
  refresh/re-authorization and a staff retry control.
- R2 may currently be public on certain bucket domains. These status records
  contain IDs and statuses only, **no Spreaker credentials**. Do not add
  secrets to R2 metadata objects.

## First verification after credentials are configured

Use a new, not previously uploaded sermon. Verify Roku MP4 first; inspect
`podcast-jobs/<id>.json` for `ready` and `podcast-spreaker/<id>.json`
for `uploaded`. Then inspect the matching episode in the **existing**
Spreaker show; it must be a **draft**, never published. If anything is
unexpected, remove/disable the WordPress `SURFSIDE_SPREAKER_DRAFTS_ENABLED`
switch before the next week's upload.

API documentation:
- https://developers.spreaker.com/guides/authentication/
- https://developers.spreaker.com/guides/working-with-draft-episodes/
- https://developers.spreaker.com/api/episodes/


## Admin-only complete end-to-end pipeline test (no new YouTube sermon required)

**Purpose:** Reproduce the entire real-world upload and publishing workflow without
deleting any existing Roku MP4 or podcast MP3 or touching the ten manually
scheduled Spreaker episodes. The older draft-only test in PR #479 does **not**
exercise the browser MP4 upload or FFmpeg and is superseded by this test.

After this PR is merged and the new Worker deployment is Ready:

1. Open **Surfside Tools → Weekly Media Publishing** as a WordPress
   administrator. Confirm `SURFSIDE_SPREAKER_DRAFTS_ENABLED` is `true` and
   that both Spreaker secrets are present in the Cloudflare Worker.
2. Under **Podcast episode preview**, select a recent sermon with a recognized
   title and date, e.g. `0_QXD1k4fcI`. You may select a sermon *already* in
   R2 because the test does not use the canonical Roku upload path.
3. Under **Full pipeline test (admin only)**, browse to the original sermon
   **MP4 on your computer**, check the confirmation, and choose
   **Run end-to-end test**. Keep the browser tab open for the upload, FFmpeg,
   and Spreaker checks.
4. The browser performs the normal **presigned direct R2 PUT** into
   `podcast-test-input/<video-id>.mp4`. PHP checks HEAD, file size, and
   `video/mp4` type before sending the HMAC-signed `POST /jobs` with an
   explicit `test: true` flag and reviewed YouTube title/description.
5. The **same Cloudflare Queue and Container/FFmpeg processing logic** extracts
   audio, prepends the current intro, appends the current outro, normalizes
   levels, and uploads to `podcast-test-audio/<video-id>.mp3`.
6. On success, Cloudflare queues the **same Spreaker draft creation and MP3
   upload** logic, with a clearly labeled title
   `[TEST - DO NOT PUBLISH] ...`.
7. The staff page polls Cloudflare via the existing nonce-protected WordPress
   route, showing audio status and Spreaker draft status. Confirm:
   - `podcast-test-jobs/<video-id>.json` has `status: "ready"`;
   - `podcast-test-spreaker/<video-id>.json` has `status: "uploaded"`;
   - the newly generated MP3 plays correctly;
   - the new episode is **unpublished** under **Spreaker → Drafts** in the
     existing show `3402314`.
8. **Delete the unmistakably labeled test draft** in Spreaker after inspection.
   To reclaim R2 storage, remove only the four `podcast-test-*` objects
   for that ID through the Cloudflare R2 object browser *after* processing
   finishes. Do not delete `sermons/<video-id>.mp4`,
   `podcast-audio/<video-id>.mp3`, normal `podcast-jobs` objects or the
   shared `podcast-assets`.

### Limits and safety

- This test is administrator-only and requires the existing WordPress
  nonce, a reviewed YouTube catalog entry, the server's Worker HMAC secret,
  and an explicit confirmation checkbox. No Worker secret reaches JavaScript.
- Test output paths are separate for input, audio, processing state, and
  Spreaker state. The public Roku catalog and established podcast MP3s
  are not altered. This is a one-run-per-video-ID test until those test
  objects are deliberately removed. Running this test again without cleaning
  up must not overwrite the previous test source.
- Test Spreaker metadata is visibly marked; no API endpoint schedules or
  publishes it. Staff should delete the test draft manually, **not** any
  existing scheduled episodes.
- If the UI reports an error after the MP4 verifies, inspect the test
  status objects and Cloudflare Observability before retrying. Blind
  resubmission could create unnecessary extra draft episodes.
- No changes to the bulk archive, public Roku endpoints, or production
  weekly upload flow are necessary for this test.
