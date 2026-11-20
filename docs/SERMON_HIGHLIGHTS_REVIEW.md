# Sermon Highlights — Staff Review (PR #519)

## Purpose

Surfside Tools now provides a staff-only **Sermon Highlights** page in the existing
**Messages & Media** workspace. It uses the private Cloudflare highlights pipeline
introduced in PRs #515–#518 and avoids routine GitHub Codespaces terminal commands.

The AI provides up to five suggestions. Staff review **video and audio from the
original R2 MP4**, not just the Whisper transcript, and make the editorial decision.

## Where to find it

After the WordPress plugin update and Cloudflare backend from PR #518 are deployed:

- **Staff Dashboard → Messages & Media → Sermon Highlights**
- Direct route: `/dashboard/site-management/?view=sermon-highlights`
- Access: signed-in WordPress staff with the existing **Weekly Media**
  Surfside Tools role permission and `upload_files` capability.

Only verified uploaded MP4 sermons from the master YouTube Messages playlist
are selectable; the latest 100 eligible sermons are displayed. No public viewer
or unauthenticated REST endpoint is added.

## Workflow

1. Select a verified uploaded sermon.
2. Click **Analyze sermon** if there are no saved AI suggestions yet. The
   WordPress server authenticates to Cloudflare and shows queue/processing/
   ready status; it **does not** start podcast or Spreaker operations.
3. Click **Generate video previews** when analysis is ready. WordPress requests
   a preview padded with **five seconds before and after** each AI suggestion,
   up to five clips. The existing MP4 renderer uploads only private preview
   objects; no public asset is published.
4. Play a video. The viewer initially plays only the suggested segment.
   Adjust **Start** and **End** independently ±5.0 seconds, in 0.5-second
   increments; the player seeks or stops at the edited boundaries without
   another FFmpeg render.
5. Click **Save changes**, **Approve**, or **Reject**. Decisions and exact
   centisecond start/end timestamps are stored in a **non-autoloaded per-sermon
   WordPress option**, along with reviewer ID and UTC update timestamp.
   Selections reload when staff reopen the sermon.

**Approve** is an internal editorial marker only and does not automatically
publish or export. The separate, manually initiated **Export approved clips**
action creates precisely trimmed private MP4s. Nothing publishes to social media,
Spreaker, or the website without a separate staff action.

## Security and architecture

- The browser makes small, nonce-verified POST requests to the **existing
  staff dashboard page** at `/dashboard/site-management/?view=sermon-highlights`.
  The staff session is valid in the `/dashboard` scope, unlike standard
  WordPress REST cookie auth. This follows the proven Weekly Media/R2 upload
  authorization pattern and avoids the REST "Cookie check failed" response.
  Requests require a logged-in authorized staff session, `upload_files`
  capability and the **Weekly Media** Tools permission. No public REST route
  or general REST authentication bypass is created.
- WordPress constructs its own HMAC request with the existing
  `SURFSIDE_PODCAST_WORKER_URL` and `SURFSIDE_PODCAST_SHARED_SECRET` from
  production `wp-config.php`. No secret, credentials or signing inputs
  are placed in HTML, JavaScript, REST responses, or public pages.
- Cloudflare serves **short-lived (10-minute), clip-scoped signed MP4 URLs**
  with browser seek support, entirely outside the public sermon bucket.
  Use **Refresh preview links** if a previously generated preview expires.
- Saved decisions are verified **against the immutable AI recommendation**:
  each edge must stay within ±5 seconds; clips must remain 15–120 seconds.
  The backend revalidates on Save/Approve/Reject rather than trusting
  JavaScript sliders. Padded previews remain unpublished private assets.
- The dashboard action dispatcher accepts eight allowlisted review modes
  (including the new `export` and `export-status` actions). WordPress
  forwards only explicit authorized highlights Worker routes.
  The podcast
  `/jobs`, Spreaker draft/publish, livestream and public sermon APIs
  remain untouched.

## Manual verification

