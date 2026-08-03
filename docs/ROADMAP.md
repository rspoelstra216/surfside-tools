# Surfside Tools Roadmap

Surfside Tools is a front-end website-management platform for Surfside Community Fellowship. The roadmap keeps completed milestones, the current development focus, and future ideas in one easy-to-scan place.

The detailed project history, durable decisions, and complete Nice Ideas backlog remain in the [Surfside Tools Development Handbook](DEVELOPMENT.md).

## Current release

**Version:** `2.3.1`  
**Current milestone:** Milestone 9 complete — next milestone planning

## Completed milestones

- ✅ Milestone 1 — Weekly Update Foundation
- ✅ Milestone 2 — Native Calendar
- ✅ Milestone 3 — Google Places
- ✅ Milestone 4 — Staff Dashboard
- ✅ Milestone 5 — Platform Consolidation
- ✅ Milestone 6 — Dashboard Intelligence
- ✅ Milestone 7 — Calendar Experience
- ✅ Milestone 8 — Church Portal
- ✅ Milestone 9 — Sitewide Information and V2 Foundation

Milestone 7 was completed in version 2.2.0 with the Calendar Experience. Milestone 8 was released in version 2.3.0 with the plugin-owned Church Portal.

## Completed milestone

### Milestone 8 — Church Portal

Milestone 8 moved the public church portal into Surfside Tools through a plugin-owned `[surfside_portal]` shortcode.

Delivered through PRs #78–#83:

- Portal foundation with the existing nine-destination hierarchy
- Plugin-owned portal markup and established card styling
- Responsive two-column desktop and single-column mobile layouts
- Message Notes and Announcements rendered in accessible dialogs
- Native This Week events rendered in a portal dialog
- Prayer Request routing to the Contact section
- Live Slides routing through the public Wi-Fi instructions page
- Keyboard focus, native dialog behavior, scroll containment, and reduced-motion support

The Portal page can now use a single shortcode for its launcher. The site header, welcome image, and footer remain page or theme content outside the shortcode.

### Milestone 8 outcome

- Routine portal layout changes no longer require page-specific HTML or CSS.
- Weekly content and events reuse existing Surfside Tools sources.
- Visitors remain inside the mobile-focused portal for notes, announcements, and this week's events.
- The established visual hierarchy is preserved across desktop and mobile.
- Portal behavior is version-controlled and deployable through the normal GitHub workflow.

## Version 2.3.1 refinements

The patch release completes a focused set of improvements delivered through PRs #85–#92:

- More accurate Today at Surfside service, sermon, live, and empty-day states
- Transparent `[surfside_today_compact]` output for the homepage hero
- In-page monthly navigation with browser-history and anchored reload fallbacks
- Clear multi-day event creation using an optional End Date instead of recurrence

## Current milestone

### Milestone 9 — Sitewide Information and V2 Foundation

Milestone 9 established one shared source for repeated Surfside information and delivered the first complete public component built on the V2 design foundation.

Delivered through PRs #95–#109:

- Structured Surfside identity, tagline, phone, meeting location, navigation, and social destinations
- Front-end Surfside Information management screen and dashboard card
- Expandable weekly service schedule with explicit livestream settings
- Today at Surfside and service countdowns migrated to the shared schedule
- Configured sixty-minute livestream states
- Blue-led coastal design tokens and reusable opt-in component primitives
- Restored high-resolution Surfside logo
- Front-end Media Library site-logo selection with attachment-ID storage and restored-logo fallback
- Responsive full-width `[surfside_footer]` with shared service, location, navigation, contact, and social data
- Site Editor replacement verified on desktop and mobile
- Plugin asset deployment corrected for cPanel

Live verification confirmed that changing a service time or site logo through Surfside Information immediately updates the public footer. Milestone 9 and its first post-milestone enhancement are complete; the next milestone will be chosen through project planning.

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
