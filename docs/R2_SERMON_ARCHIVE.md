# Cloudflare R2 sermon archive copies

## Architecture

YouTube remains the canonical public archive and metadata source. Its synchronized master Messages playlist determines valid video IDs, titles, dates, series and review decisions. R2 stores ONLY Roku-targeted MP4s under deterministic keys:

    sermons/<youtube_video_id>.mp4

Surfside Tools stores verified object ID, key, size, ETag and status, without duplicating YouTube metadata. The current WordPress/site/mobile message playback remains on YouTube. **This PR does not expose a public R2 or Roku playback API.**

## One-time setup

1. In Cloudflare R2, create a **Standard** storage bucket (e.g. surfside-sermons). Make an **R2 S3 API access key** scoped to this bucket, permitting ListObjects, HeadObject and PutObject. The Access Key ID and Secret Access Key are not a generic Cloudflare API token.
2. Add the following to the production WordPress wp-config.php, replacing placeholders. NEVER commit real credentials to the repository:

       define('SURFSIDE_R2_ACCOUNT_ID', 'ACCOUNT_ID_32_HEX_CHARACTERS');
       define('SURFSIDE_R2_BUCKET', 'surfside-sermons');
       define('SURFSIDE_R2_ACCESS_KEY_ID', 'R2_ACCESS_KEY_ID');
       define('SURFSIDE_R2_SECRET_ACCESS_KEY', 'R2_SECRET_ACCESS_KEY');

   Optional, for a FUTURE Roku delivery PR only, once you have configured a tested public R2 custom domain:

       define('SURFSIDE_R2_PUBLIC_BASE_URL', 'https://sermons.example.org');

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

If you don't have a video link, **Refresh All Messages (No Series Scan)** does a complete master playlist scan (up to 50 videos per request), without rescanning channel and series playlists. That refresh uses the existing bounded WP-Cron page worker, so return to **Check progress** to see when it finishes. It will only finalize/prune master playlist membership after a complete scan; it never deletes series memberships. On a master-only completion, the separate last_master_refresh timestamp is recorded and the last complete full-sync timestamp remains unchanged.

An active synchronization disables new manual refreshes to prevent overlapping imports. An initial full sync is required before either fast option. Neither fast action modifies staff review decisions or the YouTube videos. The regular full sync is still needed to discover new series, reconcile renamed playlists and remove stale series memberships; its daily schedule remains intact.

YouTube's documented filter and quota: https://developers.google.com/youtube/data/reference/rest/v3/playlistItems/list

## Weekly workflow

1. Publish the weekly video to YouTube normally, ensure it is in the master Messages playlist, and paste its link into **Find This YouTube Message** for an immediate one-request sync. Alternatively, let the daily sync run.
2. Prepare the Roku copy: MP4 container, H.264/AVC video, AAC-LC stereo audio, and fast-start MP4 index (moov atom before media). An MP4 extension alone does not guarantee Roku-compatible codecs.
3. From Message Archive → Roku Copies choose the YouTube video and the local MP4 and press **Upload to R2**.
4. The browser uses a scoped, expiring presigned PUT to upload straight to R2; the media does NOT pass through WordPress. After upload, Tools checks R2 via HEAD and confirms expected size and Content-Type video/mp4 before marking the ID ready.
5. Reload to see the refreshed status. Existing R2 objects cannot be overwritten by the browser uploader.

**Large-file reliability tradeoff:** The first dashboard uploader uses one PUT, which supports up to 5 GiB but is not resumable. An interrupted ~2 GB upload restarts from zero. On slow/unreliable Internet, use the local AWS CLI multipart importer below for the weekly MP4 as well. Cloudflare recommends multipart for large objects.

## Historical bulk import (approximately 368 originals)

