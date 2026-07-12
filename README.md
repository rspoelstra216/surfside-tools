# Surfside Tools

Custom WordPress tools for Surfside Community Fellowship.

## Repository structure

The repository root is the WordPress plugin root. The main plugin file is `surfside-tools.php`, and supporting modules are stored in `includes/`.

## Version 1.2.0-dev15

Development release focused on making Surfside Tools the event source of truth and improving public event display.

### Included

- Weekly Update DOCX importer
- Announcements parser
- Sermon notes parser
- Unified weekly update workflow
- Front-end staff dashboard pages
- Calendar Manager local event source
- Public event display shortcodes

### Calendar Manager changes in 1.2.0-dev13

- Keeps events managed directly in Surfside Tools/WordPress.
- Adds featured event support.
- Improves public event cards.
- Groups public event output by month by default.
- Adds a cleaner empty state when there are no upcoming events.
- Keeps past events hidden from public output automatically.

### Shortcodes

- `[surfside_weekly_update]`
- `[surfside_tools_announcements]`
- `[surfside_tools_message]`
- `[surfside_staff_dashboard]`
- `[surfside_staff_weekly_update]`
- `[surfside_staff_calendar]`
- `[surfside_tools_upcoming_events]`
- `[surfside_tools_calendar]`
- `[surfside_events]`

### Staff URLs

- `/dashboard`
- `/dashboard/weekly-update`
- `/dashboard/calendar`

### Event display examples

- `[surfside_tools_upcoming_events]`
- `[surfside_tools_upcoming_events limit="6"]`
- `[surfside_tools_upcoming_events show_description="no"]`
- `[surfside_tools_upcoming_events layout="compact"]`
- `[surfside_tools_upcoming_events group="no"]`

## Version 1.2.0-dev15

- Makes upcoming event cards and This Week event cards clickable.
- Reuses the same event detail modal as the monthly calendar.
- Leaves recurrence and monthly calendar logic unchanged.

## 1.2.0-dev22

- Added reusable Saved Locations.
- Added saved-location search in the event editor.
- Added in-place Create New Location modal.
- Existing event and recurrence behavior remains compatible.

## 1.2.0-dev22

- Enabled the Surfside Tools Settings page.
- Added Google Maps API key storage and browser connection test.
- Added This Week display mode and default event duration settings.

### Calendar Manager changes in 1.2.0-dev24

- Replaced the developer-facing shortcode instructions at the top of Calendar Manager with a staff-friendly heading and description.
- No changes to calendar data, recurrence, Google Places, or saved settings.
