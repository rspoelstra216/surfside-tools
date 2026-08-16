# Surfside Tools

Surfside Tools is a custom WordPress website-management platform built for Surfside Community Fellowship.

It gives church staff clear front-end workflows for weekly publishing, calendar management, homepage photos, locations, settings, and shared website/mobile-app services without requiring routine access to WordPress administration.

**Current release:** `3.1.0`  
**Current development phase:** Mobile app development; shared Tools integrations as needed

## Guiding principle

> Routine website maintenance should not require opening WordPress Admin.

Surfside Tools favors simple, reviewable workflows that keep staff in one place, automate repetitive work when confidence is high, and ask for clarification when important information is missing.

## Core features

### Staff Dashboard

- Front-end dashboard for routine website management
- Weekly Update and Calendar tools plus a categorized Site Management hub
- Actionable Website Status summaries focused on current needs
- A single Manage Website entry point for information, homepage photos, navigation, streaming, ministries, contact routing, and settings
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

### Sitewide information and navigation

- Front-end Surfside Information management, including a Media Library site-logo selector
- Ordered navigation manager with published-page and custom-URL destinations
- Add, rename, remove, drag-and-drop, Move Up, and Move Down controls
- Shared navigation consumed by the public header and footer
- Responsive plugin-owned header and footer installed through the Site Editor
- Sticky header with proportional logo scaling and synchronized desktop compaction
- Current-page navigation indicated with an understated accessible active state
- Cache-resilient header assets and browser-side active-link normalization
- Configured livestream services automatically promote Watch Live to Live Now
- Twitch-aware Watch Live block with a locally managed offline announcement-video fallback and next-service countdown

### Mobile app API

- Versioned `/wp-json/surfside/v1/` API surface supporting the Surfside mobile app
- Public app configuration and church identity sourced from centralized Surfside Tools data
- Published recurring event occurrences with validated date ranges and response limits
- App-ready weekly message notes that preserve useful sermon-note structure and formatting
- Mobile home-image configuration and positioning supplied from WordPress
- Public app data remains separated from administrative settings, drafts, backups, and credentials
- WordPress remains the single content-management source for the website and Surfside mobile apps

### Connect and contact

- Shared contact categories for the website and mobile app
- Dashboard-managed recipient routing by category
- Prayer Request sharing/privacy handling
- Speak to a Pastor preferred-contact handling
- Native `[surfside_contact_form]` website form using the same routing as the mobile app
- WordPress mail delivery with visitor Reply-To support
- Cloudflare Turnstile server-side verification on the public website form
- Nonce, honeypot, rate-limit, and input-validation protections
- Forminator is no longer required for the public Contact form

### Settings and visual utilities

- Front-end Google Maps and calendar settings
- Saved Places management
- Reveal-on-scroll utilities
- Service, compact, and Sunday countdowns
- Editable CSS overrides with built-in CSS reference
- Opt-in Gutenberg page standards for shared typography, buttons, sections, cards, media, and content widths

## Staff URLs

- `/dashboard`
- `/dashboard/weekly-update`
- `/dashboard/calendar`
- `/dashboard/homepage`
- `/dashboard/contact-routing`
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
- `[surfside_staff_site_management]`
- `[surfside_staff_contact_management]`

### Public displays

- `[surfside_photo_carousel]`
- `[surfside_life_at_surfside]`
- `[surfside_weekend_services]`
- `[surfside_contact_details]`
- `[surfside_contact_form]`
- `[surfside_adult_ministries]`
- `[surfside_ministry_events]`
- `[surfside_watch_live]`
- `[surfside_tools_upcoming_events]`
- `[surfside_tools_calendar]`
- `[surfside_events]`
- `[surfside_this_week]`
- `[surfside_month_calendar]`
- `[surfside_today]`
- `[surfside_today_compact]`
- `[surfside_portal]`
- `[surfside_header]`
- `[surfside_footer]`

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

## Current direction

Milestones 1–10 are complete. Version 3.0.0 delivers the V2 Website Experience across every primary navigation page. Versions 3.0.1–3.0.2 establish and refine the data bridge used by the Surfside mobile app. Version 3.1.0 completes the native Connect/contact workflow shared by the website and app, including dashboard-managed routing, native website submission, Cloudflare Turnstile, and removal of the Forminator dependency.

Primary feature development now returns to the Surfside mobile app, beginning with Giving. Surfside Tools remains the shared server-side source of truth and will continue to provide API, management, and integration plumbing when app features require it.

See the [roadmap](docs/ROADMAP.md) for milestone status, the [development handbook](docs/DEVELOPMENT.md) for durable decisions, and the [changelog](CHANGELOG.md) for complete release history.
