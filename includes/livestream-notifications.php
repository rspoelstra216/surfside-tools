<?php
/** Signed Twitch events, with a durable asynchronous push job. */
if (!defined('ABSPATH')) { exit; }

function surfside_tools_live_push_deactivate() {
    wp_clear_scheduled_hook('surfside_tools_live_push_check');
}
add_action('init', function() {
    if (wp_next_scheduled('surfside_tools_live_push_check')) surfside_tools_live_push_deactivate();
});

/** Pure decision: never alert from unknown/stale status or a long-running stream. */
function surfside_tools_live_push_candidate($status, $state, $now) {
    if (($status['status'] ?? '') !== 'live' || ($status['is_live'] ?? null) !== true) return '';
    $id = (string)($status['stream_id'] ?? '');
    $channel = (string)($status['channel'] ?? '');
    $started = strtotime((string)($status['started_at'] ?? ''));
    $checked = strtotime((string)($status['checked_at'] ?? ''));
    if ($id === '' || $channel === '' || !$started || !$checked) return '';
    if ($started > $now+30 || $now-$started > 600 || $checked > $now+30 || $now-$checked > 90) return '';
    $key = hash('sha256', $channel.'|'.$id);
    if (isset($state['seen'][$key])) return '';
    // Twitch may assign a new ID after a production reconnect.
    if (!empty($state['last_attempt']) && $now-(int)$state['last_attempt'] < 7200) return '';
    return $key;
}


function surfside_tools_eventsub_callback_url() {
    return rest_url('surfside/v1/twitch/eventsub');
}
function surfside_tools_eventsub_error($message) {
    return new WP_Error('surfside_eventsub', $message, array('status'=>400));
}

/** Explicit staff action only; persist a new secret before Twitch can challenge it. */
function surfside_tools_eventsub_connect() {
    $lock = 'surfside_eventsub_connect_lock';
    if ((int)get_option($lock, 0) < time()-120) delete_option($lock);
    if (!add_option($lock, time(), '', false)) return surfside_tools_eventsub_error('A connection is already in progress.');
    try {
        $credentials = surfside_tools_twitch_credentials();
        $info = surfside_tools_get_site_information();
        $channel = strtolower(trim((string)($info['streaming']['twitch_channel'] ?? '')));
        $callback = surfside_tools_eventsub_callback_url();
        if (!preg_match('/^[a-z0-9_]{1,25}$/', $channel) || empty($credentials['client_id']) || empty($credentials['secret'])) return surfside_tools_eventsub_error('Save Twitch credentials and a channel first.');
        $parts = wp_parse_url($callback);
        if (($parts['scheme'] ?? '') !== 'https' || (isset($parts['port']) && $parts['port'] !== 443)) return surfside_tools_eventsub_error('The website must use HTTPS on port 443.');
        $diagnostic = '';
        $token = surfside_tools_twitch_token($credentials['client_id'], $credentials['secret'], 'surfside_eventsub_token_'.hash_hmac('sha256', $credentials['client_id'], $credentials['secret']), $diagnostic);
        if (!$token) return surfside_tools_eventsub_error($diagnostic);
        $headers = array('Client-ID'=>$credentials['client_id'], 'Authorization'=>'Bearer '.$token);
        $users = wp_remote_get('https://api.twitch.tv/helix/users?login='.rawurlencode($channel), array('timeout'=>8,'redirection'=>0,'headers'=>$headers));
        if (is_wp_error($users) || wp_remote_retrieve_response_code($users) !== 200) return surfside_tools_eventsub_error('Twitch channel lookup failed.');
        $user = json_decode(wp_remote_retrieve_body($users), true)['data'][0] ?? array();
        if (strtolower($user['login'] ?? '') !== $channel || !preg_match('/^[0-9]+$/', (string)($user['id'] ?? ''))) return surfside_tools_eventsub_error('Twitch channel was not found.');
        $config = get_option('surfside_eventsub_config', array());
        $secret = bin2hex(random_bytes(32));
        // Remove this installation's old subscription before reconnecting. Never touch other subscriptions.
        if (!empty($config['id'])) {
            $deleted = wp_remote_request('https://api.twitch.tv/helix/eventsub/subscriptions?id='.rawurlencode($config['id']), array('method'=>'DELETE','timeout'=>8,'redirection'=>0,'headers'=>$headers));
            if (is_wp_error($deleted) || !in_array(wp_remote_retrieve_response_code($deleted), array(204,404), true)) return surfside_tools_eventsub_error('Could not remove the previous subscription. If the Client ID changed, remove it in the old Twitch application first.');
        }
        $config = array('secret'=>$secret,'channel'=>$channel,'broadcaster_id'=>(string)$user['id'],'callback'=>$callback,'status'=>'connecting','id'=>'');
        update_option('surfside_eventsub_config', $config, false);
        $headers['Content-Type'] = 'application/json';
        $result = wp_remote_post('https://api.twitch.tv/helix/eventsub/subscriptions', array('timeout'=>8,'redirection'=>0,'headers'=>$headers,'body'=>wp_json_encode(array(
            'type'=>'stream.online','version'=>'1','condition'=>array('broadcaster_user_id'=>$config['broadcaster_id']),
            'transport'=>array('method'=>'webhook','callback'=>$callback,'secret'=>$secret),
        ))));
        if (is_wp_error($result) || wp_remote_retrieve_response_code($result) !== 202) {
            $config = get_option('surfside_eventsub_config', $config);
            $config['status'] = 'connection_failed';
            update_option('surfside_eventsub_config', $config, false);
            return surfside_tools_eventsub_error('Twitch did not accept the subscription. Check credentials and the public callback; then reconnect.');
        }
        $sub = json_decode(wp_remote_retrieve_body($result), true)['data'][0] ?? array();
        if (empty($sub['id'])) return surfside_tools_eventsub_error('Twitch returned an invalid subscription response.');
        // A challenge may have arrived while the registration request was running.
        $config = get_option('surfside_eventsub_config', $config);
        $config['id'] = (string)$sub['id'];
        if ($config['status'] === 'connecting') $config['status'] = 'verification_pending';
        update_option('surfside_eventsub_config', $config, false);
        return true;
    } finally { delete_option($lock); }
}

