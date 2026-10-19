<?php
/** Small no-WordPress regression test for R2 ID matching, signing and upload flow. */
define('ABSPATH', __DIR__ . '/');
define('HOUR_IN_SECONDS', 3600);
define('SURFSIDE_R2_ACCOUNT_ID', '0123456789abcdef0123456789abcdef');
define('SURFSIDE_R2_BUCKET', 'surfside-sermons');
define('SURFSIDE_R2_ACCESS_KEY_ID', 'TESTACCESSKEY');
define('SURFSIDE_R2_SECRET_ACCESS_KEY', 'test-secret');
define('SURFSIDE_R2_PUBLIC_BASE_URL', 'https://sermons.example.org');

class WP_Error {
    public $code;
    public function __construct($code, $message, $data = null) { $this->code = $code; }
}
function is_wp_error($x) { return $x instanceof WP_Error; }
function add_action(...$args) {}
function add_shortcode(...$args) {}
function get_option($name, $default = null) {
    if ($name === 'surfside_r2_sermon_schema') return 1;
    return $default;
}
function set_transient($key, $value, $seconds) { $GLOBALS['transients'][$key] = $value; }
function get_transient($key) { return $GLOBALS['transients'][$key] ?? false; }
function delete_transient($key) { unset($GLOBALS['transients'][$key]); }
function get_current_user_id() { return 42; }
function current_time($what, $gmt = false) { return '2026-10-18 20:00:00'; }
function is_user_logged_in() { return $GLOBALS['logged_in'] ?? true; }
function current_user_can($cap) { return $GLOBALS['allowed'] ?? true; }
function surfside_tools_youtube_review_master_id() { return 'PLtestMaster123'; }
function surfside_tools_youtube_tables() { return array('members' => 'wp_surfside_yt_members'); }
function wp_remote_head($url, $args) {
    $GLOBALS['head_requested'] = $url;
    return array('status' => $GLOBALS['head_status'] ?? 404, 'headers' => array(
        'content-length' => (string) ($GLOBALS['head_size'] ?? 0),
        'content-type' => $GLOBALS['head_type'] ?? 'video/mp4',
        'etag' => '"test-etag"',
    ));
}
function wp_remote_retrieve_response_code($response) { return $response['status']; }
function wp_remote_retrieve_header($response, $key) { return $response['headers'][$key] ?? ''; }
function rest_ensure_response($value) { return $value; }
function trailingslashit($v) { return rtrim($v, '/') . '/'; }
function wp_parse_url($url) { return parse_url($url); }
class R2TestDB {
    public $prefix = 'wp_';
    public $saved = array();
    public function prepare($sql, ...$args) { return $sql; }
    public function get_var($sql) { return $GLOBALS['known_id'] ?? ''; }
    public function replace($table, $data, $formats) { $this->saved = $data; return 1; }
}
$wpdb = new R2TestDB();
class R2TestRequest {
    private $data;
    public function __construct($data) { $this->data = $data; }
    public function get_param($key) { return $this->data[$key] ?? null; }
}
function assert_r2($pass, $message) {
    if (!$pass) { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); }
}

require_once dirname(__DIR__) . '/includes/r2-sermon-archive.php';

$id = 'abcdefghijk';
$GLOBALS['known_id'] = $id;
assert_r2(surfside_tools_r2_video_id($id), 'valid YouTube ID');
assert_r2(surfside_tools_r2_youtube_id_from_input('https://www.youtube.com/watch?v=' . $id . '&list=PLabc') === $id,
    'YouTube watch URL parsed safely');
assert_r2(surfside_tools_r2_youtube_id_from_input('https://youtu.be/' . $id . '?si=foo') === $id,
    'Short YouTube URL parsed');
assert_r2(surfside_tools_r2_youtube_id_from_input('https://www.youtube.com/live/' . $id) === $id,
    'YouTube live URL parsed');
assert_r2(surfside_tools_r2_youtube_id_from_input('https://www.youtube.com/shorts/' . $id) === $id,
    'YouTube shorts URL parsed');
assert_r2(surfside_tools_r2_youtube_id_from_input('https://youtube.com.evil.example/watch?v=' . $id) === '',
    'Lookalike YouTube host rejected');
assert_r2(surfside_tools_r2_youtube_id_from_input('https://example.org/watch?v=' . $id) === '',
    'External host rejected');
assert_r2(surfside_tools_r2_youtube_id_from_input('../' . $id) === '',
    'Unexpected relative path rejected');

assert_r2(!surfside_tools_r2_video_id('../traversal'), 'invalid YouTube ID rejected');
assert_r2(surfside_tools_r2_sermon_key($id) === 'sermons/abcdefghijk.mp4', 'deterministic key');
$url = surfside_tools_r2_presign('PUT', surfside_tools_r2_sermon_key($id), array(), 3600, 'video/mp4');
assert_r2(strpos($url, 'X-Amz-Signature=') !== false, 'presigned signature exists');
assert_r2(strpos($url, 'content-type%3Bhost') !== false, 'Content-Type is signed');
assert_r2(strpos($url, SURFSIDE_R2_SECRET_ACCESS_KEY) === false, 'secret never in URL');
assert_r2(is_wp_error(surfside_tools_r2_upload_ticket(new R2TestRequest(array('video_id'=>'bad','size'=>2000000)))), 'unknown ID rejected');
assert_r2(is_wp_error(surfside_tools_r2_upload_ticket(new R2TestRequest(array('video_id'=>$id,'size'=>0)))), 'empty size rejected');

$GLOBALS['head_status'] = 404;
$ticket = surfside_tools_r2_upload_ticket(new R2TestRequest(array('video_id'=>$id,'size'=>2000000)));
assert_r2(is_array($ticket) && $ticket['video_id'] === $id, 'ticket for known video');
assert_r2(get_transient('surfside_r2_upload_42_'.$id) === 2000000, 'expected bytes stored server-side');

$GLOBALS['head_status'] = 200;
$GLOBALS['head_size'] = 1234;
assert_r2(is_wp_error(surfside_tools_r2_upload_complete(new R2TestRequest(array('video_id'=>$id)))), 'mismatched size rejected');
$GLOBALS['head_size'] = 2000000;
assert_r2(surfside_tools_r2_upload_complete(new R2TestRequest(array('video_id'=>$id)))['verified'], 'valid upload verified');
assert_r2($wpdb->saved['video_id'] === $id && $wpdb->saved['status'] === 'ready', 'ready record persisted');
assert_r2(get_transient('surfside_r2_upload_42_'.$id) === false, 'ticket invalidated');
assert_r2(is_wp_error(surfside_tools_r2_upload_ticket(new R2TestRequest(array('video_id'=>$id,'size'=>2000000)))), 'existing R2 file blocks overwrite');
$GLOBALS['logged_in'] = false;
assert_r2(!surfside_tools_r2_rest_permission(), 'signed-out blocked');
echo "PASS: R2 IDs, signatures, permissions, upload verification and no-overwrite\n";
