# Changelog

## [3.0.1] - 2026-08-10

### Improved

- Updated project documentation to reflect the completed Surfside Tools 3.0.0 V2 Website Experience. ([#183](https://github.com/rspoelstra216/surfside-tools/pull/183))

### Additional Changes

### Add read-only mobile API ([#184](https://github.com/rspoelstra216/surfside-tools/pull/184))

- add a public, read-only `/wp-json/surfside/v1/app` endpoint for church identity, location, services, livestream configuration, current announcements, current message notes, and public links
- add a public, read-only `/wp-json/surfside/v1/events` endpoint for published recurring event occurrences
- validate date parameters, cap event ranges at two years, and cap responses at 100 occurrences
- keep administrative settings, drafts, backups, credentials, and write operations private

## [Unreleased]

_No unreleased changes._

## [3.0.0] - 2026-08-10

### Added

- Added a durable Gutenberg class reference for the shared public-page design standards. ([#129](https://github.com/rspoelstra216/surfside-tools/pull/129))
- Reusable homepage weekend-service section classes in the shared design system. ([#130](https://github.com/rspoelstra216/surfside-tools/pull/130))
- `[surfside_weekend_services]` homepage shortcode. ([#131](https://github.com/rspoelstra216/surfside-tools/pull/131))
- Automatic service cards sourced from Surfside Information. ([#131](https://github.com/rspoelstra216/surfside-tools/pull/131))
- Plugin-rendered venue and linked address. ([#131](https://github.com/rspoelstra216/surfside-tools/pull/131))
- Full `[surfside_life_at_surfside]` homepage section shortcode. ([#136](https://github.com/rspoelstra216/surfside-tools/pull/136))
- Full-width sand section background with centered photo-story content. ([#136](https://github.com/rspoelstra216/surfside-tools/pull/136))

### Improved

- Updated Milestone 10 direction to begin the page-by-page audit with the homepage. ([#129](https://github.com/rspoelstra216/surfside-tools/pull/129))
- Clarified that pages opt in through an outer `surfside-page` Group while retaining independent editorial control. ([#129](https://github.com/rspoelstra216/surfside-tools/pull/129))
- Consistent service-card spacing, typography, borders, coastal color treatment, and mobile behavior. ([#130](https://github.com/rspoelstra216/surfside-tools/pull/130))
- Weekend services now use a consistent full-width layout without nested Gutenberg Columns. ([#131](https://github.com/rspoelstra216/surfside-tools/pull/131))
- The service grid adapts automatically when another weekly service is configured. ([#131](https://github.com/rspoelstra216/surfside-tools/pull/131))
- Homepage service content remains synchronized with shared site settings. ([#131](https://github.com/rspoelstra216/surfside-tools/pull/131))
- Weekend-service background now reaches both viewport edges. ([#133](https://github.com/rspoelstra216/surfside-tools/pull/133))
- Service section now uses white before the blue photo-carousel section. ([#133](https://github.com/rspoelstra216/surfside-tools/pull/133))
- More compact service-section top and bottom spacing. ([#135](https://github.com/rspoelstra216/surfside-tools/pull/135))
- Tighter space between the introduction and service cards. ([#135](https://github.com/rspoelstra216/surfside-tools/pull/135))
- Shorter desktop service cards with the same content hierarchy. ([#135](https://github.com/rspoelstra216/surfside-tools/pull/135))
- Correct H2 heading hierarchy for Life at Surfside. ([#136](https://github.com/rspoelstra216/surfside-tools/pull/136))
- Consistent plugin-owned spacing and typography. ([#136](https://github.com/rspoelstra216/surfside-tools/pull/136))
- Carousel now sits within the shared homepage content width without the legacy viewport offset. ([#136](https://github.com/rspoelstra216/surfside-tools/pull/136))
- Existing staff photo-management workflow remains unchanged. ([#136](https://github.com/rspoelstra216/surfside-tools/pull/136))
- Life at Surfside photos again span nearly the full browser width. ([#137](https://github.com/rspoelstra216/surfside-tools/pull/137))
- Section heading and description retain their centered readable width. ([#137](https://github.com/rspoelstra216/surfside-tools/pull/137))
- Sand section background and existing carousel animation remain unchanged. ([#137](https://github.com/rspoelstra216/surfside-tools/pull/137))
- The white-to-sand section transition is now perceptible. ([#138](https://github.com/rspoelstra216/surfside-tools/pull/138))
- Life at Surfside retains a warm neutral background without competing with the carousel photographs. ([#138](https://github.com/rspoelstra216/surfside-tools/pull/138))

### Fixed

- Nothing. ([#130](https://github.com/rspoelstra216/surfside-tools/pull/130))
- Inconsistent spacing and constrained layouts caused by the former block hierarchy. ([#131](https://github.com/rspoelstra216/surfside-tools/pull/131))
- Weekend service heading and Saturday card being pushed off the left side. ([#132](https://github.com/rspoelstra216/surfside-tools/pull/132))
- Horizontal page overflow caused by the shortcode’s duplicate full-width breakout. ([#132](https://github.com/rspoelstra216/surfside-tools/pull/132))
- Incorrect centering inside an already full-width Gutenberg Group. ([#132](https://github.com/rspoelstra216/surfside-tools/pull/132))
- Visible left and right margins around the supposedly full-width homepage section. ([#133](https://github.com/rspoelstra216/surfside-tools/pull/133))
- The white seam below the hero no longer contrasts against a sand section. ([#133](https://github.com/rspoelstra216/surfside-tools/pull/133))
- Saturday and Sunday service cards stacking prematurely. ([#134](https://github.com/rspoelstra216/surfside-tools/pull/134))
- Excessive section height at intermediate viewport widths. ([#134](https://github.com/rspoelstra216/surfside-tools/pull/134))
- The shortcode being limited to the theme’s 672px content width inside a full-width Group. ([#134](https://github.com/rspoelstra216/surfside-tools/pull/134))

### Additional Changes

### Roll project documentation forward to 2.4.0 ([#126](https://github.com/rspoelstra216/surfside-tools/pull/126))

- identify Surfside Tools 2.4.0 as the current release
- record the August 4, 2026 Sitewide Experience release
- add 2.4.0 to the concise release history
- connect the release to the completed Milestone 9 foundation and Milestone 10 header phase
- keep the next development focus on the public page-by-page design audit

### Streamline roadmap and development documentation ([#127](https://github.com/rspoelstra216/surfside-tools/pull/127))

- keep the README focused on the current product and next direction
- replace repeated completed-milestone prose with a shared outcome/release table
- reduce the concise development guide to current status, architecture boundary, workflow, and ownership
- keep roadmap detail only for the active Milestone 10 phase
- preserve architecture and durable public-experience decisions in the handbook
- point historical implementation detail to the changelog, GitHub Releases, and merged PRs

### Add reusable public page design standards ([#128](https://github.com/rspoelstra216/surfside-tools/pull/128))

- add an opt-in `surfside-page` foundation for Gutenberg-managed public pages
- add reusable section, surface, width, card, media, lede, and action classes
- standardize page headings and Gutenberg buttons with the blue-led coastal design system
- include responsive, focus, and reduced-motion behavior
- version the design-system stylesheet from its modification time so deployed CSS changes are not hidden by stale caches

### Fix secondary Gutenberg button outline ([#139](https://github.com/rspoelstra216/surfside-tools/pull/139))

- explicitly set the secondary Gutenberg button border width and style
- prevent WordPress theme border-width defaults from removing the outline
- retain the existing white default and light-blue hover treatment

### Keep Gutenberg button hover inside pill ([#140](https://github.com/rspoelstra216/surfside-tools/pull/140))

- require the generic button base class before applying generic secondary/text modifiers
- prevent Gutenberg's square `.wp-block-button` wrapper from receiving the hover background
- preserve the rounded hover treatment on the nested button link

### Add Gutenberg-friendly section classes ([#141](https://github.com/rspoelstra216/surfside-tools/pull/141))

- add `surfside-section-white`, `surfside-section-sand`, and `surfside-section-soft`
- make each shortcut include public-page typography, standard section spacing, its background, and reveal-on-scroll behavior
- keep `surfside-container` separate for inner content width
- preserve all existing classes for backward compatibility

### Add parent-driven staggered card grid ([#142](https://github.com/rspoelstra216/surfside-tools/pull/142))

- add `surfside-staggered-cards` for Gutenberg Columns or Group blocks
- automatically style each direct child as a responsive white card
- automatically reveal the first three cards with staggered delays
- retain reduced-motion and block-editor visibility safeguards

### Add flat Gutenberg video style ([#143](https://github.com/rspoelstra216/surfside-tools/pull/143))

- add a single `surfside-video` class for Gutenberg video embeds
- preserve a responsive 16:9 presentation
- retain rounded corners without applying a drop shadow
- ensure the embedded iframe fills the frame cleanly

### Add dashboard-driven Ready to Visit section ([#144](https://github.com/rspoelstra216/surfside-tools/pull/144))

- add `[surfside_ready_to_visit]` for the homepage closing call-to-action
- read service times, venue, address, map URL, and Plan Your Visit navigation from canonical Site Information
- support optional `title` and `intro` shortcode attributes without expanding the Information dashboard
- add a full-width ocean treatment with responsive service cards and accessible primary/secondary actions
- include the existing plugin-managed reveal behavior and reduced-motion safeguards

### Lighten Ready to Visit background ([#145](https://github.com/rspoelstra216/surfside-tools/pull/145))

- change the Ready to Visit background from `ocean-900` to `ocean-800`
- retain the strong closing CTA treatment while reducing the near-black appearance
- leave typography, service cards, buttons, spacing, and contrast behavior unchanged

### Tighten Ready to Visit spacing ([#146](https://github.com/rspoelstra216/surfside-tools/pull/146))

- reduce Ready to Visit vertical padding from as much as 88px to a maximum of 48px
- use 40px vertical padding on mobile
- leave the section content, colors, service cards, and actions unchanged

### Add seamless Prayer hero treatment ([#147](https://github.com/rspoelstra216/surfside-tools/pull/147))

- add `surfside-prayer-cta` for the existing Gutenberg Cover block
- retain the editable hero image, wording, and link
- replace the gray overlay with a translucent Surfside navy treatment
- standardize heading, supporting text, and primary button styling
- remove the WordPress block-gap seams above and below the Cover
- include reveal-on-scroll and reduced-motion behavior automatically

### Restore Prayer hero image visibility ([#148](https://github.com/rspoelstra216/surfside-tools/pull/148))

- reduce the Prayer hero navy overlay from 72% to 50%
- stop overriding the Gutenberg Cover block’s configured height
- preserve the editor-controlled image crop and focal positioning
- add a restrained text shadow for readability over the lighter image
- keep the CTA button text crisp by excluding it from the shadow

### Restore Prayer Cover full width ([#149](https://github.com/rspoelstra216/surfside-tools/pull/149))

- remove the Prayer component’s `width: 100%` and `max-width` overrides
- allow Gutenberg’s existing `alignfull` calculations to control the Cover width
- preserve the seamless margins, overlay, content styling, and animation

### Use Surfside brand blue for Ready to Visit ([#150](https://github.com/rspoelstra216/surfside-tools/pull/150))

- Replaced the muted ocean background with a logo-inspired blue-to-ocean gradient
- Increased the service-time cards’ translucent fill and border definition
- Preserved white text and the existing responsive layout
- The previous background read as slate gray rather than Surfside blue. This gives the CTA a clearer brand identity while keeping the service cards visually distinct.

### Lighten the homepage prayer image ([#151](https://github.com/rspoelstra216/surfside-tools/pull/151))

- Reduced the Need Prayer cover overlay from 50% to 38%
- Preserved the ocean-blue tint, white copy, text shadow, and existing layout
- The prayer image now appears overly dark beside the brighter Surfside-blue Ready to Visit section. A lighter overlay restores more of the photograph while retaining text contrast.
- Change is limited to the prayer overlay opacity in `assets/css/design-system.css`

### Enforce the homepage design system ([#152](https://github.com/rspoelstra216/surfside-tools/pull/152))

- Increased Gutenberg button selector specificity so theme colors cannot override Surfside blue and secondary styles
- Removed the automatic 32px WordPress block gap between top-level homepage sections
- Neutralized inaccessible Extendable animation wrappers around the compact Today at Surfside widget
- Replaced header and footer `100vw` breakout sizing with viewport-safe full width

### Bust the footer stylesheet cache ([#153](https://github.com/rspoelstra216/surfside-tools/pull/153))

- Versions `footer.css` with its file modification timestamp
- Retains the plugin version as the fallback
- Matches the existing header asset-loading pattern
- The footer-width correction was deployed, but browsers continued receiving the old stylesheet URL with the static `2.4.0` version. That left the known 8px horizontal overflow in place.

### Add Plan Your Visit schedule and location section ([#154](https://github.com/rspoelstra216/surfside-tools/pull/154))

- add a dedicated `[surfside_visit_details]` shortcode for the Plan Your Visit page
- read service times, service labels, venue, address, and directions from the existing Information dashboard
- add responsive sand-section styling with flexible service cards and a brand-blue directions button
- support any configured number of weekly services without Gutenberg Columns

### Tighten Plan Your Visit details spacing ([#155](https://github.com/rspoelstra216/surfside-tools/pull/155))

- reduce the visit-details section's desktop outer padding from 64px to 48px
- reduce the heading-to-cards gap from 40px to 28px
- reduce service-card vertical padding while preserving typography and responsive sizing
- reduce the cards-to-location gap from 48px to 32px
- retain a comfortable 40px section padding on mobile

### Compact the Plan Your Visit details layout ([#156](https://github.com/rspoelstra216/surfside-tools/pull/156))

- reduce the visit-details heading and supporting-text scale
- make the service cards shorter and slightly narrower
- place the meeting location and **Get Directions** button in one horizontal desktop row
- preserve the centered stacked layout on mobile
- restore the homepage Life at Surfside intro gap that PR #155 unintentionally matched while targeting a shared spacing value

### Redesign visit details as a compact two-column section ([#157](https://github.com/rspoelstra216/surfside-tools/pull/157))

- Reorganizes the Plan Your Visit details section into a two-column desktop layout
- Places the heading, introduction, venue, address, and directions on the left
- Places the two service-time cards on the right
- Preserves the existing stacked layout on smaller screens

### Style native Gutenberg expectation-card grids ([#158](https://github.com/rspoelstra216/surfside-tools/pull/158))

- Adds an explicit card selector for Paragraph blocks inside a native Gutenberg Grid using `surfside-staggered-cards`
- Applies the established white card surface, padding, border, radius, and shadow
- Adds controlled spacing and navy color to each card’s bold first-line title
- The existing generic child selector works for the earlier Columns-based pattern but was not reliably overriding the native Grid/theme styles on the redesigned Plan Your Visit section.

### Enforce native expectation-card styling and timing ([#159](https://github.com/rspoelstra216/surfside-tools/pull/159))

- Makes the native Gutenberg Grid card surface authoritative within post content
- Forces the intended padding, white background, border, radius, shadow, and full-height alignment on direct Paragraph cards
- Extends stagger timing from only the first three children to all six cards with a clear 0.2-second progression
- The grid class was present and its reveal behavior proved the children were being detected, but higher-specificity theme resets were winning over the generic card declarations. The original stagger utility also assigned delays only to the first three cards, causing the second row to reveal immediately.

### Fix Gutenberg Group expectation-card layout ([#160](https://github.com/rspoelstra216/surfside-tools/pull/160))

- Explicitly turns an ordinary Gutenberg Group carrying `surfside-staggered-cards` into the card grid
- Uses three equal columns on desktop, two on tablet, and one on mobile
- Stretches the six direct Paragraph cards evenly across the available content width
- Applies the established card surface directly to those Paragraph children

### Prevent duplicate staggered-card reveal ([#161](https://github.com/rspoelstra216/surfside-tools/pull/161))

- Keeps a styled section visible when it contains a `surfside-staggered-cards` grid
- Leaves the individual card reveal and stagger timing active
- The outer `surfside-section-white` container and each card were both registered reveal targets. The section animated first while its children were also transitioning, making the cards appear to pop in and then reload.
- Only white, sand, or soft design-system sections containing a staggered-card grid are affected. Their cards continue to reveal individually; other section reveals remain unchanged.

### Repeat stagger timing for each card row ([#162](https://github.com/rspoelstra216/surfside-tools/pull/162))

- Repeats the three-card reveal cadence for the second row
- Cards 1 and 4 use a 0.1s delay, 2 and 5 use 0.3s, and 3 and 6 use 0.5s
- Keeps the existing 700ms fade-and-rise duration
- The new six-card grid used one continuous delay sequence through 1.1 seconds. Because second-row cards also begin intersecting later while scrolling, their additional long delays created the apparent pause/reload before they moved into place. The older two-Columns implementation restarted the three-card cadence on each row.

### Trigger staggered cards from the parent grid ([#163](https://github.com/rspoelstra216/surfside-tools/pull/163))

- Observes each `surfside-staggered-cards` parent once instead of observing every child independently
- Keeps all child cards hidden until the parent grid enters the viewport
- Adds `is-visible` to the parent, triggering one coordinated six-card reveal
- Preserves the 700ms transition and repeated row timing from PR #162

### Add Plan Your Visit expectations shortcode ([#164](https://github.com/rspoelstra216/surfside-tools/pull/164))

- Adds `[surfside_visit_expectations]` for the complete “What Should I Expect?” section.
- Recreates the original six cards in a responsive three-column layout.
- Reveals the cards sequentially from one parent trigger, without Gutenberg animation classes.
- Preserves reduced-motion behavior and stacks cleanly on smaller screens.

### Widen Plan Your Visit expectation cards ([#165](https://github.com/rspoelstra216/surfside-tools/pull/165))

- Removes WordPress’s content-width cap from the Shortcode block containing the visit expectations.
- Expands the shortcode’s inner layout from 72rem to the theme’s 80rem wide-content standard.
- The parent Group was full width, but WordPress still constrained the nested Shortcode block to the 42rem content width. This rule targets only the visit-expectations shortcode and allows the three-column card grid to use the intended width.
- Confirmed the selector is scoped to Shortcode blocks containing `.surfside-visit-expectations`.

### Fix Plan Your Visit expectations width ([#166](https://github.com/rspoelstra216/surfside-tools/pull/166))

- Applies the width override directly to the rendered visit-expectations section.
- Removes the unused `.wp-block-shortcode:has(...)` selector.
- Uses border-box sizing so the section’s padding remains inside its full available width.
- On this page WordPress renders the shortcode output directly inside the full-width Group; it does not retain a `.wp-block-shortcode` wrapper. The Group’s constrained-layout rule therefore capped the shortcode’s `<section>` at 42rem.

### Remove duplicate expectations section spacing ([#167](https://github.com/rspoelstra216/surfside-tools/pull/167))

- Removes the automatic top-level block gap from the Group containing `[surfside_visit_expectations]`.
- Removes the outer Group’s duplicate compact-section padding.
- Leaves the shortcode’s own 56px section padding as the single source of vertical spacing.
- Keeps the existing card icons and all animation behavior unchanged.

### Compact Plan Your Visit expectations section ([#168](https://github.com/rspoelstra216/surfside-tools/pull/168))

- Reduces shortcode padding from 56px to 28px above and below.
- Tightens the heading-to-grid gap from 32px to 20px.
- Reduces grid gaps from 24px to 18px.
- Shortens cards from a 184px minimum to 150px and reduces their padding from 28px to 22px.

### Add Twitch-aware Watch Live streaming block ([#169](https://github.com/rspoelstra216/surfside-tools/pull/169))

- adds `[surfside_watch_live]` as a responsive 16:9 streaming section
- detects Twitch online/offline state through the official Twitch player events
- shows the live Twitch player when online and a locally hosted looping announcement video when offline
- falls back to a branded next-service panel when no announcement video is selected
- reads the next livestream from the centralized weekly service schedule and displays a countdown
- adds Twitch channel, announcement video, YouTube, and Facebook controls to Surfside Information

### Start live Twitch playback automatically ([#170](https://github.com/rspoelstra216/surfside-tools/pull/170))

- explicitly requests unmuted Twitch playback when the channel reports online
- preserves visible Twitch controls when a browser applies its own autoplay restriction
- centers the embedded Message Notes title after its semantic change from H1 to H2

### Simplify Watch Live status during broadcasts ([#172](https://github.com/rspoelstra216/surfside-tools/pull/172))

- hides the scheduled service time and countdown while Twitch is online
- leaves only the clear **Live Now** status beside the platform links
- automatically restores the next-service time and countdown when Twitch goes offline

### Disable Extendable animations on public pages ([#173](https://github.com/rspoelstra216/surfside-tools/pull/173))

- removes `ext-animate--on` from rendered public block markup
- leaves Gutenberg editor settings unchanged, avoiding repeated manual cleanup
- preserves Surfside reveal, delayed reveal, and staggered-card animation classes
- uses WordPress's HTML processor rather than broad CSS overrides

### Add dashboard-managed Adult Ministries section ([#174](https://github.com/rspoelstra216/surfside-tools/pull/174))

- add a repeatable Adult Ministries manager to Surfside Information
- support add, edit, remove, and ordered card display
- add the `[surfside_adult_ministries]` shortcode
- render a responsive sand section with centered five-card layouts and staggered Surfside reveals
- keep the shortcode empty until ministries are configured
- document the new public shortcode

### Reorganize staff tools under Site Management ([#175](https://github.com/rspoelstra216/surfside-tools/pull/175))

- replace separate Homepage and Settings quick actions with one Site Management entry
- add a focused Site Management hub for Information, Streaming, Navigation, Ministries, Homepage Photos, and Settings
- split the former long Surfside Information form into four focused category forms
- preserve the existing Homepage Photos and Settings tools and their stored data
- keep the dashboard's information summary while routing management through the new hub
- add automatic creation of the new front-end management pages

### Remove duplicate management entries from dashboard ([#176](https://github.com/rspoelstra216/surfside-tools/pull/176))

- remove Homepage and Settings from the dashboard Website Status cards
- remove the separate Surfside Information management panel
- retain only Weekly Update, Calendar, and Site Management as dashboard destinations
- keep all homepage, information, and settings health evaluation behind the scenes for alerts
- This corrects the incomplete consolidation from #175.

### Replace duplicate quick actions with Manage Website ([#177](https://github.com/rspoelstra216/surfside-tools/pull/177))

- remove the redundant Quick Actions heading and three duplicate cards
- add one full-width Manage Website button below the Weekly Update and Calendar status cards
- size the remaining dashboard action buttons consistently
- fix the Navigation management page fatal error caused by a section metadata variable being overwritten during page-option rendering

### Add curated Ministry events feed ([#178](https://github.com/rspoelstra216/surfside-tools/pull/178))

- add a **Show on Ministries page** checkbox to Calendar Manager events
- add the `[surfside_ministry_events]` shortcode
- show the next six selected future events in a compact responsive card grid
- preserve recurring-event expansion, event detail modals, and the full Events-page link
- hide the section when no events are selected

### Add weekdays to Ministry event cards ([#179](https://github.com/rspoelstra216/surfside-tools/pull/179))

- Adds the day of the week to the curated Ministry events feed:
- date badge: `SAT · AUG 15`
- event details: `Saturday, August 15 · 6:00 PM`
- This is a display-only follow-up to #178.

### Add pale-blue Gutenberg section utility ([#180](https://github.com/rspoelstra216/surfside-tools/pull/180))

- add `surfside-section-blue-soft`
- use the existing pale-blue design token (`#F1F8FE`)
- include the same typography, spacing, button, focus, and responsive behavior as the other Gutenberg section utilities
- document the new utility in the changelog

### Remove gaps between interior page sections ([#181](https://github.com/rspoelstra216/surfside-tools/pull/181))

- remove Extendable/WordPress sibling block gaps between adjacent Surfside design-system sections
- also remove the gap between a full-width Cover hero and the first design-system section
- apply consistently to white, sand, soft off-white, and pale-blue sections
- This fixes the visible white strip above the new pale-blue CTA on both Staff and Give without requiring Gutenberg margin overrides.

### Add centralized Contact details section ([#182](https://github.com/rspoelstra216/surfside-tools/pull/182))

- add an Email field to Surfside Information
- add the `[surfside_contact_details]` shortcode
- render dynamic Visit Us, Service Times, and Contact Us cards
- provide clickable phone, email, and Get Directions links
- generate a responsive embedded map from the centralized address
- add responsive design-system styling and documentation

## [Unreleased]

### Added

- Add centralized contact email management and the `[surfside_contact_details]` public contact-and-map section.

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
