# Phase 3B — Calendar Manager screen polish

## Context

Calendar Manager was consolidated with Calendar Defaults and Saved Places in
Phase 2B. This PR polishes **Manage Events** without changing event storage
or the create/edit/delete/recurrence workflows.

On desktop, the event editor and event list retain the familiar two-column
layout. On phones and tablets, staff previously had to scroll through the
entire event creation form before they could find an existing event.

## Improvements

- Replace the generic status message with a short summary, a truthful
  **active/matching events** count and direct **Browse events / Add an
  event** shortcuts.
- Give the long editor and the event list distinct headings and stable
  fragment IDs:
  - `#surfside-calendar-event-editor`
  - `#surfside-calendar-event-list`
- On screens under 900px, display the event list before the blank editor.
  When following an event's Edit link, leave the editor first instead.
- Event list Edit links now target the existing editor's anchor, avoiding
  scrolling manually through the page.
- Keep the current search URL and backend filters. Display the result count,
  the search term, and useful empty-state actions.
- Improve the readability of form inputs, recurrence settings, event rows
  and Recently Past items. Apply full-width submit buttons on small phones.
- Give Edit/Skip next/Delete actions adequate touch targets and stronger
  keyboard focus indicators. Delete remains visibly distinct.
- Add event-specific accessible action labels (for example, "Delete Senior
  Potluck Luncheon") without changing the visible words or POST handlers.
- All new styling is scoped under `.surfside-calendar-workspace` and lives
  in the existing content-versioned `assets/css/calendar-workspace.css`.
  No changes to public calendar widgets or event listings.

## Unchanged behavior

- Event metadata, database schema, title/date validation and edit/delete
  actions are the same.
- Recurrence, multi-day events, individual occurrence skipping/restoring,
  Google Places search, Saved Places and Calendar Defaults are unchanged.
- Staff authorization, `surfside_calendar_manager` nonce and all POST
  action names are unchanged.
- Old URLs, `?edit_event=...` deep links, search and pagination continue
  to work.
- No JavaScript navigation dependencies, new database tables, or migrations.

## Manual QA before merge

1. Open **Dashboard → Calendar Manager → Manage Events** on desktop.
   Verify the editor and event list remain side by side, with new
   Browse Events and Add an Event shortcuts and accurate event count.
2. On a phone, confirm the list appears above the blank Add Event form.
   Tap Add an Event at the top; the page scrolls to the editor.
3. Tap Edit on an existing event. The editor should be the first column
   when stacked on mobile, prefilled with that event's saved data.
4. Search for an existing event title, verify the matching count and Edit
   link, and clear the search.
5. Search for nonsense. Confirm the empty state offers Show all events.
6. Verify recurrence options, Google Places, multi-day event dates,
   feature-on-home/ministry checkboxes, and Save Changes work unchanged.
7. For a recurring event, confirm Skip Next still prompts confirmation.
   Confirm Delete remains visibly distinct and still prompts before
   trashing an event; avoid deleting production events while smoke-testing.
8. Confirm Calendar Defaults and Saved Places tabs still work. Public
   website and mobile calendar data should be unaffected.
9. At a ~375px mobile width, verify no horizontal scrolling, readable
   date/time inputs and tap-friendly buttons.

## Regression tests

- `php scripts/test-calendar-manager-layout.php` renders the actual
  Calendar Manager with fake WordPress events and verifies the list,
  add/edit anchors, search result states, actions, nonce, and permission
  gate. Source-level checks verify the mobile ordering and focus rules.
- Existing `scripts/test-calendar-workspace.php`, calendar recurrence
  and all other plugin regression tests remain in GitHub Actions.

Phase 3B is a layout and readability update, not an event workflow rewrite.
