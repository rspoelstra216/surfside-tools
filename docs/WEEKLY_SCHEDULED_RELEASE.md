# Weekly Update — Saturday 5 PM release

## Desired workflow

Surfside's Weekly Update upload used to publish announcements and sermon
notes immediately when staff clicked Publish. This made the new notes visible
while Cloudflare's Last Service video continued showing the *previous*
weekend's message until the next service began.

New workflow:
1. Upload the new DOCX announcements and/or sermon notes on any day.
2. Review the extracted fields and click "Schedule for Saturday at 5 PM".
3. The existing documents remain on the public website and mobile API
   through Saturday afternoon.
4. On **Saturday at 5:00 PM Eastern**, the staged documents replace the
   public ones. A modest mismatch with Cloudflare Last Service video may
   remain until the upcoming livestream, intentionally: everyone attending
   Saturday service gets the correct announcements and sermon notes.
5. Next week's update can be prepared any time after the release.

The target time is fixed in `America/New_York` (handles EST and EDT). It
does not depend on the timezone configured in a staff member's browser or
on hard-coded UTC offsets.

## Storage, publishing and the archive

Staged documents are stored privately as one WP option:
`surfside_tools_weekly_pending`. It contains a publish timestamp and
optional announcements and sermon sections. No public data is overwritten
when staff schedules content. No DOCX file remains on disk after import.

The established public readers continue to use their usual options:
`surfside_tools_announcements_current` and
`surfside_tools_message_current`. The mobile app's `surfside/v1/app` REST
response reads these same functions. The public site shortcode uses the
same functions. No app release, API format change or database migration
is required.

When the scheduled time arrives, the original publish functions save the
new current documents, rotate their backups, and archive the new sermon.
**Archival runs at publication, never during private staging.** An archive
failure blocks publication of both sections and causes a five-minute
retry. Staff see a visible error if an automatic release needs attention.
A short cross-request lock prevents duplicate cron/API activation.

Scheduling an updated announcements DOCX before the cutoff **replaces only
the staged announcement section**, leaving a previously staged sermon in
place. The reverse also works. The entire pending release can be canceled
without modifying any public documents or the permanent archive.

The old standalone DOCX importers also schedule instead of publishing
immediately, so staff do not accidentally bypass the new workflow. The
advanced "Restore Previous Announcements" action remains an explicit,
immediate emergency restore; it is not a scheduled draft.

## Dashboard

The weekly card no longer treats older documents as stale on Tuesday or on
other weekdays. When an update is pending, it reads **Scheduled** and
displays the planned Saturday publication time. When no update is pending,
it reads **Published** with an explanation that current documents remain
available. A genuine scheduling/archival failure is still shown as an alert.

## Timing and caches

The plugin registers a WordPress single event at the exact Saturday
timestamp. Since normal WP-Cron relies on site traffic, a low-traffic site
may execute the event slightly late. As a second safeguard the public
announcements and message getters attempt due publication before returning
values; this also covers the app API and site visitors.

**For a guaranteed near-5:00-PM release**, set up a real cron job to run
WordPress `wp-cron.php` at least every minute. Make sure public page caching,
an upstream reverse proxy and any CDN do not prevent WordPress cron from
executing. The existing `surfside_tools_purge_cache` is invoked at actual
publication, not at draft scheduling; it clears supported cache plugins.
Cloudflare edge-cache rules and *client-side* app refresh intervals may
still cause a short delay if separately configured.

The new feature does **not** change Cloudflare Stream's Last Service
selection, livestream go-live events, player content or recordings.

## Staff smoke-test checklist

1. Verify last week's docs are visible on website and mobile before any
   new upload.
2. Upload new announcement and sermon DOCX, preview and schedule.
3. Confirm the "Scheduled for Saturday..." box shows the exact Eastern time
   and both staged sections. Check last week's public docs still display.
4. Upload and schedule a corrected announcement DOCX alone. Confirm pending
   sermon is preserved. Check public docs still unchanged.
5. If testing before Saturday, use the test helper in a **nonproduction**
   WordPress environment to advance the clock. Verify both sections become
   public at the cutoff and the sermon enters the archive.
6. Confirm Calendar and site/app public playback haven't changed and the
   Tuesday stale warning no longer appears on the staff dashboard.
7. Test Cancel Scheduled Update with a disposable staged draft; current
   public documents should remain unchanged.
8. If server cron is disabled or archival storage is unavailable, confirm a
   conspicuous error rather than claiming the documents are already live.

## Automated regression

`scripts/test-weekly-schedule.php` verifies Saturday cutoff in EDT and
EST, DST boundary, stage/replace/cancel, auth checks, unchanged public
options before release, one-time archive at release, backup rotation,
cron-registration failure and release retry on archive errors.

Existing parser and sermon-archive tests are unchanged.

## Scope / follow-up

This PR covers publication timing and related staff status. It does not
change the DOCX parsers, sermon matching, Cloudflare recorded video timing,
or mobile app polling. Fine-grained layout polish can be a separate PR.
