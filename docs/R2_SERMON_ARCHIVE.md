# Cloudflare R2 sermon archive copies

## Architecture

YouTube remains the canonical public archive and metadata source. Its synchronized master Messages playlist determines valid video IDs, titles, dates, series and review decisions. R2 stores ONLY Roku-targeted MP4s under deterministic keys:

    sermons/<youtube_video_id>.mp4

Surfside Tools stores verified object ID, key, size, ETag and status, without duplicating YouTube metadata. The website/mobile message archive remains on YouTube; the separate public Roku catalog API uses verified R2 copies and the configured production playback domain.

## One-time setup

1. In Cloudflare R2, create a **Standard** storage bucket (e.g. surfside-sermons). Make an **R2 S3 API access key** scoped to this bucket, permitting ListObjects, HeadObject and PutObject. The Access Key ID and Secret Access Key are not a generic Cloudflare API token.
2. Add the following to the production WordPress wp-config.php, replacing placeholders. NEVER commit real credentials to the repository:

       define('SURFSIDE_R2_ACCOUNT_ID', 'ACCOUNT_ID_32_HEX_CHARACTERS');
       define('SURFSIDE_R2_BUCKET', 'surfside-sermons');
       define('SURFSIDE_R2_ACCESS_KEY_ID', 'R2_ACCESS_KEY_ID');
       define('SURFSIDE_R2_SECRET_ACCESS_KEY', 'R2_SECRET_ACCESS_KEY');

   The public Roku catalog uses the verified R2 custom domain:

       define('SURFSIDE_R2_PUBLIC_BASE_URL', 'https://video.surfsidesermons.com');

3. Set CORS on the R2 bucket for the exact Surfside dashboard origin. Require PUT, allow Content-Type, expose ETag. For S3-style CORS JSON:

       [
         {
           "AllowedOrigins": ["https://surfsidefellowship.org"],
           "AllowedMethods": ["PUT"],
           "AllowedHeaders": ["Content-Type"],
           "ExposeHeaders": ["ETag"],
           "MaxAgeSeconds": 3600
         }
       ]

   Cloudflare's dashboard may use an alternate schema (allowed.origins, allowed.methods, allowed.headers, exposeHeaders, maxAgeSeconds). Configure the equivalent fields in its interface. Add an actual www origin only if staff use it; do not use wildcard origins.

4. First synchronize the YouTube master Messages playlist in Church Settings → Integrations. Then visit **Manage Website → Message Archive → Roku Copies**. This initializes its small status table on a staff-only visit, not on public WordPress requests.

## Fast YouTube refresh from the Roku Copies manager

The ordinary YouTube sync runs on a roughly daily schedule and discovers channel playlists, imports all master Messages videos, and matches series. Roku staff no longer need to wait for it.

**Recommended after each weekly YouTube upload:**

1. Add the published video to the public master **Messages** playlist on YouTube.
2. Open **Manage Website → Message Archive → Roku Copies**.
3. Paste the YouTube watch/share link into **Find This YouTube Message**. This issues exactly one YouTube Data API playlistItems.list request with BOTH playlistId and videoId. The result is accepted only if the matching ID is actually in Messages. The new metadata and membership are written to the existing tables without triggering a full scan or pruning other records.
4. The new video is selected in the upload dropdown. Choose the prepared MP4 and upload directly to R2.

The normal full YouTube sync remains available in Integrations and maintains series memberships. The older master-only refresh endpoint is retained internally for compatibility, but its maintenance button is no longer shown on Roku Copies.

An active synchronization disables new manual refreshes to prevent overlapping imports. An initial full sync is required before either fast option. Neither fast action modifies staff review decisions or the YouTube videos. The regular full sync is still needed to discover new series, reconcile renamed playlists and remove stale series memberships; its daily schedule remains intact.

YouTube's documented filter and quota: https://developers.google.com/youtube/data/reference/rest/v3/playlistItems/list

## Weekly Roku Copies page (post-migration UI)

Roku Copies now shows only the readiness summary, the one-video YouTube lookup, and the direct MP4 uploader. One-time NAS inventory, bulk CSV export, R2 reconciliation, master-only refresh, playlist ownership diagnostics, and the long per-video registration table are not displayed. The underlying bulk uploader, legacy export/reconciliation handlers and R2 status records are preserved to avoid disrupting the separate historical migration and for authorized recovery if needed.

## Weekly workflow

1. Publish the weekly video to YouTube normally, ensure it is in the master Messages playlist, and paste its link into **Find This YouTube Message** for an immediate one-request sync. Alternatively, let the daily sync run.
2. Prepare the Roku copy: MP4 container, H.264/AVC video, AAC-LC stereo audio, and fast-start MP4 index (moov atom before media). An MP4 extension alone does not guarantee Roku-compatible codecs.
3. From Message Archive → Roku Copies choose the YouTube video and the local MP4 and press **Upload to R2**.
4. The logged-in staff page first authorizes the upload through an ordinary same-origin authenticated WordPress POST (staff upload capability plus an action-specific nonce, without depending on REST cookie authentication). Only the YouTube video ID and file byte count are submitted to WordPress. The browser then uses a scoped, expiring presigned PUT to upload the MP4 **directly to R2**; media bytes never pass through WordPress. After upload, Tools checks R2 via HEAD and confirms expected size and Content-Type video/mp4 before marking the ID ready.
5. Reload to see the refreshed status. Existing R2 objects cannot be overwritten by the browser uploader.

