<?php
/** Offline security and workflow test for the manual video-first highlight editor. */
define('ABSPATH', __DIR__ . '/');
define('SURFSIDE_TOOLS_VERSION', 'test');
define('SURFSIDE_TOOLS_PATH', dirname(__DIR__) . '/');
define('SURFSIDE_TOOLS_URL', 'https://example.test/wp-content/plugins/surfside-tools/');
define('SURFSIDE_PODCAST_WORKER_URL', 'https://worker.example.test');
define('SURFSIDE_PODCAST_SHARED_SECRET', str_repeat('f', 64));
$GLOBALS['logged'] = false;
$GLOBALS['cap'] = false;
$GLOBALS['role'] = false;
$GLOBALS['options'] = array();
$GLOBALS['worker_calls'] = array();
$GLOBALS['known_sermon'] = true;
$GLOBALS['candidate_status'] = 'ready';

class WP_Error {
    private $code; private $text;
    public function __construct($code, $text, $data = array()) { $this->code = $code; $this->text = $text; }
    public function get_error_message() { return $this->text; }
}
class FakeRequest {
    private $params;
    public function __construct($p) { $this->params = $p; }
    public function get_param($k) { return $this->params[$k] ?? null; }
}
class FakeDb {
    public function prepare($query, $args) { return $query; }
    public function get_results($query, $format) {
        if (!$GLOBALS['known_sermon']) return array();
        return array(array('video_id' => 'AjkoDHgFdeA',
            'title' => 'Celebrating Faith Steps', 'sermon_date' => '2026-11-15'));
    }
}
$GLOBALS['wpdb'] = new FakeDb();
define('ARRAY_A', 'ARRAY_A');
function add_action(...$args) {}
function add_shortcode(...$args) {}
function is_user_logged_in() { return $GLOBALS['logged']; }
function current_user_can($cap) { return $cap === 'upload_files' && $GLOBALS['cap']; }
function surfside_tools_staff_has_permission($key) {
    return $key === 'weekly_media' && $GLOBALS['role'];
}
function surfside_tools_r2_master_id() { return 'master-playlist'; }
function surfside_tools_r2_sermon_table() { return 'wp_r2'; }
function surfside_tools_youtube_tables() {
    return array('videos' => 'wp_videos', 'members' => 'wp_members');
}
function get_option($key, $default = false) { return $GLOBALS['options'][$key] ?? $default; }
function update_option($key, $value, $autoload = null) {
    $GLOBALS['options'][$key] = $value; return true;
}
function get_current_user_id() { return 42; }
function untrailingslashit($s) { return rtrim($s, '/'); }
function wp_json_encode($v) { return json_encode($v); }
function is_wp_error($v) { return $v instanceof WP_Error; }
function rest_ensure_response($v) { return $v; }
function sanitize_key($v) { return strtolower(preg_replace('/[^a-z0-9_-]/', '', (string) $v)); }
function wp_remote_post($url, $request) {
    $body = $request['body'];
    $h = $request['headers'];
    $expected = hash_hmac('sha256', $h['X-Surfside-Time'] . '.' . $body, SURFSIDE_PODCAST_SHARED_SECRET);
    if (!hash_equals($expected, $h['X-Surfside-Signature'])) throw new Exception('Missing signature');
    if (strpos($body, SURFSIDE_PODCAST_SHARED_SECRET) !== false)
        throw new Exception('Secret must not enter request JSON');
    $path = parse_url($url, PHP_URL_PATH);
    $GLOBALS['worker_calls'][] = array('path' => $path, 'request' => json_decode($body, true));
    if ($path === '/highlights/status') {
        return array('status' => 200, 'body' => json_encode(array(
            'status' => $GLOBALS['candidate_status'],
            'result' => array('highlights' => array(
                array('number' => 2, 'start' => 457.24, 'end' => 495.24, 'title' => 'Celebrate'),
                array('number' => 4, 'start' => 952.78, 'end' => 1010.52, 'title' => 'Faith'),
            ))
        )));
    }
    return array('status' => 202, 'body' => json_encode(array('status' => 'queued')));
}
function wp_remote_retrieve_response_code($r) { return $r['status']; }
function wp_remote_retrieve_body($r) { return $r['body']; }
function check($ok, $msg) { if (!$ok) throw new RuntimeException($msg); }
require dirname(__DIR__) . '/includes/sermon-highlights-review.php';

