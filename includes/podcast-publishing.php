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
    $preview = surfside_tools_podcast_episode_preview($video['title'] ?? '', $date, $id);
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
    if ($spreaker !== null) $data['spreaker'] = $spreaker;
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
    );
}
