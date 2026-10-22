# Calendar Workspace — Phase 2B

The Calendar Manager is now the home for event editing, calendar defaults and
saved-location management. All three use the original /dashboard/calendar/
page; the latter two are query-routed views, not new WordPress pages.

## Layout

- Manage Events (original): /dashboard/calendar/
- Calendar Defaults (admin): /dashboard/calendar/?view=defaults
- Saved Places (admin): /dashboard/calendar/?view=places

The Events & Serving navigation and global tool search link to these views.
Calendar Defaults and Saved Places are hidden from non-admin staff, while the
existing Manage Events capability remains upload_files.

## Existing data and behavior

Calendar Defaults saves this_week_mode (next 7 days or Sunday–Saturday) and
default_event_duration (15–480 minutes) in the existing
surfside_tools_settings option. It merges changes into existing settings,
preserving the Google Maps key and any other keys. Google Maps remains under
Integrations; its Save Integrations form now updates only google_maps_api_key
and retains any Calendar setting in the same option. No migration is needed.

Saved Places continues to read the canonical saved-location records plus
calendar-derived previous places. The three management actions remain the
same functions and POST action names as before: delete_saved_place,
hide_calendar_place, restore_calendar_place. Each requires manage_options
and the existing surfside_front_settings nonce. Hiding or deleting a saved
place does not change previously created calendar event records.

The old Integrations Calendar Defaults and Saved Places accordions remain
as links to the new Calendar views. Deep links ending in
#surfside-focus-calendar-defaults and #surfside-focus-saved-places still
open a relevant accordion directing staff to the correct new location.

The existing Calendar Manager editor, form handlers, event API, event search,
recurrence model, Google Places integration and public calendar remain
unchanged. Calendar views are selected by an allowlisted view query
parameter; no new page provisioning or redirects are required.

## Test before merge

1. On desktop and phone, visit /dashboard/calendar. Verify the three tabs
   for admins and the existing event editor. Navigate among tabs and verify
   the Tools & Search breadcrumbs correctly identify each location.
2. Calendar Defaults: confirm currently saved week display and duration.
   Make a reversible change, save, reload, and verify persistence. Confirm
   existing calendar events remain unchanged.
3. Saved Places: verify saved and previously used locations are shown.
   Remove a test/safe location, hide a previously used suggestion, and
   restore it under Removed suggestions. Check existing event records.
4. Integrations: Google Maps still shows its existing key (do not publish
   or share keys). Save and verify the Calendar Defaults are unchanged.
5. Use the old Integrations #surfside-focus-calendar-defaults and
   #surfside-focus-saved-places bookmarks and follow their new links.
6. As non-admin staff, verify Manage Events works but neither restricted
   tab appears in tool search or the tab navigation, and a direct URL
   to a restricted view refuses access.
7. Verify no changes to calendar public pages, mobile calendar data,
   Weekly Update location suggestions and recurring events.

## Automated regressions

- scripts/test-calendar-workspace.php exercises allowlisted view selection,
  task navigation tabs, map-vs-calendar save isolation, POST nonce and
  permission enforcement, and the unchanged Saved Places action handlers.
- scripts/test-staff-workspace.php verifies global tool index URLs, active
  breadcrumbs and role-based visibility.
- Existing suite also runs without modifications to event storage or APIs.

## Follow-up

Phase 2C may simplify Integrations further and refine individual management
screens. Keep new changes in new PRs per PROJECT_PR_WORKFLOW.