function surfside_tools_eventsub_receive($request) {
    $config = get_option('surfside_eventsub_config', array());
    $raw = $request->get_body();
    $id = $request->get_header('Twitch-Eventsub-Message-Id');
    $timestamp = $request->get_header('Twitch-Eventsub-Message-Timestamp');
    $signature = $request->get_header('Twitch-Eventsub-Message-Signature');
    $at = strtotime($timestamp);
    if (empty($config['secret']) || !$id || strlen($raw)>65536 || !$at || $at>time()+30 || time()-$at>600 ||
        !hash_equals('sha256='.hash_hmac('sha256', $id.$timestamp.$raw, $config['secret']), $signature)) {
        return new WP_Error('eventsub_signature','Invalid Twitch signature or timestamp.',array('status'=>403));
    }
    $data = json_decode($raw, true);
    $sub = $data['subscription'] ?? array();
    $type = $request->get_header('Twitch-Eventsub-Message-Type');
    $info = surfside_tools_get_site_information();
    $current_channel = strtolower(trim((string)($info['streaming']['twitch_channel'] ?? '')));
    if (($sub['type'] ?? '') !== 'stream.online' || ($sub['version'] ?? '') !== '1' ||
        ($sub['condition']['broadcaster_user_id'] ?? '') !== ($config['broadcaster_id'] ?? '') ||
        ($sub['transport']['callback'] ?? '') !== ($config['callback'] ?? '') ||
        $current_channel !== ($config['channel'] ?? '') || empty($sub['id']) ||
        (!empty($config['id']) && $sub['id'] !== $config['id'])) {
        return new WP_Error('eventsub_subscription','Unexpected subscription.',array('status'=>403));
    }
    if ($type === 'webhook_callback_verification') {
        if (!is_string($data['challenge'] ?? null) || $data['challenge'] === '') return surfside_tools_eventsub_error('Missing challenge.');
        $config['id'] = $sub['id']; $config['status'] = 'enabled';
        update_option('surfside_eventsub_config', $config, false);
        return new WP_REST_Response($data['challenge'],200,array('Content-Type'=>'text/plain','Content-Length'=>(string)strlen($data['challenge']),'Cache-Control'=>'no-store'));
    }
    if ($type === 'revocation') {
        $config['status'] = sanitize_key($sub['status'] ?? 'revoked');
        update_option('surfside_eventsub_config',$config,false);
        return new WP_REST_Response(null,204);
    }
    // Twitch is no longer authoritative for go-live. A valid legacy EventSub
    // message is acknowledged but must not queue or send an Expo push.
    if ($type === 'notification') return new WP_REST_Response(null, 204);
    return surfside_tools_eventsub_error('Unsupported Twitch event type.');
}

function surfside_tools_eventsub_deliver($key) {
    // Drain any old queued Twitch jobs without generating a notification.
    if (!is_string($key) || !preg_match('/^[a-f0-9]{64}$/', $key)) return;
    delete_option('surfside_eventsub_job_' . $key);
    wp_clear_scheduled_hook('surfside_eventsub_deliver', array($key));
}

add_action('surfside_eventsub_deliver','surfside_tools_eventsub_deliver');

function surfside_tools_eventsub_worker($request) {
    $key = $request->get_param('key'); $token = $request->get_param('token');
    if (!is_string($key) || !preg_match('/^[a-f0-9]{64}$/',$key) || !is_string($token)) return new WP_Error('worker_denied','Invalid worker.',array('status'=>403));
    $job = get_option('surfside_eventsub_job_'.$key);
    if (!is_array($job) || !hash_equals($job['token'],$token)) return new WP_Error('worker_denied','Invalid worker.',array('status'=>403));
    // The one-shot cron backs up a concurrent worker or blocked loopback.
    surfside_tools_eventsub_deliver($key);
    return new WP_REST_Response(null,204);
}
add_action('rest_api_init',function(){
    register_rest_route('surfside/v1','/twitch/eventsub',array('methods'=>'POST','callback'=>'surfside_tools_eventsub_receive','permission_callback'=>'__return_true'));
    register_rest_route('surfside/v1','/twitch/deliver',array('methods'=>'POST','callback'=>'surfside_tools_eventsub_worker','permission_callback'=>'__return_true'));
});
add_filter('rest_pre_serve_request',function($served,$result,$request){
    if ($request->get_route() === '/surfside/v1/twitch/eventsub') {
        if ($result->get_status() === 200 && is_string($result->get_data())) { echo $result->get_data(); return true; }
        if ($result->get_status() === 204) return true;
    }
    return $served;
},10,3);
