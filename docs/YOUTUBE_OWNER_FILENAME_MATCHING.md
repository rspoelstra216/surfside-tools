# Owner-authorized YouTube filename matching for R2 bulk import

YouTube remains the public archive and metadata authority. This workflow **does not upload, transcode, rename, or modify** NAS originals, YouTube videos, R2 objects, or existing message review decisions. It produces a conservative upload manifest for the existing dry-run-first CLI importer.

## Prerequisites

- Initial YouTube master Messages playlist synchronization complete in **Church Settings → Integrations**.
- Staff WordPress account with manage_options permission.
- YouTube channel owner / properly authorized Brand Account manager login.
- Google Cloud project with **YouTube Data API v3** enabled. The existing public API key cannot retrieve owner fileDetails.
- A **Web application OAuth client** in Google Cloud. Configure an OAuth consent screen; while in Testing, add the channel-owner login as a test user. Google may expire Testing refresh tokens after seven days. If using an External app, additional Google verification rules may apply when publishing.
- R2 need not be configured for the matching phase.

Add these private constants to production wp-config.php (never to GitHub):

    define('SURFSIDE_YOUTUBE_OAUTH_CLIENT_ID', 'YOUR_GOOGLE_WEB_CLIENT_ID');
    define('SURFSIDE_YOUTUBE_OAUTH_CLIENT_SECRET', 'YOUR_GOOGLE_WEB_CLIENT_SECRET');

Visit **Manage Website → Message Archive → Roku Copies → Historical import** as an admin. Copy the **exact Google OAuth redirect URI** shown in the interface into your Google Cloud OAuth web client under **Authorized redirect URIs**. It includes view=roku-media and owner_oauth=callback parameters; scheme, host, path, and query must exactly match. Keep the WordPress login active through the redirect.

Click **Connect YouTube owner**, sign in, and select the Surfside channel / Brand Account. The callback checks channels.list(mine=true) against the channel ID from the existing completed playlist sync. Authorization for the wrong channel is rejected. The connection uses the youtube.readonly scope and offline refresh permission; neither the client secret nor owner OAuth tokens are sent to visitors.

## Retrieve video filenames

Click **Retrieve original filenames from YouTube**. The background worker requests only videos.list with fileDetails and processingDetails for up to 50 video IDs from the synchronized master Messages playlist. It uses one bounded 50-ID request per scheduled tick. Reload **Check progress** until Complete. If YouTube reports an ownership-forbidden video, the worker divides only the affected batch into smaller requests to identify and flag inaccessible IDs for manual review. If quota is exhausted or a transient error occurs, scanning pauses with its cursor and all completed filename records retained. Use **Resume filename retrieval (keep saved results)** after resolving the limit/authorization; this also resumes scans stopped by the earlier implementation. Do not click a fresh retrieval when you only need to resume an interrupted scan. Metadata is stored in a non-autoloaded, server-side WordPress option, keyed by YouTube ID.

Some older YouTube videos may lack fileDetails. Those rows remain explicitly unmatched; they are never assigned a local file by date guessing.

Owner access/refresh tokens are kept in a non-autoloaded WordPress option. Treat WordPress backups and database access as sensitive. **Disconnect owner account** clears stored tokens, fetched filenames and scan status; optionally revoke permission from Google's account settings as well. Public YouTube metadata imports stay on their existing API-key code path.

## Inventory the Synology sermon archive

On a Mac mounted to the NAS shared folder, or on Synology if Python 3 is installed, run:

    python3 scripts/inventory-sermon-files.py \
      --root "/Volumes/YourNAS/Backup/DESKTOP-NE2CG3V/E" \
      --output "/tmp/sermon-inventory.csv"

Use the actual mounted path. The script walks nested year/month folders without following symlinks, collects MP4 relative paths and byte sizes, and **does not move the files**. No R2 credentials are required. Choose the same root later when running the uploader.

Upload the small inventory CSV into the Historical import section. Surfside Tools downloads surfside-r2-matched-manifest.csv, with the existing video_id,title,sermon_date,source_path columns plus diagnostics:

- **matched**: unique, case-sensitive original basename and exact byte-size agreement; source_path populated.
- **duplicate_filename**: multiple files with the same basename; no automatic match.
- **size_mismatch**: matching basename but byte counts differ; no automatic match (a remux or post-upload edit may explain this).
- **youtube_details_unavailable**, **no_filename_match**, **local_size_missing**: review manually; source_path blank.

