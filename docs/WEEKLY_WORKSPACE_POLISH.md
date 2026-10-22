# Phase 3A — Weekly Update dashboard usability

## Why

After the new Saturday 5 PM scheduled publishing workflow (#466), the
Weekly Update page included a scheduled notice above the existing upload
form but didn't clearly identify which documents were *currently live*
versus *scheduled for Saturday*. Staff often prepare only one file, and
the secretary should be able to confirm what the website and mobile app
are showing without having to open separate public screens.

## Changes

On the existing `/dashboard/weekly-update/` page:

- A **Currently published** card displays the announcements date (or
  count) and sermon title currently visible to website and mobile visitors.
- A **Next release** card displays the upcoming Saturday at 5 PM Eastern.
  It says "Nothing scheduled yet" until an update is staged.
- A **Scheduled update** card instead shows what's in the private queue,
  and explicitly says "Keep current version" for a section the secretary
  has not staged. A disclosure labeled **Review scheduled documents**
  lets staff inspect the private announcement text and sermon notes.
- The existing **Cancel Scheduled Update** form remains the same, with the
  same nonce and server handler. Genuine release errors display
  "Release needs attention" with details instead of "Ready".
- The two-step workflow is labeled **Upload documents** and **Review &
  schedule**, with the active step indicated visually and semantically.
- Each file input has a visible associated label and `.docx` hint.
- Buttons make the next action more obvious, and a short reminder
  explains that scheduling doesn't immediately publish anything.

The desktop layout shows live and upcoming cards side-by-side. They stack
on phones, with full-width action buttons and sensible input sizing.
Styles apply only to the staff Weekly Update screen and use content-based
asset versioning to prevent stale Chrome layouts.

## Behavior intentionally unchanged

This PR does **not** change:

- Saturday release scheduling, timezone, WP-Cron or the cPanel job.
- The upload/preview/edit/publish handlers and their action names, nonces
  or capabilities.
- Public announcements, sermon notes, app REST data, the permanent sermon
  archive, backup rotation, or the existing Last Service playback.
- The existing advanced emergency restore option.

No new WordPress tables, options, routes or migrations are required.
The queue preview is only rendered after the staff page has enforced
`upload_files`; all queued text is escaped before display.

## Manual QA before merge

1. Open `/dashboard/weekly-update/` as staff on desktop and phone.
   Confirm the new **Currently published** and **Next release** cards
   show last week's public announcements and sermon notes correctly.
2. Upload an announcements DOCX *only*. Confirm the upload/review steps
   display correctly and the file input can be used on a phone.
3. Review the extracted data and schedule. Confirm **Scheduled update**
   replaces **Next release**, and only Announcements is staged while
   Sermon Notes reads **Keep current version**.
4. Open **Review scheduled documents**. Confirm the edited announcement
   text matches what was scheduled. When both documents are staged,
   confirm sermon title and notes can be reviewed privately.
5. Confirm the public website and app still show the previous/current
   documents until Saturday's actual cutoff.
6. Try Cancel Scheduled Update only with a disposable test draft.
   Confirm the public documents remain unchanged.
7. Confirm the old advanced restore action is still available.
8. Check the dashboard navigation and responsiveness at 360–400px wide.
   Inputs and action buttons should not cause horizontal scrolling.

## Automation

The existing `scripts/test-weekly-schedule.php` CI regression now also
checks the live/scheduled summary, secure cancel form, safe escaping,
mobile stylesheet, step navigation and upload fields. It still checks
correct schedule time, daylight saving boundaries, no early publication,
backup/archive semantics and retry behavior.