After merging, install/update the plugin through the normal WordPress deployment
and confirm PR #518 Cloudflare Worker/Container is deployed.

1. Log in as an authorized Weekly Media staff user and navigate to Sermon Highlights.
2. Select `AjkoDHgFdeA` (November 15, 2026), which already has a ready AI result.
3. Generate previews, and inspect the real video for highlights #2 and #4.
4. Move the Start control for #2 earlier and the End control later; verify
   the video seeks/stops at adjusted points **without rendering another clip**.
5. Save #2, approve #4 and reject #3; reload and confirm each decision persists.
6. Try to adjust either boundary beyond five seconds (the UI blocks it;
   the REST handler also rejects forged requests).
7. Verify a nonstaff account receives 403 from all highlights REST endpoints,
   and no WordPress/Cloudflare output shows the HMAC secret.
8. Verify the existing podcast creation, Spreaker draft/publication and R2
   sermon upload flows have not been altered.

### Offline checks

```bash
php scripts/test-sermon-highlights-review.php
node --check assets/js/sermon-highlights-review.js
```

All application behavior remains manual until production validation.
The final export action makes downloadable MP4s, **not** automatic publication.

## Configuration-check troubleshooting

If the Sermon Highlights staff screen says Cloudflare configuration is not
available, the verified staff-only `sermons` response now includes six
**booleans only**, displayed as Yes/No:

- Worker URL defined
- Worker URL is text
- Worker HTTPS URL accepted
- Shared secret defined
- Shared secret is text
- Shared secret meets minimum length (32+ characters)

No raw URL, secret bytes, secret prefix, actual secret length, hashes, or
WordPress filesystem paths are exposed. This diagnostic runs locally in
WordPress and does not call Cloudflare or alter Spreaker configuration.

Do **not** regenerate credentials to troubleshoot this message. Confirm
which check is No from the deployed dashboard, then investigate only that
condition. If all six are Yes, Cloudflare validation is available and the
normal sermon selection/analysis workflow should be enabled. If the existing
sermon dropdown is empty even with valid configuration, check the local
master Messages playlist and verified R2 sermon records separately from
the Cloudflare connection.

## Diagnostic version consistency

If browser JavaScript is newer than the PHP backing the authenticated
dashboard POST, an older server response may omit `config_checks`. Previous
JavaScript incorrectly displayed these missing fields as **No**. The UI now
displays **Not reported** for missing or non-boolean fields, and includes a
fixed configuration protocol marker in both the server-rendered page and
the staff POST response. Staff can compare the two sets of boolean checks:

- **Page render: Yes, dashboard POST: Not reported** suggests an old POST
  handler or cached/stale PHP code behind the active front-end.
- **Page render: Yes, dashboard POST: No** suggests a genuine runtime
  mismatch between the two authenticated WordPress requests.
- **Both sides: No** means PHP reported the constants undefined in both
  contexts; investigate server runtime, rather than assuming a malformed URL.
- **Both sides: Yes** means local Worker configuration is available.
- **Version mismatch** means the browser must not assume it can use the
  currently deployed PHP contract.

This does not bypass the existing staff-session authorization. No raw config
values, paths, lengths or private credentials are returned to the browser.

## Sermon dropdown startup fix

The original staff editor made a second `sermons` POST immediately after
rendering the authorized WordPress page. In production, this request supplied
an inconsistent response shape: the page-render PHP checks for the existing
Cloudflare Worker and HMAC secret were all **Yes**, while the JavaScript
received no matching configuration fields from the second request. This
incorrectly blocked the entire editor.

To eliminate the extra initialization boundary, the logged-in PHP page now
embeds the **eligible, verified sermon list and configuration boolean** in
the same inline startup data as the nonce and dashboard URL. The secret,
Worker address, and raw configuration values are still never emitted. The
browser no longer makes a `sermons` request at startup and relies on the
known-valid PHP page context. Normal server-authorized POSTs continue to
handle analysis, status, MP4 previews and review decisions, using the
existing nonce, staff role and HMAC checks.

