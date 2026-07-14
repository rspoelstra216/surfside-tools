# Changelog

## [1.3.0] - 2026-07-14

### Added

- Added a standard pull request template for consistent release documentation. ([#35](https://github.com/rspoelstra216/surfside-tools/pull/35))
- Added categorized release notes using Added, Improved, and Fixed sections. ([#35](https://github.com/rspoelstra216/surfside-tools/pull/35))

### Improved

- Release automation now combines user-facing notes by category instead of relying only on complete PR summaries. ([#35](https://github.com/rspoelstra216/surfside-tools/pull/35))
- Older pull requests remain supported through the existing Summary fallback. ([#35](https://github.com/rspoelstra216/surfside-tools/pull/35))

### Additional Changes

### Organize project roadmap and documentation ([#12](https://github.com/rspoelstra216/surfside-tools/pull/12))

- Moves the roadmap into `docs/ROADMAP.md`
- Adds `docs/DECISIONS.md` for durable architectural and workflow choices
- Adds `docs/CONTRIBUTING.md` for branching, pull requests, testing, deployment, and releases
- Captures the complete Nice Ideas parking lot
- Documents Milestones 1.2.x, 1.3, 1.4, and 2.0
- Updates the README with direct links to all project documentation

### Improve generated release notes ([#13](https://github.com/rspoelstra216/surfside-tools/pull/13))

- Replaces raw `Merge pull request...` lines with each pull request's title and description
- Reads the `## Summary` section from merged PR bodies when available
- Links each release-note entry back to its pull request
- Writes the same readable content to both `CHANGELOG.md` and the GitHub Release
- Falls back to commit messages when no merged pull requests are found
- Excludes the release-note script from WordPress ZIP artifacts

### Clean up the 1.2.1 changelog ([#14](https://github.com/rspoelstra216/surfside-tools/pull/14))

- Rewrites the existing `1.2.1` changelog entry using clear feature names and descriptions
- Adds links to PRs #2 through #10
- Replaces raw merge messages with readable release history
- Leaves plugin code and deployment behavior unchanged

### Add calendar suggestions from weekly announcements ([#15](https://github.com/rspoelstra216/surfside-tools/pull/15))

- Detects announcement items that contain a recognizable event date
- Extracts a suggested event title, date, start time, and end time when available
- Displays a new **Calendar Suggestions** section beneath the weekly announcement preview
- Adds a **Review in Calendar** button for each suggestion
- Opens the Calendar Manager with the detected fields prefilled
- Keeps event creation reviewable: nothing is added to the calendar automatically

### Fix calendar suggestions on the unified Weekly Update page ([#16](https://github.com/rspoelstra216/surfside-tools/pull/16))

- Fixes calendar suggestions not appearing on the live Weekly Update workflow
- Supports the actual unified publish form class used at `/dashboard/weekly-update/`
- Inserts suggestions after the formatted announcements preview
- Keeps compatibility with the older standalone announcement form
- Improves event-title cleanup for announcements that begin with a weekday/date
- Avoids using an outdated announcement-document year when suggesting undated events

### Open calendar suggestions in an in-page review window ([#17](https://github.com/rspoelstra216/surfside-tools/pull/17))

- Opens **Review in Calendar** inside a large in-page modal instead of navigating away from the Weekly Update review
- Keeps an **Open in new tab** option in the modal header
- Allows the modal to close with the X button, the Escape key, or by clicking the backdrop
- Uses a full-screen modal layout on smaller screens
- Shortens the mission-trip suggestion to **Adult Mission Trip** while retaining the full destination wording in the event description

### Complete calendar suggestions without leaving Weekly Update ([#18](https://github.com/rspoelstra216/surfside-tools/pull/18))

- Changes the calendar modal submit button to **Save Event & Return to Weekly Update**
- Detects a successful event save inside the calendar iframe
- Automatically closes the modal after the event is saved
- Marks the corresponding suggestion card as **Added to Calendar**
- Adds a green completion state so staff can immediately see which suggestions are finished
- Keeps the Weekly Update review and any unsaved edits open underneath

### Add Save Announcement as Event to the roadmap ([#19](https://github.com/rspoelstra216/surfside-tools/pull/19))

- Adds **Save Announcement as Event** to the Productivity ideas
- Records duplicate checks against one-time and recurring calendar entries
- Defines **Already on Calendar** and **View/Edit Existing** behavior for likely matches
- Notes that recurring announcements can be ignored without being processed again
- This is documentation only and does not change the live plugin.

### Add confidence-based duplicate detection to calendar suggestions ([#20](https://github.com/rspoelstra216/surfside-tools/pull/20))

- Compares each Weekly Update calendar suggestion against existing calendar events
- Scores matches using normalized title similarity, matching dates or recurring occurrences, and start times
- Labels suggestions as **New event**, **Possible match**, **Likely existing event**, or **Exact match**
- Shows the confidence percentage for possible and likely matches
- Recognizes weekly, daily, fixed-date monthly, and fixed-weekday monthly recurrence
- Adds a **View/Edit Existing** link when a possible duplicate is found

### Add one-click Save Announcement as Event ([#21](https://github.com/rspoelstra216/surfside-tools/pull/21))

- Adds **Save Announcement as Event** to suggestions classified as **New event**
- Saves the announcement directly as a one-time calendar event without opening the review modal
- Keeps **Review Details** available when staff want to add a location, recurrence, featured status, or other refinements first
- Marks successfully saved suggestions as **Added to Calendar** and provides a link to edit the saved event
- Preserves the full announcement as the event description
- Adds a server-side duplicate safety check in case the calendar changes after the suggestion page loads

### Detect recurrence when saving announcement events ([#22](https://github.com/rspoelstra216/surfside-tools/pull/22))

- Detects clear recurrence phrases before one-click saving an announcement as an event
- Saves recurring calendar series instead of only the first occurrence
- Supports **every Tuesday / each Tuesday**, **weekly**, **daily**, **weekdays**, **Monday–Friday**, monthly date recurrence, and ordinal monthly recurrence such as **first Saturday**
- Treats date ranges such as **Oct. 5–10** as a daily series ending on the final date
- Shows the detected recurrence on the suggestion card before staff save it
- Changes the one-click button to **Save Recurring Event** when recurrence is found

### Detect locations in calendar suggestions ([#23](https://github.com/rspoelstra216/surfside-tools/pull/23))

- Detects explicit event locations from Weekly Update announcement text
- Recognizes internal locations such as **Building 4**, **Fellowship Hall**, **Room 102**, **Nursery**, **Sanctuary**, and similar room names
- Recognizes previously saved calendar venues when their names appear in an announcement
- Shows a **Location detected** notice on the suggestion card
- Includes the detected location when using **Save Announcement as Event** or **Save Recurring Event**
- Prefills the Venue or Meeting Location field when opening **Review Details**

### Require a main venue for internal room suggestions ([#24](https://github.com/rspoelstra216/surfside-tools/pull/24))

- Creates a new follow-up PR from `main` for the venue-required behavior that was mistakenly committed to the already-merged PR #23 branch
- Distinguishes an internal meeting location such as **Building 4** from the main venue
- Shows **Main venue required** when a room or building is detected without a campus or venue
- Adds an editable venue field directly to the suggestion card
- Blocks one-click saving until the missing venue is entered
- Carries both the venue and meeting location into **Review Details** and one-click saves

### Refine active event summaries and venue prompts ([#25](https://github.com/rspoelstra216/surfside-tools/pull/25))

- Softens the missing-venue wording from **Main venue required** to **Where is this event being held?**
- Clarifies that an internal room such as Building 4 was found, but the church, campus, or venue still needs to be entered
- Removes event records from the Manage Events list when they have no future occurrences
- Updates the event count to show active events only
- Shows a start-to-finish date range for recurring events that have a Repeat Until date

### Add saved-location and Google lookup to announcement suggestions ([#26](https://github.com/rspoelstra216/surfside-tools/pull/26))

- Turns the missing-venue field on calendar suggestions into a real location lookup
- Searches previously saved Surfside locations while staff type
- Connects the same field to Google Places autocomplete when available
- Carries the selected venue name, address, saved-location ID, Google Place ID, map URL, and coordinates into one-click saves and Review Details
- Keeps internal locations such as **Building 4** in the separate Meeting Location field
- Changes the venue prompt from yellow to a neutral blue-gray so it is visually distinct from duplicate warnings

### Fix and clarify announcement venue search ([#27](https://github.com/rspoelstra216/surfside-tools/pull/27))

- Replaces inconsistent browser datalist behavior with a visible location suggestion menu
- Searches both formal Saved Locations and venue details already used by calendar events
- Restores a clearer example-driven placeholder: **e.g., Surfside Community Fellowship or Cozy Corner Cafe**
- Adds help text explaining that staff can search known calendar locations or choose a Google Places suggestion
- Preserves the existing Google Places autocomplete as the fallback for unknown venues
- Keeps the missing-venue panel visually distinct from duplicate warnings

### Add saved place management to settings ([#28](https://github.com/rspoelstra216/surfside-tools/pull/28))

- Adds a **Saved Places** section to Surfside Tools Settings
- Lists formal Saved Places with their stored addresses
- Lists venue names previously used by calendar events, including older remnants such as `Surfside`
- Allows formal Saved Places to be deleted
- Allows calendar-history-only names to be removed from future location suggestions
- Keeps existing calendar events unchanged when a place is removed

### Complete the Productivity milestone workflow ([#29](https://github.com/rspoelstra216/surfside-tools/pull/29))

- This PR implements the five final enhancements discussed for the Weekly Update calendar workflow.
- ### 1. Publish summary
- Replaces the generic end state with a clear **Weekly Update Published** summary
- Reports how many announcements were published
- Confirms sermon notes were published
- Reports one-time events and recurring series created during the review

### Restore Google Places and add front-end Settings ([#30](https://github.com/rspoelstra216/surfside-tools/pull/30))

- Restores Google Places autocomplete on Weekly Update venue fields
- Reliably initializes autocomplete for venue fields added dynamically after page load
- Adds a front-end **Settings** page at `/dashboard/settings/`
- Keeps staff inside the front-end dashboard instead of sending them to `wp-admin`
- Adds **Back to Dashboard** navigation to Settings
- Moves Google Maps settings, calendar defaults, and Saved Places management into the front-end page

### Fix Settings route, Google Places visibility, and dashboard buttons ([#31](https://github.com/rspoelstra216/surfside-tools/pull/31))

- Repairs the `/dashboard/settings/` route and flushes WordPress rewrite rules once after deployment
- Reparents an existing standalone Settings page under Staff Dashboard when needed
- Creates the Settings child page if it is missing
- Fixes the apparent Google Places failure caused by the empty saved-location menu covering Google's autocomplete results
- Raises the Google Places dropdown above the custom location menu
- Makes the Weekly Update, Calendar, and Settings dashboard buttons visually consistent

### Fix Google Places search on Weekly Update suggestions ([#32](https://github.com/rspoelstra216/surfside-tools/pull/32))

- Replaces the unreliable native Google autocomplete dropdown on Weekly Update venue prompts with a dedicated Surfside Tools prediction menu
- Uses Google `AutocompleteService` to fetch predictions as staff type
- Uses `PlacesService` to retrieve the selected venue name, full address, Place ID, coordinates, and Maps URL
- Continues to support dynamically rendered suggestion cards
- Leaves the working Calendar Manager modal unchanged

### Use native Google Places on Weekly Update venue fields ([#33](https://github.com/rspoelstra216/surfside-tools/pull/33))

- Replaces the custom Weekly Update Google prediction implementation with the same native Google Places Autocomplete used by the working Calendar Manager and review modal
- Initializes autocomplete when a dynamic venue field is focused, clicked, or typed into
- Preserves venue name, address, Place ID, coordinates, and Maps URL on selection
- Keeps the Google dropdown above the Weekly Update interface

### Load Google Places correctly on Weekly Update ([#34](https://github.com/rspoelstra216/surfside-tools/pull/34))

- Moves the Google Maps/Places API enqueue from `wp_footer` to `wp_enqueue_scripts`
- Ensures WordPress actually prints the Google library on the Weekly Update page
- Keeps the dynamic venue-field initializer in the footer
- Avoids attaching duplicate autocomplete instances when another Surfside initializer has already succeeded

## [1.2.1] - 2026-07-13

### Clarify event locations ([#2](https://github.com/rspoelstra216/surfside-tools/pull/2))

- Improved the event-location instructions and Google Places wording.
- Renamed the saved location fields to **Venue** and **Street Address**.
- Added clearer search guidance for churches, businesses, and addresses.

### Add automated plugin builds ([#3](https://github.com/rspoelstra216/surfside-tools/pull/3))

- Added a GitHub Actions workflow that validates the plugin and creates an installable WordPress ZIP.
- Established a repeatable build process for merged changes.

### Flatten the repository structure ([#4](https://github.com/rspoelstra216/surfside-tools/pull/4))

- Made the repository root match the WordPress plugin root.
- Removed the extra nested `surfside-tools` directory from generated packages.

### Add cPanel deployment ([#5](https://github.com/rspoelstra216/surfside-tools/pull/5))

- Added cPanel deployment configuration for the live WordPress plugin directory.
- Enabled the GitHub-to-cPanel update and deploy workflow without manual ZIP uploads.

### Add a separate meeting-location field ([#6](https://github.com/rspoelstra216/surfside-tools/pull/6))

- Added an optional **Meeting Location** field for rooms, buildings, and on-campus locations.
- Kept venue and street address separate from details such as Building 4 or Fellowship Hall.

### Display meeting locations publicly ([#7](https://github.com/rspoelstra216/surfside-tools/pull/7))

- Added meeting-location details to public calendar displays and event information.
- Preserved venue, address, and Google Maps details in the event modal.

### Equalize month-calendar week heights ([#8](https://github.com/rspoelstra216/surfside-tools/pull/8))

- Standardized desktop month-view week heights.
- Added overflow handling so busy dates do not stretch the entire calendar row.

### Polish month-view event cards ([#9](https://github.com/rspoelstra216/surfside-tools/pull/9))

- Compacted calendar cards so more information fits without changing row height.
- Improved spacing and typography in the month view.
- Added the `+N more` indicator when additional events are hidden.

### Automate official releases ([#10](https://github.com/rspoelstra216/surfside-tools/pull/10))

- Added automatic version updates, PHP validation, changelog generation, Git tags, GitHub Releases, and installable ZIP creation.
- Kept cPanel Git as the live deployment method while adding a permanent release archive.

Release entries are generated automatically by the **Release Surfside Tools** GitHub Actions workflow.

The workflow places the newest release at the top of this file and builds the release notes from pull requests merged since the previous Git tag.
