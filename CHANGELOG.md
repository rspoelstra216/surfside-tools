# Changelog

## [2.3.1] - 2026-07-22

### Added

- Added an optional `message_url` attribute to `[surfside_today]`. ([#85](https://github.com/rspoelstra216/surfside-tools/pull/85))
- Added a responsive Message Notes dialog to Today at Surfside. ([#86](https://github.com/rspoelstra216/surfside-tools/pull/86))
- Added a Sunday **We’re Live Now** action to Today at Surfside. ([#88](https://github.com/rspoelstra216/surfside-tools/pull/88))
- Added a clear empty-day message before the next upcoming event. ([#89](https://github.com/rspoelstra216/surfside-tools/pull/89))

### Improved

- Made the current sermon title a visible link to the published message notes on Watch Live. ([#85](https://github.com/rspoelstra216/surfside-tools/pull/85))
- Made the displayed sermon title open the current published notes without leaving the page. ([#86](https://github.com/rspoelstra216/surfside-tools/pull/86))
- Marked Today at Surfside pages as dynamic content that must be rendered per request. ([#87](https://github.com/rspoelstra216/surfside-tools/pull/87))
- Kept the homepage and Today at Surfside livestream states synchronized. ([#88](https://github.com/rspoelstra216/surfside-tools/pull/88))
- Made it obvious that “Coming up next” does not represent an event happening today. ([#89](https://github.com/rspoelstra216/surfside-tools/pull/89))

### Fixed

- Removed redundant Saturday and Sunday service occurrences from “Also happening today.” ([#85](https://github.com/rspoelstra216/surfside-tools/pull/85))
- Corrected the sermon title destination so it no longer duplicates the separate Watch Live action. ([#86](https://github.com/rspoelstra216/surfside-tools/pull/86))
- Fixed Saturday's Today at Surfside output remaining visible on Sunday because of full-page caching. ([#87](https://github.com/rspoelstra216/surfside-tools/pull/87))
- Fixed Today at Surfside showing only Sunday Worship during the active livestream window. ([#88](https://github.com/rspoelstra216/surfside-tools/pull/88))

### Additional Changes

### Add compact Today at Surfside homepage widget ([#90](https://github.com/rspoelstra216/surfside-tools/pull/90))

- add a transparent `[surfside_today_compact]` shortcode sized for the homepage hero
- show Sunday’s live state with a direct Watch Live link
- show today’s worship service or first calendar event
- fall back to the next upcoming event when today is empty
- include the compact shortcode in the existing dynamic-page cache protection
- reuse the existing service schedule, calendar queries, and duplicate-service filtering

### Navigate monthly calendar without page reloads ([#91](https://github.com/rspoelstra216/surfside-tools/pull/91))

- update `[surfside_month_calendar]` in place when Previous, Today, or Next is selected
- preserve browser Back and Forward behavior for visited months
- announce loading and the newly displayed month to assistive technology
- retain normal navigation links as a no-JavaScript and request-failure fallback
- add `#surfside-month-calendar` to fallback URLs so a reload returns directly to the calendar

### Add clear multi-day event scheduling ([#92](https://github.com/rspoelstra216/surfside-tools/pull/92))

- add a “This event lasts multiple days” checkbox to Add/Edit Event
- reveal a required End Date only when the checkbox is selected
- hide and disable recurrence for multi-day events
- validate that the end date is after the start date
- render the event on every included calendar day
- show the complete date range in event-detail dialogs

## [2.3.0] - 2026-07-18

### Added

- Documented the Church Portal milestone, current portal inventory, delivery sequence, success criteria, and durable implementation decisions. ([#77](https://github.com/rspoelstra216/surfside-tools/pull/77))
- Added the `[surfside_portal]` public shortcode. ([#78](https://github.com/rspoelstra216/surfside-tools/pull/78))
- Added the current nine-destination portal hierarchy. ([#78](https://github.com/rspoelstra216/surfside-tools/pull/78))
- Added responsive one- and two-column card layouts. ([#78](https://github.com/rspoelstra216/surfside-tools/pull/78))
- Added keyboard focus, hover, touch-friendly card targets, and reduced-motion handling. ([#78](https://github.com/rspoelstra216/surfside-tools/pull/78))
- Added shortcode URL attributes and a filterable card definition. ([#78](https://github.com/rspoelstra216/surfside-tools/pull/78))
- Captured the existing portal card CSS inside Surfside Tools. ([#80](https://github.com/rspoelstra216/surfside-tools/pull/80))
- Added plugin-rendered Message Notes and Announcements dialogs to `[surfside_portal]`. ([#81](https://github.com/rspoelstra216/surfside-tools/pull/81))
- Added full-screen mobile dialog presentation and centered desktop presentation. ([#81](https://github.com/rspoelstra216/surfside-tools/pull/81))
- Added sticky dialog headers, prominent Close buttons, backdrop closing, scroll containment, and focus restoration. ([#81](https://github.com/rspoelstra216/surfside-tools/pull/81))
- Added a This Week’s Events portal dialog using the native Surfside Tools calendar shortcode. ([#82](https://github.com/rspoelstra216/surfside-tools/pull/82))
- Documented the completed Church Portal capability and durable implementation decisions. ([#84](https://github.com/rspoelstra216/surfside-tools/pull/84))
- Added `[surfside_portal]` and the portal feature set to the product overview. ([#84](https://github.com/rspoelstra216/surfside-tools/pull/84))

### Improved

- Project documentation now reflects the released 2.2.0 codebase and the transition from Calendar Experience to Website Management. ([#76](https://github.com/rspoelstra216/surfside-tools/pull/76))
- The changelog presents a concise release history instead of raw implementation-by-implementation detail. ([#76](https://github.com/rspoelstra216/surfside-tools/pull/76))
- The roadmap now clearly separates completed milestones, current work, candidate Website Management areas, and future ideas. ([#76](https://github.com/rspoelstra216/surfside-tools/pull/76))
- Moved Website Management to Milestone 9. ([#77](https://github.com/rspoelstra216/surfside-tools/pull/77))
- Updated the concise development guide to version 2.2.0 and the current post-release direction. ([#77](https://github.com/rspoelstra216/surfside-tools/pull/77))
- Aligned the README, roadmap, and detailed handbook around the portal-first plan. ([#77](https://github.com/rspoelstra216/surfside-tools/pull/77))
- Expanded the portal to the intended desktop width without requiring page-level custom CSS. ([#79](https://github.com/rspoelstra216/surfside-tools/pull/79))
- Matched the shortcode markup to the existing `surfside-portal-grid`, `surfside-portal-card`, `featured`, and `portal-icon` class structure. ([#80](https://github.com/rspoelstra216/surfside-tools/pull/80))
- Preserved plugin accessibility enhancements while matching the current visual presentation. ([#80](https://github.com/rspoelstra216/surfside-tools/pull/80))
- Kept weekly content inside the portal instead of navigating visitors to separate pages. ([#81](https://github.com/rspoelstra216/surfside-tools/pull/81))
- Reused the existing Surfside Tools weekly-content sources directly. ([#81](https://github.com/rspoelstra216/surfside-tools/pull/81))
- Kept the seven-day event view inside the mobile-focused portal instead of redirecting to the full Events page. ([#82](https://github.com/rspoelstra216/surfside-tools/pull/82))
- Routed Live Slides through the public connection-instructions page. ([#83](https://github.com/rspoelstra216/surfside-tools/pull/83))
- Moved Website Management from planned work to the current Milestone 9. ([#84](https://github.com/rspoelstra216/surfside-tools/pull/84))
- Updated the roadmap, concise development guide, and detailed handbook to reflect the post-portal direction. ([#84](https://github.com/rspoelstra216/surfside-tools/pull/84))
- Recorded the decision to route Live Slides through public Wi-Fi instructions instead of unreliable IP-based detection. ([#84](https://github.com/rspoelstra216/surfside-tools/pull/84))

### Fixed

- Removed outdated development status that still described Calendar Experience as awaiting release. ([#77](https://github.com/rspoelstra216/surfside-tools/pull/77))
- Fixed the portal appearing substantially narrower than the existing portal layout inside the theme content container. ([#79](https://github.com/rspoelstra216/surfside-tools/pull/79))
- Fixed the plugin-derived portal remaining narrow and left-aligned. ([#80](https://github.com/rspoelstra216/surfside-tools/pull/80))
- Removed the unnecessary outer portal wrapper that WordPress treated as constrained content. ([#80](https://github.com/rspoelstra216/surfside-tools/pull/80))
- Fixed Message Notes linking to the former Message Notes Entry workflow. ([#81](https://github.com/rspoelstra216/surfside-tools/pull/81))
- Fixed Announcements linking to a missing page. ([#81](https://github.com/rspoelstra216/surfside-tools/pull/81))
- Fixed Prayer Request so it targets the Contact section at `/contact/#Contact`. ([#81](https://github.com/rspoelstra216/surfside-tools/pull/81))
- Fixed the portal bypassing required Wi‑Fi instructions by linking directly to the internal viewer. ([#83](https://github.com/rspoelstra216/surfside-tools/pull/83))
- Corrected the roadmap's stale current-milestone label. ([#84](https://github.com/rspoelstra216/surfside-tools/pull/84))

## [2.2.0] - 2026-07-17

### Added

- Interactive monthly-calendar day details for crowded dates. ([#52](https://github.com/rspoelstra216/surfside-tools/pull/52))
- Printable monthly-calendar output. ([#67](https://github.com/rspoelstra216/surfside-tools/pull/67), [#68](https://github.com/rspoelstra216/surfside-tools/pull/68))
- Personal-calendar actions for Apple Calendar, Google Calendar, and downloadable event files. ([#69](https://github.com/rspoelstra216/surfside-tools/pull/69), [#70](https://github.com/rspoelstra216/surfside-tools/pull/70), [#71](https://github.com/rspoelstra216/surfside-tools/pull/71), [#72](https://github.com/rspoelstra216/surfside-tools/pull/72))
- Optional event images in Calendar Manager and public event details. ([#73](https://github.com/rspoelstra216/surfside-tools/pull/73))
- `[surfside_today]` public shortcode for service information, today’s events, and the next upcoming event. ([#74](https://github.com/rspoelstra216/surfside-tools/pull/74))
- Optional `[surfside_today]` attributes for `title`, `events_url`, and `show_link="no"`. ([#74](https://github.com/rspoelstra216/surfside-tools/pull/74))

### Improved

- Refined crowded-day calendar behavior through focused layout, overflow, and accessibility fixes. ([#53](https://github.com/rspoelstra216/surfside-tools/pull/53)–[#66](https://github.com/rspoelstra216/surfside-tools/pull/66))
- Polished calendar action labels, branding, button spacing, and responsive layout. ([#70](https://github.com/rspoelstra216/surfside-tools/pull/70)–[#72](https://github.com/rspoelstra216/surfside-tools/pull/72))
- Added event-image support to larger Today at Surfside cards. ([#74](https://github.com/rspoelstra216/surfside-tools/pull/74))
- Updated dashboard language so Calendar is consistently presented as a management workflow. ([#75](https://github.com/rspoelstra216/surfside-tools/pull/75))
- Simplified the Staff Dashboard so Website Status flows directly into Quick Actions. ([#75](https://github.com/rspoelstra216/surfside-tools/pull/75))

### Removed

- Removed the prominent Recent Activity panel from the main Staff Dashboard while preserving the underlying activity infrastructure. ([#75](https://github.com/rspoelstra216/surfside-tools/pull/75))

### Documentation

- Recorded Calendar Experience as complete and established Website Management as the next milestone. ([#76](https://github.com/rspoelstra216/surfside-tools/pull/76))
- Rolled the README, changelog, and roadmap forward to release 2.2.0. ([#76](https://github.com/rspoelstra216/surfside-tools/pull/76))

## [2.1.0] - 2026-07-15

### Added

- Dashboard Intelligence status cards, attention states, alerts, and contextual actions. ([#47](https://github.com/rspoelstra216/surfside-tools/pull/47)–[#50](https://github.com/rspoelstra216/surfside-tools/pull/50))

### Improved

- Turned the Staff Dashboard into an actionable website-status center while preserving existing management workflows.
- Refined dashboard presentation and mobile usability.

## [2.0.0] - 2026-07-15

### Added

- Unified development handbook, milestone retrospectives, and durable project decisions. ([#36](https://github.com/rspoelstra216/surfside-tools/pull/36))
- Front-end Manage Homepage workflow for carousel photos. ([#37](https://github.com/rspoelstra216/surfside-tools/pull/37)–[#42](https://github.com/rspoelstra216/surfside-tools/pull/42))
- Editable front-end CSS overrides for reveal and countdown utilities. ([#44](https://github.com/rspoelstra216/surfside-tools/pull/44), [#45](https://github.com/rspoelstra216/surfside-tools/pull/45))

### Improved

- Consolidated homepage photo management, settings, and visual utilities into Surfside Tools.
- Added automatic cache invalidation and responsive full-width carousel behavior.

## [1.3.0] - 2026-07-14

### Added

- Standard pull-request template and categorized release notes. ([#35](https://github.com/rspoelstra216/surfside-tools/pull/35))
- Weekly Update calendar suggestions with review, duplicate detection, one-click saving, recurrence, and location support. ([#15](https://github.com/rspoelstra216/surfside-tools/pull/15)–[#34](https://github.com/rspoelstra216/surfside-tools/pull/34))
- Front-end Settings and Saved Places management. ([#28](https://github.com/rspoelstra216/surfside-tools/pull/28)–[#31](https://github.com/rspoelstra216/surfside-tools/pull/31))

### Improved

- Organized project roadmap and documentation. ([#12](https://github.com/rspoelstra216/surfside-tools/pull/12))
- Improved generated release notes and changelog readability. ([#13](https://github.com/rspoelstra216/surfside-tools/pull/13), [#14](https://github.com/rspoelstra216/surfside-tools/pull/14))

## [1.2.1] - 2026-07-13

### Added

- Automated plugin builds, cPanel deployment, and official GitHub releases. ([#3](https://github.com/rspoelstra216/surfside-tools/pull/3)–[#5](https://github.com/rspoelstra216/surfside-tools/pull/5), [#10](https://github.com/rspoelstra216/surfside-tools/pull/10))
- Separate meeting-location field and public display support. ([#6](https://github.com/rspoelstra216/surfside-tools/pull/6), [#7](https://github.com/rspoelstra216/surfside-tools/pull/7))

### Improved

- Clarified event-location fields and Google Places guidance. ([#2](https://github.com/rspoelstra216/surfside-tools/pull/2))
- Improved monthly-calendar row sizing, event-card spacing, and overflow indicators. ([#8](https://github.com/rspoelstra216/surfside-tools/pull/8), [#9](https://github.com/rspoelstra216/surfside-tools/pull/9))

Release entries are generated by the **Release Surfside Tools** GitHub Actions workflow and may be polished afterward to provide a concise milestone-level history.
