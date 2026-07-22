# Surfside Tools

Surfside Tools is a custom WordPress website-management platform built for Surfside Community Fellowship.

It gives church staff clear front-end workflows for weekly publishing, calendar management, homepage photos, locations, and settings without requiring routine access to WordPress administration.

**Current release:** `2.3.1`  
**Current development phase:** Milestone 9 — Sitewide Information and V2 Foundation

## Guiding principle

> Routine website maintenance should not require opening WordPress Admin.

Surfside Tools favors simple, reviewable workflows that keep staff in one place, automate repetitive work when confidence is high, and ask for clarification when important information is missing.

## Core features

### Staff Dashboard

- Front-end dashboard for routine website management
- Weekly Update, Calendar, Manage Homepage, and Settings tools
- Actionable Website Status summaries focused on current needs
- Direct Quick Actions for common staff workflows
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

- One-time, multi-day, and recurring events
- Clear multi-day date ranges without recurrence workarounds
- Daily, weekly, and monthly recurrence
- Repeat-until dates
- Separate venue, address, and meeting-location fields
- Google Places and saved locations
- Optional event images selected from the WordPress Media Library
- Active-event management and recently past events
- Public upcoming, weekly, and monthly calendar displays

### Public calendar experience

- Accessible event-detail modals
- Interactive crowded-day details without hiding events
- Printable monthly calendar
- Apple Calendar, Google Calendar, and downloadable event actions
- Event images in standard event details without cluttering compact month cells
- Automatic Today at Surfside summary for service days, today’s events, or the next upcoming event
- Compact transparent Today at Surfside homepage summary with live-service state
- In-page monthly navigation with browser-history and anchored reload fallbacks

### Church Portal

- Plugin-owned `[surfside_portal]` visitor launcher
- Full-width Live Slides destination with connection-instructions routing
- Responsive two-column desktop and single-column mobile card layout
- Plugin-rendered Message Notes and Announcements dialogs
- Native seven-day event dialog
- Accessible keyboard focus, native dialog behavior, scroll containment, and reduced-motion support
- Portal markup and styling version-controlled inside Surfside Tools

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
- `[surfside_today_compact]`
- `[surfside_portal]`

`[surfside_today]` supports optional `title`, `events_url`, and `show_link="no"` attributes.

`[surfside_today_compact]` provides a transparent homepage-friendly summary and supports optional `events_url` and `watch_url` attributes.

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
- `CHANGELOG.md` — official release history
- `DEVELOPMENT.md` — concise current-development entry point

## Documentation

Start with:

- [Current development status](DEVELOPMENT.md)
- [Detailed development handbook](docs/DEVELOPMENT.md)
- [Project roadmap](docs/ROADMAP.md)
- [Release changelog](CHANGELOG.md)

Compatibility references remain available under `docs/` for decisions and contribution history.

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

### Completed through Milestone 8

- Milestone 1 — Weekly Update Foundation
- Milestone 2 — Native Calendar
- Milestone 3 — Google Places
- Milestone 4 — Staff Dashboard
- Milestone 5 — Platform Consolidation
- Milestone 6 — Dashboard Intelligence
- Milestone 7 — Calendar Experience
- Milestone 8 — Church Portal

Milestone 8 was released in version 2.3.0. Version 2.3.1 adds focused Today at Surfside and calendar experience refinements.

### Current: Milestone 9 — Sitewide Information and V2 Foundation

The next phase creates one source of truth for Surfside identity, service times, meeting location, phone, navigation, and social destinations. It adds a front-end Surfside Information manager and dashboard card, migrates existing plugin features to shared data, establishes the blue-led coastal V2 design foundation, and delivers a redesigned plugin-owned `[surfside_footer]` that replaces the current Site Editor footer.

See [`docs/ROADMAP.md`](docs/ROADMAP.md) for the Milestone 9 delivery plan and organized backlog.