This change does not touch the established MP4 upload, podcast queue,
Cloudflare Worker, Spreaker or sermon source files. Re-test using the
already-analyzed November 15 sermon; there is no need to re-upload it.

## Approved Highlights: final MP4 downloads

After reviewing actual MP4 previews and saving adjusted start/end times:

1. Click **Approve** on the clips you want to export. **Save changes** alone
   creates a draft; rejected and draft suggestions are never exported.
2. The **Approved Clips** panel near the top of the Sermon Highlights page
   counts approved suggestions and offers **Export approved clips**.
3. This explicit action reads the exact persisted trim times on the **WordPress
   server**, checks staff permissions/nonce, and sends only approved ranges in
   a signed Worker request. The Worker independently compares each range to
   the immutable AI result, enforcing each edge within ±5 seconds, 15–120
   second clips and at most five unique clips. No browser-supplied timestamps
   can override the saved reviews.
4. The existing Queue/FFmpeg Container downloads the original sermon MP4
   once for the batch, renders accurate H.264/AAC final clips with **no padded
   video handles**, and writes private, immutable R2 objects under
   `sermon-highlights/exports/<video-id>/<number>-<start-cs>-<end-cs>.mp4`.
   Review previews under `sermon-highlights/previews/` are unaffected.
5. The panel polls export progress independently of previews. Once ready,
   **Download MP4** appears for every approved clip. Download URLs are
   HMAC-signed, scoped to that single export, expire in ten minutes, and
   use `Content-Disposition: attachment`. Use **Refresh download links**
   for new short-lived URLs; the MP4s are retained privately in R2.
6. Changing a review to **Rejected** or **Saved**, or changing an approved
   range, hides any old export links. Re-run the manual export for the current
   approval set. Existing private R2 objects are not automatically deleted
   in this PR; lifecycle cleanup can be considered separately.

**Privacy:** There is no public archive, public R2 bucket, social-media
posting, sharing webhook, or automatic Spreaker/YouTube/Facebook publishing.
Only authorized staff can initiate export and fetch download URLs. A bearer
download URL may be opened by its possessor until it expires, so do not share
the private links publicly. Download the MP4 and upload/share it wherever staff
choose after final review.

**Scope:** No AI re-analysis is needed for sermons with existing results;
no source MP4 modifications; no podcast, livestream, or Spreaker changes.
This uses the existing Cloudflare Container and adds billable FFmpeg compute
for each manually requested export batch.

### Export live verification

1. Deploy the Cloudflare Worker and Container from this PR, then update
   Surfside Tools WordPress plugin.
2. Open an already analyzed sermon. Confirm **Approved Clips** initially shows
   the number of approved reviews.
3. Approve one clip and leave another as **Saved**; only the approved one
   should be included in the export.
4. Click **Export approved clips** and wait for `ready`, then download
   the MP4 and confirm the player starts and ends at the saved timestamps.
5. Refresh the page and use **Refresh download links**. The same exported
   private MP4 should be available without re-rendering.
6. Reject the previously approved clip; confirm the old download link
   disappears and the existing WordPress review decision persists.
7. Verify no other weekly MP4/podcast workflow changes and no public
   R2 objects were created.

## PR #525 — Retry recovery for initial export failures

The first live final-export Queue attempt exposed a contract mismatch:
`resolveApprovedExports()` returns `title` alongside `number`, `start`
and `end`, but its strict input parser only accepts the three numeric
fields. When the full response was sent through `PODCAST_QUEUE` and passed
back to the parser, it failed with `invalid_clip_times` before the status
handler's `try/catch`. Some jobs remained in `queued` even after retries.

This fix sends only `number/start/end` in newly queued messages, strips
only known display-title metadata from legacy queued messages, and still
validates every chosen time against the original AI suggestion on the
consumer. All validation errors now save `failed` so a manual retry is
possible. A job left **queued for more than 20 minutes** displays as
`stalled` and can be requeued from the existing **Export approved clips**
button. **Processing** jobs are never auto-marked stalled because FFmpeg
could still be rendering.

