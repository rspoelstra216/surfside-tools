<?php
/** Server-side Twitch live status. Credentials belong in wp-config.php, never the app. */
if (!defined('ABSPATH')) { exit; }

function surfside_tools_twitch_unknown($channel) {
    return array('status'=>'unknown','is_live'=>null,'channel'=>$channel,'stream_id'=>null,'started_at'=>null,'checked_at'=>gmdate(DATE_ATOM));
}

/** Use an app token for at most 55 minutes; validate every newly acquired token. */
function surfside_tools_twitch_token($client_id, $secret, $key) {
    $cached = get_transient($key);
    if (is_string($cached) && $cached !== '') return $cached;
    $response = wp_remote_post('https://id.twitch.tv/oauth2/token', array(
        'timeout'=>8, 'redirection'=>0,
        'body'=>array('client_id'=>$client_id,'client_secret'=>$secret,'grant_type'=>'client_credentials'),
    ));
    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) return null;
    $data = json_decode(wp_remote_retrieve_body($response), true);
    $token = is_array($data) && is_string($data['access_token'] ?? null) ? $data['access_token'] : '';
    $expires = (int)($data['expires_in'] ?? 0);
    if ($token === '' || $expires <= 60 || preg_match('/[\r\n]/', $token)) return null;
    $validation = wp_remote_get('https://id.twitch.tv/oauth2/validate', array(
        'timeout'=>8,'redirection'=>0,'headers'=>array('Authorization'=>'OAuth '.$token),
    ));
    if (is_wp_error($validation) || wp_remote_retrieve_response_code($validation) !== 200) return null;
    $valid = json_decode(wp_remote_retrieve_body($validation), true);
    if (!is_array($valid) || ($valid['client_id'] ?? '') !== $client_id || (int)($valid['expires_in'] ?? 0) <= 60) return null;
    set_transient($key, $token, min(3300, $expires-60, (int)$valid['expires_in']-60));
    return $token;
}

/** Pure response mapping: malformed responses must never report a confirmed offline state. */
function surfside_tools_twitch_parse_streams($data, $channel) {
    if (!is_array($data) || !isset($data['data']) || !is_array($data['data']) || array_values($data['data']) !== $data['data']) return null;
    if (!$data['data']) {
        return array('status'=>'offline','is_live'=>false,'channel'=>$channel,'stream_id'=>null,'started_at'=>null,'checked_at'=>gmdate(DATE_ATOM));
    }
    foreach ($data['data'] as $stream) {
        if (!is_array($stream) || strtolower((string)($stream['user_login'] ?? '')) !== $channel || ($stream['type'] ?? '') !== 'live') continue;
        if (!is_string($stream['id'] ?? null) || $stream['id'] === '' || !is_string($stream['started_at'] ?? null) || strtotime($stream['started_at']) === false) return null;
        return array('status'=>'live','is_live'=>true,'channel'=>$channel,'stream_id'=>$stream['id'],'started_at'=>$stream['started_at'],'checked_at'=>gmdate(DATE_ATOM));
    }
    return null;
}

function surfside_tools_twitch_live_status() {
    $information = surfside_tools_get_site_information();
    $channel = strtolower(trim((string)($information['streaming']['twitch_channel'] ?? '')));
    $unknown = surfside_tools_twitch_unknown($channel);
    $client_id = defined('SURFSIDE_TWITCH_CLIENT_ID') ? trim((string)SURFSIDE_TWITCH_CLIENT_ID) : '';
    $secret = defined('SURFSIDE_TWITCH_CLIENT_SECRET') ? trim((string)SURFSIDE_TWITCH_CLIENT_SECRET) : '';
    if (!preg_match('/^[a-z0-9_]{1,25}$/', $channel) || !preg_match('/^[a-zA-Z0-9]+$/', $client_id) || $secret === '') return $unknown;

    // Cache identity changes when credentials or channel change. No secrets appear in keys or responses.
    $identity = hash_hmac('sha256', $client_id.'|'.$channel, $secret);
    $status_key = 'surfside_twitch_status_'.$identity;
    $token_key = 'surfside_twitch_token_'.$identity;
    $lock_key = 'surfside_twitch_lock_'.$identity;
    $cached = get_transient($status_key);
    if (is_array($cached)) return $cached;

    // Coalesce concurrent public requests, with a bounded lock lifetime after a crashed request.
    $lock_time = (int)get_option($lock_key, 0);
    if ($lock_time && $lock_time < time()-60) delete_option($lock_key);
    if (!add_option($lock_key, time(), '', false)) return $unknown;
    try {
        $status = $unknown;
        $token = surfside_tools_twitch_token($client_id, $secret, $token_key);
        if ($token) {
            // Retry once with a fresh app token if Twitch revoked the cached token.
            for ($attempt=0; $attempt<2; $attempt++) {
                $response = wp_remote_get('https://api.twitch.tv/helix/streams?user_login='.rawurlencode($channel), array(
                    'timeout'=>8,'redirection'=>0,'headers'=>array('Client-ID'=>$client_id,'Authorization'=>'Bearer '.$token),
                ));
                if (is_wp_error($response)) break;
                $code = wp_remote_retrieve_response_code($response);
                if ($code === 401) {
                    delete_transient($token_key);
                    if ($attempt === 0) {
                        $token = surfside_tools_twitch_token($client_id, $secret, $token_key);
                        if ($token) continue;
                    }
                    break;
                }
                if ($code === 200) {
                    $parsed = surfside_tools_twitch_parse_streams(json_decode(wp_remote_retrieve_body($response), true), $channel);
                    if ($parsed) $status = $parsed;
                }
                break;
            }
        }
        // Unknown results also receive a short backoff instead of hammering Twitch.
        set_transient($status_key, $status, 30);
        return $status;
    } finally {
        delete_option($lock_key);
    }
}

function surfside_tools_twitch_status_response() {
    $response = rest_ensure_response(surfside_tools_twitch_live_status());
    // Keep CDN/browser caching from extending the internal 30-second status cache.
    $response->header('Cache-Control', 'no-store, max-age=0');
    return $response;
}
add_action('rest_api_init', function() {
    register_rest_route('surfside/v1', '/livestream/status', array(
        'methods'=>WP_REST_Server::READABLE,
        'callback'=>'surfside_tools_twitch_status_response',
        'permission_callback'=>'__return_true',
    ));
});
