# Changelog

This changelog records **release-level outcomes**, not every implementation pull request. Detailed implementation history remains available in merged pull requests and GitHub Releases.

## [Unreleased]

_No unreleased changes._

## [3.1.0] - 2026-08-16 — Native Connect

- Added shared contact categories and dashboard-managed recipient routing for the website and mobile app.
- Added the mobile Connect submission service with prayer privacy and pastor preferred-contact handling.
- Replaced the Forminator website contact form with native `[surfside_contact_form]` using the same routing.
- Added Cloudflare Turnstile server-side verification, nonce and honeypot protection, rate limiting, validation, and visitor Reply-To support.
- Verified website and app submissions end-to-end and removed the Forminator dependency.

## [3.0.2] - 2026-08-16 — Mobile App Integration

- Expanded the mobile API with Watch Live offline media and formatted sermon-note HTML.
- Added a dedicated Manage Mobile App staff area while keeping shared church content centralized.
- Added app Home hero management with Media Library selection, drag positioning, zoom, live preview, and API-delivered focal settings.
- Added the initial mobile contact submission endpoint with server-side validation and rate limiting.
- Refined the staff dashboard hierarchy so app-specific management remains separate from Manage Website.

## [3.0.1] - 2026-08-10 — Mobile Data Bridge

- Added versioned, read-only app and events endpoints for approved church, service, livestream, weekly-content, link, and published-event data.
- Added event date validation and response limits.
- Kept administrative settings, drafts, credentials, and write operations private.
- Established WordPress and Surfside Tools as the shared content source for the website and mobile apps.

## [3.0.0] - 2026-08-10 — V2 Website Experience

- Completed the page-by-page redesign of Home, Plan Your Visit, Ministries, Events, Watch Live, Staff, Give, and Contact.
- Established the blue-led coastal design system with reusable Gutenberg classes, sections, buttons, cards, media, spacing, and responsive behavior.
- Added plugin-owned dynamic sections for services, homepage content, contact information, adult ministries, ministry events, and Watch Live.
- Centralized service, location, contact, navigation, streaming, logo, and adult-ministry information for reuse across the site.
- Added the organized Manage Website staff experience and dashboard-managed adult ministries.
- Added Twitch-aware live detection, visitor-initiated playback, next-service state, and locally managed offline announcement media.
- Consolidated public header/footer behavior and completed sitewide responsive and accessibility refinements.

## [2.4.0] — Sitewide Information and V2 Foundation

- Centralized church identity, contact information, locations, service schedules, navigation, social destinations, and streaming configuration.
- Added plugin-owned responsive header and footer experiences driven by shared information.
- Added ordered navigation management and Media Library logo selection.
- Consolidated website-management tools into a single Manage Website entry point.
- Established the foundation used by the V2 public-site redesign.

## [2.3.0] — Church Portal

- Added the plugin-owned `[surfside_portal]` mobile-focused visitor launcher.
- Added Message Notes, Announcements, This Week, Live Slides, Prayer Request, and other key destinations in a responsive card hierarchy.
- Added accessible dialogs, keyboard behavior, scroll containment, and reduced-motion support.
- Moved substantial portal markup and styling into version-controlled Surfside Tools code.

## [2.2.0] — Calendar Experience

- Expanded the native calendar with polished public upcoming, weekly, monthly, and event-detail experiences.
- Added multi-day support, recurrence improvements, crowded-day handling, print support, and calendar export actions.
- Added event images and richer location/meeting information while preserving compact calendar scanability.
- Added Today at Surfside service/event summaries and improved in-page month navigation.

## [2.1.0] — Dashboard Intelligence

- Improved the Staff Dashboard with actionable website status and recent activity rather than passive information.
- Refined front-end staff workflows, management navigation, and visual consistency.
- Continued reducing routine dependence on WordPress Admin.

## [2.0.0] — Platform Consolidation

- Consolidated the original weekly publishing and calendar tools into the Surfside Tools platform.
- Established focused modules, front-end settings, GitHub-based development, cPanel deployment, and automated release packaging.
- Added homepage management, saved locations, and shared staff workflow foundations used by later milestones.

## [1.x] — Foundation

- Established Weekly Update DOCX parsing for announcements and sermon notes.
- Built the native calendar, recurrence handling, Google Places integration, duplicate detection, calendar suggestions, review, batch creation, and undo.
- Introduced the Staff Dashboard and the front-end-first approach that remains the core project principle.

For individual fixes, design iterations, and implementation details, use the repository's merged pull requests and GitHub Releases.