**Do not fill ambiguous entries from an inferred date alone.** Manually confirm the actual MP4 recording and YouTube video before adding any source_path.

## Manual matching without renaming NAS recordings

A completed owner scan now includes **Manually match orphaned sermons** beneath the manifest download form. It is restricted to WordPress administrators.

1. Upload the current NAS inventory CSV and click **Download matched R2 manifest** once. The export also stores a lightweight copy of valid inventory paths and byte sizes inside WordPress for manual review. Return to the Roku Copies page to see unmatched YouTube messages.
2. Each unmatched video displays its YouTube ID, title, original uploaded filename, and a link to watch it. When precisely one *unused* NAS path has the expected byte size, that path is suggested—not silently associated. Other paths can be selected using the NAS path autocomplete.
3. **Verify the actual recording** (do not rely on a date or file-size match alone), check the confirmation checkbox, and click **Save match**. This saves a YouTube ID → relative NAS file path association. **No NAS filenames or local files are changed.** Saved associations can be updated or removed.
4. For every subsequent manifest export, select the **latest NAS inventory CSV** again. Only paths that still exist in that uploaded inventory can populate source_path. A saved association with a missing path or changed size exports a blank path with manual_path_missing or manual_size_mismatch for review.

Automatic original-filename + byte-size matches always take priority. Valid manually associated rows export as **manually_matched**, or **manual_unverified_youtube_size** when owner file size is unavailable and staff explicitly verified the content.

### Verified size differences

A NAS copy can differ in size from YouTube's original uploaded file (e.g., an independently trimmed sermon). For these cases, a reviewer can still choose the NAS path in **Needs review**, verify the actual contents against the YouTube recording, check **both** the normal recording-verification checkbox and the additional **size-difference approval** checkbox, and save. Without the second checkbox, a differing size is rejected and the precise byte counts are shown **in the manual matching section** after redirect.

Approved differing-size associations export as **manually_matched_size_override** with source_path populated. The approval records the exact NAS path, NAS byte count, and YouTube original byte count. Future manifest exports still require the path to exist in the latest supplied NAS inventory; if the NAS or YouTube byte count changes, the old approval no longer applies, producing **manual_size_mismatch** with a blank source_path. This is a deliberate human-reviewed exception, **not** a claim that two unequal MP4 files are identical; verify playback and codec compatibility before uploading to R2. The exporter never assigns the same source path to two video IDs in one manifest; a conflict yields review_duplicate_source_path with an empty path. A manual path is also restricted to one saved video ID.

The upload CLI still sees only video_id and source_path; diagnostic statuses do not change the uploader or the R2 key format (sermons/<YouTube-ID>.mp4). A manual match is **not** an upload. Continue to dry-run and verify compatibility before bulk uploads.

## Dry run, import and reconcile

The matched CSV is compatible with the existing scripts/upload-r2-sermons.py importer, which ignores extra diagnostic columns. Follow docs/R2_SERMON_ARCHIVE.md for the exact command, beginning without --upload. Inspect entries and run a subset first before a large migration.

The existing uploader names each object sermons/<youtube_video_id>.mp4, flattening the original NAS folder structure **only in R2**, not locally. It performs multipart uploads through AWS CLI v2. After uploading, press **Reconcile R2 objects** in Surfside Tools and verify a real Roku/MP4 playback test before publishing a large batch.

The current importer requires AWS CLI v2. rclone on the DS418j is a possible future uploader but is **not introduced in this PR**. Use the existing supported importer until a separate rclone PR is tested.

## Security/functional regression checklist

1. Admin sees historical-import panel; non-admin staff does not see OAuth controls or owner filenames.
2. Missing Google constants disable Connect without changing existing YouTube sync or Roku Copies upload.
3. Wrong channel or expired OAuth state cannot complete a connection.
4. Owner authorization and filename scan do not change existing YouTube public catalog, playlists or R2 objects.
5. Scan yields counts for available and missing metadata, and stops on YouTube quota/authorization errors.
6. Inventory with nested paths yields matches only when both filename and size agree. Duplicate/missing/mismatched entries remain blank.
7. Run the matched CSV through the existing uploader **without** --upload and confirm no source files move or change.
8. Disconnect removes tokens and metadata without altering the public archive.
