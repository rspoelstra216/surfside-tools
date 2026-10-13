<?php
/** Run: php scripts/test-cloudflare-live-notifications.php */
define('ABSPATH', __DIR__);
$GLOBALS['options'] = array();
$GLOBALS['scheduled'] = array();
$GLOBALS['posted'] = array();
$GLOBALS['sent'] = 0;
$GLOBALS['failed_send'] = false;
$GLOBALS['transient_clears'] = 0;
function add_action(...$args) {}
function get_option($key, $default = false) { return $GLOBALS['options'][$key] ?? $default; }
function update_option($key, $value, ...$args) { $GLOBALS['options'][$key] = $value; return true; }
function add_option($key, $value, ...$args) {
    if (array_key_exists($key, $GLOBALS['options'])) return false;
    $GLOBALS['options'][$key] = $value; return true;
}
function delete_option($key) { unset($GLOBALS['options'][$key]); }
function delete_transient($key) { $GLOBALS['transient_clears']++; }
function wp_schedule_single_event($when, $hook, $args) { $GLOBALS['scheduled'][] = array($when, $hook, $args); }
function wp_clear_scheduled_hook(...$args) {}
function rest_url($path) { return 'https://example.test/wp-json/' . $path; }
function wp_remote_post($url, $args) { $GLOBALS['posted'][] = array($url, $args); return true; }
function wp_json_encode($value) { return json_encode($value); }
function is_wp_error($value) { return $value instanceof WP_Error; }
class WP_Error {
    public $code, $data;
    function __construct($code, $message, $data = array()) { $this->code = $code; $this->data = $data; }
    function get_error_code() { return $this->code; }
}
class WP_REST_Response {
    public $data, $status;
    function __construct($data, $status = 200) { $this->data = $data; $this->status = $status; }
}
class Request {
    public $body, $headers, $params;
    function __construct($body, $headers = array(), $params = array()) {
        $this->body = $body;
        $this->headers = $headers;
        $this->params = $params;
    }
    function get_header($key) { return $this->headers[$key] ?? ''; }
    function get_body() { return $this->body; }
    function get_param($key) { return $this->params[$key] ?? null; }
}
function surfside_tools_push_send($title, $body, $destination, $audiences) {
    assert_true($title === 'Surfside is live', 'Push title');
    assert_true($destination === 'worship', 'Push must open Worship');
    assert_true($audiences === array('livestream'), 'Push must honor opt-in Livestream preference');
    $GLOBALS['sent']++;
    return $GLOBALS['failed_send']
        ? new WP_Error('surfside_push_no_devices', 'No subscribed test device')
        : array('sent' => 1, 'errors' => array());
}
function assert_true($value, $message) {
    if (!$value) throw new RuntimeException($message);
}
require dirname(__DIR__) . '/includes/cloudflare-live-notifications.php';

$secret = str_repeat('a', 64);
update_option(SURFSIDE_TOOLS_CF_LIVE_PUSH_SECRET_OPTION, $secret);
$good_headers = array('cf-webhook-auth' => $secret);
$input = SURFSIDE_TOOLS_CF_LIVE_PUSH_INPUT;
function cloudflare_event($type, $offset = 0, $input_id = null) {
    global $input;
    $at = time() + $offset;
    return json_encode(array(
        'name' => 'Surfside Live',
        'data' => array(
            'notification_name' => 'Stream Live Input',
            'input_id' => $input_id === null ? $input : $input_id,
            'event_type' => $type,
            'updated_at' => gmdate('Y-m-d\TH:i:s', $at) . '.000Z',
        ),
        'ts' => $at
    ));
}
function deliver_first_job() {
    $job = $GLOBALS['scheduled'][count($GLOBALS['scheduled']) - 1] ?? null;
    assert_true(is_array($job), 'Expected scheduled job');
    return $job[2][0];
}

assert_true(is_wp_error(surfside_tools_cf_live_receive(new Request(cloudflare_event('live_input.connected')))),
    'Missing webhook secret must reject the request');
assert_true(is_wp_error(surfside_tools_cf_live_receive(new Request(cloudflare_event('live_input.connected'),
    array('cf-webhook-auth' => 'wrong')))), 'Incorrect secret must be rejected');
assert_true(is_wp_error(surfside_tools_cf_live_receive(new Request('invalid json', $good_headers))),
    'Invalid JSON must be rejected');
assert_true(is_wp_error(surfside_tools_cf_live_receive(new Request(str_repeat('x', 16385), $good_headers))),
    'Oversized payload must be rejected');
assert_true(surfside_tools_cf_live_receive(new Request(json_encode(array('text' => 'Cloudflare webhook test')), $good_headers))->status === 204,
    'Cloudflare Save and Test must be acknowledged without alert');
