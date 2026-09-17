# Surfside Tools

Surfside Tools is the custom WordPress platform behind Surfside Community Fellowship's website and shared mobile-app services.

It gives church staff front-end workflows for routine publishing and management while keeping WordPress and Surfside Tools as the shared source of truth for website and app content.

**Current release baseline:** `3.3.0`  
**Current development focus:** Surfside mobile app; Tools integrations and shared services as needed

## What Surfside Tools does

### Staff workflows

- **Weekly Update** — upload the weekly DOCX, review announcements and sermon notes, publish content, and create calendar suggestions.
- **Calendar Manager** — manage one-time, multi-day, and recurring events, locations, images, public calendar presentation, and independent Ministry/Bible Study classifications.
- **Manage Website** — maintain website-specific presentation such as navigation and homepage media.
- **Manage Mobile App** — maintain Home Experience, Featured Announcement, and Push Notifications.
- **Member Engagement** — manage Prayer Requests and Volunteer Needs.
- **Church Settings** — maintain shared church information, Ministries, Contact Routing, Integrations, and other infrequently changed shared configuration.

### Ministries

Ministries are shared website/app content managed from **Church Settings → Ministries**.

- Canonical ministry records include icon, name, usual schedule/location, description, audience, contact information, Featured status, Published status, and ordering.
- Audience supports Kids, Youth, Adults, and All Ages and drives website/app filtering without cluttering the normal website cards with age labels.
- Featured Ministry controls the Serve & Get Involved block; the compact Ministry Directory handles the broader published list.
- Ministry Directory entries open details with schedule, location, description, and contact information.
- A default ministry email can be used when a ministry-specific email is blank; phone remains ministry-specific.
- Calendar Manager's **Ministry** checkbox can create a draft Ministry Manager entry when needed. Staff finish the record in Ministry Manager and explicitly publish it before it appears publicly or in the app.
- **Bible Study** remains an independent Calendar Manager classification; no separate Bible Study Manager is currently required.

### Public website services

- Shared blue-led coastal design system and reusable Gutenberg standards.
- Plugin-owned responsive header, footer, service sections, homepage components, ministries, events, Watch Live, contact form, and Church Portal.
- Twitch-aware live/offline experience with next-service state and locally managed announcement media.
- Native calendar views, event details, Today at Surfside, printing, calendar-export actions, and direct month navigation.
- Native Contact form with shared category routing and Cloudflare Turnstile protection.
- Full-width Ministry Directory with audience filters, selectable details, and serving/contact actions.

### Mobile app services

The versioned `/wp-json/surfside/v1/` API supplies approved public data to the Surfside mobile app, including:

- church identity, location, services, links, livestream configuration, and Giving URL;
- announcements and formatted message notes;
- published event occurrences;
- published Ministries with audience, Featured status, and resolved contact data;
- app Home hero image, focal position, zoom, and Featured Announcement;
- offline Worship media;
- validated Connect/contact submission with shared category routing;
- public Bible passage/version access backed by YouVersion; and
- push-device registration support for staff-sent Expo notifications.

Public mobile endpoints are bounded, validated, and protected against inexpensive resource abuse. Administrative settings, draft ministries, credentials, push tokens, and unrelated internal data remain private.

## 3.3.0 baseline

3.3.0 is the post-audit clean baseline for Surfside Tools. Two repository-wide audits were completed before this release, followed by live testing of each affected subsystem.

The release consolidates permanent UI and runtime ownership, removes dead/orphaned and superseded code, centralizes staff-page provisioning, hardens authentication/session behavior, reduces duplicate calendar work, protects public mobile API resources, validates PHP on pull requests, and synchronizes cPanel deployment so files removed from Git are also removed from production.

The repository should not continue cleanup for its own sake. New cleanup work should be driven by a concrete defect, measurable runtime concern, proven duplication, orphaned code, or architectural conflict.

## Guiding principles

- Routine website maintenance should not require WordPress Admin.
- Website and app features should share centralized content and service plumbing whenever practical.
- Gutenberg remains preferred for straightforward editorial content; plugin shortcodes are used for dynamic or complex reusable experiences.
- Staff interfaces should favor clear actions, review, confirmation, and accessible responsive behavior.
- Keep one authoritative owner for permanent fields, layout, and behavior rather than repairing finished output later.
- Staff-only page checks, migrations, broad queries, and rendering must not run globally on ordinary requests.
- Public endpoints should validate inputs, bound expensive work, protect credentials, and cache shared upstream resources where practical.
- Historical implementation and troubleshooting detail belongs in GitHub pull requests and Releases; project documentation should describe the current product and release-level outcomes.

## Key staff URLs

- `/dashboard`
- `/dashboard/weekly-update`
- `/dashboard/calendar`
- `/dashboard/homepage`
- `/dashboard/mobile-app`
- `/dashboard/member-engagement/`
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
- `docs/` — development handbook, roadmap, audit closeout, and supporting references
- `.github/workflows/` — pull-request validation, builds, and release automation
- `.cpanel.yml` — cPanel deployment synchronization recipe
- `CHANGELOG.md` — concise release-level history
- `DEVELOPMENT.md` — current development status and direction

## Development workflow

1. Confirm the current objective in `DEVELOPMENT.md`.
2. Create a focused branch from `main`.
3. Implement a subsystem-sized, testable change.
4. Open a pull request with Summary, Release Notes, and Testing sections.
5. Confirm the automated PHP/plugin-structure validation passes.
6. Merge after review and deploy through cPanel Git Version Control.
7. Verify the live workflow and watch hosting Resource Usage when runtime behavior changes.
8. Update documentation when capability, direction, or a durable decision changes.

Official releases are created through the **Release Surfside Tools** GitHub Actions workflow, which validates PHP, updates the plugin version, generates release artifacts, creates the tag and GitHub Release, and attaches an installable WordPress ZIP.

## Current direction

3.3.0 establishes a clean, audited shared-services baseline. Primary feature development continues in the Surfside mobile app. Surfside Tools remains the supporting server-side platform for shared data, management, APIs, protected integrations, and deliberately scheduled website improvements.

For more detail, see [Development](DEVELOPMENT.md), the [Development Handbook](docs/DEVELOPMENT.md), the [Roadmap](docs/ROADMAP.md), the [Code Audit Closeout](docs/CODE-AUDIT-CLOSEOUT.md), and the [Changelog](CHANGELOG.md).
