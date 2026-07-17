# Surfside Tools Roadmap

Surfside Tools is a front-end website-management platform for Surfside Community Fellowship. The roadmap keeps completed milestones, the current development focus, and future ideas in one easy-to-scan place.

The detailed project history, durable decisions, and complete Nice Ideas backlog remain in the [Surfside Tools Development Handbook](DEVELOPMENT.md).

## Current release

**Version:** `2.2.0`  
**Current milestone:** Milestone 8 — Website Management

## Completed milestones

- ✅ Milestone 1 — Weekly Update Foundation
- ✅ Milestone 2 — Native Calendar
- ✅ Milestone 3 — Google Places
- ✅ Milestone 4 — Staff Dashboard
- ✅ Milestone 5 — Platform Consolidation
- ✅ Milestone 6 — Dashboard Intelligence
- ✅ Milestone 7 — Calendar Experience

Milestone 7 was completed in version 2.2.0 with interactive crowded-day details, printable monthly calendars, Apple and Google Calendar actions, downloadable events, optional event images, the `[surfside_today]` shortcode, and final dashboard cleanup.

## Current milestone

### Milestone 8 — Church Portal

Move the public church portal into Surfside Tools through a plugin-owned `[surfside_portal]` shortcode. The goal is to replace the portal page's substantial custom markup and CSS with a responsive, accessible, version-controlled experience while preserving its familiar visitor pathways.

The WordPress Portal page should ultimately contain only the shortcode. The existing site header, welcome image, and footer remain page or theme content outside the shortcode.

### Current portal inventory

- A prominent full-width **Live Slides** card
- **First Time Here**
- **Message Notes**
- **Announcements**
- **This Week's Events**
- **Prayer Request**
- **Ministry Opportunities**
- **Give Online**
- **Explore Surfside**

On wider screens, the eight standard destinations use a two-column card grid. The layout must collapse cleanly on mobile and keep each complete card as a clear interactive target.

### Planned delivery

Build and verify the portal through focused pull requests:

1. **Portal foundation** — Add `[surfside_portal]`, reproduce the current card hierarchy and responsive layout, and preserve existing destinations.
2. **Weekly content integration** — Connect Message Notes and Announcements to the content already published through Surfside Tools.
3. **Calendar integration** — Connect This Week's Events to the native Surfside calendar.
4. **Service-day experience** — Reuse the native calendar and service-day intelligence where it improves the portal without cluttering the launcher.
5. **Portal settings** — Provide front-end configuration for destinations that cannot be derived automatically, including Live Slides.
6. **Migration and cleanup** — Replace the Portal page content with `[surfside_portal]`, verify desktop and mobile behavior, and remove obsolete portal-specific CSS only after the shortcode is confirmed live.

### Success criteria

- The Portal page is rendered by a single shortcode.
- Routine portal changes do not require editing page-specific HTML or CSS.
- Existing visitor destinations remain available throughout migration.
- Weekly content and events come from their existing Surfside Tools sources rather than duplicated markup.
- The portal remains recognizable, accessible, responsive, and easy to use on a phone during worship.
- The old custom portal CSS is removed only after the replacement is deployed and verified.

## Planned milestone

### Milestone 9 — Website Management

Expand Surfside Tools beyond calendars, weekly publishing, homepage photos, and the church portal by allowing church staff to manage additional high-value website content through intuitive front-end workflows, reducing the need to access WordPress Admin.

### Candidate work

The exact feature order will be defined one focused pull request at a time. Candidate areas include:

- Homepage hero content
- Service times and location information
- Featured events and ministry highlights
- Staff directory content
- Livestream destinations
- Giving-page content
- Footer and contact information
- Additional homepage content blocks

## Future ideas

Future ideas are preserved in the handbook rather than duplicated here. Review the organized backlog before beginning a new milestone or feature:

- [Nice Ideas](DEVELOPMENT.md#nice-ideas)
- [Rejected or Deferred Ideas](DEVELOPMENT.md#rejected-or-deferred-ideas)

## Development workflow

- Build one focused feature per pull request.
- Merge and verify each pull request before beginning the next.
- Update the README, changelog, roadmap, and handbook when project direction or delivered capability changes.
- Cut releases after a milestone or meaningful group of user-facing improvements is complete.

See the [Development Workflow](DEVELOPMENT.md#development-workflow) for the full process.
