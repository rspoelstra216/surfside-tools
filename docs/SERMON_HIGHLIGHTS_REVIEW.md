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
