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
    $recognized = $week !== '' && $message !== '' && $series !== '';
    $title = $recognized ? $message . ' | ' . $series . ' (Week ' . $week . ')' : $original;

    $date_text = '';
    $date = is_string($sermon_date)
        ? DateTimeImmutable::createFromFormat('!Y-m-d', $sermon_date) : false;
    if ($date && $date->format('Y-m-d') === $sermon_date) {
        $date_text = $date->format('F j, Y');
    }
    $valid_id = is_string($youtube_id) && preg_match('/^[A-Za-z0-9_-]{11}$/D', $youtube_id);
    $description = '"' . ($recognized ? $message : $original) . '" — a sermon from Surfside Community Fellowship.';
    if ($series !== '') $description .= "\nSeries: " . $series;
    if ($week !== '') $description .= "\nWeek: " . $week;
    if ($date_text !== '') $description .= "\nSermon date: " . $date_text;
    if ($valid_id) $description .= "\n\nWatch on YouTube: https://www.youtube.com/watch?v=" . $youtube_id;
    $description .= "\nLearn more: https://surfsidefellowship.org";

    return array(
        'title' => $title,
        'description' => $description,
        'needs_review' => !$recognized || $date_text === '' || !$valid_id || strlen($title) > 140,
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
 * One-time full-pipeline test: signed Worker request using a staged MP4.
 * No production sermon/audio key is read or overwritten by this mode.
 * This is separate from normal weekly uploads and never auto-publishes Spreaker.
 */
function surfside_tools_podcast_test_worker($id, $status_only = false) {
    if (!current_user_can('manage_options') || !is_string($id) ||
        !preg_match('/^[A-Za-z0-9_-]{11}$/D', $id)) {
        return new WP_Error('podcast_test_forbidden', 'Site administrator access required.', array('status' => 403));
    }
    $metadata = surfside_tools_podcast_spreaker_metadata($id);
    if ($metadata === null) {
        return new WP_Error('podcast_test_metadata', 'A complete, reviewed title and sermon date are required.', array('status' => 409));
    }
    if (!defined('SURFSIDE_PODCAST_WORKER_URL') || !defined('SURFSIDE_PODCAST_SHARED_SECRET') ||
        !is_string(SURFSIDE_PODCAST_WORKER_URL) || !is_string(SURFSIDE_PODCAST_SHARED_SECRET) ||
        strlen(SURFSIDE_PODCAST_SHARED_SECRET) < 32) {
        return new WP_Error('podcast_test_config', 'The podcast Worker is not configured.', array('status' => 503));
    }
    $base = untrailingslashit(SURFSIDE_PODCAST_WORKER_URL);
    if (!preg_match('~^https://[a-z0-9.-]+(?::443)?$~iD', $base)) {
        return new WP_Error('podcast_test_config', 'Podcast Worker URL is not valid.', array('status' => 503));
    }
    $payload = wp_json_encode($status_only
        ? array('video_id' => $id, 'test' => true)
        : array('video_id' => $id, 'test' => true, 'spreaker' => $metadata));
    $when = (string) time();
    $result = wp_remote_post($base . ($status_only ? '/status' : '/jobs'), array(
        'headers' => array(
            'Content-Type' => 'application/json',
            'X-Surfside-Time' => $when,
            'X-Surfside-Signature' => hash_hmac('sha256', $when . '.' . $payload, SURFSIDE_PODCAST_SHARED_SECRET),
        ),
        'body' => $payload,
        'timeout' => 12,
        'redirection' => 0,
        'reject_unsafe_urls' => true,
    ));
    if (is_wp_error($result)) {
        return new WP_Error('podcast_test_unavailable', 'Cloudflare Worker could not be contacted.', array('status' => 503));
    }
    $code = (int) wp_remote_retrieve_response_code($result);
    $body = json_decode(wp_remote_retrieve_body($result), true);
    if (($code === 200 || $code === 202) && is_array($body) && isset($body['status'])) {
        return $body;
    }
    return new WP_Error('podcast_test_worker', 'Cloudflare test request failed (' .
        sanitize_text_field(is_array($body) && is_string($body['error'] ?? null) ? $body['error'] : 'HTTP ' . $code) . ').',
        array('status' => 409));
}