After PR #525 is merged and the Cloudflare Worker is deployed:

1. Return to the same previously reviewed November 15 sermon.
2. Confirm the saved Approve/Reject decisions and trims still appear.
3. If the old export is `stalled` or `failed`, click **Export approved clips**
   once more. Do not re-analyze or regenerate the previews.
4. The job should progress from queued → processing → ready.
5. Click **Download MP4** and verify the export uses your approved trim
   boundaries without the extra preview padding.

**No WordPress plugin redeploy is required for this server-side fix unless
the site needs the updated stalled-job help text.** The normal sermon/podcast,
Spreaker publishing, and original R2 MP4 flows are untouched.

## PR #526 — Automatic weekly preparation and faster final export

### Normal weekly workflow

The successful **production** podcast Queue job now schedules private AI
analysis **automatically** after the podcast MP3 has been stored. This runs
in the **existing background Cloudflare Queue** and is deliberately best-effort
so AI errors cannot block the podcast or Spreaker publication. The process
reuses the sermon-only MP3 sidecar when available; historical records still
use the existing one-time sidecar backfill.

When the AI result is ready, a subsequent Queue job **automatically renders
up to five private MP4 previews** with 5-second editable handles on either
side of each selected clip. Both phases use the existing Queue with
single-consumer concurrency and private R2 storage, so there are no concurrent
FFmpeg Container requests fighting for the same instance. Staff can simply
open **Messages & Media → Sermon Highlights** later and review the ready videos.

The pipeline does **not** automatically approve, export, publicly share, or
post anything. Existing saved review decisions and previous exports persist.
Test-mode podcast jobs **do not** schedule AI/highlight analysis, preventing
unexpected charges from regular audio test runs.

### Manual preparation and retries

The existing **Analyze / Prepare now** button remains available. It calls
the signed `/highlights/jobs` Worker endpoint, now an idempotent preparation
orchestrator:

- No analysis result: queue AI analysis. Its Queue success queues padded clips.
- Existing AI result and missing/failed previews: queue only the previews,
  reusing the saved transcription and AI recommendations.
- Existing AI result and ready matching previews: return ready **without**
  re-transcribing or re-rendering any video.
- In-flight matching jobs: return existing status, not a duplicate job.

The old **Generate video previews** action remains as a manual fallback.
Only known, verified sermon video IDs from the staff interface are accepted.
No Cloudflare secrets are exposed to the browser.

### Optimized exact-clip export

Final approved video export now preferentially uses the already generated
**short padded preview MP4s as FFmpeg inputs**, cutting with time offsets
relative to each preview, rather than downloading and transcoding the full
sermon MP4 again.

The Worker reads the stored private preview-job manifest and checks:
matching number, preview start at/before approved start, preview end
at/after approved end, valid bounded time fields and R2 object existence.
The Container derives the R2 key using the verified timestamps; no arbitrary
URL, path or public bucket access is possible. It downloads only each short
matching preview MP4, re-encodes the exact approved cut, then writes a
private immutable final export. **If a valid matching preview is absent,
that clip falls back to the original sermon MP4**, preserving compatibility
for older or manually generated results. A batch with all matching previews
does not download the source sermon at all.

The final output is H.264/AAC MP4, using the original source-time timestamps
for final filenames. Exact trims still require manual approval. All podcast
MP3, source video, Spreaker and public site routes remain unchanged.

### Deployment and acceptance

1. Merge PR #526 after the podcast FFmpeg tests and WordPress plugin build pass.
2. Deploy the **Cloudflare Worker and Container** from the merged commit.
   Deploy the updated Surfside Tools plugin to show the revised dashboard
   descriptions. No new credentials, database tables or Worker bindings.
3. For **existing** November 15 or older test sermons: use
   **Analyze / Prepare now** if necessary. If the analysis and previews are
   ready, this must return quickly without a second AI run.
4. Test on the **next weekly uploaded sermon**. After its normal podcast has
   successfully processed, visit Sermon Highlights and verify analysis and
   five video previews are already ready (or show independent progress).
