<?php
/** Regression coverage for Cloudflare's nested live-input status response. */
define('ABSPATH', __DIR__);
$GLOBALS['status_fixture'] = array('status' => array('current' => array('state' => 'connected')));
$GLOBALS['transients'] = array();
$GLOBALS['request_count'] = 0;

class WP_Error {
    public function __construct($code, $message) {}
}
function is_wp_error($value) { return $value instanceof WP_Error; }
function add_action($hook, $callback, $priority = 10) {}
function add_shortcode($name, $callback) {}
function get_option($name, $default = false) {
    if ($name === 'surfside_tools_cloudflare_stream') {
        return array('token' => 'dummy-test-token-not-a-secret');
    }
    return $default;
}
function get_transient($name) {
    return array_key_exists($name, $GLOBALS['transients']) ? $GLOBALS['transients'][$name] : false;
}
function set_transient($name, $value, $expiration = 0) {
    $GLOBALS['transients'][$name] = $value;
    return true;
}
function wp_remote_get($url, $args) {
    $GLOBALS['request_count']++;
    if (strpos($url, '/videos') !== false) {
        $result = array();
    } elseif ($GLOBALS['status_fixture'] instanceof WP_Error) {
        return $GLOBALS['status_fixture'];
    } else {
        $result = $GLOBALS['status_fixture'];
    }
    return array('code' => 200, 'body' => json_encode(array('success' => true, 'result' => $result)));
}
function wp_remote_retrieve_response_code($response) { return $response['code']; }
function wp_remote_retrieve_body($response) { return $response['body']; }
function sanitize_text_field($value) { return (string) $value; }

require dirname(__DIR__) . '/includes/cloudflare-stream.php';

function check($condition, $description) {
    if (!$condition) throw new RuntimeException($description);
}
$cases = array(
    array(array('status' => 'connected'), true, 'string connected'),
    array(array('status' => 'reconnected'), true, 'string reconnected'),
    array(array('status' => 'client_disconnect'), false, 'string disconnected'),
    array(array('status' => array('current' => array('state' => 'connected'))), true, 'nested connected'),
    array(array('status' => array('current' => array('state' => 'reconnected'))), true, 'nested reconnected'),
    array(array('status' => array('current' => array('state' => 'disconnected', 'reason' => 'client_disconnect'))), false, 'nested disconnected'),
    array(array('status' => array('current' => array('state' => 'reconnecting'))), false, 'nested reconnecting'),
    array(array('status' => array('history' => array(array('state' => 'connected')))), false, 'never use historical connected status'),
    array(array(), false, 'missing status'),
    array(array('status' => array()), false, 'empty status'),
);

foreach ($cases as $case) {
    list($response, $expected, $label) = $case;
    check(surfside_tools_stream_is_live($response) === $expected, 'Incorrect live result for ' . $label);
}

foreach ($cases as $case) {
    list($response, $expected, $label) = $case;
    $GLOBALS['status_fixture'] = $response;
    $GLOBALS['transients'] = array();
    $first = surfside_tools_stream_public_data();
    check($first['live'] === $expected, 'Public API returned incorrect live for ' . $label);
    check(strpos($first['live_hls_url'], '/manifest/video.m3u8') !== false, 'HLS URL remains available');
    $before = $GLOBALS['request_count'];
    $second = surfside_tools_stream_public_data();
    check($second['live'] === $expected && $GLOBALS['request_count'] === $before,
        'Second request should use transient for ' . $label);
}
$GLOBALS['status_fixture'] = new WP_Error('network', 'Unavailable');
$GLOBALS['transients'] = array();
check(surfside_tools_stream_public_data()['live'] === null,
    'API failure should return unknown status, not incorrectly offline');

echo "Cloudflare live input status response checks passed.\n";
