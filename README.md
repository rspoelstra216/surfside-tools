# Surfside Tools

Surfside Tools is the custom WordPress platform behind Surfside Community Fellowship's website and shared mobile-app services.

It gives church staff front-end workflows for routine publishing and site management while keeping WordPress and Surfside Tools as the shared source of truth for website and app content.

**Current release:** `3.2.0`  
**Current development focus:** Surfside mobile app; Tools integrations and stability as needed

## What Surfside Tools does

### Staff workflows

- **Weekly Update** — upload the weekly DOCX, review announcements and sermon notes, publish content, and create calendar suggestions.
- **Calendar Manager** — manage one-time, multi-day, and recurring events, locations, images, public calendar presentation, and independent Ministry/Bible Study classifications.
- **Manage Website** — maintain website-specific presentation such as navigation and homepage media.
- **Manage Mobile App** — maintain app-specific Home presentation and Push Notifications.
- **Site Settings** — maintain shared/infrequently changed configuration including Surfside Information, streaming, contact routing, integrations, Giving, and the Ministry Manager.

### Ministries

Version 3.2.0 establishes Ministries as shared website/app content managed from **Site Settings → Ministries**.

- Canonical ministry records include icon, name, usual schedule/location, description, audience, contact information, Featured status, Published status, and ordering.
- Audience supports Kids, Youth, Adults, and All Ages and drives website/app filtering without cluttering the normal website cards with age labels.
- Featured Ministry controls the Serve & Get Involved block; the compact Ministry Directory handles the broader published list.
- Ministry Directory entries open details with schedule, location, description, and contact information.
- A default ministry email can be used when a ministry-specific email is blank; phone remains ministry-specific.
- Calendar Manager's **Ministry** checkbox creates a draft Ministry Manager entry when needed. Staff finish the record in Ministry Manager and explicitly publish it before it appears publicly or in the app.
- **Bible Study** remains an independent Calendar Manager classification for now; no separate Bible Study Manager is required yet.

### Public website services

- Shared blue-led coastal design system and reusable Gutenberg standards.
- Plugin-owned responsive header, footer, service sections, homepage components, ministries, events, Watch Live, contact form, and Church Portal.
- Twitch-aware live/offline experience with next-service state and locally managed announcement media.
- Native calendar views, event details, Today at Surfside, printing, and calendar-export actions.
- Native Contact form with shared category routing and Cloudflare Turnstile protection.
- Full-width Ministry Directory with centered audience filters, compact selectable rows, details dialogs, and a serving/contact CTA.

### Mobile app services

The versioned `/wp-json/surfside/v1/` API supplies approved public data to the Surfside mobile app, including:

- church identity, location, services, links, livestream configuration, and Giving URL;
- announcements and formatted message notes;
- published event occurrences;
- published Ministries with audience, Featured status, and resolved contact data;
- app Home hero image, focal position, and zoom;
- offline Worship media;
- validated Connect/contact submission with shared category routing; and
- push-device registration support for staff-sent Expo notifications.

Administrative settings, draft ministries, credentials, push tokens, and unrelated internal data remain private.

## Guiding principles

- Routine website maintenance should not require WordPress Admin.
- Website and app features should share centralized content and service plumbing whenever practical.
- Gutenberg remains preferred for straightforward editorial content; plugin shortcodes are used for dynamic or complex reusable experiences.
- Staff interfaces should favor clear actions, review, confirmation, and accessible responsive behavior.
- Expensive page-ensure, migration, or staff-only work must not run globally on ordinary WordPress requests; scope work to the page/action that needs it.
- Historical implementation and troubleshooting detail belongs in GitHub pull requests and Releases; project documentation should describe the current product and release-level outcomes.

## Key staff URLs

- `/dashboard`
- `/dashboard/weekly-update`
- `/dashboard/calendar`
- `/dashboard/homepage`
- `/dashboard/mobile-app`
- `/dashboard/site-settings/`
- `/dashboard/site-ministries/`

## Key public shortcodes

- Weekly content: `[surfside_weekly_update]`, `[surfside_tools_announcements]`, `[surfside_tools_message]`
- Calendar: `[surfside_tools_upcoming_events]`, `[surfside_this_week]`, `[surfside_month_calendar]`, `[surfside_today]`, `[surfside_today_compact]`
- Website components: `[surfside_weekend_services]`, `[surfside_life_at_surfside]`, `[surfside_photo_carousel]`, `[surfside_watch_live]`, `[surfside_featured_ministries]`, `[surfside_all_ministries]`, `[surfside_contact_details]`, `[surfside_contact_form]`
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
6. Verify the live workflow and watch hosting Resource Usage when runtime behavior changes.
7. Update documentation when capability, direction, or a durable decision changes.

Official releases are created through the **Release Surfside Tools** GitHub Actions workflow, which validates PHP, updates the plugin version, generates release artifacts, creates the tag and GitHub Release, and attaches an installable WordPress ZIP.

## Current direction

Version 3.2.0 completes the shared Ministries management/presentation layer and restores a stable runtime baseline after removing request-wide page-ensure behavior. Primary feature development continues in the Surfside mobile app. Surfside Tools remains the supporting server-side platform for shared data, management, APIs, protected integrations, and deliberately scheduled website improvements.

For more detail, see [Development](DEVELOPMENT.md), the [Development Handbook](docs/DEVELOPMENT.md), the [Roadmap](docs/ROADMAP.md), and the [Changelog](CHANGELOG.md).