assert_true(surfside_tools_cf_live_receive(new Request(cloudflare_event('live_input.connected', 0, str_repeat('f', 32)), $good_headers))->status === 204,
    'Another live input must be ignored');
assert_true(surfside_tools_cf_live_receive(new Request(cloudflare_event('live_input.errored'), $good_headers))->status === 204,
    'Error event must not send a go-live notification');
assert_true(surfside_tools_cf_live_receive(new Request(cloudflare_event('live_input.connected', -601), $good_headers))->status === 204,
    'Stale event must be ignored');
assert_true(surfside_tools_cf_live_receive(new Request(cloudflare_event('live_input.connected', 90), $good_headers))->status === 204,
    'Future event must be ignored');
assert_true(count($GLOBALS['scheduled']) === 0 && $GLOBALS['sent'] === 0,
    'All invalid and test events must have no side effects');

// Start after a valid disconnect, to ensure only a fresh transition alerts.
assert_true(surfside_tools_cf_live_receive(new Request(cloudflare_event('live_input.disconnected', -4), $good_headers))->status === 204,
    'Disconnect is acknowledged');
assert_true(count($GLOBALS['scheduled']) === 0, 'Disconnect alone must not send');
assert_true(surfside_tools_cf_live_receive(new Request(cloudflare_event('live_input.connected', -2), $good_headers))->status === 204,
    'Connected event is acknowledged');
assert_true(count($GLOBALS['scheduled']) === 1, 'Exactly one delivery job is scheduled');
assert_true(count($GLOBALS['posted']) === 1, 'Only one immediate loopback is scheduled');
assert_true($GLOBALS['sent'] === 0, 'Incoming webhook is nonblocking');
$key = deliver_first_job();
$body = json_decode($GLOBALS['posted'][0][1]['body'], true);
assert_true($body['key'] === $key && !empty($body['token']), 'Loopback uses a one-time authenticated token');

assert_true(surfside_tools_cf_live_receive(new Request(cloudflare_event('live_input.connected', -2), $good_headers))->status === 204,
    'Duplicate event should be acknowledged');
assert_true(count($GLOBALS['scheduled']) === 1, 'Duplicate must not enqueue again');
assert_true(is_wp_error(surfside_tools_cf_live_worker(new Request('', array(),
    array('key' => $key, 'token' => 'forged')))), 'Forged worker token must not send');
assert_true($GLOBALS['sent'] === 0, 'Worker authentication stops forged requests');

assert_true(surfside_tools_cf_live_worker(new Request('', array(), $body))->status === 204,
    'Authorized worker should acknowledge delivery');
assert_true($GLOBALS['sent'] === 1, 'One opted-in push should be submitted');
assert_true(get_option('surfside_tools_cf_live_push_health')['outcome'] === 'submitted',
    'Last push handoff should be recorded');
surfside_tools_cf_live_deliver($key);
assert_true($GLOBALS['sent'] === 1, 'Retry/cron must not send a second push');

// A disconnect followed by reconnect must not generate another push within 2h.
surfside_tools_cf_live_receive(new Request(cloudflare_event('live_input.disconnected', -1), $good_headers));
surfside_tools_cf_live_receive(new Request(cloudflare_event('live_input.connected', 0), $good_headers));
assert_true(count($GLOBALS['scheduled']) === 1, 'Reconnect cannot bypass two-hour push guard');
assert_true(get_option(SURFSIDE_TOOLS_CF_LIVE_PUSH_STATE_OPTION)['active'] === true,
    'Last state should reflect an active broadcast');

// Simulate later Sunday service after previous anti-spam period.
$state = get_option(SURFSIDE_TOOLS_CF_LIVE_PUSH_STATE_OPTION);
$state['active'] = false;
$state['last_attempt'] = time() - 7400;
$state['last_event_at'] = time() - 20;
update_option(SURFSIDE_TOOLS_CF_LIVE_PUSH_STATE_OPTION, $state);
$GLOBALS['failed_send'] = true;
surfside_tools_cf_live_receive(new Request(cloudflare_event('live_input.connected', 0), $good_headers));
assert_true(count($GLOBALS['scheduled']) === 2, 'Fresh broadcast after cooldown must enqueue');
$key2 = deliver_first_job();
surfside_tools_cf_live_deliver($key2);
assert_true($GLOBALS['sent'] === 2, 'Second eligible push should be attempted');
assert_true(get_option('surfside_tools_cf_live_push_health')['outcome'] === 'surfside_push_no_devices',
    'Sender errors are recorded without repeating delivery');
surfside_tools_cf_live_deliver($key2);
assert_true($GLOBALS['sent'] === 2, 'Failed/ambiguous send must never be retried');

echo "Cloudflare authenticated live events, send-once, cooldown, legacy-safe routing, and delivery checks passed.\n";
