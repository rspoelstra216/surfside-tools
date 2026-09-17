# Changelog

## [3.3.0] - 2026-09-17

### Added

- Added an in-page Bible passage viewer for Scripture references in website Message Notes. ([#280](https://github.com/rspoelstra216/surfside-tools/pull/280))
- Website Scripture passages can now be switched among the licensed Bible translations. ([#281](https://github.com/rspoelstra216/surfside-tools/pull/281))
- Staff can schedule temporary featured content from a dedicated **Manage Mobile App → Featured Announcement** screen. ([#290](https://github.com/rspoelstra216/surfside-tools/pull/290))
- One canonical registry for the current staff page tree. ([#383](https://github.com/rspoelstra216/surfside-tools/pull/383))

### Improved

- Scripture references can now be read without leaving the Surfside website. ([#280](https://github.com/rspoelstra216/surfside-tools/pull/280))
- Translation choices are organized English-first with the supported additional languages after them. ([#281](https://github.com/rspoelstra216/surfside-tools/pull/281))
- Website Scripture translation selection now uses a compact Surfside-styled dropdown instead of a browser-default field plus duplicate version label. ([#282](https://github.com/rspoelstra216/surfside-tools/pull/282))
- Website Scripture viewer now makes the translation selector more discoverable. ([#283](https://github.com/rspoelstra216/surfside-tools/pull/283))
- Dashboard management navigation is clearer and separates website-only, app-only, and shared church configuration. ([#284](https://github.com/rspoelstra216/surfside-tools/pull/284))
- Site Settings is now presented as Church Settings. ([#284](https://github.com/rspoelstra216/surfside-tools/pull/284))
- Ministries is managed from one shared location. ([#284](https://github.com/rspoelstra216/surfside-tools/pull/284))
- Routine weekly rollover no longer adds the redundant stale-content sentence to the Weekly Update card. ([#284](https://github.com/rspoelstra216/surfside-tools/pull/284))
- Church Settings now separates shared church content from technical integrations more clearly. ([#285](https://github.com/rspoelstra216/surfside-tools/pull/285))
- Integrations is substantially more compact and technical in presentation. ([#285](https://github.com/rspoelstra216/surfside-tools/pull/285))
- Contact Routing is focused only on message recipients. ([#285](https://github.com/rspoelstra216/surfside-tools/pull/285))
- Child settings pages now return to Church Settings instead of Manage Website. ([#285](https://github.com/rspoelstra216/surfside-tools/pull/285))
- Integrations now has one consistent collapsible presentation from top to bottom. ([#286](https://github.com/rspoelstra216/surfside-tools/pull/286))
- Shared technical settings can be saved with one action instead of several separate save buttons. ([#286](https://github.com/rspoelstra216/surfside-tools/pull/286))
- Integrations rows now use consistent spacing, sizing, and status alignment. ([#287](https://github.com/rspoelstra216/surfside-tools/pull/287))
- Streaming is organized with the rest of Surfside’s technical integrations. ([#287](https://github.com/rspoelstra216/surfside-tools/pull/287))
- Church Settings is simplified to shared content/admin areas rather than technical services. ([#287](https://github.com/rspoelstra216/surfside-tools/pull/287))
- Integrations now uses a cleaner two-column row layout without inconsistent status labels. ([#288](https://github.com/rspoelstra216/surfside-tools/pull/288))
- Featured Announcement now has its own management screen under Manage Mobile App. ([#291](https://github.com/rspoelstra216/surfside-tools/pull/291))
- Mobile App management cards now align more consistently across the three-column desktop layout. ([#292](https://github.com/rspoelstra216/surfside-tools/pull/292))
- Featured Announcement now aligns with the other Mobile App management action buttons in the desktop three-card layout. ([#293](https://github.com/rspoelstra216/surfside-tools/pull/293))
- Mobile App management action labels now stay visually consistent without wrapping. ([#294](https://github.com/rspoelstra216/surfside-tools/pull/294))
- Reduced dead/legacy code without changing current behavior. ([#334](https://github.com/rspoelstra216/surfside-tools/pull/334))
- Page provisioning runs only when the staff-page schema changes. ([#383](https://github.com/rspoelstra216/surfside-tools/pull/383))
- Nested pages use their complete parent path. ([#383](https://github.com/rspoelstra216/surfside-tools/pull/383))
- Custom page content is left untouched when it is no longer plugin-managed. ([#383](https://github.com/rspoelstra216/surfside-tools/pull/383))
- Mobile App settings now have one authoritative management path. ([#384](https://github.com/rspoelstra216/surfside-tools/pull/384))
- wp-admin Mobile App continues to provide navigation without maintaining a second settings implementation. ([#384](https://github.com/rspoelstra216/surfside-tools/pull/384))
- Firebase staff authorization now has one authoritative UID/Tools-role path. ([#385](https://github.com/rspoelstra216/surfside-tools/pull/385))
- Normal WordPress logout clears both Surfside Tools custom session cookies. ([#385](https://github.com/rspoelstra216/surfside-tools/pull/385))
- The existing custom-session DELETE endpoint clears both Firebase and WordPress Tools sessions. ([#385](https://github.com/rspoelstra216/surfside-tools/pull/385))
- Staff Dashboard has one authoritative renderer. ([#386](https://github.com/rspoelstra216/surfside-tools/pull/386))
- Volunteer Needs has explicit Member Engagement ownership while retaining its existing mobile API. ([#386](https://github.com/rspoelstra216/surfside-tools/pull/386))
- Volunteer Needs management renders its final navigation and descriptive copy directly. ([#386](https://github.com/rspoelstra216/surfside-tools/pull/386))
- ### Removed ([#386](https://github.com/rspoelstra216/surfside-tools/pull/386))
- Obsolete first-generation Staff Dashboard renderer. ([#386](https://github.com/rspoelstra216/surfside-tools/pull/386))
- Late `remove_shortcode()` / re-register Dashboard replacement hook. ([#386](https://github.com/rspoelstra216/surfside-tools/pull/386))
- Dashboard Settings `do_shortcode_tag` rewrite. ([#386](https://github.com/rspoelstra216/surfside-tools/pull/386))
- Member Engagement regex/string mutation of Volunteer Needs HTML. ([#386](https://github.com/rspoelstra216/surfside-tools/pull/386))
- Hidden Volunteer Needs dependency on the Mobile App hub. ([#386](https://github.com/rspoelstra216/surfside-tools/pull/386))

### Fixed

- Retires the active request-time page creation/repair hooks previously registered by multiple management modules. ([#383](https://github.com/rspoelstra216/surfside-tools/pull/383))
- Prevents the legacy wp-admin form from wiping newer app settings stored in the same option. ([#384](https://github.com/rspoelstra216/surfside-tools/pull/384))
- Aligns Giving management with the administrator-only Integrations screen. ([#384](https://github.com/rspoelstra216/surfside-tools/pull/384))
- Limits WordPress Tools credential login to five failed attempts per username in a 15-minute window. ([#385](https://github.com/rspoelstra216/surfside-tools/pull/385))
- Removes the superseded Firebase email-to-WordPress authorization generation. ([#385](https://github.com/rspoelstra216/surfside-tools/pull/385))
- Removes the unused older Firebase email/password login markup. ([#385](https://github.com/rspoelstra216/surfside-tools/pull/385))
- ### Removed ([#385](https://github.com/rspoelstra216/surfside-tools/pull/385))
- `firebase-permission-seeding.php`, whose pre-dispatch behavior is now owned directly by the canonical Firebase session route. ([#385](https://github.com/rspoelstra216/surfside-tools/pull/385))

### Additional Changes

### Document Surfside Tools 3.2.0 baseline ([#265](https://github.com/rspoelstra216/surfside-tools/pull/265))

- roll README, development status, handbook, and roadmap forward to the released 3.2.0 baseline
- consolidate the generated 3.2.0 changelog from PR-by-PR history into release-level outcomes
- document the canonical Ministry Manager, Featured vs Published behavior, contacts/default contact, Calendar Ministry/Bible Study workflow, public Ministry Directory, and mobile Ministries API
- record the durable runtime rule learned during production stabilization: staff page-ensure/migration work must not run globally on ordinary WordPress requests
- document Giving and push-notification server plumbing as part of the shared app-services baseline

### Restore Bible Studies mobile API ([#266](https://github.com/rspoelstra216/surfside-tools/pull/266))

- restore public `GET /wp-json/surfside/v1/bible-studies`
- return each active Bible Study calendar series once rather than every occurrence
- use the next occurrence for date/time and include the recurrence label plus up to 12 upcoming dates
- automatically omit Bible Study series that no longer have a future occurrence
- preserve the independent Ministry/Bible Study classifications already in Calendar Manager

### Add YouVersion integration foundation ([#267](https://github.com/rspoelstra216/surfside-tools/pull/267))

- add protected YouVersion configuration under Mobile App settings
- add a server-side YouVersion API client foundation
- keep the integration credential server-side and out of mobile API responses

### Move YouVersion key to Site Settings integrations ([#268](https://github.com/rspoelstra216/surfside-tools/pull/268))

- move YouVersion credential management out of Mobile App settings
- add YouVersion to the staff-facing Site Settings → Integrations page
- store the App Key in a dedicated integrations option
- keep the server-side client backward-compatible with any key saved by the initial foundation PR

### Add YouVersion connection test ([#269](https://github.com/rspoelstra216/surfside-tools/pull/269))

- add a server-side **Test YouVersion Connection** action under Site Settings → Integrations
- call the official `/v1/bibles` endpoint with the saved App Key
- report whether authentication succeeds and how many English Bible versions are currently accessible
- show a small sample of accessible version names/abbreviations for license/access verification

### Improve YouVersion diagnostics and integrations UI ([#270](https://github.com/rspoelstra216/surfside-tools/pull/270))

- show the actual YouVersion HTTP status when connection validation fails
- surface common API error messages without exposing the App Key
- compact the YouVersion Integration card for a tech-facing settings console
- place the key field, Save, and Test controls on one row on desktop
- reduce card padding, use a small status pill, and keep removal/help text secondary

### Fix YouVersion Bible validation request ([#271](https://github.com/rspoelstra216/surfside-tools/pull/271))

- simplify the YouVersion connection test to the documented `GET /v1/bibles` request with no query parameters
- remove the `language_ranges=en` and `page_size=99` parameters that were producing HTTP 422
- report the total accessible Bible versions and retain the compact version sample UI

### Improve YouVersion test return and diagnostics ([#272](https://github.com/rspoelstra216/surfside-tools/pull/272))

- return Save/Test actions directly to the YouVersion section using a section anchor
- add scroll margin so the card lands cleanly below the top of the page
- expand YouVersion API error parsing to inspect nested `errors`, `details`, and validation error structures
- keep the App Key protected and never display raw API response bodies

### Fix YouVersion language range parameter ([#273](https://github.com/rspoelstra216/surfside-tools/pull/273))

- update the YouVersion `/v1/bibles` connection test to send the required `language_ranges[]` query parameter
- use `en` for the validation request so the test returns licensed English Bible versions
- keep the compact Integrations UI and section-anchor return behavior

### Add YouVersion passage proof test ([#274](https://github.com/rspoelstra216/surfside-tools/pull/274))

- add a compact `Verse` test alongside the existing YouVersion connection test
- fetch BSB John 3:16 from YouVersion server-side
- fetch BSB version metadata separately so required copyright attribution is displayed with the passage
- show YouVersion's version deep link in the proof result
- keep the App Key server-side and never expose it

### Add version-aware YouVersion mobile API ([#275](https://github.com/rspoelstra216/surfside-tools/pull/275))

- add `GET /wp-json/surfside/v1/bible/versions` for licensed English Bible versions
- add `GET /wp-json/surfside/v1/bible/passage` for app-ready passage lookup
- default passage lookup to NIV (YouVersion version ID 111)
- allow explicit Bible selection by version ID or abbreviation
- return passage text, canonical reference, version metadata, required attribution, and an Explore More Bible.com link
- keep the YouVersion App Key entirely server-side

### Fix YouVersion version resolver request ([#276](https://github.com/rspoelstra216/surfside-tools/pull/276))

- normalize Bible collection requests to the exact query shape already verified against YouVersion
- convert `language_ranges[]=en*` to `language_ranges[]=en`
- remove `page_size` from Bible collection requests because it caused validation failures for this application
- leave direct NIV lookup and passage behavior unchanged

### Preserve YouVersion diagnostic results ([#277](https://github.com/rspoelstra216/surfside-tools/pull/277))

- keep licensed Bible versions visible after running the Verse proof
- keep the verse proof available after rerunning the connection/version test
- show Available versions and Verse proof as independent collapsed diagnostic sections
- retain diagnostic state briefly (5 minutes) instead of deleting it immediately after render

### Polish YouVersion diagnostic panels ([#278](https://github.com/rspoelstra216/surfside-tools/pull/278))

- style Available versions and Verse proof as consistent compact diagnostic panels
- replace native-looking disclosure markers with aligned chevrons
- move version count / verse reference into secondary summary metadata
- preserve collapsed-by-default behavior and existing YouVersion functionality
- keep responsive single-column version list on smaller screens
- This is intentionally limited to the YouVersion card; broader Site Settings UI cleanup can remain a separate initiative.

### Expose multilingual YouVersion versions ([#279](https://github.com/rspoelstra216/surfside-tools/pull/279))

- expand the public `/surfside/v1/bible/versions` source from English-only to the six Surfside-supported language ranges
- supported languages: English, Spanish, Portuguese, Vietnamese, French, and German
- preserve NIV as the fixed default
- keep the existing passage endpoint and abbreviation-based switching contract intact
- continue returning `language_tag` so the mobile app can present English first and group all other languages under **Other languages**
- deduplicate versions by YouVersion ID and cache the combined supported list for one hour

### Add push notification device preferences ([#295](https://github.com/rspoelstra216/surfside-tools/pull/295))

- Builds the anonymous device/audience foundation for Surfside push notifications.
- Migrates existing token-only registrations into anonymous device records
- Stores notification preferences per Expo push token
- Supports Church Updates, Events & Ministries, Kids Ministry, and Livestream Reminders

### Polish push notification sender ([#296](https://github.com/rspoelstra216/surfside-tools/pull/296))

- Finishes the staff push-notification sender after successful end-to-end audience testing.
- Fixes **Open in app → Home** so it explicitly routes to Home instead of sending a blank destination
- Shows current subscriber counts beside each notification audience
- Preserves entered form values when validation fails

### Clean stale push registrations ([#297](https://github.com/rspoelstra216/surfside-tools/pull/297))

- Fixes stale/phantom notification registrations discovered during subscriber-count testing.
- Makes the original token-only migration one-time instead of re-importing legacy tokens on every read
- Deletes the legacy token option after migration so old tokens cannot be resurrected
- Parses Expo push ticket responses and removes tokens immediately when Expo reports `DeviceNotRegistered`

### Process Expo receipts and clean legacy registrations ([#298](https://github.com/rspoelstra216/surfside-tools/pull/298))

- Finishes stale push-registration cleanup after testing showed the initial Expo push ticket is not sufficient to identify all dead devices.
- Stores Expo push receipt IDs for successful tickets
- Checks pending receipts on later visits to the Push Notifications sender
- Removes tokens when a later receipt reports `DeviceNotRegistered`

### Add This Week announcement curation ([#299](https://github.com/rspoelstra216/surfside-tools/pull/299))

- Builds the Tools/API foundation for the new **This Week at Surfside** app experience.
- Adds **This Week** under Manage Mobile App
- Lists the currently imported weekly announcements with explicit checkboxes for inclusion in the app weekly view
- Leaves the existing DOCX/weekly announcement workflow unchanged

### Remove This Week announcement curation ([#300](https://github.com/rspoelstra216/surfside-tools/pull/300))

- Removes the extra staff workflow that was added for curating announcements into **This Week at Surfside**.
- Removes the This Week card from Manage Mobile App
- Removes the This Week manager/page registration
- Removes the `app.this_week.announcements` API extension

### Disable Featured Announcement init page ensure ([#301](https://github.com/rspoelstra216/surfside-tools/pull/301))

- Removes the global WordPress `init` execution path for the Featured Announcement staff-page ensure routine.
- Why:
- PR #291 introduced `add_action('init','surfside_tools_ensure_featured_announcement_page',84)`.
- This is the same page-ensure-on-every-request pattern previously removed in PRs #244/#245 during the earlier hosting CPU/database incident.

### Add optional event grouping for app presentation ([#302](https://github.com/rspoelstra216/surfside-tools/pull/302))

- Adds a lightweight way to group separate recurring calendar events together in the mobile app without changing the underlying event/recurrence model.
- Adds an optional **Event Group** field to the existing calendar editor, immediately before the Repeats section
- Saves the group as event metadata
- Adds `event_group` to each item returned by `/wp-json/surfside/v1/events`

### Use existing event groups from a dropdown ([#303](https://github.com/rspoelstra216/surfside-tools/pull/303))

- Follow-up to merged PR #302.
- Replaces the free-text **Event Group** field with a dropdown
- Shows **No group** plus all existing group names already in use
- Adds **+ Add New Group…** as the final option

### Clarify App Event Series in calendar editor ([#304](https://github.com/rspoelstra216/surfside-tools/pull/304))

- Follow-up to merged PR #303. This is a new PR and does not modify the merged PR.
- Renames the editor-facing **Event Group** label to **App Event Series**
- Changes **No group** to **No series**
- Changes **+ Add New Group…** to **+ Add New Series…**

### Hide unused App Event Series name field ([#305](https://github.com/rspoelstra216/surfside-tools/pull/305))

- Follow-up to merged PR #304.
- Keeps **App Event Series** as the single visible control during normal editing
- Fixes the CSS conflict that caused the hidden new-series field to render even when **No series** was selected
- Shows the new series text input only after **+ Add New Series…** is selected

### Expose recurrence metadata to mobile events ([#306](https://github.com/rspoelstra216/surfside-tools/pull/306))

- Adds recurrence metadata to the existing `/surfside/v1/events` payload so the mobile app can identify ordinary recurring events without any new staff workflow.
- Adds `recurrence_type` to each mobile event occurrence
- Adds the existing human-readable `recurrence_label`
- Adds `event_start_date` for the original event series start

### Add current volunteer needs for mobile app ([#307](https://github.com/rspoelstra216/surfside-tools/pull/307))

- Adds the v1 Current Volunteer Needs backend and staff workflow for the Surfside mobile app.
- Adds a Volunteer Needs card to Manage Mobile App
- Keeps the editor inside the existing Mobile App page using a query view, so no new WordPress child page or page-ensure hook is required
- Staff can add, remove, reorder, activate/deactivate current needs

### Add Church Prayer List review workflow ([#308](https://github.com/rspoelstra216/surfside-tools/pull/308))

- First phase of the Church Prayer List feature.
- Reframes prayer privacy as Pastoral Staff Only / Prayer Team / Church Prayer List
- Church Prayer List reveals name visibility and 7 / 14 / 30 day active-period choices
- Stores Church Prayer List submissions as pending only after the existing email send succeeds

### Preserve contact form values after errors ([#309](https://github.com/rspoelstra216/surfside-tools/pull/309))

- Follow-up fix for the prayer/contact form.
- Makes the contact requirement explicit with `Email or Phone *` and helper text that at least one is required
- Preserves category, name, email, phone, message, preferred contact, prayer audience, prayer name visibility, and requested active period after validation/send/Turnstile errors
- Keeps the existing validation logic unchanged

### Add prayer review link to notification email ([#310](https://github.com/rspoelstra216/surfside-tools/pull/310))

- Adds an approval reminder only to Church Prayer List notification emails.
- Appends an ACTION REQUIRED section when the prayer audience is Church Prayer List
- Includes a direct Review Prayer Request link to the pending review screen
- Leaves Prayer Team and Pastoral Staff Only notification emails unchanged

### Reorganize dashboard and add prayer request manager ([#311](https://github.com/rspoelstra216/surfside-tools/pull/311))

- Reorganizes the Surfside staff dashboard around what staff are managing rather than where content happens to appear.
- Dashboard changes:
- Renames Website Status to Current Status
- Replaces the old Quick Actions area with six clear Management Areas: Weekly Update, Calendar, Member Engagement, Mobile App, Website, and Settings

### Render new management areas in current dashboard ([#312](https://github.com/rspoelstra216/surfside-tools/pull/312))

- Corrective follow-up to PR #311.
- The current staff dashboard is rendered by `includes/dashboard-polish.php`, so the post-render replacement added in #311 never matched the active markup. This PR updates the actual renderer directly.
- Changes:
- Renames Website Status to Current Status

### Remove duplicate dashboard management cards ([#313](https://github.com/rspoelstra216/surfside-tools/pull/313))

- Small dashboard cleanup after PR #312.
- Keeps Weekly Update and Calendar in Current Status, where their health/counts/actions already live
- Removes duplicate Weekly Update and Calendar cards from Management Areas
- Leaves Management Areas ordered as Member Engagement, Mobile App, Website, Church Settings

### Expose active Church Prayer List to mobile app ([#314](https://github.com/rspoelstra216/surfside-tools/pull/314))

- Adds the public read-only API for the Church Prayer List so the mobile app can consume approved requests.
- Adds `/wp-json/surfside/v1/prayer-list`
- Returns only requests with status `published` whose expiration time has not passed
- Respects anonymous requests by returning `Anonymous` instead of the submitter name

### Add Prayer Requests push notification audience ([#315](https://github.com/rspoelstra216/surfside-tools/pull/315))

- add `prayer_requests` as a push-notification audience
- keep the new audience opt-in by default for existing and new devices
- show Prayer Requests in the staff push sender with subscriber counts
- allow notifications to open directly to the Church Prayer List
- automatically send a push when staff approves and publishes a new Church Prayer List request

### Accept Church Prayer List submissions from app ([#316](https://github.com/rspoelstra216/surfside-tools/pull/316))

- extend the mobile contact endpoint to accept Church Prayer List as a prayer audience
- validate named/anonymous display choice
- validate 7, 14, or 30 day active duration
- include the prayer-list details in the staff email
- add successful Church Prayer List submissions to the existing pending-review queue

### Add prayer request status sync API ([#317](https://github.com/rspoelstra216/surfside-tools/pull/317))

- add a member-facing prayer request status endpoint keyed by the existing WordPress prayer-list UUID
- return lifecycle state as pending, published, private, archived, answered, or expired
- attach the WordPress prayer-list UUID to successful Church Prayer List contact responses so the app can persist a stable cross-system ID
- keep the existing prayer submission, moderation, email, and push workflows unchanged
- no global queries or page-creation work; the new logic runs only on the contact response and explicit status endpoint

### MM5: move staff login to Firebase ([#318](https://github.com/rspoelstra216/surfside-tools/pull/318))

- add Firebase ID-token verification in Surfside Tools
- add a Firebase-based staff login page with Google and email/password sign-in
- keep wp-admin username/password authentication unchanged
- map verified Firebase email to the existing WordPress staff user and issue a normal WordPress auth cookie, so staff do not need to enter a WordPress password
- preserve existing `upload_files` capability checks as the temporary authorization bridge until MM6 introduces Firebase UID-based permissions

### Fix Firebase staff login and Google button styling ([#319](https://github.com/rspoelstra216/surfside-tools/pull/319))

- fix the non-responsive Firebase staff login controls
- initialize the login from an explicit module script instead of relying on `document.currentScript`, which is null in module scripts
- restyle Continue with Google as a conventional white Google sign-in button with the Google mark
- clean up the email/password form layout so this can serve as the visual reference for the later mobile-app button update

### MM5: scope Firebase login to Surfside Tools ([#320](https://github.com/rspoelstra216/surfside-tools/pull/320))

- remove the WordPress auth-cookie bridge from Firebase staff login
- keep Firebase authentication scoped to `/dashboard` and Surfside Tools REST requests
- preserve the temporary existing `upload_files` capability bridge for MM5 without creating a persistent WordPress login session
- hide the WordPress admin bar for Firebase-only dashboard sessions
- leave normal `wp-admin` authentication unchanged

### MM6: add Firebase UID-based Tools permissions ([#321](https://github.com/rspoelstra216/surfside-tools/pull/321))

- move Surfside Tools authorization from WordPress staff capabilities to Firebase UID permission records
- add native Tools roles: **Tools Admin**, **Tools Staff**, and **Disabled**
- automatically carry existing WordPress administrators forward as **Tools Admins** the first time their Firebase identity is seen
- automatically carry existing WordPress users with `upload_files` forward as **Tools Staff**
- record unknown Firebase users as pending so an admin can approve them after their first sign-in attempt
- add `/dashboard/access/` for Tools Admins to manage access

### Staff Access: manage WordPress access ([#322](https://github.com/rspoelstra216/surfside-tools/pull/322))

- add WordPress access management to the existing Staff Access page
- keep Surfside Tools roles and WordPress roles as separate controls
- allow Tools Admins to grant, change, or disable WordPress access for a person
- create a WordPress account and send password-setup instructions when a Firebase person does not yet have one
- block disabled WordPress accounts at login and terminate existing WordPress sessions
- prevent removal or disabling of the final active WordPress Administrator

### Refactor Staff Access account model ([#323](https://github.com/rspoelstra216/surfside-tools/pull/323))

- make the Staff Access page start from the site's real WordPress users instead of only Firebase identities
- keep WordPress username/password credentials separate from WordPress site roles
- allow **No WordPress Site Access** without deleting the username/password credential
- show existing WordPress users such as `admin` and `dgreen` even if they have never used Google sign-in
- stop creating WordPress accounts automatically from Firebase email addresses
- add explicit linking between a Google/Firebase identity and an existing WordPress user

### Unify Staff Access into one people table ([#324](https://github.com/rspoelstra216/surfside-tools/pull/324))

- replace the separate WordPress Users and Google/Firebase sections with one person-centered Staff Access table
- add a clear **Source** column showing Username, Google, or Username + Google
- widen the Staff Access shell and remove the forced desktop horizontal-scroll layout
- keep Google linkage visible in the final **Linked Account** column
- keep WordPress username visible as secondary person detail rather than as a full-width column
- add WordPress-user Tools Access records that persist independently of WordPress site roles

### Clarify WordPress source labels ([#325](https://github.com/rspoelstra216/surfside-tools/pull/325))

- change Staff Access Source labels from **Username** to **WordPress**
- change linked source from **Username + Google** to **WordPress + Google**
- clarify the Staff Access description to say **WordPress username/password** when describing the sign-in method
- No permission, linking, or authentication behavior is changed.

### Use WordPress credentials for Staff Login ([#326](https://github.com/rspoelstra216/surfside-tools/pull/326))

- replace the Firebase email/password path on the Staff Login page with existing WordPress **Username + Password** credentials
- keep **Continue with Google** as the Firebase sign-in option
- authenticate WordPress credentials into a Surfside Tools-scoped session rather than issuing a general WordPress auth cookie
- authorize that session from the independent **Tools Access** role, so WordPress Site Access can be removed without breaking Tools login
- preserve the existing Google/Firebase login and redirect behavior

### Recognize WordPress Tools Admin sessions ([#327](https://github.com/rspoelstra216/surfside-tools/pull/327))

- make the shared Tools-admin check recognize all supported sign-in sources
- preserve Firebase Tools Admin behavior
- recognize a Tools-scoped WordPress username/password session when that WordPress person has **Tools Admin**
- recognize a normal WordPress-authenticated user when that person has **Tools Admin**

### Send signed-out dashboard visits straight to Staff Login ([#328](https://github.com/rspoelstra216/surfside-tools/pull/328))

- remove the redundant **Staff Login Required → Log In to Continue** step for `/dashboard/`
- redirect signed-out dashboard visitors directly to `/dashboard/login/`
- preserve the dashboard URL as the post-login return destination
- leave authenticated Google, Tools-scoped WordPress, and normal WordPress sessions unchanged

### Add mobile admin access endpoint ([#329](https://github.com/rspoelstra216/surfside-tools/pull/329))

- add an authenticated mobile-app access endpoint at `/wp-json/surfside/v1/mobile-admin/access`
- accept the signed-in app user's Firebase ID token as a Bearer token
- verify the Firebase token server-side using the existing Surfside Tools verifier
- return only the current Surfside Tools authorization state (`role`, `is_staff`, `is_admin`)
- do not create a WordPress login session or expose WordPress credentials/roles

### Make Staff Access dashboard card compact ([#330](https://github.com/rspoelstra216/surfside-tools/pull/330))

- keep Staff Access visible to Tools Admins on the Staff Dashboard
- reduce the card from a large full-width management block to a compact utility-style row
- place the title/description on the left and the Manage Access action on the right
- preserve a stacked, full-width button layout on small screens
- No Staff Access permissions or authentication behavior changes.

### Add mobile admin prayer request API ([#331](https://github.com/rspoelstra216/surfside-tools/pull/331))

- add authenticated mobile-admin prayer request endpoints for Tools Admins
- return pending, active, and history prayer requests with counts
- support approve/publish, keep private, archive/remove, mark answered, and extend actions
- reuse the existing Firebase bearer-token verification and Surfside Tools Admin role
- preserve the existing push notification behavior when a request is first approved
- This PR adds only the server-side API foundation. The mobile Admin UI will be a separate PR after this is deployed.

### Add mobile admin push notification API ([#332](https://github.com/rspoelstra216/surfside-tools/pull/332))

- add Tools Admin-only mobile endpoints for push notification management
- return registered device count, audience counts, and allowed app destinations
- allow an authenticated Tools Admin to send an immediate notification to one or more existing notification audiences
- reuse the existing Surfside push sender and Firebase/Tools Admin authorization
- enforce the same title/message length limits and audience/destination allowlists used by the current staff sender
- This is the server-side foundation only. The next mobile app PR will add the Push Notifications screen and will also hide the prayer-admin route from the bottom tab bar.

### Add mobile admin featured announcement API ([#333](https://github.com/rspoelstra216/surfside-tools/pull/333))

- add Tools Admin-only mobile endpoints for Featured Announcement management
- return the current enabled/active status and editable featured-announcement fields
- allow authorized mobile admins to enable/disable, edit, and schedule the featured Home announcement
- preserve the existing 90/240/30 character limits, optional start time, required end time when enabled, and optional button/link behavior
- reuse the existing Surfside Tools featured-announcement settings and Firebase/Tools Admin authorization
- This is the server-side foundation only. After deployment, the next mobile app PR will add Featured Announcement to Surfside Admin and complete this mobile Admin milestone.

### Scope calendar refinements to Calendar Manager ([#335](https://github.com/rspoelstra216/surfside-tools/pull/335))

- stop Calendar Manager refinement work from running on unrelated staff-facing frontend pages
- require the current page to actually contain `[surfside_tools_calendar_manager]` before loading all events and calculating occurrences through the five-year refinement window
- leave Calendar Manager behavior itself unchanged

### Scope public calendar location work ([#336](https://github.com/rspoelstra216/surfside-tools/pull/336))

- stop public calendar location enhancement work from running on unrelated frontend pages
- require the queried page to contain a Surfside public/month calendar shortcode before querying all published events
- leave calendar display, meeting-location enhancement, and overflow behavior unchanged on calendar pages

### Remove obsolete mobile giving filter ([#337](https://github.com/rspoelstra216/surfside-tools/pull/337))

- remove the legacy `do_shortcode_tag` filter that searched the Manage Mobile App HTML for a Giving panel
- leave Church Settings as the single editor for the shared giving URL

### Consolidate dashboard calendar occurrence work ([#338](https://github.com/rspoelstra216/surfside-tools/pull/338))

- calculate dashboard calendar occurrences once in `dashboard-intelligence.php`
- derive the 30-day dashboard count during that same 366-day pass instead of running a second recurrence expansion in `dashboard-recent-activity.php`
- remove the superseded V1 and V2 dashboard shortcode renderers and their registrations, leaving V3 in `dashboard-polish.php` as the only dashboard renderer
- keep the shared status, activity, and settings-tracking helpers used by V3

### Consolidate Google Places initializers ([#339](https://github.com/rspoelstra216/surfside-tools/pull/339))

- keep the early Google Places API enqueue fix
- remove the later duplicate Google Places initializer from `google-places-regression-fix.php`
- remove the second native initializer from `weekly-update-native-google-places.php`
- preserve the high z-index style for Google Places suggestion dropdowns
- leave `calendar-suggestion-locations.php` as the single Weekly Update venue-field initializer

### Remove duplicate monthly calendar overflow logic ([#340](https://github.com/rspoelstra216/surfside-tools/pull/340))

- remove the client-side monthly overflow implementation from `location-clarity.php`
- leave `location-clarity.php` responsible for meeting-location display and equal calendar row sizing
- keep the renderer + `calendar-simple-overflow-layout.php` as the single authority for crowded-day overflow cards

### Remove duplicate Google Places z-index module ([#341](https://github.com/rspoelstra216/surfside-tools/pull/341))

- remove `weekly-update-native-google-places.php`
- remove its bootstrap include
- keep the identical `.pac-container` z-index rule in `final-productivity-fixes.php` as the single authority

### Fold empty location menu handling into search ([#342](https://github.com/rspoelstra216/surfside-tools/pull/342))

- make the Weekly Update saved-location search close its own menu when there are no local matches
- remove the later compatibility JavaScript that watched the same venue fields and hid empty menus after rendering
- preserve the Google Places z-index rule and unrelated staff-button compatibility behavior

### Retire featured announcement page ensure hook ([#343](https://github.com/rspoelstra216/surfside-tools/pull/343))

- stop registering the Featured Announcement page-ensure function on every `init`
- remove the matching bootstrap `remove_action()` that immediately disabled that hook
- keep the ensure helper itself available for any explicit/manual use

### Retire Home Experience page ensure hook ([#344](https://github.com/rspoelstra216/surfside-tools/pull/344))

- stop registering the Home Experience page-ensure function on every `init`
- remove the matching bootstrap `remove_action()` that immediately disabled that hook
- keep the ensure helper itself available for explicit/manual use

### Remove obsolete homepage registration repair ([#345](https://github.com/rspoelstra216/surfside-tools/pull/345))

- remove `homepage-page-registration-fix.php`
- remove its bootstrap include
- retain the normal Homepage Manager `admin_init` page ensure as the authoritative registration path

### Remove obsolete Settings route repair ([#346](https://github.com/rspoelstra216/surfside-tools/pull/346))

- remove the old `/dashboard/settings` route-repair routine from `final-productivity-fixes.php`
- keep the remaining Productivity UI compatibility styles/JS unchanged
- rely on the normal Settings page registration already owned by `frontend-settings.php` on `admin_init`

### Fold carousel full-width styles into homepage manager ([#347](https://github.com/rspoelstra216/surfside-tools/pull/347))

- move the public carousel full-width rule into the carousel's existing authoritative style block in `homepage-manager.php`
- remove the standalone `homepage-carousel-full-width.php` override module
- remove its bootstrap include

### Fold homepage drag fix into compact manager ([#348](https://github.com/rspoelstra216/surfside-tools/pull/348))

- move the working drag-handle behavior into `homepage-manager-compact.php`
- keep the same handle-only drag interaction, numbering refresh, and drag styling
- remove the standalone `homepage-manager-drag-fix.php` module and its bootstrap include

### Remove redundant carousel shortcode rebind ([#349](https://github.com/rspoelstra216/surfside-tools/pull/349))

- remove the second `wp`-hooked carousel shortcode rebind
- retain the late `init` priority 999 rebind that keeps Surfside Tools authoritative if the legacy Code Snippet is still enabled
- leave homepage cache purge behavior unchanged

### Remove superseded Firebase session auth filter ([#350](https://github.com/rspoelstra216/surfside-tools/pull/350))

- remove the older priority-5 `rest_pre_dispatch` implementation for `/surfside-tools/v1/staff-auth/session`
- leave the current priority-4 authorization flow in `firebase-permission-seeding.php` unchanged
- preserve all permission storage, bridge-user, Staff Access, and WordPress-login behavior

### Remove obsolete WordPress permission seeder ([#351](https://github.com/rspoelstra216/surfside-tools/pull/351))

- remove the now-unused `surfside_tools_seed_permission_from_wordpress()` helper
- update the permissions module comment to reflect the current authorization model
- leave current Firebase pending/approval behavior, bridge users, Staff Access, and WordPress login unchanged

### Consolidate integrations layout module ([#352](https://github.com/rspoelstra216/surfside-tools/pull/352))

- fold the Integrations layout finishing pass into `integrations-page-polish.php`
- remove the standalone `integrations-layout-finish.php` module and bootstrap include
- preserve the existing filter priorities, Streaming card placement, Integrations card layout, and Save Integrations behavior

### Centralize mobile admin authorization helper ([#353](https://github.com/rspoelstra216/surfside-tools/pull/353))

- move `surfside_tools_mobile_admin_require_admin()` from the prayer module into `mobile-admin-access.php`
- keep the helper implementation unchanged
- leave prayer, push, and featured-announcement endpoint behavior unchanged

### Remove superseded Staff Access renderer ([#354](https://github.com/rspoelstra216/surfside-tools/pull/354))

- remove the intermediate Staff Access shortcode renderer from `staff-access-wordpress.php`
- keep its WordPress/Firebase linking and WordPress-role helper functions intact
- leave the current unified Staff Access renderer in `staff-access-unified.php` unchanged

### Remove base Staff Access renderer ([#355](https://github.com/rspoelstra216/surfside-tools/pull/355))

- remove the original `surfside_tools_permissions` shortcode renderer from `firebase-permissions.php`
- keep permission storage, bridge-user logic, page registration, admin counting, and dashboard card behavior unchanged
- leave `staff-access-unified.php` as the single current Staff Access renderer

### Remove obsolete Staff Access shortcode reset ([#356](https://github.com/rspoelstra216/surfside-tools/pull/356))

- remove the now-unnecessary `remove_shortcode('surfside_tools_permissions')` call from `staff-access-unified.php`
- keep the unified Staff Access shortcode registration unchanged

### Remove unused WordPress permission helper ([#357](https://github.com/rspoelstra216/surfside-tools/pull/357))

- remove the unused `surfside_tools_wp_user_for_permission()` helper from `staff-access-wordpress.php`
- leave all active Staff Access linking, role, and permission helpers unchanged

### Remove superseded Firebase login renderer ([#358](https://github.com/rspoelstra216/surfside-tools/pull/358))

- remove the original Firebase-only `surfside_firebase_staff_login` shortcode renderer from `firebase-staff-login.php`
- keep scoped Firebase sessions, bridge-user behavior, login URL routing, and Staff Login page registration unchanged
- leave `staff-login-wordpress.php` as the single current Google + WordPress login renderer

### Remove obsolete Staff Login shortcode reset ([#359](https://github.com/rspoelstra216/surfside-tools/pull/359))

- remove the now-unnecessary `remove_shortcode('surfside_firebase_staff_login')` call from `staff-login-wordpress.php`
- keep the current combined Google + WordPress Staff Login shortcode registration unchanged

### Retire Staff Access page ensure hook ([#360](https://github.com/rspoelstra216/surfside-tools/pull/360))

- remove the runtime `init` hook that recreates `/dashboard/access` if it is missing
- keep the Staff Access page URL helper, unified renderer, authorization, permission storage, and dashboard card unchanged

### Retire Staff Login page ensure hook ([#361](https://github.com/rspoelstra216/surfside-tools/pull/361))

- remove the runtime `init` hook that recreates `/dashboard/login` if it is missing
- keep Staff Login URL routing, scoped Firebase sessions, bridge-user behavior, and the current Google + WordPress login renderer unchanged

### Fold Staff Access dashboard styles into permissions ([#362](https://github.com/rspoelstra216/surfside-tools/pull/362))

- move the compact Staff Access dashboard-card styling into the permissions module that renders the card
- remove the standalone `staff-access-dashboard-compact.php` corrective module
- remove its bootstrap include

### Remove superseded homepage manager renderer ([#363](https://github.com/rspoelstra216/surfside-tools/pull/363))

- remove the original Homepage Manager styles and shortcode renderer from `homepage-manager.php`
- keep carousel data/storage, uploads, public carousel rendering, dashboard card, and page registration unchanged
- leave `homepage-manager-compact.php` as the single current `surfside_staff_homepage` renderer

### Remove obsolete Homepage Manager shortcode reset ([#364](https://github.com/rspoelstra216/surfside-tools/pull/364))

- remove the now-unnecessary `remove_shortcode('surfside_staff_homepage')` call from `homepage-manager-compact.php`
- keep the compact Homepage Manager shortcode registration unchanged

### Retire legacy homepage ACF migration ([#365](https://github.com/rspoelstra216/surfside-tools/pull/365))

- remove the one-time ACF carousel migration path from `homepage-manager.php`
- read current homepage carousel images directly from the Tools option
- keep image normalization, carousel rendering, uploads, ordering, and management behavior unchanged

### Remove redundant carousel shortcode reset ([#366](https://github.com/rspoelstra216/surfside-tools/pull/366))

- remove the unnecessary `remove_shortcode('surfside_photo_carousel')` call from `homepage-manager.php`
- keep the current carousel shortcode registration unchanged
- leave the late compatibility rebind in `homepage-carousel-cache-sync.php` untouched

### Combine Staff Access dashboard filters ([#367](https://github.com/rspoelstra216/surfside-tools/pull/367))

- combine the Staff Access dashboard card render and compact styling into one `the_content` filter
- remove the second dashboard authorization/page check
- preserve the existing card markup, styles, Manage Access link, and admin-only behavior

### Consolidate calendar suggestion support modules ([#368](https://github.com/rspoelstra216/surfside-tools/pull/368))

- combine calendar suggestion duplicate matching, known-location search compatibility, and early Google Places loading into one `calendar-suggestion-support.php` module
- remove the three standalone corrective/support modules and their separate bootstrap entries
- preserve existing function names, hooks, priorities, matching thresholds, location menu behavior, and Google Places loading

### Consolidate remaining productivity compatibility support ([#369](https://github.com/rspoelstra216/surfside-tools/pull/369))

- retire the standalone `final-productivity-fixes.php` compatibility module
- preserve the Google Places autocomplete z-index rule inside `productivity-modal-tracking.php`
- remove the old staff dashboard button rewrite and page-wide `MutationObserver`
- remove the retired module from plugin bootstrap

### Scope calendar and Weekly Update runtime assets ([#370](https://github.com/rspoelstra216/surfside-tools/pull/370))

- limit Weekly Update productivity modal tracking to pages that actually render the `surfside_weekly_update` shortcode
- unhook the heavy Productivity footer payload on unrelated pages before it can load the full calendar event set
- limit the Weekly Update Google Places prediction observer/interval to the Weekly Update screen
- limit Calendar Manager Google Places status normalization to pages that actually render `surfside_tools_calendar_manager`
- limit monthly-calendar overflow styling to pages that actually render `surfside_month_calendar`

### Consolidate Staff Dashboard overview layer ([#371](https://github.com/rspoelstra216/surfside-tools/pull/371))

- replace the misleading `dashboard-polish.php` layer with an explicit `dashboard-overview.php` current renderer
- fold the final weekly-date and 30-day calendar health rules directly into the active dashboard overview
- retire `dashboard-recent-activity.php`
- stop querying the latest modified calendar event and tracking settings-update timestamps for a Recent Activity list that is no longer rendered
- remove the retired dashboard modules from plugin bootstrap

### Make Ministry Manager authoritative ([#372](https://github.com/rspoelstra216/surfside-tools/pull/372))

- render Featured and Published controls directly inside the Ministry Manager instead of injecting them after shortcode rendering
- fold the current compact manager layout, card headings, phone formatting, and icon-field behavior into the authoritative manager
- remove the standalone Featured Ministry manager decorator
- remove the Ministry Manager branch from the old mixed UI-polish module and keep the public directory refinements in a clearly named `ministry-directory-polish.php`
- remove the Published-control DOM injector from the draft/published workflow module
- update plugin bootstrap to load the authoritative manager and public directory modules

### Make Church Settings hub authoritative ([#373](https://github.com/rspoelstra216/surfside-tools/pull/373))

- make `site-settings-hub.php` render the final Church Settings card set directly
- remove the dead Giving form/save path from the Church Settings hub; Giving remains managed in Integrations
- remove Streaming from the hub because Streaming is already intentionally grouped under Integrations
- render the final Contact Routing and Integrations descriptions directly
- remove the now-obsolete Church Settings hub rewrites from `church-settings-polish.php`
- remove the now-obsolete Streaming-card removal from `integrations-page-polish.php`

### Consolidate Saved Places management ([#374](https://github.com/rspoelstra216/surfside-tools/pull/374))

- centralize Saved Places normalization, discovery, hide/restore/delete mutations, and Weekly Update suggestion payloads in `saved-places-settings.php`
- make the front-end Integrations screen reuse the same Saved Places helpers as the WP-admin fallback instead of maintaining a second implementation
- make Weekly Update consume the canonical known-location payload instead of rebuilding saved/calendar locations itself
- filter hidden locations in PHP before they are emitted to the browser
- remove the site-wide footer `MutationObserver` that previously deleted hidden location options after rendering

### Make Integrations page authoritative ([#375](https://github.com/rspoelstra216/surfside-tools/pull/375))

- make `frontend-settings.php` render the final Integrations page directly, including final heading/back link, accordion cards, Streaming placement, and unified Save Integrations behavior
- keep Google Maps/Calendar, Saved Places, Giving/Turnstile, Visual CSS, YouVersion, and Streaming storage/test behavior unchanged
- convert Visual CSS and YouVersion from `do_shortcode_tag` appenders into callable panel renderers
- stop `church-settings-polish.php` from rewriting the Integrations shortcode after render
- remove the retired `integrations-page-polish.php` module and its bootstrap entry

### Consolidate homepage carousel runtime ([#376](https://github.com/rspoelstra216/surfside-tools/pull/376))

- fold homepage carousel cache invalidation into `homepage-manager.php`
- remove the dead Staff Dashboard shortcode wrapper from the homepage module now that `dashboard-overview.php` is authoritative
- keep the current compact Homepage Photos manager UI unchanged
- keep the late legacy `[surfside_photo_carousel]` compatibility rebind, but colocate it with the authoritative carousel runtime
- remove `homepage-carousel-cache-sync.php` and its bootstrap include

### Consolidate Member Engagement dashboard routing ([#377](https://github.com/rspoelstra216/surfside-tools/pull/377))

- route the `view=member-engagement` dashboard state directly from the authoritative `dashboard-overview.php` renderer
- make pending prayer reviews part of the dashboard's normal server-side alert evaluation instead of mutating the rendered dashboard in `wp_footer`
- remove the obsolete Member Engagement `do_shortcode_tag` dashboard rewrite and its duplicate Management Areas renderer
- retire `prayer-dashboard-alert.php` and its bootstrap include
- preserve older `?surfside-prayer-review=1` email links by redirecting them to the current Member Engagement → Prayer Requests route

### Consolidate public calendar action integration ([#378](https://github.com/rspoelstra216/surfside-tools/pull/378))

- make `calendar-integration.php` create the final branded Apple, Google, and download actions directly
- fold the existing calendar-action icons, labels, responsive layout, and brand hover states into the integration module
- remove the later `calendar-action-branding.php` DOM-decoration pass and its bootstrap include
- scope personal-calendar action CSS/JavaScript to pages that actually render the monthly or public calendar shortcodes
- leave event lookup, recurrence occurrence handling, ICS generation, and Google Calendar URL behavior unchanged

### Make monthly calendar overflow rendering authoritative ([#379](https://github.com/rspoelstra216/surfside-tools/pull/379))

- render crowded-day overflow directly as the final two-line calendar card inside `calendar-day-details.php`
- move the overflow-card styling into the Day Details asset bundle that already owns the interaction
- remove the later `do_shortcode_tag` regex transformation that rebuilt the overflow button after shortcode rendering
- remove the standalone `calendar-simple-overflow-layout.php` module and its bootstrap include
- drop the redundant `remove_shortcode()` call when registering the interactive monthly calendar renderer

### Make calendar location rendering authoritative ([#380](https://github.com/rspoelstra216/surfside-tools/pull/380))

- make Meeting Location a first-class Calendar Manager event field, loaded and saved with the rest of event location data
- render the final Venue, Street Address, Meeting Location, help text, and Google Places connected status directly from Calendar Manager
- render meeting-location details server-side in public event cards, event-detail modals, the monthly calendar, and Day Details
- move the equal-height desktop monthly-calendar rules into the calendar owner styles
- remove the post-render `location-clarity.php` compatibility module, including its shortcode HTML rewriting, page observer, public calendar DOM enhancement, and separate event query
- leave recurrence, Google Places selection, saved locations, Maps links, month navigation, and personal-calendar actions unchanged

### Remove orphaned Weekly Update Google predictions code ([#381](https://github.com/rspoelstra216/surfside-tools/pull/381))

- delete `includes/weekly-update-google-predictions.php`
- make no bootstrap or runtime changes

### Close out repository code audit ([#382](https://github.com/rspoelstra216/surfside-tools/pull/382))

- add a durable `docs/CODE-AUDIT-CLOSEOUT.md` record for the completed post-3.2.0 repository audit
- update the concise development status to mark the audit complete and link the closeout
- update the roadmap to record the completed cleanup phase and return primary focus to the Surfside mobile app
- document the ownership/runtime guardrails established during the cleanup

### Fix Volunteer Needs control styling ([#387](https://github.com/rspoelstra216/surfside-tools/pull/387))

- Makes the Volunteer Needs manager own its Add / move / Remove / save-action styling instead of depending on `surfside-information-*` classes whose CSS is only loaded by the Surfside Information manager.

### Consolidate Church Settings and ministry ownership ([#388](https://github.com/rspoelstra216/surfside-tools/pull/388))

- Removes the remaining Church Settings and ministry post-render compatibility layers so Contact Routing, Surfside Information, and the public Ministry Directory render and save their authoritative behavior directly.
- Contact Routing now owns recipient routing only and preserves the Turnstile settings managed from Integrations. Surfside Information links back to Church Settings directly and no longer carries the unused legacy Ministries editor. The public Ministry Directory now includes its final full-width layout, serving CTA, and contact presentation in its authoritative renderer, allowing the separate polish module to be retired.

### Make calendar manager rendering authoritative ([#389](https://github.com/rspoelstra216/surfside-tools/pull/389))

- Removes the remaining duplicate Calendar Manager event-list correction pass and the monthly calendar post-render navigation rewrite.
- Calendar Manager now treats its existing server-side recurrence scan as authoritative: expired events are excluded before counting/pagination, recurring date-range labels are calculated once, and the final event count/date labels render directly in PHP. The monthly calendar shortcode now emits its navigation hooks, anchored fallback URLs, and accessibility status node directly, so `calendar-month-navigation.php` only owns the progressive-enhancement assets.

### Protect public mobile API resources ([#390](https://github.com/rspoelstra216/surfside-tools/pull/390))

- Hardens the intentionally public mobile-app REST resources without changing their response contracts.
- Push registration now rate-limits only brand-new registrations and no longer evicts older devices when the registry reaches its safety cap. Existing registered devices can continue refreshing their preferences normally. The YouVersion proxy now caches resolved versions and passage payloads, extends the supported-version cache, and rate-limits only uncached upstream work so repeated legitimate reads do not consume additional YouVersion quota.

### Harden PR validation and deployment sync ([#391](https://github.com/rspoelstra216/surfside-tools/pull/391))

- Closes the remaining CI/deployment hygiene findings from the repository audit.
- Pull requests targeting `main` now run the existing plugin structure and PHP syntax validation before merge. ZIP packaging remains limited to post-merge/manual runs. cPanel deployment now mirrors the tracked `includes/` and `assets/` directories with `rsync --delete`, so files removed from Git are also removed from production instead of accumulating indefinitely.

### Document second repository audit closeout ([#392](https://github.com/rspoelstra216/surfside-tools/pull/392))

- Updates the repository audit closeout to reflect the independent second verification audit and the deployed hardening/consolidation work in PRs #383–#391.
- This corrects the earlier #382 closeout by explicitly documenting that it was an intermediate milestone, records the second audit findings and fixes, updates the final verification point to deployed PR #391, and adds the durable CI/deployment rules established during the second pass.

### Roll documentation forward for 3.3.0 ([#393](https://github.com/rspoelstra216/surfside-tools/pull/393))

- Rolls the repository documentation forward to the planned 3.3.0 clean baseline before the release workflow is run.
- This aligns the current product documentation with the post-audit architecture: Church Settings and Member Engagement ownership, centralized staff-page provisioning, consolidated auth/session handling, authoritative calendar rendering, public mobile API protection, PR-time PHP validation, and synchronized cPanel deployment.

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