**Large-file reliability tradeoff:** The first dashboard uploader uses one PUT, which supports up to 5 GiB but is not resumable. An interrupted ~2 GB upload restarts from zero. On slow/unreliable Internet, use the local AWS CLI multipart importer below for the weekly MP4 as well. Cloudflare recommends multipart for large objects.

## Historical bulk import (reference for existing migration tooling)

1. Use an existing canonical manifest of master YouTube video IDs, titles, dates, and local source_path values. The legacy CSV export action remains in the plugin for compatibility, but is no longer a visible weekly-dashboard control.
2. Fill source_path ONLY after you have positively matched the correct local recording to that YouTube ID. Use paths relative to a local --root directory, e.g. 2024/05 May - Message.mp4. Leave uncertain rows blank; never guess ID association based solely on a similar date/title. Keep the video_id column unchanged.
3. Install Python 3.9+ and AWS CLI v2 on the computer with the recordings. Configure these local environment variables using your OS secure environment handling: AWS_ACCESS_KEY_ID, AWS_SECRET_ACCESS_KEY, AWS_DEFAULT_REGION=auto. Do not put keys in the CSV.
4. Download scripts/upload-r2-sermons.py from the repository. Dry run first:

       python3 scripts/upload-r2-sermons.py \
         --manifest surfside-r2-sermon-manifest.csv \
         --root "/Volumes/Sermons" \
         --account-id YOUR_32_CHAR_ACCOUNT_ID \
         --bucket surfside-sermons

5. Inspect every match, then add --upload to the same command to run it. Large files use the AWS CLI's automatic multipart transfers. The script checks for already-existing objects and skips matching sizes/content types; it rejects conflicts rather than silently overwriting. Re-run it for failures or to resume a batch.
6. Historical uploads still require their R2 object status to be registered. The existing staff-authorized reconciliation backend is retained for the separate bulk-import workflow, but is no longer displayed on the weekly Roku Copies page. Do not assume uploading files externally automatically makes them ready in the Roku catalog.

Use ffprobe to check source codecs:

    ffprobe -v error -show_entries stream=codec_type,codec_name,profile -of default=noprint_wrappers=1 "original.mp4"

Already H.264/AAC? Remux for fast start without video re-encoding:

    ffmpeg -i original.mp4 -map 0:v:0 -map 0:a:0? -c copy -movflags +faststart prepared.mp4

Need conversion? For 1080p Roku-targeted playback, adjust quality to fit your needs:

    ffmpeg -i original.mp4 -map 0:v:0 -map 0:a:0? -c:v libx264 -preset medium -crf 21 -pix_fmt yuv420p -c:a aac -b:a 160k -ac 2 -movflags +faststart prepared.mp4

Protect original recordings and test several old/new converted recordings on an actual Roku before mass publication. Reconciliation checks uploaded object names and sizes, NOT actual codecs or playable stream content.

## Upload authorization on the front-end staff dashboard

The Roku Copies browser uploader submits only a video ID, file size, operation (ticket/complete), and action-specific WordPress nonce to the **same-origin** staff management page. It is handled before normal page rendering by a targeted `template_redirect` POST handler. This matches the proven YouTube staff refresh route and avoids an observed `rest_cookie_invalid_nonce` / “Cookie check failed” when the host processes REST cookie authentication. It does not disable or bypass WordPress login checks: both a logged-in WordPress session and `upload_files` capability are required, with a nonce verified on each request. The existing REST routes remain protected for compatibility but are no longer used by this page's uploader.

The request does **not** contain the MP4. The browser uploads media directly to R2 over an expiring signed S3 PUT, then uses the same staff POST path to request WordPress's existing HEAD verification and record the finished object. A failed authorization does not issue an R2 upload ticket. Test against a real logged-in staff session to confirm the host passes the page POST correctly.

## Security and deployment tests

- Only signed-in users with WordPress upload_files capability can request upload tickets, complete verifications, export the CSV, or run R2 reconciliation. REST calls use WordPress cookie/nonce authentication; admin-post uses nonce and permission.
- Credentials remain in wp-config.php. Browser receives only a 4-hour bearer-style presigned PUT for one ID; never put these URLs in logs or screenshots. Signatures use the S3 endpoint, not a public custom domain.
- No public R2 write routes, no upload through PHP, no per-visitor R2 requests, no global hook migrations, no deletion of R2 objects.
- Verify CORS/origin works in the real staff dashboard; check bad/missing ID, too-large file, duplicate key, signed-out user, unsuccessful HEAD, and complete verification. Confirm CSV contains YouTube IDs with blank source_path and that import dry-run rejects duplicates/missing/unsafe paths.
- R2 object readiness is distinct from **public visibility**. The Roku catalog checks master playlist membership, current staff exclusion decisions, and the readiness record before it returns playback URLs. The production R2 domain is https://video.surfsidesermons.com.
- Estimated initial storage 368 × 1.6–2 GB ≈ 589–736 GB. R2 Standard storage currently $0.015 per GB-month, with 10 GB-month free tier: approximately $8.70–$10.90 per month plus any operations, before future video conversion changes. Standard has no download/egress fees under R2 pricing terms.

Cloudflare references:
- https://developers.cloudflare.com/r2/api/s3/presigned-urls/
- https://developers.cloudflare.com/r2/objects/upload-objects/
- https://developers.cloudflare.com/r2/pricing/