5. Approve a clip and export it; confirm the final MP4 matches its trimmed
   video/audio boundaries. Cloudflare logs should indicate how many exports
   used `previews` versus `original_fallbacks`.
6. Test the existing production podcast/Spreaker path and a test-mode audio
   job: the latter must not trigger highlight AI/video processing.

**Cost:** This makes one AI analysis and video preview batch automatic for
each successful production sermon upload. Jobs are idempotent to prevent
accidental repeated inference/transcoding. It does consume Cloudflare Workers
AI, R2 and Container resources; the account's billing and usage limits
remain applicable. No OpenAI API key is used.

## PR #527 — Find more suggestions (manual second-pass AI search)

Some sermons, including the November 1, 2026 **What We Have Learned**
sermon, return only one or two initial candidate clips, and staff may find
none of them editorially useful. **Find more suggestions** now provides a
manual, private, transcript-only alternative search rather than repeating
the same full analysis.

### Staff workflow

1. Open **Staff Dashboard → Messages & Media → Sermon Highlights** and
   select any analyzed sermon (e.g. November 1).
2. Preview the initial suggestions; reject or save those that are unusable.
3. Click **Find more suggestions**. The dashboard displays **queued** and
   **processing** progress and checks the existing signed staff status route.
4. The existing **Whisper transcript** is reused in R2; only the Llama
   candidate search runs again, with explicit instructions to avoid earlier
   suggestions, overlap, announcements, fragmented phrases, and weak context.
5. If new quality-qualified candidates exist, up to **three** are appended
   with new stable numbers (up to ten total). The originals and all WordPress
   per-number review options remain unchanged.
6. The original/private MP4 previews remain accessible. Only the new
   suggestions are queued for new five-second-padded preview rendering;
   the ready manifest combines old and new private video links.
7. Review and approve any additional suggestions normally, then **Export
   approved clips**. Exact cuts and private downloads continue to work for
   original and additional clip numbers.
8. If there are no suitable new candidates, the dashboard reports zero
   additions instead of inventing weak suggestions. The action can be
   retried up to four searches per sermon, with a maximum of ten suggestions
   overall. This is intentional manual AI use with small additional costs.

### Architecture and safety

- Staff POST `mode=find-more` follows the existing **dashboard-scoped
  nonce + Weekly Media permissions**. Only WordPress signs the Worker
  request to `/highlights/more/jobs`. No public admin action, upload, or
  embedded HMAC secret.
- The Worker requires an existing, completed analysis and R2 transcript.
  It queues `kind=highlight_more` with `base_count` and `round`.
- Queue processing reads R2 `sermon-highlights/transcripts/<id>.json`;
  it never calls Whisper or re-extracts sermon audio. Llama evaluates
  timestamped windows, excludes overlap and similar titles, requires
  a **4/5-or-better AI score**, and chooses up to three alternative
  standalone excerpts. Original AI suggestions retain their numbers and
  timestamp ranges. New candidates are appended in the private results
  JSON only after validation.
- R2 `sermon-highlights/more-jobs/<id>.json` tracks progress independently
  of the existing initial analysis and clip-preview jobs. Repeated clicks
  while queued/processing return the current job; Queue retries reuse any
  already persisted results instead of duplicating AI calls.
- Preview jobs remain bounded to **five renders per batch** and aggregate
  existing validated private clips up to ten across multiple batches.
  Each private clip/download URL is still HMAC-signed, scoped to one
  verified R2 object and limited to ten minutes. Original podcast,
  Spreaker, R2 sermon and livestream flows are untouched.
- **Analyze / Prepare now** and manual **Generate video previews** can
  prepare only missing additional previews without repeating AI
  transcription, preserving previously rendered original videos.

### Verification

1. With November 1 selected, note the initial two clips and any saved
   decisions.
2. Click **Find more suggestions**; verify queued → processing → ready.
3. Confirm existing clips #1/#2 retain their titles/times and new clips
   get numbers #3 and onward (if Llama finds suitable alternatives).
