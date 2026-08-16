# Surfside Tools

Surfside Tools is the custom WordPress platform behind Surfside Community Fellowship's website and shared mobile-app services.

It gives church staff front-end workflows for routine publishing and site management while keeping WordPress and Surfside Tools as the shared source of truth for website and app content.

**Current release:** `3.1.0`  
**Current development focus:** Surfside mobile app; Tools integrations as needed

## What Surfside Tools does

### Staff workflows

- **Weekly Update** — upload the weekly DOCX, review announcements and sermon notes, publish content, and create calendar suggestions.
- **Calendar Manager** — manage one-time, multi-day, and recurring events, locations, images, and public calendar presentation.
- **Manage Website** — maintain shared church information, homepage media, navigation, streaming, ministries, contact routing, and settings.
- **Manage Mobile App** — maintain app-specific presentation settings such as the Home hero while shared content remains centralized.

### Public website services

- Shared blue-led coastal design system and reusable Gutenberg standards.
- Plugin-owned responsive header, footer, service sections, homepage components, ministries, events, Watch Live, contact form, and Church Portal.
- Twitch-aware live/offline experience with next-service state and locally managed announcement media.
- Native calendar views, event details, Today at Surfside, printing, and calendar-export actions.
- Native Contact form with shared category routing and Cloudflare Turnstile protection.

### Mobile app services

The versioned `/wp-json/surfside/v1/` API supplies approved public data to the Surfside mobile app, including:

- church identity, location, services, links, and livestream configuration;
- announcements and formatted message notes;
- published event occurrences;
- app Home hero image, focal position, and zoom;
- offline Worship media; and
- validated Connect/contact submission with shared category routing.

Administrative settings, drafts, credentials, and unrelated internal data remain private.

## Guiding principles

- Routine website maintenance should not require WordPress Admin.
- Website and app features should share centralized content and service plumbing whenever practical.
- Gutenberg remains preferred for straightforward editorial content; plugin shortcodes are used for dynamic or complex reusable experiences.
- Staff interfaces should favor clear actions, review, confirmation, and accessible responsive behavior.
- Historical implementation detail belongs in GitHub pull requests and Releases; project documentation should describe the current product and release-level outcomes.

## Key staff URLs

- `/dashboard`
- `/dashboard/weekly-update`
- `/dashboard/calendar`
- `/dashboard/homepage`
- `/dashboard/mobile-app`
- `/dashboard/contact-routing`
- `/dashboard/settings`

## Key public shortcodes

- Weekly content: `[surfside_weekly_update]`, `[surfside_tools_announcements]`, `[surfside_tools_message]`
- Calendar: `[surfside_tools_upcoming_events]`, `[surfside_this_week]`, `[surfside_month_calendar]`, `[surfside_today]`, `[surfside_today_compact]`
- Website components: `[surfside_weekend_services]`, `[surfside_life_at_surfside]`, `[surfside_photo_carousel]`, `[surfside_watch_live]`, `[surfside_adult_ministries]`, `[surfside_ministry_events]`, `[surfside_contact_details]`, `[surfside_contact_form]`
- Site shell and portal: `[surfside_header]`, `[surfside_footer]`, `[surfside_portal]`

## Repository structure

- `surfside-tools.php` — plugin entry point and module loader
- `includes/` — focused functional modules
- `docs/` — development handbook, roadmap, and supporting references
- `.github/workflows/` — validation, builds, and release automation
- `.cpanel.yml` — cPanel deployment recipe
- `CHANGELOG.md` — concise release-level history
- `DEVELOPMENT.md` — current development status and direction

## Development workflow

1. Confirm the current objective in `DEVELOPMENT.md`.
2. Create a focused branch from `main`.
3. Implement the smallest useful, testable change.
4. Open a pull request with Summary, Release Notes, and Testing sections.
5. Merge after review and deploy through cPanel Git Version Control.
6. Verify the live workflow.
7. Update documentation when capability, direction, or a durable decision changes.

Official releases are created through the **Release Surfside Tools** GitHub Actions workflow, which validates PHP, updates the plugin version, generates release artifacts, creates the tag and GitHub Release, and attaches an installable WordPress ZIP.

## Current direction

Version 3.1.0 completes the native Connect workflow shared by the website and app. Primary feature development now continues in the Surfside mobile app, beginning with Giving. Surfside Tools remains the supporting server-side platform for shared data, management, APIs, and integrations.

For more detail, see [Development](DEVELOPMENT.md), the [Development Handbook](docs/DEVELOPMENT.md), the [Roadmap](docs/ROADMAP.md), and the [Changelog](CHANGELOG.md).