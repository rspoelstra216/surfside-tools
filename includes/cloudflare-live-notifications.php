<?php
/**
 * Event-driven Cloudflare Stream go-live alerts.
 *
 * One authenticated webhook per input transition; no stream-status polling,
 * interval cron job, or staff-page provisioning on visitor requests.
 */
if (!defined('ABSPATH')) { exit; }

const SURFSIDE_TOOLS_CF_LIVE_PUSH_INPUT = 'd8fe3de81677a407a70b62f0bb470769';
const SURFSIDE_TOOLS_CF_LIVE_PUSH_SECRET_OPTION = 'surfside_tools_cf_live_push_webhook_secret';
const SURFSIDE_TOOLS_CF_LIVE_PUSH_STATE_OPTION = 'surfside_tools_cf_live_push_state';

/**
 * Cloudflare generic webhooks send the configured secret as cf-webhook-auth.
 * Never authorize a notification using an input ID or caller IP address alone.
 */
function surfside_tools_cf_live_receive($request) {
    $expected = (string) get_option(SURFSIDE_TOOLS_CF_LIVE_PUSH_SECRET_OPTION, '');
    $received = (string) $request->get_header('cf-webhook-auth');
    if (strlen($expected) < 32 || $received === '' || !hash_equals($expected, $received)) {
        return new WP_Error('surfside_cf_live_unauthorized', 'Unauthorized webhook.', array('status' => 403));
    }

    $raw = (string) $request->get_body();
    if (strlen($raw) > 16384) {
        return new WP_Error('surfside_cf_live_payload_large', 'Webhook payload is too large.', array('status' => 413));
    }
    $packet = json_decode($raw, true);
    if (!is_array($packet)) {
        return new WP_Error('surfside_cf_live_bad_json', 'Malformed webhook payload.', array('status' => 400));
    }
    $data = $packet['data'] ?? null;
    // "Save and Test" sends a generic test message; acknowledge, do not push.
    if (!is_array($data)) return new WP_REST_Response(null, 204);

    if (($data['input_id'] ?? '') !== SURFSIDE_TOOLS_CF_LIVE_PUSH_INPUT) {
        return new WP_REST_Response(null, 204);
    }
    $event = $data['event_type'] ?? '';
    if (!in_array($event, array('live_input.connected', 'live_input.disconnected'), true)) {
        return new WP_REST_Response(null, 204);
    }

    $now = time();
    $sent_at = $packet['ts'] ?? null;
    $updated = $data['updated_at'] ?? '';
    $event_time = is_string($updated) ? strtotime($updated) : false;
    if (!is_int($sent_at) || $sent_at < $now - 600 || $sent_at > $now + 30 ||
        !$event_time || $event_time < $now - 600 || $event_time > $now + 30) {
        // Delayed/replayed events cannot trigger notifications.
        return new WP_REST_Response(null, 204);
    }

    $lock_key = 'surfside_tools_cf_live_push_ingest_lock';
    $existing_lock = (int) get_option($lock_key, 0);
    if ($existing_lock && $existing_lock < $now - 120) delete_option($lock_key);
    if (!add_option($lock_key, $now, '', false)) {
        // Cloudflare may redeliver; the first request is already processing.
        return new WP_REST_Response(null, 204);
    }
    try {
        $state = get_option(SURFSIDE_TOOLS_CF_LIVE_PUSH_STATE_OPTION, array());
        if (!is_array($state)) $state = array();
        if ($event_time <= (int) ($state['last_event_at'] ?? 0)) {
            return new WP_REST_Response(null, 204);
        }

        $state['last_event_at'] = $event_time;
        if ($event === 'live_input.disconnected') {
            $state['active'] = false;
            update_option(SURFSIDE_TOOLS_CF_LIVE_PUSH_STATE_OPTION, $state, false);
            delete_transient('surfside_stream_live_v1');
            return new WP_REST_Response(null, 204);
        }

        $already_active = !empty($state['active']);
        $state['active'] = true;
        // Retain the first transition's identity if Cloudflare delivers a
        // second connected event. Otherwise the first queued job could be
        // incorrectly discarded by the worker.
        if (!$already_active) $state['last_connected_at'] = $event_time;
        delete_transient('surfside_stream_live_v1');

        // Do not alert on duplicate/reconnects or another broadcast within
        // two hours. Keep the pre-existing go-live anti-spam policy.
        $last_attempt = (int) ($state['last_attempt'] ?? 0);
        if ($already_active || ($last_attempt && $now - $last_attempt < 7200)) {
            update_option(SURFSIDE_TOOLS_CF_LIVE_PUSH_STATE_OPTION, $state, false);
            return new WP_REST_Response(null, 204);
        }

        // Claim first, then enqueue. Repeated delivery cannot send twice.
        $state['last_attempt'] = $now;
        update_option(SURFSIDE_TOOLS_CF_LIVE_PUSH_STATE_OPTION, $state, false);

        $key = hash('sha256', SURFSIDE_TOOLS_CF_LIVE_PUSH_INPUT . '|' . $event_time);
        $job = array('created_at' => $now, 'event_time' => $event_time, 'token' => bin2hex(random_bytes(32)));
        $job_key = 'surfside_cf_live_push_job_' . $key;
        if (!add_option($job_key, $job, '', false)) {
            return new WP_REST_Response(null, 204);
        }

        // One event-driven job, with a WordPress cron backup if loopback fails.
        wp_schedule_single_event($now + 60, 'surfside_cf_live_push_deliver', array($key));
        wp_remote_post(rest_url('surfside/v1/cloudflare/live-deliver'), array(
            'timeout' => 0.01, 'blocking' => false, 'redirection' => 0,
            'headers' => array('Content-Type' => 'application/json'),
            'body' => wp_json_encode(array('key' => $key, 'token' => $job['token'])),
        ));
        return new WP_REST_Response(null, 204);
    } finally {
        delete_option($lock_key);
    }
}

