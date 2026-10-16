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

- **Latest message:** most recent **recognized or staff-corrected sermon date**, never a YouTube upload or publication date. A dated featured sermon appears once and is not repeated in the grid.
- **All Messages:** videos from the master Messages playlist, one record per YouTube video ID. Recognized and manual sermon dates take precedence. Videos **explicitly marked Include** with no sermon date use the video's real YouTube publication date as a chronological fallback, shown with the label **Published on YouTube**. Undated Needs Review videos remain visible and retain **Date not available**.
- **Search:** title and series name; year and series filters. The Year dropdown shows **only years with visible dated messages**, including the fallback years of explicitly included videos. Results are paginated (12 video records per request).
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

## Publication date source (2026-10-16 follow-up)

The existing importer captured `playlistItems.snippet.publishedAt` as `uploaded_at`, but YouTube documents that value as the **date the video was added to the playlist**, not the video publication date. Do **not** use that value for visitor-facing date fallback.

Schema v2 adds a separate `published_date` field sourced from `playlistItems.contentDetails.videoPublishedAt`, which is already provided by our existing `part=snippet,contentDetails` API call. No additional API requests are needed. On first sync after deploying this change, the existing three metadata tables are migrated and each master Messages item receives its publication date if YouTube supplies it.

The public archive checks schema readiness before referring to `published_date`, so it will not mistake pre-migration playlist-added timestamps for video publication. Leave unknown dates unknown when YouTube does not supply `videoPublishedAt`.

**Deployment validation:** Run Sync Now after the plugin update and wait for Complete. Verify a staff-Included undated 2014 video displays `Published on YouTube: <date>`, that 2014 appears in the Year filter if it contains public messages, and that Latest Message still follows its actual sermon date.