4. Confirm up to three new playable previews are automatically generated,
   while both original preview videos remain available.
5. Reject/approve and refresh. Check persisted WordPress reviews and
   downloaded MP4 exports still use their exact adjusted times.
6. Click the new button again only if necessary; it must not rerun Whisper
   or overwrite previous results.
7. If the AI finds no stronger moments, the dashboard should explicitly
   say so; it must never force five low-quality selections.

## PR #528 — Experimental moving-speaker vertical phone export

This is a **manual opt-in pilot**, not automatic weekly social-media output.
The existing landscape previews and exact approved MP4 exports remain unchanged.

### Staff test using Nov 15 clip #4

1. Merge and deploy the **Cloudflare Worker + Container** from this PR.
   Container deployment now includes Debian OpenCV, a bundled traditional
   face detector, and a local Python subject tracker. No new API keys,
   paid detection provider, external inference call, or WordPress database
   tables are required.
2. Deploy the Surfside Tools WordPress plugin.
3. Open **Messages & Media → Sermon Highlights**, choose the **November 15**
   sermon, and verify clip **#4 Celebrating Faith Steps** is *Approved*
   with the correct saved start/end times. Approve it if necessary.
4. Under **Vertical Phone Clip (experimental)**, choose approved clip #4.
   Move **Pastor starts (left ↔ right)** toward his starting position
   (0% left edge, 50% center, 100% right edge).
5. Click **Generate vertical test**. It queues a single opt-in Container
   job. The result is not published anywhere.
6. When ready, play the 9:16 preview to inspect whether the pastor stays
   in frame throughout, then optionally **Download vertical MP4**.
   The links are valid for 10 minutes; the dashboard provides
   **Refresh vertical links** without regenerating the video.
7. If tracking follows the wrong person, try another starting position and
   generate another test. If tracking fails often, retain the normal
   landscape video rather than treating this beta as a final deliverable.

### Algorithm and practical limitations

- The Container reads the original **source MP4** (rather than a lower
  resolution 720p preview), accurately extracts only the approved time
  range, then samples the short excerpt at **2 Hz**. Nothing changes the
  input sermon or audio/podcast files.
- CPU-only local OpenCV **frontal and profile face cascades** locate
  candidates. The staff's horizontal starting position and distance to
  the previously tracked face help maintain continuity. Lucas–Kanade
  optical flow bridges up to approximately four seconds when the pastor
  turns his head. The tracker generates smoothed horizontal crop
  movements (8 updates/sec) with limited panning velocity.
- FFmpeg follows the time-indexed crop positions and renders **720×1280
  H.264/AAC**, preserving the original sermon audio. This format is 9:16,
  appropriate for phones, Reels and Shorts.
- It is **not facial recognition** and does **not prove who the pastor is**.
  Multi-person shots, tiny faces, quick cuts, and severe profile angles
  can confuse a lightweight detector. When detection coverage is too
  low, it instead renders the **entire wide shot over a blurred vertical
  background** to avoid completely cropping people out. The dashboard
  explicitly describes that fallback.
- CPU tracking, extracting from the original MP4, and a full portrait
  encode can be slower and incur additional **Cloudflare Container/R2
  usage**. Only staff-requested clips are processed; do not invoke this
  for every sermon automatically while evaluating cost/quality.

### Security boundaries

- The **vertical** dashboard POST accepts only a number and bounded
  horizontal anchor. WordPress reads the exact persisted approved trim
  from its private review option; the browser cannot override times.
- The signed Worker checks the chosen range against immutable AI
  suggestion limits (±5 seconds, 15–120 seconds) before Queue or Container
  work. A changed/rejected review no longer exposes the old vertical
  playback/download URLs through WordPress.
- R2 object names are deterministic and immutable:
  `sermon-highlights/vertical/<video-id>/<number>-<startcs>-<endcs>-a<anchor>.mp4`.
  Container storage access is PUT-only for that strict key namespace.
  Browser preview and attachment downloads use independently signed,
  10-minute, clip-specific HMAC URLs; private R2 access is never public.
