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
$GLOBALS['export_status'] = 'not_queued';
$GLOBALS['export_clips'] = array();

class WP_Error {
    private $code; private $text;
    public function __construct($code, $text, $data = array()) { $this->code = $code; $this->text = $text; }
    public function get_error_message() { return $this->text; }
}
class FakeRequest {
    protected $params;
    public function __construct($p) { $this->params = $p; }
    public function get_param($k) { return $this->params[$k] ?? null; }
}
class WP_REST_Request extends FakeRequest {
    public function __construct($method, $path) { parent::__construct(array()); }
    public function set_param($key, $value) { $this->params[$key] = $value; }
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
function add_action($hook, $callback, ...$rest) {
    if ($hook === 'template_redirect') $GLOBALS['highlight_page_dispatch'] = $callback;
    if ($hook === 'rest_api_init') throw new RuntimeException('No public REST routes should be used');
}
function add_shortcode(...$args) {}
function register_rest_route($namespace, $route, $options) {
    throw new RuntimeException('The editor must use scoped staff page POST, not REST cookie auth');
}
function add_query_arg($key, $value, $url) {
    return $url . '?'. http_build_query(array($key => $value));
}
function wp_verify_nonce($nonce, $action) {
    return $nonce === 'test-nonce' && $action === 'surfside_highlights_browser_review';
}
function surfside_tools_prevent_cache() {}
function surfside_tools_staff_enqueue_styles() {}
function surfside_tools_staff_login_box($message = '') { return 'STAFF LOGIN REQUIRED'; }
function surfside_tools_staff_page_url($path = '') {
    return 'https://example.test/dashboard/' . trim($path, '/') . '/';
}
function wp_enqueue_style(...$args) {}
function wp_enqueue_script(...$args) {}
function wp_add_inline_script($handle, $script, $position = 'after') {
    $GLOBALS['highlight_inline_script'] = $script;
}
function esc_url($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }
function esc_url_raw($v) { return (string) $v; }
function rest_url($path) { return 'https://example.test/wp-json/' . ltrim($path, '/'); }
function wp_create_nonce($action) { return 'test-nonce'; }
function sanitize_text_field($v) { return (string) $v; }
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
/**
 * WordPress rest_ensure_response() returns WP_REST_Response objects rather
 * than arrays; exercise that type in the browser dispatcher. WordPress
 * jsonSerialize() returns the underlying data; this normalized PHP return
 * shape is defensive, while bootstrapping from the page fixes the UI race.
 */
class WP_REST_Response implements JsonSerializable {
    private $data;
    public function __construct($data) { $this->data = $data; }
    public function get_data() { return $this->data; }
    public function jsonSerialize(): mixed { return $this->data; }
}
function rest_ensure_response($v) {
    return !empty($GLOBALS['mock_wp_rest_response']) ? new WP_REST_Response($v) : $v;
}
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
    if ($path === '/highlights/exports/jobs') {
        $GLOBALS['export_status'] = 'queued';
        $GLOBALS['export_clips'] = json_decode($body, true)['clips'];
        return array('status' => 202, 'body' => json_encode(array('status' => 'queued')));
    }
    if ($path === '/highlights/exports/status') {
        $clips = $GLOBALS['export_clips'];
        if ($GLOBALS['export_status'] === 'ready') {
            foreach ($clips as &$clip) {
                $clip['download_url'] = 'https://example.workers.dev/private-download-test';
            }
            unset($clip);
        }
        return array('status' => 200, 'body' => json_encode(array(
            'status' => $GLOBALS['export_status'], 'clips' => $clips,
        )));
    }
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

check(is_callable($GLOBALS['highlight_page_dispatch'] ?? null),
    'Highlights request handler registered on authenticated dashboard page');
check(str_contains(surfside_tools_staff_sermon_highlights_shortcode(), 'STAFF LOGIN REQUIRED'),
    'Anonymous users cannot see the editor or secret configuration');

check(!surfside_tools_highlights_can_review(), 'Anonymous users cannot review');
$GLOBALS['logged'] = true;
check(!surfside_tools_highlights_can_review(), 'Login without upload capability is insufficient');
$GLOBALS['cap'] = true;
check(!surfside_tools_highlights_can_review(), 'Upload capability without Tools media role is insufficient');
$GLOBALS['role'] = true;
check(surfside_tools_highlights_can_review(), 'Authorized staff may review');
$_GET = array();
$html = surfside_tools_staff_sermon_highlights_shortcode();
check(str_contains($html, 'Sermon Highlights') &&
    str_contains($html, 'Generate video previews') &&
    str_contains($html, 'Nothing publishes automatically'),
    'Staff editor exposes private video review tools');
check(str_contains($GLOBALS['highlight_inline_script'], 'test-nonce') &&
    str_contains($GLOBALS['highlight_inline_script'], 'site-management') &&
    str_contains($GLOBALS['highlight_inline_script'], 'sermon-highlights') &&
    !str_contains($GLOBALS['highlight_inline_script'], '/wp-json/') &&
    !str_contains($GLOBALS['highlight_inline_script'], SURFSIDE_PODCAST_SHARED_SECRET),
    'Browser receives the correct staff-page endpoint/own nonce but never REST auth or HMAC secrets');
check(str_contains($GLOBALS['highlight_inline_script'], 'highlights-config-v2') &&
    str_contains($GLOBALS['highlight_inline_script'], '"pageChecks":') &&
    str_contains($GLOBALS['highlight_inline_script'], '"configured":true') &&
    str_contains($GLOBALS['highlight_inline_script'], '"sermons":') &&
    str_contains($GLOBALS['highlight_inline_script'], 'AjkoDHgFdeA') &&
    !str_contains($GLOBALS['highlight_inline_script'], SURFSIDE_PODCAST_WORKER_URL),
    'Authenticated page includes populated sermon list and true Worker configuration without raw secrets');
$highlight_js = file_get_contents(dirname(__DIR__) . '/assets/js/sermon-highlights-review.js');
check(is_string($highlight_js) &&
    str_contains($highlight_js, 'Array.isArray(cfg.sermons)') &&
    !str_contains($highlight_js, 'json("sermons")'),
    'Startup uses server-rendered sermons and does not make a second inconsistent config POST');

// Exercise the real browser form transport: WordPress's custom staff cookie
// works on /dashboard, unlike wp_rest cookie nonce authentication.
$payload = array(
    'highlight_nonce' => 'test-nonce', 'mode' => 'sermons',
);
// Exercise the real WordPress response-object behavior for all browser modes.
$GLOBALS['mock_wp_rest_response'] = true;
check(surfside_tools_highlights_rest_sermons() instanceof WP_REST_Response,
    'Realistic fixture wraps REST data instead of returning a plain array');
$sermons = surfside_tools_highlights_browser_dispatch($payload);
check(is_array($sermons) && !isset($sermons['data']),
    'The staff page unwraps the sermon-list response before JSON serialization');
check(count($sermons['sermons']) === 1 && $sermons['configured'],
    'Front-end staff POST can load the sermon dropdown');
check(($sermons['config_protocol'] ?? '') === 'highlights-config-v2',
    'Dashboard request carries a stable protocol to distinguish stale PHP versions');
$expected_checks = array('worker_url_defined','worker_url_string','worker_url_valid',
    'secret_defined','secret_string','secret_length_valid');
check(array_keys($sermons['config_checks'] ?? array()) === $expected_checks,
    'Only six narrow boolean diagnostic check names are returned');
check(count(array_filter($sermons['config_checks'])) === 6,
    'Properly configured Worker and 64-character test secret pass every check');
foreach ($sermons['config_checks'] as $value) {
    check(is_bool($value), 'Diagnostics must expose only boolean values');
}
$public_json = wp_json_encode($sermons);
check(strpos($public_json, SURFSIDE_PODCAST_SHARED_SECRET) === false &&
    strpos($public_json, SURFSIDE_PODCAST_WORKER_URL) === false &&
    strpos($public_json, '64 characters') === false,
    'Browser responses must not reveal Worker address, secret, or secret length');
check(is_wp_error(surfside_tools_highlights_browser_dispatch(array(
    'highlight_nonce' => 'wrong', 'mode' => 'sermons',
))), 'Forged browser action nonce is rejected');
check(is_wp_error(surfside_tools_highlights_browser_dispatch(array(
    'highlight_nonce' => 'test-nonce', 'mode' => 'publish',
))), 'No publication actions exist');
$GLOBALS['role'] = false;
check(is_wp_error(surfside_tools_highlights_browser_dispatch($payload)),
    'Browser dispatcher fails closed for a staff member without Weekly Media permission');
$GLOBALS['role'] = true;
$sermonForm = array_merge($payload,
    array('mode' => 'status', 'video_id' => 'AjkoDHgFdeA'));
$existing = surfside_tools_highlights_browser_dispatch($sermonForm);
check($existing['status'] === 'ready', 'Browser form status fetch reads existing analysis');
$more = surfside_tools_highlights_browser_dispatch(array_merge(
    $sermonForm, array('mode' => 'find-more')));
check($more['status'] === 'queued',
    'Authorized staff can request additional transcript-only suggestions');
$last = end($GLOBALS['worker_calls']);
check($last['path'] === '/highlights/more/jobs' &&
    $last['request'] === array('video_id' => 'AjkoDHgFdeA'),
    'Find more sends only a verified video ID through the signed Worker');

check(is_wp_error(surfside_tools_highlights_browser_dispatch(array_merge(
    $sermonForm, array('mode' => 'export')))),
    'Unapproved saved drafts cannot be exported');
$decisionForm = array_merge($sermonForm, array(
    'mode' => 'review', 'number' => '4', 'decision' => 'approved',
    'start' => '952.78', 'end' => '1010.52',
));
$choice = surfside_tools_highlights_browser_dispatch($decisionForm);
check($choice['review']['decision'] === 'approved' && $choice['review']['number'] === 4,
    'Encoded HTML form string fields safely convert into typed internal review requests');
check(is_wp_error(surfside_tools_highlights_browser_dispatch(
    array_merge($decisionForm, array('number' => '4.0')))),
    'Malformed numeric form fields do not bypass canonical validation');
check(is_wp_error(surfside_tools_highlights_browser_dispatch(
    array_merge($decisionForm, array('start' => '940')))),
    'Server checks five-second trim limits even for forged browser requests');
check(is_wp_error(surfside_tools_highlights_browser_dispatch(array(
    'highlight_nonce' => 'wrong', 'mode' => 'find-more',
    'video_id' => 'AjkoDHgFdeA',
))), 'Find more cannot bypass staff nonce authentication');


// Direct REST handler assertions below use their original array-shaped test
// contract; the browser-dispatch cases above used real response wrappers.
$GLOBALS['mock_wp_rest_response'] = false;
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

$export = surfside_tools_highlights_browser_dispatch(array_merge(
    $sermonForm, array('mode' => 'export')));
check($export['status'] === 'queued', 'Approved MP4 export can be queued privately');
$last = end($GLOBALS['worker_calls']);
check($last['path'] === '/highlights/exports/jobs' &&
    count($last['request']['clips']) === 2,
    'Only two explicitly approved highlights are sent to the final export Worker');
check($last['request']['clips'][0] === array('number'=>2, 'start'=>456.74, 'end'=>491.24) &&
    $last['request']['clips'][1] === array('number'=>4, 'start'=>952.78, 'end'=>1010.52),
    'Final export uses exact stored trims, not five-second-padded previews');
$GLOBALS['export_status'] = 'ready';
$downloads = surfside_tools_highlights_browser_dispatch(array_merge(
    $sermonForm, array('mode' => 'export-status')));
check($downloads['matches_current_approvals'] === true &&
    count($downloads['clips']) === 2,
    'Only current approvals produce final download links');

$reject = surfside_tools_highlights_rest_decision(new FakeRequest(array_merge(
    $review, array('decision' => 'rejected'))));
check($reject['review']['decision'] === 'rejected',
    'Rejection updates the same per-sermon review record');
$stale = surfside_tools_highlights_rest_export_status($id);
check($stale['status'] === 'outdated' && !isset($stale['clips']) &&
    !$stale['matches_current_approvals'],
    'Rejecting a formerly approved clip hides outdated private export links');
$save = surfside_tools_highlights_rest_decision(new FakeRequest(array_merge(
    $review, array('decision' => 'saved'))));
check($save['review']['decision'] === 'saved' &&
    count(surfside_tools_highlights_approved_clips('AjkoDHgFdeA')) === 1,
    'Save changes alone does not constitute export approval');


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
