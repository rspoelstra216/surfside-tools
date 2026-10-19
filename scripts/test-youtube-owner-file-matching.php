<?php
/** Read-only owner metadata matching and scan regression checks. */
define('ABSPATH', __DIR__ . '/');
define('MINUTE_IN_SECONDS', 60);
class WP_Error {
    private $message;
    public function __construct($code, $message) { $this->message = $message; }
    public function get_error_message() { return $this->message; }
}
function is_wp_error($value) { return $value instanceof WP_Error; }
function add_action() {}
function get_option($key, $default = false) { return $GLOBALS['options'][$key] ?? $default; }
function update_option($key, $value, $autoload = null) { $GLOBALS['options'][$key] = $value; return true; }
function sanitize_text_field($value) { return trim(strip_tags((string) $value)); }
function add_query_arg($params, $url) { return $url . '?' . http_build_query($params); }
function wp_remote_get($url, $options) {
    $GLOBALS['requested_url'] = $url;
    $GLOBALS['requested_headers'] = $options['headers'] ?? array();
    return array('status' => 200, 'body' => json_encode(array(
        'items' => array(
            array('id' => 'AAAAAAAAAAA', 'fileDetails' => array('fileName' => 'capture - 09 February 2025.mp4', 'fileSize' => '1234')),
            array('id' => 'BBBBBBBBBBB', 'processingDetails' => array('fileDetailsAvailability' => 'unavailable')),
            array('id' => 'INVALIDXXXX', 'fileDetails' => array('fileName' => 'Should Not Import.mp4', 'fileSize' => '999')),
        ),
    )));
}
function wp_remote_retrieve_response_code($response) { return $response['status']; }
function wp_remote_retrieve_body($response) { return $response['body']; }
function wp_next_scheduled() { return false; }
function wp_schedule_single_event() {}
function check($ok, $message) { if (!$ok) throw new RuntimeException($message); }

require_once __DIR__ . '/../includes/youtube-owner-file-matching.php';

foreach (array('../bad.mp4', '/absolute.mp4', 'C:\\bad.mp4', '2024//bad.mp4', '2024/./bad.mp4',
    '2024/../bad.mp4', "line\nbreak.mp4", '2024/movie.mkv') as $bad) {
    check(!surfside_yt_owner_valid_source_path($bad), 'Rejected unsafe path: ' . $bad);
}
check(surfside_yt_owner_valid_source_path('2025/Video/February/capture - 09 February 2025.mp4'), 'Valid nested path');
$detail = array('file_name' => 'capture - 09 February 2025.mp4', 'size_bytes' => '1234');
$match = array('source_path' => '2025/Video/February/capture - 09 February 2025.mp4', 'size_bytes' => '1234');
$index = array($detail['file_name'] => array($match));
check(surfside_yt_owner_match_file($detail, $index)['status'] === 'matched', 'Exact name/size match');
check(surfside_yt_owner_match_file($detail, $index)['source_path'] === $match['source_path'], 'Source path preserved');
$index[$detail['file_name']][] = array('source_path' => 'older/duplicate.mp4', 'size_bytes' => '1234');
check(surfside_yt_owner_match_file($detail, $index)['status'] === 'duplicate_filename', 'Duplicate names must not be selected');
$index[$detail['file_name']] = array(array('source_path' => $match['source_path'], 'size_bytes' => '1200'));
check(surfside_yt_owner_match_file($detail, $index)['status'] === 'size_mismatch', 'Wrong byte size must not match');
check(surfside_yt_owner_match_file(array(), $index)['status'] === 'youtube_details_unavailable', 'Missing YouTube details require review');
check(surfside_yt_owner_match_file($detail, array())['status'] === 'no_filename_match', 'Missing file requires review');
check(surfside_yt_owner_csv_safe('=CMD()') === "'=CMD()", 'CSV formulas escaped');

$GLOBALS['options'] = array(
    SURFSIDE_YT_OWNER_TOKEN_OPTION => array('access_token' => 'TEST_ONLY_TOKEN', 'expires_at' => time() + 3600),
    SURFSIDE_YT_OWNER_STATE_OPTION => array(
        'status' => 'running', 'ids' => array('AAAAAAAAAAA', 'BBBBBBBBBBB', 'CCCCCCCCCCC'),
        'cursor' => 0, 'total' => 3, 'available' => 0, 'missing' => 0,
    ),
);
surfside_yt_owner_scan_tick();
$state = $GLOBALS['options'][SURFSIDE_YT_OWNER_STATE_OPTION];
$files = $GLOBALS['options'][SURFSIDE_YT_OWNER_FILES_OPTION];
check($state['status'] === 'complete' && $state['cursor'] === 3, 'Bounded scan completes');
check($state['available'] === 1 && $state['missing'] === 2, 'Tracks missing metadata and missing API items');
check($files['AAAAAAAAAAA']['file_name'] === $detail['file_name'], 'Stores original filename');
check($files['AAAAAAAAAAA']['size_bytes'] === '1234', 'Stores original uploaded byte size');
check($files['BBBBBBBBBBB']['file_name'] === '' && $files['CCCCCCCCCCC']['file_name'] === '', 'No fabricated names');
check(!isset($files['INVALIDXXXX']), 'Never imports unrequested ID');
check(strpos($GLOBALS['requested_url'], 'maxResults') === false, 'No unsupported videos.list parameter');
check(strpos($GLOBALS['requested_url'], 'fileDetails') !== false, 'Owner fileDetails requested');
check($GLOBALS['requested_headers']['Authorization'] === 'Bearer TEST_ONLY_TOKEN', 'Uses OAuth bearer token');
echo "YouTube owner filename matching tests passed.\n";