- Queue status is saved under
  `sermon-highlights/vertical-jobs/<video-id>.json`, separate from podcast,
  AI selection, previews and normal approved landscape exports.

### Regression checks

The podcast test workflow installs ffmpeg, python3-opencv and opencv-data.
It checks the tracker Python syntax, safe no-face behavior, panning
interpolation, a synthetic actual MP4, H.264/AAC portrait FFmpeg generation,
R2 private key restrictions, signed URL tamper/expiration and staff-only
WordPress approval/nonce controls.

This is intentionally an **experiment**. A more reliable person
detector/tracker can replace the cascade after real on-stage clips show
where it fails. Do not promise perfect tracking before the November 15
pilot is reviewed.

## PR #529 — Visual pastor starting point + actionable portrait failures

On mobile, the original `Pastor starts (left ↔ right)` percentage slider
had no visual context; staff could not tell whether 50% was actually on the
pastor. The vertical dashboard now displays the **already-generated, private
landscape MP4 preview paused at the approved start time**. Staff tap/click
the pastor in that video frame. A visible blue marker tracks the chosen
horizontal location; the range slider remains below for keyboard users
and fine adjustment. The same approved timestamps and bounded 0–100 integer
starting anchor continue to be sent to the WordPress+Cloudflare API; the
private preview never makes the original sermon public.

The focus still is available only when a matching padded MP4 preview exists
and its expiring private link is valid. Use **Refresh preview links** if the
video frame cannot load. On mobile browsers that do not display a seeked
frame until a user gesture, tapping the frame unlocks muted playback and
pauses at the approved start. This change does **not** introduce an identity
recognition model; it only makes the existing initial position usable.

The first live vertical job showed `Vertical generation failed` with no
stage indication. This PR does **not** claim to have identified its root
cause from the screenshot alone. It adds bounded stage diagnostics:
`source` (original MP4 download), `extraction` (approved MP4 cut),
`tracking` (offline OpenCV), `encoding` (9:16 H.264/AAC render), and
`upload` (private R2). Container error details remain in Cloudflare logs;
the Worker only records the safe failure stage in the vertical job status,
and the dashboard describes it in plain language. Podcast and all
existing landscape highlight processing keep their original error paths.

After merge and Worker+Container deployment, staff can retry clip #4,
view the failure stage if it still fails, and capture the **Podcast
container job failed** / **Experimental vertical pipeline failed** Cloudflare
Observability event corresponding to the request. On the frontend,
the focus picker is mobile-friendly and no longer asks staff to guess
horizontal percentages without showing the source frame.

## PR #530 — Surfside staff action redirects and session-expiry guidance

The first phone trial of the tap-to-select portrait focus successfully displayed
the source MP4 and a marker on the pastor. On **Generate vertical test**, however,
the browser displayed `The staff authorization request failed. Check your login
and connection.`

Code review confirms this message came from the browser `fetch()` **throwing**
before an HTTP/JSON response was available. It is **not** evidence of a failed
FFmpeg/Cloudflare portrait render. The existing AJAX transport used
`redirect: "error"`, which throws on any redirect (including an expired
staff login or canonical host/path rewrite) and incorrectly labels it
an authorization/network problem.

WordPress and Firebase custom Surfside staff sessions are currently created
with a **maximum one-hour expiration**. Because the browser page and private
preview video can remain visible after a session expires, staff may still see
the form even though subsequent POSTs cannot authorize.

This fix:

- Posts to the **actual canonical staff page currently displayed in the browser**
  when it is `/dashboard/site-management/?view=sermon-highlights`, instead of
  relying on a possibly redirected WordPress configured URL.
- Uses `redirect: "manual"` (never follows a potential login/cross-origin
  redirect carrying request parameters). An opaque or HTTP redirect now shows
  a clear **reload/sign in again** instruction.
