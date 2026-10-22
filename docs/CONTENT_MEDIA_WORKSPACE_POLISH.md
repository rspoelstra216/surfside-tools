# Phase 3D — Content & Media Staff UI Polish

## Scope

One moderately sized review PR for three related content/publishing screens.
All changes are to **presentation**, without altering publication pipelines,
sermon database records, YouTube disposition rules, or the mobile API.

### Featured Announcement (mobile app Home)

- Replace the ambiguous `Scheduled or expired` state with a truthful saved
  status: Not enabled, Currently showing, Scheduled to start, Expired, or
  a needs-attention state for missing headline/end date or invalid window.
- Display saved start/end times, explicitly noting the site time zone.
- Place a **Saved content preview** next to the form on desktop, below it
  on narrower screens. The preview displays escaped saved text only
  and is labeled **Not a live app view**.
- Clarify that enabling a feature with a blank Start Showing time can
  publish it to the app immediately upon saving.
- Retain all POST fields, nonce, capability guard, end-time validation,
  JavaScript dynamic required date field and existing API projection.

### Archived Sermon Notes

- Distinguish written notes preserved by Weekly Update from YouTube video
  archive management. Provide shortcuts to both related screens.
- Improve list count, search term display, no-results action and details
  readability. Clearly mark detail pages read-only.
- Retain exact archive SQL queries, search sanitization, pagination,
  record rendering and privacy guard.

### Message Archive Manager (YouTube)

- Explain that imported YouTube videos and sermon series are managed
  separately from written sermon notes.
- Make the **Needs Review**, **Missing Sermon Dates**, and **Excluded Videos**
  counters link directly to their already-supported filtered queues.
- Reinforce that Needs Review videos remain public; only Excluded hides
  an individual video. Series hiding is a separate scope.
- Improve filter-result status, empty results and Review Video links.
- Retain all review form fields, POST targets, queue/pagination parameters,
  manual dates, disposition rules, YouTube sync and public archive feeds.

### Shared presentation

- `assets/css/content-media-workspace.css` styles these three screens only.
- It's enqueued by the authenticated staff workspace asset loader for
  `dashboard/?view=sermon-archive`,
  `dashboard/site-management/?view=message-archive`, and
  `dashboard/mobile-app/featured-announcement/`.
- Version is based on file content, avoiding stale cache without changing
  public frontend assets.

## Tests

- Existing `scripts/test-sermon-archive-viewer.php` now checks read-only
  status, staff navigation and result/filter messaging.
- Existing `scripts/test-youtube-message-manager.php` checks distinction
  from archived notes and public visibility guidance. Its previous
  form/queue/nonce tests remain intact.
- New `scripts/test-content-media-workspace.php` covers featured Home
  status states, escaped preview, saved-only content, original POST fields,
  successful save, access/privacy enforcement and staff-only asset scoping.
- All are part of the full GitHub Actions validation workflow.

## Manual QA after merging/deploying

1. Visit **Featured Announcement** from staff dashboard on desktop and
   phone. Check Not enabled, Scheduled and active/expired saved statuses
   against current real settings. Do not toggle live production content
   just to test labels.
2. Confirm the saved preview displays the configured headline, body and
   button label. It should not claim to be a pixel-exact app rendering.
3. Confirm the existing date inputs, toggle, optional button fields and
   Save action look clean without horizontal overflow.
4. Open **Archived Sermon Notes**. Search for a known published title or
   note phrase. Open a record, return to search results and use the new
   shortcut to the YouTube Video Archive Manager.
5. In **Message Archive Manager**, use a Review counter to filter,
   check status tabs, search and an individual Review Video page.
   Confirm the existing Save Review / Save & Next behavior and filtered
   back link. Avoid excluding production videos solely for a smoke test.
6. Confirm the mobile app's featured Home card, public YouTube Messages
   archive and permanent written sermon notes are unchanged.
7. Check 375px mobile width for accessible labels and no horizontal
   scrolling across all three screens.

## Explicitly out of scope

Cloudflare R2 uploads, Roku compatibility, YouTube synchronization, new
media storage, prayer/list management, changes to calendar event logic,
new settings fields, new public pages, and any scheduled publishing rules.
