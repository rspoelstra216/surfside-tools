# Surfside Tools Roadmap

Surfside Tools is a front-end website-management platform for Surfside Community Fellowship. The roadmap keeps completed milestones, the current development focus, and future ideas in one easy-to-scan place.

The detailed project history, durable decisions, and complete Nice Ideas backlog remain in the [Surfside Tools Development Handbook](DEVELOPMENT.md).

## Current release

**Version:** `2.3.1`  
**Current milestone:** Milestone 9 — Sitewide Information and V2 Foundation

## Completed milestones

- ✅ Milestone 1 — Weekly Update Foundation
- ✅ Milestone 2 — Native Calendar
- ✅ Milestone 3 — Google Places
- ✅ Milestone 4 — Staff Dashboard
- ✅ Milestone 5 — Platform Consolidation
- ✅ Milestone 6 — Dashboard Intelligence
- ✅ Milestone 7 — Calendar Experience
- ✅ Milestone 8 — Church Portal

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

Create a single source of truth for information repeated throughout Surfside's website, then use it to deliver the first site-wide V2 component.

### Planned deliverables

- Structured Surfside identity, tagline, phone, meeting location, service schedule, navigation, and social destinations
- Front-end Surfside Information management screen
- Surfside Information dashboard card
- Existing Today and countdown features migrated to the shared service schedule
- Blue-led coastal design tokens using deep ocean and Surfside blues sparingly with white and warm sandy off-white surfaces
- Redesigned plugin-owned `[surfside_footer]`
- Current Site Editor footer replaced with the shortcode after live verification

The redesigned footer is part of Milestone 9, not a future candidate. It will provide the logo, tagline, service times, Google Maps-linked location, main navigation, Contact and phone information, social icons, and automatic copyright year.

Logo reconstruction remains a separate, non-blocking branding project. The exact delivery sequence will continue one focused pull request at a time.

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
