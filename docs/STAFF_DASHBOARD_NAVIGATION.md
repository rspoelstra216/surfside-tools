# Staff Dashboard — Navigation Refresh, Phase 1

This PR introduces task-based navigation and local tool search across the
existing front-end staff dashboard (/dashboard and its child pages). It does
not move, rename, migrate or replace the editors.

## Navigation

- Content & Publishing: Weekly Update, Archived Sermon Notes, Featured
  Announcement, Ministries (admin).
- Messages & Media: Message Archive, Sermon Series, Roku MP4 Uploads,
  Streaming Management (admin), Watch Live settings (admin), Live Slides.
- Events & Serving: Calendar Manager, Volunteer Needs, Saved Places (admin),
  Calendar Defaults (admin).
- Community & Communications: Prayer Requests, Push Notifications,
  Contact Routing (admin).
- Appearance: Website Navigation (admin), Homepage Photos, App Home
  Experience, Reveal & Countdown Styling (admin).
- Settings & Access: Surfside Information (admin), Integrations (admin)
  and specific integrations, Staff Access (Tools admin).

The same capability-filtered list populates the sidebar, tool search and
dashboard management-area cards. Backend permission checks remain unchanged.

## Usage

Desktop: the sidebar stays on screen and scrolls independently; enter keywords
such as prayer, Roku, YouTube or calendar to filter its links.
Mobile/tablet: Tools & Search opens a drawer containing the same full index.
Keyboard: Ctrl+K (Cmd+K on macOS) focuses search; Escape closes the mobile
drawer; Arrow Down from search selects the first visible matching link.
Breadcrumbs show Dashboard › Category › Tool.

Search filters only pre-rendered navigation links authorized for the current
user. It performs no API calls, no R2/YouTube requests and no database content
searches. It searches tool names and related words, not individual sermons,
prayer requests or calendar records.

## Backward compatibility

Existing URLs remain stable, including:
- /dashboard/?view=sermon-archive
- /dashboard/?view=member-engagement&tool=prayer-requests
- /dashboard/?view=member-engagement&tool=volunteer-needs
- /dashboard/site-management/?view=message-archive
- /dashboard/site-management/?view=message-archive&tab=series
- /dashboard/site-management/?view=roku-media

Existing /dashboard/mobile-app/* and /dashboard/site-settings/*,
homepage, calendar, navigation and staff-access pages are unchanged.
Integrations search results link to the existing Settings page with a
fragment like #surfside-focus-youversion. The client-side script opens the
matching accordion and scrolls to it.

The new workspace wrapper is applied after WordPress renders the main
page shortcode (the_content priority 99), only on authenticated staff
dashboard pages and in the main post loop. Public pages, WordPress admin,
secondary post queries and API routes retain original behavior.

## Scope

Phase 1 changes only navigation, layout shell, breadcrumbs and home cards.
Older platform-specific hub pages remain live to preserve bookmarks and
existing links. Consolidating settings and editors is a separate follow-up
after this foundation is tested. No database schema, form handlers,
authentication, stored content or page-provisioning hierarchy changes.

## Manual smoke tests

1. Open /dashboard as Tools admin on desktop; sidebar and grouped cards
   should render alongside the existing Weekly Update and Calendar status.
2. Search Roku to open Roku MP4 Uploads; search prayer for Prayer Requests.
3. Visit Weekly Update, Calendar, Message Archive (Videos and Series),
   Roku Copies, App Home, Live Slides and Integrations. Check breadcrumbs
   and that original editors still work. Do not re-upload a verified MP4.
4. Check Ctrl+K/Cmd+K, empty results, Arrow Down, Escape and keyboard focus.
5. Test Tools & Search on a phone, including open/close, search and scrolling.
6. Find YouVersion, Google Maps and Calendar Defaults; test the automatic
   expansion of each matching Integrations accordion.
7. Log in as ordinary Tools staff: admin-only items must be absent.
8. Follow existing prayer-review email links and bookmarked management URLs.
9. Confirm that public website pages and wp-admin are not wrapped and that
   public Roku, website, mobile, streaming and calendar APIs are unaffected.

Automated regression test covers routing, staff capability filtering and
the scope of the new WordPress content hooks. CI checks JavaScript syntax.