/** Deliver using the existing Expo sender without blocking the webhook. */
function surfside_tools_cf_live_deliver($key) {
    if (!is_string($key) || !preg_match('/^[a-f0-9]{64}$/', $key)) return;
    $job_key = 'surfside_cf_live_push_job_' . $key;
    $job = get_option($job_key, false);
    if (!is_array($job)) return;

    $lock_key = 'surfside_tools_cf_live_push_delivery_lock';
    $now = time();
    $locked_at = (int) get_option($lock_key, 0);
    if ($locked_at && $locked_at < $now - 180) delete_option($lock_key);
    if (!add_option($lock_key, $now, '', false)) return;
    try {
        $job = get_option($job_key, false);
        if (!is_array($job)) return;
        $state = get_option(SURFSIDE_TOOLS_CF_LIVE_PUSH_STATE_OPTION, array());
        $still_current = is_array($state)
            && !empty($state['active'])
            && (int) ($state['last_connected_at'] ?? 0) === (int) ($job['event_time'] ?? 0);
        $age = $now - (int) ($job['created_at'] ?? 0);
        if ($still_current && $age >= 0 && $age <= 600) {
            $result = surfside_tools_push_send(
                'Surfside is live',
                'Join us for worship. Tap to watch the livestream.',
                'worship',
                array('livestream')
            );
            $success = !is_wp_error($result);
            update_option('surfside_tools_cf_live_push_health', array(
                'attempted_at' => $now,
                'outcome' => $success ? 'submitted' : $result->get_error_code(),
                'accepted' => $success ? (int) ($result['sent'] ?? 0) : 0,
            ), false);
        }
        // Never retry ambiguous Expo send results, to avoid duplicate alerts.
        delete_option($job_key);
        wp_clear_scheduled_hook('surfside_cf_live_push_deliver', array($key));
    } finally {
        delete_option($lock_key);
    }
}
add_action('surfside_cf_live_push_deliver', 'surfside_tools_cf_live_deliver');

/** Authenticated, one-time HTTPS loopback delivery. */
function surfside_tools_cf_live_worker($request) {
    $key = $request->get_param('key');
    $token = $request->get_param('token');
    if (!is_string($key) || !preg_match('/^[a-f0-9]{64}$/', $key) || !is_string($token)) {
        return new WP_Error('surfside_cf_live_worker_denied', 'Invalid worker request.', array('status' => 403));
    }
    $job = get_option('surfside_cf_live_push_job_' . $key);
    if (!is_array($job) || !is_string($job['token'] ?? null) || !hash_equals($job['token'], $token)) {
        return new WP_Error('surfside_cf_live_worker_denied', 'Invalid worker request.', array('status' => 403));
    }
    surfside_tools_cf_live_deliver($key);
    return new WP_REST_Response(null, 204);
}

add_action('rest_api_init', function() {
    register_rest_route('surfside/v1', '/cloudflare/live-event', array(
        'methods' => 'POST',
        'callback' => 'surfside_tools_cf_live_receive',
        // The endpoint is public but *requires* cf-webhook-auth verification.
        'permission_callback' => '__return_true',
    ));
    register_rest_route('surfside/v1', '/cloudflare/live-deliver', array(
        'methods' => 'POST',
        'callback' => 'surfside_tools_cf_live_worker',
        'permission_callback' => '__return_true',
    ));
});
