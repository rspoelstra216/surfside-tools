# Changelog

## [3.2.0] - 2026-08-23

### Improved

- Project documentation now reflects the released 3.1.0 baseline and completed Connect architecture. ([#206](https://github.com/rspoelstra216/surfside-tools/pull/206))
- Project documentation is substantially shorter and reflects the current 3.1.0 platform state without duplicating PR-by-PR history. ([#207](https://github.com/rspoelstra216/surfside-tools/pull/207))

### Additional Changes

### Add mobile app giving configuration ([#208](https://github.com/rspoelstra216/surfside-tools/pull/208))

- add a Giving section to the existing Mobile App settings page
- allow the direct Tithely Giving Form URL to be managed in WordPress
- sanitize and store the giving URL with the existing app settings
- expose `app.giving_url` through the existing `/surfside/v1/app` API response

### Show giving configuration in Manage Mobile App ([#209](https://github.com/rspoelstra216/surfside-tools/pull/209))

- add the Giving Form URL to the actual front-end **Manage Mobile App** screen used from the Staff Dashboard
- save the giving URL alongside the existing hero image, focal point, and zoom settings
- preserve all current Home Experience preview/drag/zoom behavior
- place Giving between Home Experience and Save App Settings

### Move contact routing into Site Management ([#210](https://github.com/rspoelstra216/surfside-tools/pull/210))

- remove **Manage Contact Routing** as a separate Staff Dashboard action
- add **Contact Routing** as a card inside **Site Management / Manage Website**
- update the Contact Routing back link to return to Site Management instead of the main dashboard
- keep all existing recipient routing and Cloudflare Turnstile behavior unchanged

### Introduce shared Site Settings hub ([#211](https://github.com/rspoelstra216/surfside-tools/pull/211))

- Establish the management hierarchy we agreed on:
- **Manage Website** = website-specific content and presentation
- **Manage Mobile App** = app-specific presentation and future operational actions such as push notifications
- **Site Settings** = shared/infrequently changed configuration

### Add push notification foundation and sender ([#212](https://github.com/rspoelstra216/surfside-tools/pull/212))

- enable the **Push Notifications** card under Manage Mobile App
- add a staff sender with title, message, and optional app destination
- add a public `/surfside/v1/push/register` endpoint for app devices to register Expo push tokens
- store registered Expo push tokens server-side and never expose them to the app or staff UI
- send notifications through the Expo Push Service in batches of up to 100 devices
- show the current registered-device count and disable sending when no devices are registered

### Fix Mobile App Home Experience navigation ([#213](https://github.com/rspoelstra216/surfside-tools/pull/213))

- restore the dedicated Home Experience editor instead of recursively rendering the Mobile App action hub
- prevent duplicated `/mobile-app/mobile-app/...` URLs that led to Page Not Found
- preserve the existing hero image, drag positioning, zoom, and save behavior
- return directly to Manage Mobile App from the nested editor

### Broaden ministries beyond adult-only groups ([#214](https://github.com/rspoelstra216/surfside-tools/pull/214))

- add a canonical Ministries data model with audience classification
- support Kids, Youth, Adults, and All Ages audiences, including multi-select combinations
- preserve all existing Adult Ministries records as Adults by default
- add a new `[surfside_ministries]` shortcode while keeping `[surfside_adult_ministries]` backward-compatible
- update the public heading from Adult Ministries to Ministries and display audience badges

### Add dedicated Ministries manager ([#215](https://github.com/rspoelstra216/surfside-tools/pull/215))

- add Ministries to the Manage Website hub
- route the staff Ministries page to a dedicated manager instead of the public Ministries page
- allow editing ministry name, icon, schedule, location, description, ordering, and audience tags
- support Kids, Youth, Adults, and All Ages classifications
- preserve existing ministries through the canonical Ministries model introduced in PR #214

### Restore Ministries under Site Settings ([#216](https://github.com/rspoelstra216/surfside-tools/pull/216))

- point the existing Site Settings → Ministries card back to the staff Ministries manager
- remove the duplicate Ministries card that PR #215 added to Manage Website
- keep the dedicated `site-ministries` staff page and the new audience-aware manager from PR #215

### Finish audience-aware Ministries manager ([#217](https://github.com/rspoelstra216/surfside-tools/pull/217))

- keeps Ministries under Site Settings
- updates the dedicated Ministries manager to return to Site Settings rather than Manage Website
- clarifies that ministry records are shared by the website and mobile app
- exposes the existing Kids, Youth, Adults, and All Ages audience selections as “Who is this ministry for?”
- preserves the existing ministry data/migration behavior and leaves Event Manager/Bible Study work untouched

### Migrate legacy Ministries dashboard page ([#218](https://github.com/rspoelstra216/surfside-tools/pull/218))

- migrates the existing `/dashboard/site-ministries/` page from the legacy Site Information ministries editor to `[surfside_staff_ministries_manager]`
- only updates the page when it still contains the known legacy plugin shortcode
- leaves custom WordPress page content untouched

### Finish Ministries manager migration and styling ([#219](https://github.com/rspoelstra216/surfside-tools/pull/219))

- loads the established Site Information manager styles inside the dedicated Ministries manager so its fields, cards, controls, and mobile layout render correctly
- moves the legacy `site-ministries` page migration from `admin_init` to a normal front-end-safe `init` hook
- keeps the migration narrowly limited to the known legacy Ministries shortcode so custom WordPress page content is not overwritten

### Add emoji picker to Ministries manager ([#220](https://github.com/rspoelstra216/surfside-tools/pull/220))

- adds a curated emoji picker to the Ministries manager
- includes Surfside-relevant options such as 🏄 Surfing, 🥋 Jiu Jitsu, 📖 Bible, 🙏 Prayer, ☕ Fellowship, and more
- keeps the existing Icon field editable so any emoji can still be pasted manually
- supports both existing and newly added ministry rows
- adds responsive picker styling and Escape/backdrop close behavior

### Add Bible Study event classification ([#221](https://github.com/rspoelstra216/surfside-tools/pull/221))

- adds **List as Bible Study** to the existing Calendar Manager while keeping **Show on Ministries page** independent
- allows an event to be Ministry only, Bible Study only, both, or neither
- adds an optional Bible Study audience (Kids, Youth, Adults, All Ages)
- adds a Bible Study badge to managed event records
- enriches the existing mobile Events API with `is_ministry`, `is_bible_study`, and `bible_study_audience`
- adds `/surfside/v1/bible-studies` for the app's separate Current Bible Studies section

### Share audience across Ministries and Bible Studies ([#222](https://github.com/rspoelstra216/surfside-tools/pull/222))

- moves Audience out of the Bible Study-specific panel and places it directly beneath **Show on Ministries page**
- keeps one shared Audience value for an event, usable by Ministries and Bible Studies
- only shows the Audience dropdown when **Show on Ministries page** or **List as Bible Study** is checked
- hides the dropdown again if both classifications are unchecked
- preserves audience values already entered through the original Bible Study field
- exposes the shared `audience` value to the mobile Events and Bible Studies API responses while retaining `bible_study_audience` for compatibility

### Separate featured ministries from ministry listings ([#223](https://github.com/rspoelstra216/surfside-tools/pull/223))

- adds a **Featured Ministry** checkbox that only appears when **Show on Ministries page** is selected
- preserves existing ministry-card behavior by treating legacy ministry events as featured until explicitly changed
- renames the primary featured-card shortcode to `[surfside_featured_ministries]`
- keeps `[surfside_ministry_events]` as a temporary compatibility alias so existing pages do not break immediately
- adds `[surfside_all_ministries]` for a formatted list of non-featured ministries
- lists each non-featured ministry once, with audience, recurrence, location, next occurrence, time, and a short description

### Move Featured Ministry control to Ministry Manager ([#224](https://github.com/rspoelstra216/surfside-tools/pull/224))

- adds **Featured Ministry** to each ongoing Ministry Manager record
- makes the Ministry Manager the source of truth for website featured placement
- removes the Featured Ministry control from Calendar Manager so event classification and ministry presentation are not duplicated
- keeps existing Ministry Manager records featured by default until staff explicitly unchecks them
- changes `[surfside_featured_ministries]` to render featured Ministry Manager records
- changes `[surfside_all_ministries]` to render non-featured Ministry Manager records as a formatted list

### Polish ministry website presentation ([#225](https://github.com/rspoelstra216/surfside-tools/pull/225))

- restores white backgrounds for the ministry cards
- changes the public featured-section heading from **Featured Ministries** to **Ministries**
- changes the non-featured section heading from **More Ministries** to **Ministry Directory**
- makes the directory much more compact for a future list of 10–20 ministries
- uses a two-column directory on desktop and one column on mobile
- keeps audience, schedule, location, and a shortened description without oversized cards

### Make Serve & Get Involved dynamic ([#226](https://github.com/rspoelstra216/surfside-tools/pull/226))

- keeps `[surfside_featured_ministries]` dynamic from the Ministry Manager
- restores the original **Serve & Get Involved** visual structure instead of cardifying it
- keeps **Use Your Gifts to Serve Others** and the original intro copy
- renders featured ministries in the same open two-column layout as the old manually maintained section
- keeps the original **Interested in Serving?** closing copy and **Contact Us About Serving** CTA
- makes the section full-width while keeping the content constrained for readability

### Restore Serve section sand layout ([#227](https://github.com/rspoelstra216/surfside-tools/pull/227))

- restores the Serve & Get Involved section to the Surfside sand background
- fixes the full-width breakout so the shortcode is not constrained by the Gutenberg content column
- keeps the dynamic Ministry Manager content in the open two-column layout
- preserves the original intro, subheading, closing copy, and CTA
- keeps the inner content constrained to 1180px for readability while the sand band spans the viewport

### Align featured ministry cards and compact directory ([#228](https://github.com/rspoelstra216/surfside-tools/pull/228))

- keeps the Serve & Get Involved section as a full-width sand band
- makes featured ministries use the same established card markup/classes as the former Adult Ministries shortcode
- preserves icon, audience, schedule, location, and description on featured cards
- keeps the original Serve intro, subheading, closing copy, and CTA
- makes the Ministry Directory intentionally compact for 10–20+ entries
- removes directory descriptions and uses a three-column desktop list with only name, audience, schedule, and location

### Fix featured ministry full-width layout ([#229](https://github.com/rspoelstra216/surfside-tools/pull/229))

- applies the post-merge full-width fix in a new PR rather than modifying merged PR #228
- marks the Featured Ministries shortcode block as `alignfull` so Gutenberg/theme layout handles the full-width section natively
- keeps the Serve & Get Involved sand band and the established Adult Ministries card structure
- preserves the compact Ministry Directory layout

### Restore proven Featured Ministries layout ([#230](https://github.com/rspoelstra216/surfside-tools/pull/230))

- removes the custom `alignfull`/viewport-width workaround from Featured Ministries
- puts the dynamic Serve & Get Involved section back on the exact `surfside-adult-ministries` section foundation introduced in PR #174
- reuses the proven full-width sand `::before` background breakout, 72rem centered inner container, established ministry grid, and white card styling
- keeps the dynamic Ministry Manager data, audience badges, Serve intro/subheading, closing copy, and CTA
- leaves the compact Ministry Directory unchanged

### Make Ministry Directory full width and move serving CTA ([#231](https://github.com/rspoelstra216/surfside-tools/pull/231))

- keeps the Featured Ministries / Serve & Get Involved section focused on the featured ministry cards only
- moves **Interested in Serving?**, its supporting text, and the **Contact Us About Serving** CTA beneath the Ministry Directory
- makes `[surfside_all_ministries]` use the same proven full-width breakout pattern as the old Adult Ministries section
- keeps the directory itself compact: three columns on desktop, two on tablet, one on mobile
- leaves the featured card layout and Ministry Manager data model unchanged

### Add audience filtering to Ministry Directory ([#232](https://github.com/rspoelstra216/surfside-tools/pull/232))

- removes age/audience badges from the public Featured Ministry cards
- removes age/audience text from the default Ministry Directory cards
- adds compact audience filter buttons above the Ministry Directory
- keeps featured ministries hidden from the default directory view so the page does not duplicate the featured cards
- when an audience filter is selected, includes matching ministries from both featured and non-featured records
- Clear filter returns to the compact default directory view

### Make Ministry Directory entries interactive ([#233](https://github.com/rspoelstra216/surfside-tools/pull/233))

- centers Ministry Directory entry names to make the compact grid feel intentional
- turns each directory tile into a clickable detail control
- opens a small modal with the ministry name/icon, usual schedule, location, and full description
- keeps audience classifications hidden from normal website presentation
- preserves the existing audience filter behavior, including featured ministries appearing only when a matching filter is selected
- keeps the directory compact and full width

### Prepare Ministries for app integration ([#234](https://github.com/rspoelstra216/surfside-tools/pull/234))

- Centers the Ministry Directory heading and adds the canonical Ministries mobile API endpoint so the app can consume Ministry Manager records directly. The endpoint returns ministry details, audiences, and featured status while preserving manager order. Existing website filters, modal behavior, and Bible Study handling remain unchanged.

### Center Ministry Directory intro ([#235](https://github.com/rspoelstra216/surfside-tools/pull/235))

- Centers the Ministry Directory intro paragraph beneath the already-centered heading. Leaves the audience filter and directory grid alignment unchanged.

### Center Ministry Directory filters ([#236](https://github.com/rspoelstra216/surfside-tools/pull/236))

- Centers the Ministry Directory audience filter row beneath the centered heading and intro. Directory cards, modal behavior, filtering logic, CTA, and mobile API remain unchanged.

### Add ministry contact information ([#237](https://github.com/rspoelstra216/surfside-tools/pull/237))

- add optional contact name, email, and phone to each Ministry Manager record
- add a Default ministry contact email field above the manager; blank ministry emails fall back to this value
- include resolved contact information in `/surfside/v1/ministries`
- add contact actions to the website Ministry Directory details modal
- hide the Call action when no ministry phone number is entered

### Scope ministry contact runtime to ministry pages ([#238](https://github.com/rspoelstra216/surfside-tools/pull/238))

- stop Ministry contact footer hooks from loading ministry records on every frontend page
- only render Ministry Manager contact controls when the staff Ministry Manager shortcode is present
- only build public Ministry Directory contact data when the directory shortcode is present
- avoid capability checks on unrelated POST requests by checking for the ministry nonce first

### Contain expensive staff runtime hooks ([#239](https://github.com/rspoelstra216/surfside-tools/pull/239))

- stop the completed legacy Ministries page migration from polling on every WordPress request
- scope the Featured Ministry manager footer helper to the dedicated Ministry Manager page
- scope Bible Study and shared audience manager footer helpers to Calendar Manager only
- remove the corresponding wp-admin footer hooks where these front-end dashboard helpers are not needed

### Diagnostic: isolate later runtime modules ([#240](https://github.com/rspoelstra216/surfside-tools/pull/240))

- Production testing shows Surfside Tools disabled drops CPU to roughly 0–5%, while enabling the plugin eventually drives CPU into the 75–84% range and causes 503/database failures. PR #239 removed several obvious broad hooks but did not eliminate the runaway load.
- Temporarily do not load the later admin/productivity enhancement group while preserving the core public site, APIs, ministries, calendar rendering, Today portal, header/footer, and primary dashboard modules.
- Disabled for this test:
- calendar suggestion/refinement helpers

### Rollback to post-PR #215 state ([#241](https://github.com/rspoelstra216/surfside-tools/pull/241))

- Restore the plugin codebase exactly to the repository tree that existed immediately after PR #215 (`Add dedicated Ministries manager`) merged on Aug. 17.
- Production testing has repeatedly shown Surfside Tools disabled at roughly 0–5% CPU, while enabling the current plugin drives CPU into the 70–80% range and causes 503/database failures. Diagnostic isolation in #239 and #240 did not eliminate the runaway load.
- This rollback removes all code changes introduced after #215 so we can test a known earlier baseline and determine whether the instability was introduced somewhere in PRs #216–#240.
- This intentionally removes functionality added after #215, including later ministry refinements, Bible Study integrations, directory/contact work, runtime diagnostics, and other subsequent changes. Data already stored in WordPress is not deleted by this code rollback, but newer fields/features will not be available while this version is deployed.

### Restore Ministry website shortcodes safely ([#242](https://github.com/rspoelstra216/surfside-tools/pull/242))

- restore `[surfside_featured_ministries]` and `[surfside_all_ministries]` so the current Ministries page renders correctly again
- preserve the existing Ministry Manager data and audience classifications
- preserve the saved Featured Ministry flag in the canonical ministry model
- restore the centered Ministry Directory intro/filter layout, compact cards, audience filtering, details modal, and serving CTA

### Revert ministry shortcode restoration ([#243](https://github.com/rspoelstra216/surfside-tools/pull/243))

- Production CPU returned to ~75% immediately after PR #242 was deployed. This PR reverts #242 completely and restores the exact pre-#242 code for the three files it changed.
- After deploy, verify Resource Usage returns to the low baseline before any further ministry restoration work.

### Diagnostic: remove push page init hook ([#244](https://github.com/rspoelstra216/surfside-tools/pull/244))

- Test whether the Push Notifications page-ensure hook is contributing to the production CPU/database spikes.
- remove only the `add_action('init', 'surfside_tools_ensure_push_notifications_page', 84)` hook
- leave push token registration, REST route registration, sender UI, and send behavior unchanged
- 1. Keep Surfside Tools deactivated until this PR is deployed.

### Diagnostic: disable remaining page ensure init hooks ([#245](https://github.com/rspoelstra216/surfside-tools/pull/245))

- Test whether the remaining post-v3.1.0 staff-page creation checks are causing the production CPU/database spikes.
- stop running the Site Settings page ensure on every WordPress `init`
- unhook the Home Experience page ensure from global `init`
- keep both shortcodes/features otherwise intact

### Restore Ministry Manager foundation ([#246](https://github.com/rspoelstra216/surfside-tools/pull/246))

- restore the final pre-contact Ministry Manager UI from the Aug. 18 build
- return Ministries to the Site Settings workflow
- restore the curated emoji picker and direct emoji editing
- restore the clearer audience wording and shared website/app description
- restore the Site Information manager assets so the manager fields/cards render correctly

### Fix Site Settings Ministries route ([#247](https://github.com/rspoelstra216/surfside-tools/pull/247))

- point the Site Settings → Ministries card to the dedicated `/dashboard/site-ministries/` manager route instead of the public `/ministries/` page
- restore the route behavior originally introduced in PR #216

### Restore Featured Ministry selection ([#248](https://github.com/rspoelstra216/surfside-tools/pull/248))

- restore a Featured Ministry boolean to the canonical ministry data model
- add a Featured Ministry checkbox to each record in the dedicated Ministry Manager
- keep existing ministry records featured by default until staff explicitly changes and saves them
- start newly added ministries as non-featured
- describe Featured Ministry as controlling the Serve & Get Involved block

### Restore public featured ministries block ([#249](https://github.com/rspoelstra216/surfside-tools/pull/249))

- restore `[surfside_featured_ministries]` using the canonical Ministry Manager records
- render only ministries whose Featured Ministry checkbox is enabled
- reuse the current Ministries card presentation and audience labels
- preserve the polished public heading from the original #225 implementation

### Restore compact Ministry Directory ([#250](https://github.com/rspoelstra216/surfside-tools/pull/250))

- restore `[surfside_all_ministries]` from the final compact directory design
- show only Ministry Manager records that are not marked Featured Ministry
- use the three-column desktop / two-column tablet / one-column mobile layout from #228
- show name, icon, audience, schedule, and location without long descriptions
- keep Featured Ministries behavior unchanged

### Restore Ministry Directory audience filters ([#251](https://github.com/rspoelstra216/surfside-tools/pull/251))

- restore the audience filters from PR #232
- remove Kids / Youth / Adults / All Ages labels from the normal public ministry presentation
- remove those audience badges from Featured Ministries as well
- keep the default Ministry Directory limited to non-featured ministries
- when a filter is selected, show all matching ministries, including featured ministries
- Clear filter returns to the default non-featured directory view

### Restore interactive Ministry Directory details ([#252](https://github.com/rspoelstra216/surfside-tools/pull/252))

- restore clickable Ministry Directory details from PR #233
- keep the compact directory presentation from #251 instead of expanding every entry into a tall card
- remove the repeated “View details” label from every ministry
- add one subtle instruction beneath the Ministry Directory intro: “Select a ministry below to view more details.”
- keep schedule/location visible in the compact directory row while the dialog provides the full description
- preserve the audience filters and filtered featured-ministry behavior

### Keep Ministry Directory compact ([#253](https://github.com/rspoelstra216/surfside-tools/pull/253))

- follow up the merged interactive-directory PR with the compact presentation adjustment
- remove the repeated “View details” label from every ministry
- restore the compact row sizing from the prior directory iteration
- keep schedule and location visible in each compact row
- add one subtle header hint: “Select a ministry below to view more details.”
- keep the whole row clickable for the existing details dialog

### Restore ministry contact information ([#254](https://github.com/rspoelstra216/surfside-tools/pull/254))

- restore optional contact name, email, and phone fields in the Ministry Manager
- restore a Default ministry contact email above the manager
- use the ministry-specific email when present and fall back to the default email when blank
- keep phone ministry-specific only
- add contact information to the existing compact Ministry Directory details dialog
- preserve the current compact directory, audience filters, featured behavior, and modal layout

### Polish Ministry Manager and contact display ([#255](https://github.com/rspoelstra216/surfside-tools/pull/255))

- keep ministry email actions usable while always showing the actual email address so it can be copied even if the computer has no default mail app configured
- stop forcing desktop users into a phone application; show the ministry phone number as plain selectable text instead
- make Ministry Manager records easier to scan with alternating backgrounds and a strong left accent
- add a small `Ministry N — Name` heading to each record so adjacent ministries are visually distinct while scrolling
- reduce card padding, grid gaps, contact-box padding, and description height so one ministry does not consume nearly the whole screen
- preserve all existing fields and behavior; this is presentation-only

### Improve Ministry Manager spacing ([#256](https://github.com/rspoelstra216/surfside-tools/pull/256))

- keep the compact Ministry Manager card design from #255
- add clearer vertical separation between the card heading, primary fields, audience controls, contact fields, Featured toggle, description, and actions
- add a subtle divider below the Ministry N heading
- slightly increase space between ministry cards while preserving the alternating backgrounds
- keep description height compact

### Make ministry emoji compact and clickable ([#257](https://github.com/rspoelstra216/surfside-tools/pull/257))

- remove the visible Choose button from Ministry Manager cards
- make the emoji itself the picker control
- reduce the icon column to a compact square instead of a wide field
- keep keyboard access with Enter/Space
- preserve the existing emoji picker and saved values

### Auto-format ministry contact phone numbers ([#258](https://github.com/rspoelstra216/surfside-tools/pull/258))

- format 10-digit North American ministry phone numbers as `(321) 555-1234`
- accept an optional leading `1` and normalize it to the same display format
- format existing saved values when the Ministry Manager loads
- format newly entered values when the phone field loses focus
- show the same formatted number in the public ministry detail dialog
- leave non-10-digit values untouched rather than guessing

### Restore Ministries mobile API ([#259](https://github.com/rspoelstra216/surfside-tools/pull/259))

- restore public `GET /wp-json/surfside/v1/ministries`
- return canonical Ministry Manager records in manager order
- include key, icon, name, schedule, location, description, audiences, audience labels, and Featured Ministry status
- include resolved contact data using ministry-specific email when present and the default ministry email otherwise
- keep phone ministry-specific only

### Restore Ministry Directory layout and serving CTA ([#260](https://github.com/rspoelstra216/surfside-tools/pull/260))

- make the Ministry Directory section break out to full viewport width while keeping its content in the established 72rem inner container
- center the Ministry Directory heading, description/detail hint, and audience filter controls
- restore the `Interested in Serving?` closing CTA beneath the directory
- link `Contact Us About Serving` to the existing Contact page
- preserve the current compact directory rows, audience filtering, details dialog, contact display, and manager behavior

### Tighten Ministry Directory CTA spacing ([#261](https://github.com/rspoelstra216/surfside-tools/pull/261))

- move the serving CTA inside the Ministry Directory inner container instead of appending it after the section
- remove the extra bottom-padding + top-margin combination that created the large white gap
- tighten CTA spacing slightly while preserving the full-width directory layout

### Restore Calendar Ministry and Bible Study classifications ([#262](https://github.com/rspoelstra216/surfside-tools/pull/262))

- present **Ministry** and **Bible Study** as independent checkboxes in Calendar Manager
- preserve the existing Calendar Manager Ministry event flag while simplifying its label from `Show on Ministries page` to `Ministry`
- persist Bible Study classification on Surfside events
- show Ministry and Bible Study badges in the Calendar Manager event list
- when an event is saved with **Ministry** checked, create a non-featured Ministry Manager entry if that event is not already linked to one
- avoid duplicates by reusing an existing Ministry Manager record with the same name

### Add draft and published ministry status ([#263](https://github.com/rspoelstra216/surfside-tools/pull/263))

- add a persistent `published` status to Ministry Manager records
- existing ministries default to Published so nothing currently live is hidden
- add a Published checkbox and Draft/Published status badge to each Ministry Manager card
- new ministries created manually in Ministry Manager start as Draft until explicitly published
- ministries created from Calendar Manager via the new Ministry checkbox are immediately marked Draft after the seed completes
- existing same-name Ministry Manager records reused by Calendar Manager are never unpublished

### Clarify Ministry draft guidance ([#264](https://github.com/rspoelstra216/surfside-tools/pull/264))

- update the Calendar Manager ministry classification help text to explain that selecting Ministry creates a draft Ministry Manager entry
- direct staff to the Ministry Manager to finish details and publish

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