<?php
/**
 * Server-side YouVersion Platform integration foundation.
 */
if (!defined('ABSPATH')) { exit; }

function surfside_tools_youversion_app_key() {
    $key = trim((string)get_option('surfside_tools_youversion_app_key', ''));
    if ($key !== '') {
        return $key;
    }

    // Backward-compatible fallback for the initial foundation PR. The next
    // Site Settings -> Integrations save migrates this value to the dedicated option.
    $settings = function_exists('surfside_tools_app_settings') ? surfside_tools_app_settings() : array();
    return trim((string)($settings['youversion_app_key'] ?? ''));
}

function surfside_tools_youversion_is_configured() {
    return surfside_tools_youversion_app_key() !== '';
}

function surfside_tools_youversion_request($path, $query = array()) {
    $app_key = surfside_tools_youversion_app_key();
    if ($app_key === '') {
        return new WP_Error('surfside_youversion_not_configured', 'YouVersion App Key is not configured.');
    }

    $path = ltrim((string)$path, '/');
    if ($path === '' || strpos($path, '..') !== false) {
        return new WP_Error('surfside_youversion_invalid_path', 'Invalid YouVersion API path.');
    }

    $url = 'https://api.youversion.com/v1/' . $path;
    if (!empty($query) && is_array($query)) {
        $url = add_query_arg($query, $url);
    }

    $response = wp_remote_get($url, array(
        'timeout' => 12,
        'headers' => array(
            'Accept' => 'application/json',
            'X-YVP-App-Key' => $app_key,
        ),
    ));

    if (is_wp_error($response)) {
        return $response;
    }

    $status = (int)wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    $data = $body !== '' ? json_decode($body, true) : null;

    if ($status >= 200 && $status < 300) {
        return is_array($data) ? $data : array();
    }

    if ($status === 429) {
        return new WP_Error(
            'surfside_youversion_rate_limited',
            'YouVersion API rate limit reached.',
            array('retry_after' => wp_remote_retrieve_header($response, 'retry-after'))
        );
    }

    $message = is_array($data) && !empty($data['message'])
        ? sanitize_text_field((string)$data['message'])
        : 'YouVersion API request failed.';

    return new WP_Error('surfside_youversion_api_error', $message, array('status' => $status));
}
