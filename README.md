# Surfside Tools

Surfside Tools is a custom WordPress website-management platform built for Surfside Community Fellowship.

It gives church staff clear front-end workflows for weekly publishing, calendar management, homepage photos, locations, and settings without requiring routine access to WordPress administration.

**Current release:** `2.1.0`  
**Current development phase:** Calendar Experience

## Guiding principle

> Routine website maintenance should not require opening WordPress Admin.

Surfside Tools favors simple, reviewable workflows that keep staff in one place, automate repetitive work when confidence is high, and ask for clarification when important information is missing.

## Core features

### Staff Dashboard

- Front-end dashboard for routine website management
- Weekly Update, Calendar, Manage Homepage, and Settings tools
- Actionable website-status summaries and recent activity
- Login and capability protection
- Consistent navigation and front-end workflows

### Weekly Update

- DOCX upload and parsing
- Announcement review and publishing
- Sermon-note review and publishing
- Calendar suggestions generated from announcement text
- Date, time, recurrence, location, and duplicate detection
- One-click and reviewed event creation
- Batch creation, completion tracking, and undo

### Calendar Manager

- One-time and recurring events
- Daily, weekly, and monthly recurrence
- Repeat-until dates
- Separate venue, address, and meeting-location fields
- Google Places and saved locations
- Optional event images selected from the WordPress Media Library
- Active-event management and recently past events
- Public upcoming, weekly, and monthly calendar displays

### Public calendar experience

- Accessible event-detail modals
- Crowded-day details without hiding events
- Printable monthly calendar
- Apple Calendar, Google Calendar, and downloadable event actions
- Event images in standard event details without cluttering compact month cells
- Automatic Today at Surfside summary for service days, today’s events, or the next upcoming event

### Manage Homepage

- Front-end homepage carousel management
- Multiple-image upload
- Replace, remove, and drag-and-drop ordering
- Compact photo gallery
- Automatic cache invalidation
- Full-width responsive public carousel

### Settings and visual utilities

- Front-end Google Maps and calendar settings
- Saved Places management
- Reveal-on-scroll utilities
- Service, compact, and Sunday countdowns
- Editable CSS overrides with built-in CSS reference

## Staff URLs

- `/dashboard`
- `/dashboard/weekly-update`
- `/dashboard/calendar`
- `/dashboard/homepage`
- `/dashboard/settings`

## Public shortcodes

### Weekly content

- `[surfside_weekly_update]`
- `[surfside_tools_announcements]`
- `[surfside_tools_message]`

### Staff tools

- `[surfside_staff_dashboard]`
- `[surfside_staff_weekly_update]`
- `[surfside_staff_calendar]`
- `[surfside_staff_homepage]`
- `[surfside_staff_settings]`

### Calendar and homepage displays

- `[surfside_photo_carousel]`
- `[surfside_tools_upcoming_events]`
- `[surfside_tools_calendar]`
- `[surfside_events]`
- `[surfside_this_week]`
- `[surfside_month_calendar]`
- `[surfside_today]`

`[surfside_today]` supports optional `title`, `events_url`, and `show_link="no"` attributes.

### Visual utilities

- `[surfside_service_countdown]`
- `[surfside_service_countdown_compact]`
- `[surfside_sunday_countdown]`

## Repository structure

The repository root is the WordPress plugin root.

- `surfside-tools.php` — plugin entry point and module loader
- `includes/` — focused functional modules
- `docs/` — detailed project handbook and supporting documentation
- `.github/workflows/` — validation, builds, and release automation
- `.cpanel.yml` — cPanel deployment recipe
- `CHANGELOG.md` — official generated release history
- `DEVELOPMENT.md` — concise current-development entry point

## Documentation

Start with:

- [Current development status](DEVELOPMENT.md)
- [Detailed development handbook](docs/DEVELOPMENT.md)
- [Release changelog](CHANGELOG.md)

Compatibility references remain available under `docs/` for roadmap, decisions, and contribution history.

## Development workflow

1. Begin from the current project direction in `DEVELOPMENT.md` and the detailed handbook.
2. Create a focused branch from `main`.
3. Implement the smallest useful, testable change.
4. Open a pull request with Summary, Release Notes, and Testing sections.
5. Merge after review.
6. In cPanel, run **Update from Remote** and **Deploy HEAD Commit**.
7. Verify the live workflow.
8. Update project documentation when capability, direction, or a durable decision changes.

## Releases

Official releases are created through the **Release Surfside Tools** GitHub Actions workflow. The workflow validates PHP, updates the plugin version, generates release notes and `CHANGELOG.md`, creates the Git tag and GitHub Release, and attaches an installable WordPress ZIP.

The live site continues to deploy from `main` through cPanel Git Version Control.

## Roadmap

### Completed through 2.1.0

- Weekly Update publishing foundation
- Native calendar and public displays
- Google Places and Saved Places
- Staff Dashboard and front-end Settings
- Manage Homepage and carousel migration
- Platform consolidation
- Dashboard Intelligence

### Current: Calendar Experience

The current milestone improves crowded-day exploration, printing, personal-calendar actions, optional event imagery, and an automatic `Today at Surfside` public display.

### Future: Website Management

Future work may expand Manage Homepage and the Staff Dashboard to additional editable website content such as hero content, featured events, ministry highlights, service information, and staff details.
