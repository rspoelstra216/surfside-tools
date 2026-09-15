<?php
/** Shared abuse-protection helpers for intentionally public Surfside REST endpoints. */
if (!defined('ABSPATH')) { exit; }

/**
 * Build a privacy-preserving rate-limit identity from the direct client IP.
 *
 * The raw address is never stored; only a one-way hash contributes to the
 * transient key. REMOTE_ADDR is intentionally preferred over forwarded headers
 * so an arbitrary public client cannot choose its own limiter identity.
 */
function surfside_tools_public_api_client_key() {
    $address = isset($_SERVER['REMOTE_ADDR']) ? trim((string) wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
    if (!filter_var($address, FILTER_VALIDATE_IP)) {
        $address = 'unknown';
    }

    return hash('sha256', $address);
}

/**
 * Apply a lightweight fixed-window limiter to expensive public API work.
 *
 * Returns true when the request may proceed or a REST-friendly WP_Error when
 * the bucket is exhausted.
 */
function surfside_tools_public_api_rate_limit($bucket, $limit, $window_seconds) {
    $bucket = sanitize_key((string) $bucket);
    $limit = max(1, absint($limit));
    $window_seconds = max(1, absint($window_seconds));
    $now = time();
    $key = 'surfside_api_rl_' . md5($bucket . '|' . surfside_tools_public_api_client_key());
    $state = get_transient($key);

    if (!is_array($state) || empty($state['reset_at']) || (int) $state['reset_at'] <= $now) {
        $state = array('count' => 0, 'reset_at' => $now + $window_seconds);
    }

    if ((int) ($state['count'] ?? 0) >= $limit) {
        $retry_after = max(1, (int) $state['reset_at'] - $now);
        return new WP_Error(
            'surfside_rate_limited',
            'Too many requests. Please try again shortly.',
            array('status' => 429, 'retry_after' => $retry_after)
        );
    }

    $state['count'] = (int) ($state['count'] ?? 0) + 1;
    set_transient($key, $state, $window_seconds + MINUTE_IN_SECONDS);
    return true;
}
