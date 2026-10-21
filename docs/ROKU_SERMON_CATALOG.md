# Roku sermon catalog API (Surfside Tools)

This is the backend catalog, not the Roku channel application. It reuses
Surfside Tools' existing YouTube metadata, staff review decisions, series
associations and verified R2 copy status.

## Production prerequisites

- The YouTube master Messages playlist has synced through Surfside Tools.
- R2 copies have been uploaded and verified in Roku Copies, or reconciled
  after a bulk import. The object key is sermons/<11-character-youtube-id>.mp4.
- WordPress wp-config.php defines SURFSIDE_R2_PUBLIC_BASE_URL pointing to
  https://video.surfsidesermons.com.
- The Cloudflare R2 custom domain is active and MP4 playback + seeking work.

The existing church DNS, nameservers, website and email are not changed.
Without the public base URL, R2 status table, or master playlist, list
endpoints safely return available:false and no videos. Detail returns 404.

## Public read-only endpoints

| URL | Purpose |
| --- | --- |
| GET /wp-json/surfside/v1/roku/messages | Recent playable sermons, 12 per page |
| GET /wp-json/surfside/v1/roku/messages?page=2&series=PL... | Paginated or series-filtered sermons |
| GET /wp-json/surfside/v1/roku/messages?year=2025&month=2 | February 2025 playable sermons |
| GET /wp-json/surfside/v1/roku/series | Series with one or more playable sermons |
| GET /wp-json/surfside/v1/roku/messages/<youtube-id> | One playable sermon and visible series |

Search/year/month/series/page filters use the existing mobile Message Archive's
strict validation and staff series visibility rules. Month is only valid when
a year is also selected.

The message-list response includes a descending `years` array containing only
years that have at least one verified Roku-ready R2 recording. When a specific
year is selected, it also includes an ascending `months` array containing only
months in that year with at least one verified Roku-ready R2 recording.

Each item includes the canonical YouTube video ID, title, short display
heading, date/source, YouTube thumbnail and watch URL, plus a playback object.
Example:

{
  "id": "OTt1dfYhlDY",
  "title": "Made to Serve – Made for More Series – Week 6 – 18 October 2026",
  "thumbnail_url": "https://i.ytimg.com/vi/OTt1dfYhlDY/hqdefault.jpg",
  "playback": {
    "url": "https://video.surfsidesermons.com/sermons/OTt1dfYhlDY.mp4",
    "format": "mp4",
    "content_type": "video/mp4"
  }
}

The SQL joins the R2 status table and requires status = ready and the expected
object key BEFORE counting and pagination. Only public, staff-visible sermons
with verified copies appear. Missing/unverified R2 objects never appear in
the catalog. There is no extra R2 HEAD per card and no live YouTube API call.
Actual media bytes flow from R2 to the Roku device, never through WordPress.

## Sync and staff visibility

- Single-video YouTube lookup updates the master metadata and membership;
  after a verified R2 upload the video can appear in All Messages.
- Series membership is maintained by the existing regular full YouTube sync.
  A new sermon might appear in its series after the next full sync.
- After bulk import, staff must run R2 reconciliation before the Roku API
  knows which MP4s were uploaded by the script.
- Staff-hidden videos and series stay hidden on Roku, even if R2 has a copy.
- A later external bucket deletion isn't recognized until reconciliation.
- The existing website and mobile YouTube catalogs are unchanged.

## Live test after merging

1. GET /wp-json/surfside/v1/roku/messages should report available:true,
   the dynamic R2-ready `years` array, pagination totals, and playback URLs.
2. GET /wp-json/surfside/v1/roku/messages?year=2025 should return a dynamic
   `months` array for 2025; adding `&month=2` should return February only.
3. GET /wp-json/surfside/v1/roku/series should omit YouTube-only series.
4. GET /wp-json/surfside/v1/roku/messages/OTt1dfYhlDY should return a
   playable MP4 URL and appropriate visible series.
5. A valid but unregistered video ID should return 404; invalid list
   filters, including month without year, should return HTTP 400.
6. Open the returned MP4 URL in a browser and seek within it. Actual Roku
   device compatibility needs testing later when the Roku application exists.

This PR changes no database schema and adds no admin screen.
