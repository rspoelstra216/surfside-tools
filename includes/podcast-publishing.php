<?php
/**
 * Proposed Spreaker metadata from the existing YouTube sermon catalog.
 * No external calls, uploads, or publishing are performed by this module.
 */
if (!defined('ABSPATH')) { exit; }

function surfside_tools_podcast_episode_preview($youtube_title, $sermon_date, $youtube_id) {
    $original = trim((string) $youtube_title);
    $without_date = preg_replace(
        '/\s+[-–—]\s+\d{1,2}\s+(?:January|February|March|April|May|June|July|August|September|October|November|December),?\s+\d{2,4}$/iu',
        '', $original
    );
    $base = trim($without_date);
    $week = '';
    if (preg_match('/^(.*?)\s+[-–—]\s+Week\s+(\d{1,2})$/iu', $base, $match)) {
        $base = trim($match[1]);
        $week = (string) ((int) $match[2]);
    }
    $parts = preg_split('/\s+[-–—]\s+/u', $base);
    $message = trim((string) ($parts[0] ?? ''));
    $series_parts = count($parts) > 1 ? array_slice($parts, 1) : array();
    $series = count($series_parts) > 1
        ? trim(array_shift($series_parts)) . ': ' . implode(' - ', $series_parts)
        : trim((string) ($series_parts[0] ?? ''));
    $date_text = '';
    $date = is_string($sermon_date)
        ? DateTimeImmutable::createFromFormat('!Y-m-d', $sermon_date) : false;
    if ($date && $date->format('Y-m-d') === $sermon_date) {
        $date_text = $date->format('F j, Y');
    }

    $recognized = $week !== '' && $message !== '' && $series !== '';
    // A dated, single-title sermon is valid without a series or week.
    // Require the canonical sermon date so undated titles aren't silently
    // normalized; keep malformed multi-part titles in review.
    $standalone = $week === '' && count($parts) === 1 && $message !== '' &&
        $without_date !== $original && $date_text !== '';
    $title = $recognized ? $message . ' | ' . $series . ' (Week ' . $week . ')'
        : ($standalone ? $message : $original);
    $valid_id = is_string($youtube_id) && preg_match('/^[A-Za-z0-9_-]{11}$/D', $youtube_id);
    $description = '"' . (($recognized || $standalone) ? $message : $original) . '" — a sermon from Surfside Community Fellowship.';
    if ($series !== '') $description .= "\nSeries: " . $series;
    if ($week !== '') $description .= "\nWeek: " . $week;
    if ($date_text !== '') $description .= "\nSermon date: " . $date_text;
    if ($valid_id) $description .= "\n\nWatch on YouTube: https://www.youtube.com/watch?v=" . $youtube_id;
    $description .= "\nLearn more: https://surfsidefellowship.org";

    return array(
        'title' => $title,
        'description' => $description,
        'needs_review' => !($recognized || $standalone) || $date_text === '' || !$valid_id || strlen($title) > 140,
    );
}



/**
 * Archive lookups are restricted to a valid canonical sermon date and exact
 * normalized message title. A shared date alone is never enough to attach
 * another sermon or another service's notes.
 *
 * Only staff preview/explicit podcast upload paths call this helper. Results
 * are cached for the current request so the dropdown does not repeatedly
 * query the archive for sermons with the same date.
 */
