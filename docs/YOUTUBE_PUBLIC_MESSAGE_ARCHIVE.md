# Surfside Public Messages Archive

## Overview

The public website Message Archive reads **only** the locally synchronized YouTube metadata in Surfside Tools. It does not request YouTube Data API pages from visitors, download media, or duplicate YouTube hosting. Each video is uniquely identified by its YouTube video ID.

## Publishing / first deployment

1. Deploy this PR and open the WordPress admin dashboard **once** with a user who can manage options. The installer creates a **draft** WordPress page titled **Messages** at the proposed `/messages/` slug, with the shortcode `[surfside_message_archive]`. It does not alter an existing authored page at this slug.
2. Open **Pages → Messages → Preview**. Browse All Messages, search, filter, open a sermon, and Browse by Series, **including a mobile preview**. Draft preview links preserve the WordPress preview context.
3. In Surfside Tools **Manage Website → Message Archive**, verify exclusions, date corrections, and hidden series. Confirm that the draft site excludes them.
4. When approved, **Publish** the WordPress Messages page. Add it to the website navigation through the existing navigation controls as a separate deliberate action. The PR does **not** auto-publish or change the header links.
5. If an authored `/messages/` page already exists, the plugin does not overwrite it. Place `[surfside_message_archive]` on that page manually if desired.

## Browsing rules

- **Latest message:** most recent **recognized or staff-corrected sermon date**, not most recently uploaded video. A dated featured sermon appears once, and is not repeated in the grid.
- **All Messages:** videos from the master Messages playlist, one record per YouTube video ID, ordered by sermon date newest first. Explicitly included undated videos remain visible at the bottom.
- **Search:** title and series name; filters for year and series. Results are paginated (12 video records per request).
- **Browse by Series:** discovered series with visible sermons; hidden series are omitted. A hidden series does *not* hide its member sermons from All Messages.
- **Visibility:** Include and Needs Review appear publicly unless explicitly Excluded. An excluded video cannot be opened with a direct `?message=` link from the website shortcode.
- **Dates:** staff `manual_date` takes precedence over the date parsed from a YouTube title.
- **Playback:** official `youtube-nocookie.com/embed/<video_id>` player on detail pages, plus a direct Watch on YouTube link. The church does not host any videos or audio.

## Performance and safety

- Rendering reads bounded, indexed WordPress metadata queries only. Normal visitor requests do not trigger migrations, imports, upstream API calls, or media downloads.
- Public input filters and video IDs are validated; SQL predicates are prepared and escaped; output is escaped.
- New page provisioning is a version-gated **admin-only** action and is intentionally **draft-first**. Existing page content is not overwritten.
- If the master sync has not completed, the shortcode displays an informational placeholder with a YouTube channel link.

## Later PRs

- Connect staff-archived sermon notes to matching YouTube videos where confident date/title matches exist, with staff corrections as needed.
- Add the same browsing experience to the Expo mobile app through a public read-only Tools endpoint. The app will continue to use YouTube's official player for archived recordings.
- Optional enhancements such as search relevance, year counts, and series art can be considered after live preview/testing.
