# Mobile Message Archive — public read-only API

Surfside's native Expo app should browse **the same YouTube metadata already synchronized to WordPress**, with identical staff review, date, and series visibility rules. This PR adds the backend contract only. **Do not deploy a new app screen until this backend PR is merged, deployed, and verified.**

## GET endpoints (no login)

Base: `https://surfsidefellowship.org/wp-json/surfside/v1`

| Endpoint | Purpose |
| --- | --- |
| `GET /messages` | Browse 12 messages per page, with optional `search`, `year`, `series`, `page` query parameters |
| `GET /messages/series` | Available sermon series, with titles, message counts, and YouTube thumbnail artwork |
| `GET /messages/{video_id}` | One publicly visible message and its visible series memberships (video ID must be exactly 11 YouTube ID characters) |

The list response contains:
```json
{
  "api_version": 1,
  "available": true,
  "latest": {
    "id": "<youtube-video-id>",
    "title": "Full original YouTube title",
    "display_title": "Short message headline",
    "subtitle": "Sermon series · Week 2",
    "date": { "value": "2026-09-20", "source": "sermon", "label": "September 20, 2026" },
    "thumbnail_url": "https://i.ytimg.com/...",
    "watch_url": "https://www.youtube.com/watch?v=<youtube-video-id>",
    "embed_url": "https://www.youtube-nocookie.com/embed/<youtube-video-id>"
  },
  "years": [2026, 2025, 2024],
  "items": [],
  "pagination": { "page": 1, "page_size": 12, "total": 368, "total_pages": 31, "has_more": true }
}
```
(The values shown are illustrative, not fixed response data.)

The `/messages/series` response is `{ "api_version": 1, "available": true, "series": [...] }`. Each series has `id`, `title`, `message_count`, and `thumbnail_url`. The detail response is `{ "api_version": 1, "message": { ... }, "series": [{ "id": "...", "title": "..." }] }`.

When the backend has no completed metadata import, list/series respond with `available: false` and empty collections. Excluded, nonexistent, and non-master videos return HTTP **404** on detail. Hidden or unknown series return **404** when directly selected. Malformed year, series, page, or video ID returns **400**.

## Key behavioral agreements

- **All Messages:** exactly one record per video in the master Messages playlist, ordered by effective date (newest first), then existing stable tie-breakers. Needs Review remains visible unless explicitly Excluded.
- **Manual corrections:** staff-selected dates override title-parsed dates. For explicitly Included videos without a sermon date, the genuine YouTube publication date is a fallback and clearly labeled `Published on YouTube`.
- **Latest sermon:** the most recent **recognized or staff-corrected sermon date**; YouTube publication timestamps do *not* choose the feature.
- **Series:** only discovered series with visible messages; staff-hidden series do not appear or expose a series-filtered listing, but their individually visible messages still appear in All Messages.
- **Playback:** clients use YouTube's supported embed/watch URLs. Do not extract media, feed YouTube streams to `expo-video`, or attach the existing native Cloudflare floating/PiP player to these videos.
- **No new storage or duplication:** videos remain on YouTube. Read-only endpoints never perform a sync, migration, API download, or metadata write and never expose the YouTube API key or unpublished staff notes.
- **Pagination:** server limits browsing to 12 records per request and caps `page` at 100000. Only series and year filters from the public archive are honored; unsupported filters should not silently widen a hidden playlist.

## Manual deployment smoke tests

After merging and deploying the Tools plugin, open these public URLs **without logging in**:

1. `https://surfsidefellowship.org/wp-json/surfside/v1/messages` — should return JSON with `available: true`, latest, dynamic years, and a maximum of 12 items.
2. `https://surfsidefellowship.org/wp-json/surfside/v1/messages?search=Love` — should return a filtered JSON list.
3. `https://surfsidefellowship.org/wp-json/surfside/v1/messages?page=2` — should return the second page with correct continuation fields.
4. `https://surfsidefellowship.org/wp-json/surfside/v1/messages/series` — should return only visible series with titles and thumbnails.
5. Substitute a real allowed 11-character ID at `/messages/{video_id}` and verify its detail and YouTube embed URL. Substitute an excluded video's ID and verify HTTP 404.
6. Open `?year=invalid`, `?page=0`, and a known staff-hidden series ID; ensure HTTP 400/404, never the unfiltered archive.

No app store release or rebuild is required for this backend-only change.

## Next PR (surfside-app)

After the above passes, add an **Archive / Messages** entry to the existing Worship screen that opens a native Browse Messages screen with series, year/search filters, infinite or paged scrolling, and a sermon detail with official YouTube embedded playback and a Watch on YouTube fallback. Keep the existing Cloudflare Live / Last Service experience, current-week notes, and floating-player context unchanged. Consider linking stored archived sermon notes to videos in a separate subsequent PR.
