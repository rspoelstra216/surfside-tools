# Homepage Latest Sermon — launch checklist

## Why

The existing homepage **Watch a Service** section was built in the WordPress page editor with a fixed YouTube recording (at the time it still featured a May 17, 2026 message). This is separate from the managed, dynamically updated **Watch Live** player.

Use the shortcode below to show one latest-sermon *preview* on the homepage. **No video is streamed on the homepage**; visitors click the thumbnail or **Watch Sermon** to open the sermon on the public `/messages/` page. The teaser is sourced from the existing local YouTube archive, not Cloudflare or a hard-coded ID.

## WordPress homepage change (editor action after PR deployment)

1. Open **Pages → Home → Edit** (or the Site Editor's Home page, whichever holds the current **Watch a Service** section).
2. Locate the old **Watch a Service** heading, description, stale May video block/embed, and the **Watch Live** / **Sermon Archive** buttons.
3. Replace that **entire group/section**, rather than merely adding another section underneath it, with one **Shortcode** block containing:

   `[surfside_home_latest_sermon]`

4. Preview desktop and mobile, then **Update** the homepage. Confirm the old static embedded video no longer appears.
5. Verify clicking the teaser image / **Watch Sermon** opens the latest eligible sermon on `/messages/?sm_video=<video ID>`, while **Browse All Messages** and **Watch Live or catch up on a service** lead to the correct internal pages.

## Navigation and Watch Live page links (editor/dashboard settings)

- **Dashboard → Manage Website → Navigation**: Add the published **Messages** page as a page-type navigation item, positioning it immediately after **Watch Live**. Save. This is the canonical shared navigation list; **header and footer update together**. Do not separately hard-code another footer list or replace the stored navigation by changing plugin defaults.
- **Pages → Watch Live → Edit**: In the existing lower **Missed Sunday? Catch Up Anytime** section, leave the working YouTube player and Spotify / Apple Podcasts / Spreaker links as they are. Update only the **View Sermon Archive** button text to **Browse All Messages** and point it at the published internal `/messages/` page. Save.
- Do not change the Cloudflare player or its latest *full-service* replay. That serves a separate purpose from the YouTube sermon archive.

## Data/refresh behavior

- The shortcode uses the existing `surfside_tools_public_messages_latest()` query, so it honors explicit staff exclusions and manual sermon-date overrides and chooses the latest actual sermon date. It never uses YouTube publication/upload dates to decide what's the latest **sermon**.
- All video and image content remains on YouTube. This preview uses only one cached WordPress metadata lookup and thumbnail; no YouTube API calls or Cloudflare requests occur on a visitor view.
- A successful YouTube synchronization updates the metadata and triggers the **existing homepage-cache purge helper** once, making new latest sermons eligible for display without a staff manual homepage edit. Full-page caching may still be affected by an external CDN beyond WordPress.
- When no eligible sermon exists, the section degrades to a simple **Browse All Messages** link rather than showing a broken video.

## Test considerations

- Mobile title wrapping, responsive thumbnail, buttons and absence of duplicate homepage video blocks.
- The latest message published on YouTube tomorrow should replace the previous homepage feature **after the next completed scheduled sync** if it belongs to the master Messages playlist and has a recognizable/manual sermon date.
- No page content is rewritten automatically when the plugin is installed; WordPress-authored sections must be changed through the editor to prevent overwriting design work.