- Distinguishes transport/network failures from login redirects, invalid
  JSON/HTML login pages, structured WordPress 403/nonce-expiry errors, and
  Worker queue failures.
- Keeps the existing session lifetime, nonce, staff permissions, HTTPS,
  `same-origin` credentials, and server-side HMAC. No new public API,
  secrets, background network polling, or session bypass.
- Does **not** retry or duplicate portrait Queue requests automatically:
  only a fresh staff click can enqueue work.
- Adds a guard in the staff browser tests that canonical page POSTs never
  follow redirects and still preserve same-origin credentials.

### How to verify

1. Deploy the updated Surfside Tools plugin. This change is frontend-only
   and requires **no Cloudflare redeployment**.
2. On a previously open Sermon Highlights page, refresh and sign in again
   if asked. The approved clips and private R2 previews remain saved.
3. Choose Nov 15 #4, select the pastor on the paused frame, and click
   **Generate vertical test** once.
4. If the request redirects, the UI should explicitly request a reload/sign-in
   instead of the generic authorization/network error. If an actual Worker
   render fails, the prior PR's safe **source / extraction / tracking /
   encoding / upload** stage is displayed separately after the job completes.
5. Preserve any Cloudflare Observability errors if generation proceeds but
   fails, because this PR improves the request transport, not the unverified
   FFmpeg/OpenCV failure from the earlier screenshot.

## PR #531 — Recover from portrait speaker-tracking failures

The first live November 15 clip #4 vertical export reached the **tracking**
stage and reported **"Vertical generation failed while detecting and following
the speaker"**. The source MP4 download and approved segment extraction
completed. The dashboard's safe stage code does not expose the actual Python
error or prove the tracker exceeded its timeout; that exact reason remains in
Cloudflare's private Container observability log.

This change addresses two specific weaknesses in the original beta:

1. **Stop repeatedly seeking full-resolution H.264 video during tracking.**
   FFmpeg creates a temporary 480px-wide, **2-frame-per-second** private
   local tracking proxy from the already-extracted short clip. Python reads
   its frames sequentially, tracks face/feature positions, and scales the
   resulting horizontal positions back to the **original source dimensions**
   before FFmpeg applies smoothed movement. The 720×1280 final portrait is
   **still encoded from the original-quality approved segment**, never from
   the low-resolution tracking proxy. No frames leave the Container.
2. **Graceful and explicit safe fallback.** If OpenCV/Python cannot track
   within a 120-second bounded step, the job logs the private Python/FFmpeg
   failure and renders the full landscape scene inside a blurred-background
   phone video, rather than losing the whole export. The dashboard clearly
   reports `tracking_unavailable` versus `insufficient_face_detections`.
   It never claims to follow the pastor if automatic detection failed.
   The Queue can still retry if the final FFmpeg encode/upload fails.

Other safeguards: clip approval, exact cut boundaries, staff nonce,
signed private download URLs, and WordPress-only manual opt-in behavior
are unchanged. No additional Workers AI model, identity recognition,
external CV API, or social-media posting.

### Retest

1. Merge and deploy **Cloudflare Worker and Container**, then update the
   Surfside Tools plugin for the new fallback message.
2. Reopen November 15, approved clip #4. Tap the pastor in the frame.
3. Click **Generate vertical test** once. Previously failed jobs are
   retryable. The proxy stage now has bounded work, and should progress
   to **Ready** if the final FFmpeg encoding and R2 upload succeed.
4. Inspect the full 9:16 result. If it says **tracked**, evaluate whether
   the pastor stays in frame while walking. If it says **wide fallback**,
   confirm no speaker was cropped off, then inspect the matching Cloudflare
   `Private vertical tracking unavailable` log for the exact detector error.
5. Do not switch this on for every weekly sermon until we have watched
   multiple real clips and measured Container compute and tracking quality.

Validation includes actual ffmpeg 2fps proxy production, bounded original
coordinate mapping, offline OpenCV synthetic-video tests, and existing
portrait H.264/AAC + signed/download/privacy regression checks.