function surfside_tools_podcast_note_title_key($title) {
    $text = html_entity_decode(trim((string) $title), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = strtolower($text);
    return trim((string) preg_replace('/\s+/u', ' ',
        preg_replace('/[^\p{L}\p{N}]+/u', ' ', $text)));
}

function surfside_tools_podcast_find_archived_notes($message_title, $sermon_date) {
    if (!function_exists('surfside_tools_sermon_archive_table') ||
        !defined('SURFSIDE_TOOLS_SERMON_ARCHIVE_SCHEMA_OPTION') ||
        !defined('SURFSIDE_TOOLS_SERMON_ARCHIVE_SCHEMA_VERSION') ||
        // Title/date podcast descriptions only require the original archive
        // columns; the optional video-ID relationship was added in schema v2.
        (int) get_option(SURFSIDE_TOOLS_SERMON_ARCHIVE_SCHEMA_OPTION, 0) < 1) {
        return array('status' => 'archive_unavailable');
    }
    $date = is_string($sermon_date)
        ? DateTimeImmutable::createFromFormat('!Y-m-d', $sermon_date) : false;
    if (!$date || $date->format('Y-m-d') !== $sermon_date) {
        return array('status' => 'no_match');
    }
    $title_key = surfside_tools_podcast_note_title_key($message_title);
    if ($title_key === '') return array('status' => 'no_match');

    // Weekly Update's Word documents generally use "November 8, 2026";
    // retain support for ISO dates and common staff-edited date spellings.
    $dates = array_values(array_unique(array(
        $date->format('Y-m-d'), $date->format('F j, Y'),
        $date->format('F d, Y'), $date->format('F j Y'),
        $date->format('M j, Y'), $date->format('M. j, Y'),
        $date->format('M d, Y'), $date->format('n/j/Y'),
        $date->format('m/d/Y'),
    )));
    static $by_date = array();
    if (!array_key_exists($sermon_date, $by_date)) {
        global $wpdb;
        $table = surfside_tools_sermon_archive_table();
        $placeholders = implode(', ', array_fill(0, count($dates), '%s'));
        $sql = "SELECT title, message_date, main_heading, notes, prayer
            FROM {$table} WHERE message_date IN ({$placeholders})
            ORDER BY id DESC LIMIT 30";
        $rows = $wpdb->get_results($wpdb->prepare($sql, $dates), ARRAY_A);
        $by_date[$sermon_date] = is_array($rows) ? $rows : array();
    }

    $matches = array();
    foreach ($by_date[$sermon_date] as $record) {
        // Check the date as well as the title, even if a database adapter
        // unexpectedly returns more rows than the exact-date SQL requested.
        if (!in_array((string) ($record['message_date'] ?? ''), $dates, true) ||
            surfside_tools_podcast_note_title_key($record['title'] ?? '') !== $title_key) {
            continue;
        }
        $matches[] = $record;
    }
    if (count($matches) > 1) return array('status' => 'ambiguous');
    if (!$matches) return array('status' => 'no_match');
    return array('status' => 'matched', 'record' => $matches[0]);
}

/** Attach written notes before the existing source links, without changing audio. */
function surfside_tools_podcast_episode_with_notes($youtube_title, $sermon_date, $youtube_id) {
    $preview = surfside_tools_podcast_episode_preview($youtube_title, $sermon_date, $youtube_id);
    $preview['notes_status'] = 'no_match';
    if ($preview['needs_review']) {
        $preview['notes_status'] = 'metadata_review';
        return $preview;
    }

    // The recognized podcast title begins with the actual message title;
    // series/week decorations are only for the episode display.
    $message_title = explode(' | ', $preview['title'], 2)[0];
    $found = surfside_tools_podcast_find_archived_notes($message_title, $sermon_date);
    $preview['notes_status'] = $found['status'];
    if ($found['status'] !== 'matched') return $preview;

    $record = $found['record'];
    $heading = trim((string) ($record['main_heading'] ?? ''));
    $notes = trim((string) ($record['notes'] ?? ''));
    if ($heading !== '' && function_exists('surfside_tools_notes_without_main_heading')) {
        $notes = trim(surfside_tools_notes_without_main_heading($notes, $heading));
    }
    $prayer = trim((string) ($record['prayer'] ?? ''));
    $parts = array();
    if ($heading !== '') $parts[] = $heading;
    if ($notes !== '') $parts[] = $notes;
    if ($prayer !== '') $parts[] = "Closing Prayer\n" . $prayer;
    if (!$parts) {
        $preview['notes_status'] = 'empty';
        return $preview;
    }

    $description = $preview['description'];
    $links_at = strpos($description, "\n\nWatch on YouTube:");
    if ($links_at === false) {
        $preview['notes_status'] = 'no_match';
        return $preview;
    }
    $before = substr($description, 0, $links_at);
    $links = substr($description, $links_at);
    $prefix = "\n\nSermon Notes\n\n";
    $body = implode("\n\n", $parts);
    $room = 8000 - strlen($before) - strlen($links) - strlen($prefix);
    if ($room < 120) {
        $preview['notes_status'] = 'too_long';
        return $preview;
    }
    if (strlen($body) > $room) {
        $marker = "\n\n[Notes shortened to fit the podcast description.]";
        $excerpt = substr($body, 0, max(0, $room - strlen($marker)));
        // Preserve valid UTF-8 if the byte cap lands inside a character.
        while ($excerpt !== '' && !preg_match('//u', $excerpt)) {
            $excerpt = substr($excerpt, 0, -1);
        }
        $whole_words = preg_replace('/\s+\S*$/u', '', $excerpt);
        if (is_string($whole_words) && $whole_words !== '') $excerpt = $whole_words;
        $body = rtrim($excerpt) . $marker;
        $preview['notes_status'] = 'truncated';
    } else {
        $preview['notes_status'] = 'added';
    }
    $preview['description'] = $before . $prefix . $body . $links;
    return $preview;
}



/**
 * Only weekly verified uploads can opt in to Spreaker draft preparation.
 * The existing YouTube review catalog supplies canonical metadata; ambiguous
 * or excluded sermons are skipped rather than creating a misleading draft.
 * No Spreaker credentials ever pass through WordPress or the browser.
 */
function surfside_tools_podcast_spreaker_metadata($id) {
    if (!defined('SURFSIDE_SPREAKER_DRAFTS_ENABLED') || SURFSIDE_SPREAKER_DRAFTS_ENABLED !== true ||
        !function_exists('surfside_tools_youtube_review_video') ||
        !function_exists('surfside_tools_youtube_review_master_id')) {
        return null;
    }
    $master = surfside_tools_youtube_review_master_id();
    if ($master === '') return null;
    $video = surfside_tools_youtube_review_video($master, $id);
    if (!is_array($video) || ($video['disposition'] ?? '') === 'exclude') return null;
    $date = $video['effective_date'] ?? $video['sermon_date'] ?? '';
    $preview = surfside_tools_podcast_episode_with_notes($video['title'] ?? '', $date, $id);
    if ($preview['needs_review'] || strlen($preview['description']) > 8000) return null;
    return array('title' => $preview['title'], 'description' => $preview['description']);
}

/**
 * Queue only newly verified *weekly* R2 uploads. Historical reconciliations
 * never call this function. Missing configuration always fails closed.
 */
function surfside_tools_podcast_queue_verified($id) {
    if (!is_string($id) || !preg_match('/^[A-Za-z0-9_-]{11}$/D', $id)) {
        return array('status' => 'invalid_video');
    }
    if (!defined('SURFSIDE_PODCAST_WORKER_URL') ||
        !defined('SURFSIDE_PODCAST_SHARED_SECRET') ||
        !is_string(SURFSIDE_PODCAST_WORKER_URL) ||
        !is_string(SURFSIDE_PODCAST_SHARED_SECRET) ||
        strlen(SURFSIDE_PODCAST_SHARED_SECRET) < 32) {
        return array('status' => 'not_configured');
    }
    $url = untrailingslashit(SURFSIDE_PODCAST_WORKER_URL);
    if (!preg_match('~^https://[a-z0-9.-]+(?::443)?$~iD', $url)) {
        return array('status' => 'invalid_configuration');
    }
    $data = array('video_id' => $id);
    $spreaker = surfside_tools_podcast_spreaker_metadata($id);
    if ($spreaker !== null) {
        $data['spreaker'] = $spreaker;
        // Explicit opt-in applies to newly verified weekly uploads and
        // retry jobs only. Existing Spreaker drafts stay unpublished.
        if (defined('SURFSIDE_SPREAKER_AUTO_PUBLISH_ENABLED') &&
            SURFSIDE_SPREAKER_AUTO_PUBLISH_ENABLED === true) {
            $data['auto_publish'] = true;
        }
    }
    $payload = wp_json_encode($data);
    $timestamp = (string) time();
    $signature = hash_hmac('sha256', $timestamp . '.' . $payload, SURFSIDE_PODCAST_SHARED_SECRET);
    $result = wp_remote_post($url . '/jobs', array(
        'method' => 'POST',
        'headers' => array(
            'Content-Type' => 'application/json',
            'X-Surfside-Time' => $timestamp,
            'X-Surfside-Signature' => $signature,
        ),
        'body' => $payload,
        'timeout' => 8,
        'redirection' => 0,
        'reject_unsafe_urls' => true,
    ));
    if (is_wp_error($result)) return array('status' => 'queue_unavailable');
    $http = (int) wp_remote_retrieve_response_code($result);
    if ($http !== 200 && $http !== 202) return array('status' => 'queue_unavailable');
    $body = json_decode(wp_remote_retrieve_body($result), true);
    $status = is_array($body) && isset($body['status']) ? $body['status'] : null;
    return array(
        'status' => in_array($status, array('queued', 'processing', 'ready'), true)
            ? $status : 'queue_unavailable',
        'spreaker_draft_requested' => $spreaker !== null && $status === 'queued',
        'spreaker_auto_publish_requested' => !empty($data['auto_publish']) && $status === 'queued',
    );
}

/**
 * Staff-initiated recovery for a verified weekly sermon that was uploaded
 * without Spreaker metadata (for example, a standalone sermon before its
 * title format was supported). Only attaches an already-generated MP3 to
 * an unpublished draft; Cloudflare enforces completed audio and no prior
 * Spreaker job. Never initiates FFmpeg or publishes an episode.
 */
function surfside_tools_podcast_prepare_existing_draft($id) {
    if (!is_string($id) || !preg_match('/^[A-Za-z0-9_-]{11}$/D', $id)) {
        return new WP_Error('podcast_invalid_video', 'Invalid YouTube video ID.', array('status' => 400));
    }
    if (!defined('SURFSIDE_PODCAST_WORKER_URL') || !defined('SURFSIDE_PODCAST_SHARED_SECRET') ||
        !is_string(SURFSIDE_PODCAST_WORKER_URL) || !is_string(SURFSIDE_PODCAST_SHARED_SECRET) ||
        strlen(SURFSIDE_PODCAST_SHARED_SECRET) < 32) {
        return new WP_Error('podcast_not_configured', 'The podcast Worker is not configured.', array('status' => 409));
    }
    $url = untrailingslashit(SURFSIDE_PODCAST_WORKER_URL);
    if (!preg_match('~^https://[a-z0-9.-]+(?::443)?$~iD', $url)) {
        return new WP_Error('podcast_invalid_configuration', 'Invalid podcast Worker URL.', array('status' => 409));
    }
    $spreaker = surfside_tools_podcast_spreaker_metadata($id);
    if ($spreaker === null) {
        return new WP_Error('podcast_metadata_review', 'A valid sermon title and date are required for the Spreaker draft.', array('status' => 409));
    }
    $payload = wp_json_encode(array(
        'video_id' => $id, 'confirmed_no_draft' => true, 'spreaker' => $spreaker,
    ));
    $timestamp = (string) time();
    $signature = hash_hmac('sha256', $timestamp . '.' . $payload, SURFSIDE_PODCAST_SHARED_SECRET);
    $result = wp_remote_post($url . '/recover-spreaker', array(
        'method' => 'POST',
        'headers' => array(
            'Content-Type' => 'application/json',
            'X-Surfside-Time' => $timestamp,
            'X-Surfside-Signature' => $signature,
        ),
        'body' => $payload,
        'timeout' => 8,
        'redirection' => 0,
        'reject_unsafe_urls' => true,
    ));
    if (is_wp_error($result)) {
        return new WP_Error('podcast_worker_unavailable', 'Could not reach the podcast Worker.', array('status' => 503));
    }
    $code = (int) wp_remote_retrieve_response_code($result);
    $body = json_decode(wp_remote_retrieve_body($result), true);
    if ($code !== 202 || ($body['status'] ?? '') !== 'queued') {
        $reason = is_array($body) && is_string($body['error'] ?? null)
            ? $body['error'] : 'HTTP ' . $code;
        return new WP_Error('podcast_recovery_rejected',
            'Spreaker draft recovery was not queued (' . sanitize_text_field($reason) .
            '). Check podcast-spreaker status before retrying.', array('status' => 409));
    }
    return array('status' => 'queued', 'video_id' => $id);
}

/**
 * Administrator-requested retry for a previously failed weekly audio job.
 * Reuse the successful server-side HMAC authentication path rather than
 * requiring a staff member to sign a PowerShell request.
 *
 * Read the Worker status before queuing. Only a "failed" audio job can be
 * explicitly restarted; the Worker separately enforces idempotency and
 * checks for a completed MP3. The usual server-side YouTube metadata is
 * included in /jobs so a valid sermon also receives an unpublished Spreaker
 * draft automatically when audio processing succeeds.
 */
function surfside_tools_podcast_retry_failed_audio($id) {
    if (!is_string($id) || !preg_match('/^[A-Za-z0-9_-]{11}$/D', $id)) {
        return new WP_Error('podcast_invalid_video', 'Invalid YouTube video ID.', array('status' => 400));
    }
    if (!defined('SURFSIDE_PODCAST_WORKER_URL') || !defined('SURFSIDE_PODCAST_SHARED_SECRET') ||
        !is_string(SURFSIDE_PODCAST_WORKER_URL) || !is_string(SURFSIDE_PODCAST_SHARED_SECRET) ||
        strlen(SURFSIDE_PODCAST_SHARED_SECRET) < 32) {
        return new WP_Error('podcast_not_configured', 'Podcast Worker is not configured.', array('status' => 409));
    }
    $url = untrailingslashit(SURFSIDE_PODCAST_WORKER_URL);
    if (!preg_match('~^https://[a-z0-9.-]+(?::443)?$~iD', $url)) {
        return new WP_Error('podcast_invalid_configuration', 'Invalid podcast Worker URL.', array('status' => 409));
    }
    $payload = wp_json_encode(array('video_id' => $id));
    $timestamp = (string) time();
    $signature = hash_hmac('sha256', $timestamp . '.' . $payload, SURFSIDE_PODCAST_SHARED_SECRET);
    $response = wp_remote_post($url . '/status', array(
        'method' => 'POST',
        'headers' => array(
            'Content-Type' => 'application/json',
            'X-Surfside-Time' => $timestamp,
            'X-Surfside-Signature' => $signature,
        ),
        'body' => $payload,
        'timeout' => 8,
        'redirection' => 0,
        'reject_unsafe_urls' => true,
    ));
    if (is_wp_error($response)) {
        return new WP_Error('podcast_status_unavailable', 'Could not read the podcast audio job status.', array('status' => 503));
    }
    if ((int) wp_remote_retrieve_response_code($response) !== 200) {
        return new WP_Error('podcast_status_rejected', 'Cloudflare rejected the audio status check.', array('status' => 409));
    }
    $job = json_decode(wp_remote_retrieve_body($response), true);
    if (!is_array($job) || ($job['video_id'] ?? '') !== $id) {
        return new WP_Error('podcast_invalid_status', 'Unexpected podcast job response. No retry was queued.', array('status' => 409));
    }
    if (($job['status'] ?? '') !== 'failed') {
        return new WP_Error('podcast_not_failed',
            'Audio status is ' . sanitize_text_field($job['status'] ?? 'unknown') .
            '; only failed audio jobs can be retried.', array('status' => 409));
    }

    $outcome = surfside_tools_podcast_queue_verified($id);
    if (($outcome['status'] ?? '') !== 'queued') {
        return new WP_Error('podcast_audio_retry_rejected',
            'Audio was not requeued (status: ' .
            sanitize_text_field($outcome['status'] ?? 'unknown') .
            '). Recheck podcast-jobs before retrying.', array('status' => 409));
    }
    return array(
        'status' => 'queued',
        'video_id' => $id,
        'spreaker_draft_requested' => !empty($outcome['spreaker_draft_requested']),
        'spreaker_auto_publish_requested' => !empty($outcome['spreaker_auto_publish_requested']),
    );
}
