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
    $payload = wp_json_encode(array('video_id' => $id));
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
    return array('status' => in_array($status, array('queued', 'processing', 'ready'), true)
        ? $status : 'queue_unavailable');
}
