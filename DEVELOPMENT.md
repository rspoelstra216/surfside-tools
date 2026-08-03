# Surfside Tools Development

This is the concise entry point for current Surfside Tools development. For architecture, detailed capabilities, the Nice Ideas backlog, decision history, validation checklists, and release procedures, see the [Development Handbook](docs/DEVELOPMENT.md).

## Current version

**2.3.1** — patch release prepared July 22, 2026

Version 2.3.0 released the plugin-owned Church Portal. Version 2.3.1 adds focused public-experience improvements: clearer Today at Surfside states, a compact homepage summary, in-page monthly navigation, and straightforward multi-day event scheduling.

## Project vision

Surfside Tools should let church staff perform routine website maintenance through clear front-end workflows without needing WordPress Admin, while giving visitors useful and accessible public website experiences.

## Design principles

- Staff first.
- Front end first.
- Automate when confidence is high.
- Prompt clearly when information is missing.
- Keep related workflows together.
- Preserve review, confirmation, duplicate protection, and undo.
- Build focused, testable pull requests.
- Avoid adding dashboard information that is not clearly actionable.
- Favor accessible public experiences that work well on desktop and mobile.
- Move reusable public experiences into version-controlled plugin shortcodes instead of maintaining substantial page-specific CSS.

## Completed milestones

- Milestone 1 — Weekly Update Foundation
- Milestone 2 — Native Calendar
- Milestone 3 — Google Places
- Milestone 4 — Staff Dashboard
- Milestone 5 — Platform Consolidation, released as 2.0.0
- Milestone 6 — Dashboard Intelligence, released as 2.1.0
- Milestone 7 — Calendar Experience, released as 2.2.0
- Milestone 8 — Church Portal, released as 2.3.0

## Completed direction

### Milestone 8 — Church Portal

The public church portal is now rendered through the plugin-owned `[surfside_portal]` shortcode.

Delivered through PRs #78–#83:

- The established nine-card portal hierarchy and visual presentation
- Responsive desktop and mobile layouts
- Plugin-rendered Message Notes and Announcements dialogs
- Native This Week calendar dialog
- Prayer Request routing to the Contact section
- Live Slides routing through the public Wi-Fi instructions page
- Accessible keyboard, dialog, scrolling, and reduced-motion behavior

The Portal page retains its site header, welcome image, and footer outside the shortcode. Portal launcher markup, interaction, and CSS now belong to Surfside Tools.

## Patch release refinements

Delivered through PRs #85–#92:

- Removed duplicate worship-service entries from Today at Surfside.
- Linked sermon titles to the Message Notes dialog.
- Protected dynamic Today output from page caching.
- Added the Sunday live-service state.
- Clarified empty days before “Coming up next.”
- Added the transparent `[surfside_today_compact]` homepage summary.
- Added in-page monthly-calendar navigation with browser-history and anchor fallbacks.
- Added an optional multi-day event workflow with a clear End Date and mutually exclusive recurrence.

## Current direction

### Milestone 9 — Sitewide Information and V2 Foundation

Milestone 9 and its first post-milestone enhancement are complete through PR #109.

Delivered through PRs #95–#109:

- Centralized Surfside identity, contact, location, navigation, social, and weekly service data
- Front-end Surfside Information manager and dashboard card
- Expandable weekly services with per-service livestream settings
- Today at Surfside and countdown features migrated to the shared schedule
- Sixty-minute livestream windows driven by configured services
- Blue-led coastal design tokens and opt-in component primitives
- Restored high-resolution Surfside logo
- Front-end Media Library site-logo selector with live preview and one-click default restoration
- Responsive plugin-owned `[surfside_footer]` driven by shared information
- Google Maps-linked location, navigation, social icons, contact action, and automatic copyright year
- Full-width Site Editor integration with verified desktop and mobile layouts
- cPanel deployment of version-controlled CSS and image assets

Live verification confirmed that service-time and logo changes made in Surfside Information immediately update the public footer. Custom logos are stored as WordPress attachment IDs, while the restored plugin logo remains the automatic fallback.

### Milestone 10 — V2 Website Experience

Milestone 10 applies the shared information and blue-led coastal foundation to the visible website while preserving WordPress as the editor for unique page content.

The plugin will own sitewide settings, navigation, header and footer components, dynamic widgets, and reusable design standards. Individual WordPress pages will continue to own their editorial content and page-specific layout.

Planned delivery order:

1. Replace the fixed navigation fields with an ordered front-end menu manager supporting add, rename, remove, drag-and-drop, accessible move controls, internal-page selection, and custom URLs.
2. Build a plugin-owned `[surfside_header]` with the shared replaceable logo, opaque full-width white surface, compact sticky state, responsive mobile menu, and accessible keyboard behavior.
3. Use Plan Your Visit as the normal primary action and automatically promote Watch Live to a Live Now action during configured livestream windows.
4. Replace the Site Editor header only after isolated desktop and mobile testing.
5. Audit page styling together and add reusable plugin styles or widgets where consistency provides value, without turning Surfside Tools into a page builder.

## Release history

| Version | Focus |
| --- | --- |
| 1.0.0 | Initial Surfside Tools release |
| 1.5.0 | Major feature expansion |
| 2.0.0 | Platform Consolidation |
| 2.1.0 | Dashboard Intelligence |
| 2.2.0 | Calendar Experience |
| 2.3.0 | Church Portal |
| 2.3.1 | Today and calendar experience refinements |

## Development workflow

1. Confirm the current objective here and in the detailed handbook.
2. Create a focused branch from `main` using `feature/`, `fix/`, or `docs/`.
3. Implement the smallest useful and testable change.
4. Open a pull request with Summary, categorized Release Notes, and Testing instructions.
5. Merge after review.
6. Deploy through cPanel Git Version Control.
7. Verify the affected live workflow.
8. Update documentation when capability, direction, or a durable decision changes.

## Documentation ownership

- `README.md` — product overview and documentation links
- `DEVELOPMENT.md` — current version, completed milestones, and active development direction
- `docs/DEVELOPMENT.md` — detailed living handbook
- `docs/ROADMAP.md` — concise milestone roadmap and delivery plan
- `CHANGELOG.md` — official release history generated by the release workflow
- GitHub Releases — versioned notes and installable ZIP packages
