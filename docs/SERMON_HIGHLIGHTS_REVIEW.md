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

**Approve** is an internal editorial marker only. None of these controls
publishes, exports, captions, reframes, uploads to social media, or modifies
the original R2 sermon/podcast. The actual final, precisely trimmed export
belongs to a later PR.

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
- The dashboard action dispatcher accepts only six supported review modes
  (`sermons`, `analyze`, `status`, `previews`, `preview-status`, `review`).
  WordPress forwards only four explicitly approved highlights Worker routes.
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

All application behavior remains a manual pilot until production validation;
there is no automatic share/export pipeline.
