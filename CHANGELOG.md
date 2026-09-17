# Changelog

## [3.3.0] - 2026-09-17

### Added

- Added protected **YouVersion** integration for licensed Bible versions and passage lookup, including app-ready Bible APIs, multilingual version discovery, required attribution, and NIV as the default version.
- Added an in-page Scripture viewer for website Message Notes so references can be read and switched between available translations without leaving the site.
- Added **Featured Announcement** management under Manage Mobile App for temporary, scheduled app-home content.
- Expanded push notifications with device-level audience preferences, subscriber counts, Expo receipt processing, stale-device cleanup, and a dedicated Prayer Requests audience.
- Added the mobile-facing **Church Prayer List** workflow, including approved public requests, app submissions, member status tracking, and authenticated staff moderation support.
- Added the current unified staff-access model with Google/Firebase and WordPress credential paths plus authenticated mobile-admin access for supported staff actions.

### Improved

- Reorganized the staff information architecture around **Manage Website**, **Manage Mobile App**, **Member Engagement**, and **Church Settings**, with technical services consolidated under Integrations.
- Consolidated Staff Dashboard, Volunteer Needs, Ministry Directory, Contact Routing, Mobile App settings, and other management surfaces so each subsystem renders and saves its final behavior directly.
- Centralized staff-page provisioning into a versioned registry instead of repeatedly checking or repairing dashboard pages during ordinary requests.
- Consolidated Calendar Manager and public calendar rendering, removing duplicate recurrence/event-list work and post-render correction layers while preserving existing calendar behavior.
- Hardened Surfside Tools authentication and session handling with one authoritative Firebase Tools-role path, login throttling, and complete custom-session logout handling.
- Hardened public mobile resources with safer push-registration limits plus YouVersion caching and throttling for uncached upstream requests.

### Fixed

- Prevented the legacy wp-admin Mobile App form from overwriting newer Home Experience and Featured Announcement settings.
- Removed stale/phantom push registrations more reliably by processing Expo tickets and receipts and preventing legacy registrations from being re-imported.
- Removed remaining request-wide page provisioning/repair work and duplicate compatibility layers that could add unnecessary CPU/database and browser-side work.

### Maintenance and reliability

- Completed two repository-wide code-audit passes, retiring orphaned modules, superseded renderers, duplicate ownership paths, post-render shortcode mutation, broad DOM observers, and obsolete compatibility resets.
- Reduced production-code complexity substantially while preserving tested behavior across the affected staff, website, calendar, authentication, and mobile-app workflows.
- Added PHP/plugin validation to pull requests before merge.
- Updated cPanel deployment to synchronize tracked plugin directories with deletion enabled so files removed from Git are also removed from production.
- Rolled README, development guidance, roadmap, and audit documentation forward to the clean 3.3.0 baseline.

## [3.2.0] - 2026-08-23

### Added

- Shared **Site Settings** hierarchy for common website/app configuration, including Giving and Ministries.
- Push-notification server foundation with device registration and a staff sender for Expo notifications.
- Canonical **Ministry Manager** shared by the website and mobile app, with audience classification, ordering, Featured status, Draft/Published status, and ministry/default contact handling.
- Independent **Ministry** and **Bible Study** classifications in Calendar Manager. Ministry-classified events can create draft Ministry Manager records for staff completion and publishing.
- Public `/surfside/v1/ministries` API for published Ministry Manager records and resolved contact information.

### Improved

- Ministry website presentation now uses a dynamic Featured Ministries / Serve & Get Involved block plus a full-width, compact Ministry Directory with audience filters, selectable details, contact information, and a serving CTA.
- Ministry Manager is denser and easier to scan, with alternating record treatment, compact clickable emoji controls, clearer field grouping, and North American phone formatting.
- Ministry audience data remains available for filtering and the app without cluttering normal website ministry cards with age labels.
- Giving is centrally configurable and exposed through the existing app configuration API.
- Mobile App Home Experience navigation and the staff management hierarchy were clarified so website-specific, app-specific, and shared settings have distinct homes.
- Project documentation now reflects the released 3.2.0 shared-services baseline.

### Fixed

- Removed request-wide staff page-ensure behavior that could cause sustained CPU/database utilization and site instability. Staff-only page/migration work is now scoped away from ordinary public requests.
- Restored Ministries functionality incrementally after production rollback while preserving the stable runtime baseline.
- Calendar-created ministries now remain Draft until staff explicitly publishes them, preventing incomplete records from appearing on the website or mobile API.

## [3.1.0] - 2026-08-16

### Added

- Native website Contact form using the same five categories and routing configuration as the mobile app.
- Staff-managed contact routing for General Questions, Prayer Request, Ministry Information, Small Group Information, and Speak to a Pastor.
- Cloudflare Turnstile protection for the public website Contact form.

### Improved

- Website and mobile Connect submissions now share centralized server-side routing and validation.
- Contact routing falls back to the centralized church email when a category-specific recipient is blank.
- Forminator is no longer required for the public Contact workflow.

## [3.0.2] - 2026-08-16

### Improved

- Expanded the mobile-app integration baseline with formatted sermon-note HTML and supporting app-management/API plumbing.

## [3.0.1] - 2026-08-15

### Added

- Versioned mobile-app data bridge for approved Surfside website content and configuration.
- Staff-managed Mobile App Home hero presentation controls.

## [3.0.0] - 2026-08-13

### Added

- V2 Website Experience with the plugin-owned responsive site shell and reusable public sections.
- Expanded homepage, Watch Live, Ministries, and sitewide design integration.

## [2.4.0] - 2026-08-09

### Added

- Sitewide Information and V2 Foundation.

## [2.3.0] - 2026-08-08

### Added

- Church Portal experience and supporting staff/public integration.

## [2.2.0] - 2026-08-02

### Added

- Expanded Calendar Experience, including Today at Surfside and richer public calendar workflows.

## [2.1.0] - 2026-07-27

### Added

- Dashboard Intelligence and staff workflow improvements.

## [2.0.0] - 2026-07-20

### Changed

- Consolidated the Surfside website tooling into the versioned Surfside Tools platform.

## 1.x

The 1.x releases established the core platform: Weekly Update DOCX import/review/publishing, native calendar management, Google Places integration, saved locations, and the front-end Staff Dashboard.

Detailed implementation history for all releases remains available in GitHub Releases and merged pull requests.