check(!surfside_tools_highlights_can_review(), 'Anonymous users cannot review');
$GLOBALS['logged'] = true;
check(!surfside_tools_highlights_can_review(), 'Login without upload capability is insufficient');
$GLOBALS['cap'] = true;
check(!surfside_tools_highlights_can_review(), 'Upload capability without Tools media role is insufficient');
$GLOBALS['role'] = true;
check(surfside_tools_highlights_can_review(), 'Authorized staff may review');

$id = new FakeRequest(array('video_id' => 'AjkoDHgFdeA'));
check(!is_wp_error(surfside_tools_highlights_request_id($id)), 'Known ready sermons are eligible');
$GLOBALS['known_sermon'] = false;
check(is_wp_error(surfside_tools_highlights_request_id($id)), 'Unknown or unverified sermon rejected');
$GLOBALS['known_sermon'] = true;
check(is_wp_error(surfside_tools_highlights_request_id(
    new FakeRequest(array('video_id' => '../../wrong')))), 'Invalid ID rejected');

$analysis = surfside_tools_highlights_rest_analyze($id);
check($analysis['status'] === 'queued', 'Manual AI analysis queues through signed Worker');
$render = surfside_tools_highlights_rest_previews($id);
check($render['status'] === 'queued', 'Video previews queue through signed Worker');
$last = end($GLOBALS['worker_calls']);
check($last['path'] === '/highlights/clips/jobs', 'Preview request only reaches private render job');
check(count($last['request']['clips']) === 2, 'One private preview per supplied AI suggestion');
check(abs($last['request']['clips'][0]['start'] - 452.24) < .001 &&
    abs($last['request']['clips'][0]['end'] - 500.24) < .001,
    'Preview supplies five seconds of handles on both sides');
check(abs($last['request']['clips'][1]['start'] - 947.78) < .001 &&
    abs($last['request']['clips'][1]['end'] - 1015.52) < .001,
    'Second preview has five seconds of padding too');

$review = array('video_id' => 'AjkoDHgFdeA', 'number' => 2,
    'decision' => 'approved', 'start' => 456.74, 'end' => 491.24);
$saved = surfside_tools_highlights_rest_decision(new FakeRequest($review));
check($saved['review']['decision'] === 'approved' && $saved['review']['reviewed_by'] === 42,
    'Review decision saved with editor identity');
check(abs($saved['review']['start'] - 456.74) < .001,
    'Trim is preserved to centisecond precision');
$loaded = surfside_tools_highlights_rest_status($id);
check($loaded['saved_reviews']['2']['decision'] === 'approved',
    'Decisions survive later page loads');

foreach (array(
    array('start' => 451.74, 'end' => 491.24),
    array('start' => 456.74, 'end' => 501.24),
    array('start' => 456.74, 'end' => 456.74),
) as $bad) {
    check(is_wp_error(surfside_tools_highlights_rest_decision(
        new FakeRequest(array_merge($review, $bad)))),
        'Out-of-range or zero-length trims must be rejected');
}
$GLOBALS['candidate_status'] = 'processing';
check(is_wp_error(surfside_tools_highlights_rest_decision(
    new FakeRequest($review))), 'No approval before analysis is ready');
check(!array_filter($GLOBALS['worker_calls'], function($c) {
    return in_array($c['path'], array('/jobs','/publish','/recover-spreaker'), true);
}), 'Highlight review never touches podcast or publication endpoints');

echo "Sermon Highlights staff access, private HMAC, padded preview, trim and review tests passed.\n";