1. Click **Download bulk-import CSV template**. It contains one row per known master YouTube video ID, its title, date, and a blank source_path.
2. Fill source_path ONLY after you have positively matched the correct local recording to that YouTube ID. Use paths relative to a local --root directory, e.g. 2024/05 May - Message.mp4. Leave uncertain rows blank; never guess ID association based solely on a similar date/title. Keep the video_id column unchanged.
3. Install Python 3.9+ and AWS CLI v2 on the computer with the recordings. Configure these local environment variables using your OS secure environment handling: AWS_ACCESS_KEY_ID, AWS_SECRET_ACCESS_KEY, AWS_DEFAULT_REGION=auto. Do not put keys in the CSV.
4. Download scripts/upload-r2-sermons.py from the repository. Dry run first:

       python3 scripts/upload-r2-sermons.py \
         --manifest surfside-r2-sermon-manifest.csv \
         --root "/Volumes/Sermons" \
         --account-id YOUR_32_CHAR_ACCOUNT_ID \
         --bucket surfside-sermons

5. Inspect every match, then add --upload to the same command to run it. Large files use the AWS CLI's automatic multipart transfers. The script checks for already-existing objects and skips matching sizes/content types; it rejects conflicts rather than silently overwriting. Re-run it for failures or to resume a batch.
6. Click **Reconcile R2 objects** in Tools. This explicitly lists R2 sermon keys, compares exact 11-character IDs to the locally synchronized YouTube master playlist, saves matches and ignores unknown keys. No YouTube metadata is created or changed. Missing objects are reflected after reconciliation.

Use ffprobe to check source codecs:

    ffprobe -v error -show_entries stream=codec_type,codec_name,profile -of default=noprint_wrappers=1 "original.mp4"

Already H.264/AAC? Remux for fast start without video re-encoding:

    ffmpeg -i original.mp4 -map 0:v:0 -map 0:a:0? -c copy -movflags +faststart prepared.mp4

Need conversion? For 1080p Roku-targeted playback, adjust quality to fit your needs:

    ffmpeg -i original.mp4 -map 0:v:0 -map 0:a:0? -c:v libx264 -preset medium -crf 21 -pix_fmt yuv420p -c:a aac -b:a 160k -ac 2 -movflags +faststart prepared.mp4

Protect original recordings and test several old/new converted recordings on an actual Roku before mass publication. Reconciliation checks uploaded object names and sizes, NOT actual codecs or playable stream content.

## Security and deployment tests

- Only signed-in users with WordPress upload_files capability can request upload tickets, complete verifications, export the CSV, or run R2 reconciliation. REST calls use WordPress cookie/nonce authentication; admin-post uses nonce and permission.
- Credentials remain in wp-config.php. Browser receives only a 4-hour bearer-style presigned PUT for one ID; never put these URLs in logs or screenshots. Signatures use the S3 endpoint, not a public custom domain.
- No public R2 routes, no upload through PHP, no per-visitor R2 calls, no global hook migrations, no deletion of R2 objects.
- Verify CORS/origin works in the real staff dashboard; check bad/missing ID, too-large file, duplicate key, signed-out user, unsuccessful HEAD, and complete verification. Confirm CSV contains YouTube IDs with blank source_path and that import dry-run rejects duplicates/missing/unsafe paths.
- R2 object readiness is distinct from **public visibility**. A later Roku endpoint must check master playlist membership, current staff exclusion decisions, and the readiness record before it returns playback URLs. Validate byte-range seeking and Content-Type through the real public R2 custom domain in that follow-up PR.
- Estimated initial storage 368 × 1.6–2 GB ≈ 589–736 GB. R2 Standard storage currently $0.015 per GB-month, with 10 GB-month free tier: approximately $8.70–$10.90 per month plus any operations, before future video conversion changes. Standard has no download/egress fees under R2 pricing terms.

Cloudflare references:
- https://developers.cloudflare.com/r2/api/s3/presigned-urls/
- https://developers.cloudflare.com/r2/objects/upload-objects/
- https://developers.cloudflare.com/r2/pricing/
