# Changelog

## [Unreleased]

### Added

- Add `surfside-section-blue-soft` for pale-blue Gutenberg CTA sections.

- Add a “Show on Ministries page” event setting and the curated `[surfside_ministry_events]` feed.
- Add a Site Management hub and split sitewide information, streaming, navigation, ministries, homepage photos, and settings into focused workflows.
- Add dashboard-managed Adult Ministries entries and the `[surfside_adult_ministries]` section shortcode.
- Opt-in Gutenberg public-page standards for shared headings, buttons, sections, cards, media, content widths, responsive behavior, focus states, and reduced motion. ([#128](https://github.com/rspoelstra216/surfside-tools/pull/128))

### Improved

- Remove theme block gaps between adjacent design-system sections on redesigned interior pages.

- Keep Homepage Photos, Surfside Information, and Settings exclusively under Site Management instead of duplicating them on the Staff Dashboard.
- Shared design-system styles now use file-based versioning so deployed refinements are not hidden by stale browser or page caches. ([#128](https://github.com/rspoelstra216/surfside-tools/pull/128))
- Milestone 10 now has a documented homepage-first page audit and a durable Gutenberg class reference.

## [2.4.0] - 2026-08-04

### Added

- Central Surfside Information data foundation for future dashboard management, shared schedules, and the redesigned footer. ([#95](https://github.com/rspoelstra216/surfside-tools/pull/95))
- Front-end management screen for the centralized Surfside Information source. ([#96](https://github.com/rspoelstra216/surfside-tools/pull/96))
- Surfside Information summary and management entry point on the Staff Dashboard. ([#97](https://github.com/rspoelstra216/surfside-tools/pull/97))
- Blue-led coastal design tokens and reusable V2 component primitives. ([#103](https://github.com/rspoelstra216/surfside-tools/pull/103))

### Improved

- Surfside Information now feels visually consistent with the current Staff Dashboard. ([#98](https://github.com/rspoelstra216/surfside-tools/pull/98))
- Service-time changes in Surfside Information now propagate to Today at Surfside and all service countdowns. ([#99](https://github.com/rspoelstra216/surfside-tools/pull/99))
- Future Surfside Tools public components can share consistent color, spacing, typography, focus, and responsive behavior. ([#103](https://github.com/rspoelstra216/surfside-tools/pull/103))

### Additional Changes

### Prepare documentation for the 2.3.1 patch release ([#93](https://github.com/rspoelstra216/surfside-tools/pull/93))

- roll the README and development guides forward to version 2.3.1
- record Church Portal as released in version 2.3.0
- document the Today at Surfside refinements from PRs #85–#90
- document in-page monthly navigation from PR #91
- document clear multi-day event scheduling from PR #92
- add `[surfside_today_compact]` to the public shortcode reference

### Define Milestone 9 sitewide information and V2 foundation ([#94](https://github.com/rspoelstra216/surfside-tools/pull/94))

- rename Milestone 9 to Sitewide Information and V2 Foundation
- define the centralized Surfside Information source and dashboard management experience
- record the blue-led coastal, clean contemporary V2 direction
- make the redesigned plugin-owned `[surfside_footer]` an explicit milestone deliverable
- specify replacement of the current Site Editor footer
- record logo reconstruction as separate, non-blocking brand work

### Add expandable weekly service schedule ([#100](https://github.com/rspoelstra216/surfside-tools/pull/100))

- make the Surfside Information weekly service schedule expandable
- add and remove recurring weekly services without cluttering the default form
- add a Livestream checkbox to each service
- preserve Sunday as the initial livestream service for existing installations
- expose an ordered service-list helper while keeping the existing weekday-keyed helper compatible

### Use configured livestream services in countdowns ([#101](https://github.com/rspoelstra216/surfside-tools/pull/101))

- make Next Livestream use the services marked **Livestream** in Surfside Information instead of assuming Sunday
- allow any configured weekly service to trigger the live state
- shorten the livestream window from 90 minutes to 60 minutes
- keep general Next Service countdowns aware of whether the upcoming service is actually streamed
- automatically leave the live state when the 60-minute window ends
- update full and compact Today at Surfside widgets to use the same livestream source of truth

### Allow skipping recurring event occurrences ([#102](https://github.com/rspoelstra216/surfside-tools/pull/102))

- add recurrence exception dates to Surfside calendar events
- add a quick **Skip next** action for recurring events in Calendar Manager
- add a date picker on the Edit Event screen for removing any valid occurrence
- show skipped dates with a Restore action
- exclude skipped occurrences from the shared occurrence engine, including public calendars, Today widgets, upcoming lists, and calendar exports

### Document completed Milestone 9 foundation ([#104](https://github.com/rspoelstra216/surfside-tools/pull/104))

- record the centralized Surfside Information and shared schedule work delivered through PRs #95–#102
- record the blue-led coastal design foundation delivered in PR #103
- document the restored high-resolution logo as ready for the footer
- identify `[surfside_footer]` and Site Editor replacement as the remaining Milestone 9 deliverables
- align the README, concise development guide, roadmap, and detailed handbook

### Add plugin-owned Surfside footer ([#105](https://github.com/rspoelstra216/surfside-tools/pull/105))

- add the new `[surfside_footer]` public shortcode
- render identity, tagline, service times, location, navigation, phone, contact, and social destinations from the shared Surfside Information source
- add the restored 3138×882 transparent Surfside logo as a version-controlled plugin asset
- introduce a responsive warm off-white footer using the blue-led coastal design foundation
- provide linked Google Maps location, accessible social icons, and an automatic copyright year

### Fix footer asset deployment and loading ([#106](https://github.com/rspoelstra216/surfside-tools/pull/106))

- deploy the plugin's complete `assets/` directory through the existing cPanel recipe
- enqueue the footer stylesheet during `wp_enqueue_scripts` before WordPress prints the page head
- prevent unstyled social SVGs from expanding across the page
- make the restored footer logo and blue-led coastal design styles available on the live server

### Allow Surfside footer to span the viewport ([#107](https://github.com/rspoelstra216/surfside-tools/pull/107))

- let `[surfside_footer]` break out of WordPress's constrained Shortcode block width
- size the footer against the viewport rather than its content-width wrapper
- preserve the centered responsive inner content while extending the background, accent, and legal bar edge to edge

### Document completed Milestone 9 ([#108](https://github.com/rspoelstra216/surfside-tools/pull/108))

- mark Milestone 9 complete through PR #107
- document the deployed plugin-owned `[surfside_footer]`
- record verified desktop and mobile behavior
- record live confirmation that Surfside Information service-time changes update the public footer
- add the footer to the public shortcode inventory
- move the project into next-milestone planning

### Add front-end site logo selector ([#109](https://github.com/rspoelstra216/surfside-tools/pull/109))

- add a Site Logo control to the front-end Surfside Information manager
- open the standard WordPress Media Library without requiring staff to enter WordPress Admin
- show the selected logo in a responsive preview before saving
- store the WordPress attachment ID in the shared Surfside Information source
- add a one-click Use Default Logo action
- update `[surfside_footer]` to use the shared logo with the restored plugin image as its automatic fallback

### Document front-end site logo management ([#110](https://github.com/rspoelstra216/surfside-tools/pull/110))

- record the front-end Media Library site-logo selector delivered in PR #109
- document attachment-ID storage and the restored plugin-logo fallback
- update live verification to include service-time and logo changes
- add the logo selector to the README feature overview
- remove the selector from the enhancement-candidate language
- keep the project in next-milestone planning

### Define Milestone 10 V2 website experience ([#111](https://github.com/rspoelstra216/surfside-tools/pull/111))

- define Milestone 10 as the V2 Website Experience
- establish the boundary between plugin-owned sitewide tools and independently editable WordPress pages
- document the ordered navigation manager as the first feature
- document the plugin-owned header as the next visible component
- record the approved sticky, opaque white, responsive, flat-navigation design
- record the time-aware Plan Your Visit and Live Now primary-action behavior

### Add ordered site navigation manager ([#112](https://github.com/rspoelstra216/surfside-tools/pull/112))

- replace the fixed navigation URL fields with an ordered menu manager in Surfside Information
- support published WordPress pages or custom URLs, including optional new-tab behavior for custom links
- add, remove, drag, and accessible move-up/move-down controls
- preserve existing navigation during automatic data migration
- update the existing footer to consume the ordered navigation model

### Hotfix navigation manager parse error ([#113](https://github.com/rspoelstra216/surfside-tools/pull/113))

- Adds the missing closing quote on the navigation manager inline-script call introduced in PR #112.
- The missing quote causes PHP to stop parsing `includes/site-information-manager.php`, producing the sitewide WordPress critical-error screen.
- PHP parser check now passes for the corrected file.
- This changes one line only; no stored data or settings are affected.

### Add plugin-owned responsive site header ([#114](https://github.com/rspoelstra216/surfside-tools/pull/114))

- add the new `[surfside_header]` shortcode
- use the shared replaceable logo and ordered Surfside Information navigation
- provide a full-width opaque white header with the coastal-blue accent
- add sticky compact behavior on scroll
- add an accessible mobile hamburger menu with Escape and outside-click handling
- make Plan Your Visit the normal primary action

### Rebalance header logo and navigation proportions ([#115](https://github.com/rspoelstra216/surfside-tools/pull/115))

- enlarge the desktop logo from the compact 56px-height treatment to a footer-consistent 260–320px visual width
- slightly reduce navigation typography, spacing, and primary-button padding
- retain the existing header height and sticky compact behavior
- move the mobile-menu breakpoint to 1080px so the larger logo and navigation never crowd each other
- keep the JavaScript breakpoint synchronized with the CSS

### Correct shared logo display dimensions ([#116](https://github.com/rspoelstra216/surfside-tools/pull/116))

- reduce the plugin header logo from a 260–320px range to 220–260px
- reduce the compact sticky logo proportionally
- reduce the footer logo from 320px to 256px
- preserve the high-resolution source image and its natural aspect ratio

### Correct restored logo aspect ratio ([#117](https://github.com/rspoelstra216/surfside-tools/pull/117))

- replace the shared restored logo with a narrower-proportioned version
- reduce the source canvas from 3138×882 to 2700×882
- retain the full vertical resolution, transparent background, colors, lettering, and artwork
- optimize the replacement PNG for web delivery

### Fix logged-in mobile sticky header offset ([#118](https://github.com/rspoelstra216/surfside-tools/pull/118))

- remove the WordPress admin-toolbar offset from the sticky header below 600px
- retain the standard 46px tablet and 32px desktop offsets
- leave the logged-out visitor experience unchanged

### Document completed Milestone 10 header phase ([#119](https://github.com/rspoelstra216/surfside-tools/pull/119))

- document the completed ordered navigation manager and shared header/footer menu source
- add `[surfside_header]` to the public shortcode inventory
- record the production Site Editor header replacement
- capture responsive, sticky, mobile-menu, livestream, logo, and WordPress-toolbar behavior
- record desktop, mobile, logged-in, and public validation
- advance Milestone 10 to the page-by-page style audit

### Finalize approved Surfside logo proportions ([#120](https://github.com/rspoelstra216/surfside-tools/pull/120))

- replace the bundled fallback logo with the narrower version approved in live testing
- retain the full 882px vertical resolution and transparent background
- optimize the PNG for normal website delivery
- update the shared fallback used by both `[surfside_header]` and `[surfside_footer]`

### Keep livestream countdowns in sync with the service schedule ([#121](https://github.com/rspoelstra216/surfside-tools/pull/121))

- purge supported page caches whenever Surfside Information is saved
- purge even when the submitted values are unchanged, giving staff a reliable refresh action
- prevent pages containing dynamic service countdown shortcodes from being cached

### Synchronize the compact header and active navigation ([#122](https://github.com/rspoelstra216/surfside-tools/pull/122))

- compact desktop navigation text, spacing, padding, and link height when the sticky logo shrinks
- highlight the navigation item matching the current page
- expose the current link to assistive technology with `aria-current="page"`
- preserve the red **Live Now** Watch Live override during livestream windows

### Refine header logo scaling and active navigation ([#123](https://github.com/rspoelstra216/surfside-tools/pull/123))

- preserve the logo's source aspect ratio at full, compact, and mobile sizes
- replace the current-page blue pill with stronger blue text and a thin underline
- keep the red **Live Now** treatment as the only pill-style navigation state

### Keep the site header consistent across cached pages ([#124](https://github.com/rspoelstra216/surfside-tools/pull/124))

- version header CSS and JavaScript from each asset's modification time
- recalculate the current navigation link in the browser on every page load
- remove stale non-live primary classes left in cached page markup
- retain the red Live Now state while correcting ordinary active-page formatting
- add CSS compatibility for pages cached before the active-state redesign

### Document the completed Milestone 10 header phase ([#125](https://github.com/rspoelstra216/surfside-tools/pull/125))

- update the product overview with the final sticky-header behavior
- mark the Milestone 10 navigation and header phase complete through PR #124
- replace the earlier Plan Your Visit pill decision with the approved current-page underline
- document proportional logo scaling, synchronized desktop compaction, and the red Live Now override
- record browser-side active-link normalization and file-based asset versioning as durable cache decisions
- add the completed header work to the unreleased changelog

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

- Fix the Navigation manager's Save area after splitting Site Management into focused forms.
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
