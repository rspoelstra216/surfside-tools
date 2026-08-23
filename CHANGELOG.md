# Changelog

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