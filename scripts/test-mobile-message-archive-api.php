<?php
/**
 * Regression tests for the public mobile Message Archive API.
 * Run: php scripts/test-mobile-message-archive-api.php
 */
define('ABSPATH', __DIR__ . '/');
define('SURFSIDE_PUBLIC_MESSAGES_PER_PAGE', 12);

$GLOBALS['routes'] = array();
$GLOBALS['hooks'] = array();
$GLOBALS['archive_master'] = 'PLMESSAGES1234';
$GLOBALS['archive_calls'] = array();
$GLOBALS['archive_years'] = array(2026, 2014);
$GLOBALS['archive_visible_series'] = array(
    array('playlist_id' => 'PLVISIBLE1234', 'title' => 'Made for More',
        'message_count' => 5, 'sample_thumbnail' => 'https://i.ytimg.com/series.jpg'),
);
$GLOBALS['archive_video'] = array(
    'video_id' => 'abcdEF12_34',
    'title' => 'Discovering God’s Calling – Made for More – Week 2 – 20 September 2026',
    'thumbnail_url' => 'https://i.ytimg.com/vi/abcdEF12_34/hqdefault.jpg',
    'message_date' => '2026-09-20',
    'date_source' => 'sermon',
);
$GLOBALS['archive_published'] = array(
    'video_id' => 'abcdEF12_35',
    'title' => 'What Love Looks Like',
    'thumbnail_url' => 'https://i.ytimg.com/vi/abcdEF12_35/hqdefault.jpg',
    'message_date' => '2014-03-05',
    'date_source' => 'youtube',
);

class WP_REST_Server { const READABLE = 'GET'; }
class WP_REST_Request {
    private $data;
    public function __construct($data = array()) { $this->data = $data; }
    public function get_param($name) { return $this->data[$name] ?? null; }
}
class WP_Error {
    public function __construct(public $code, public $message, public $data = array()) {}
    public function get_error_code() { return $this->code; }
    public function get_error_data() { return $this->data; }
}
function is_wp_error($v) { return $v instanceof WP_Error; }
function add_action($hook, $func) { $GLOBALS['hooks'][$hook] = $func; }
function register_rest_route($namespace, $route, $args) {
    $GLOBALS['routes'][$namespace . $route] = $args;
}
function rest_ensure_response($result) { return $result; }
function esc_url_raw($value, $protocols = array('https')) {
    if ($value === '') return '';
    if (!str_starts_with($value, 'https://')) throw new RuntimeException('Non-HTTPS thumbnail');
    return $value;
}
function surfside_tools_youtube_valid_playlist_id($id) {
    return is_string($id) && preg_match('/^[A-Za-z0-9_-]{10,100}$/', $id);
}
function surfside_tools_public_messages_master_id() { return $GLOBALS['archive_master']; }
function surfside_tools_public_messages_filters($source) {
    $GLOBALS['archive_calls'][] = 'filters';
    return array(
        'q' => substr(trim($source['sm_search'] ?? ''), 0, 100),
        'year' => $source['sm_year'] ?? '',
        'series' => $source['sm_series'] ?? '',
        'page' => (int) ($source['sm_page'] ?? 1),
    );
}
function surfside_tools_public_messages_latest($master) {
    $GLOBALS['archive_calls'][] = 'latest';
    return $GLOBALS['archive_video'];
}
function surfside_tools_public_messages_list($master, $filters) {
    $GLOBALS['archive_calls'][] = 'list';
    if ($master !== 'PLMESSAGES1234') throw new RuntimeException('Unexpected master ID');
    $GLOBALS['list_filters'] = $filters;
    return array('total'=>26, 'pages'=>3,
        'page'=>min(3, max(1, $filters['page'])),
        'rows'=>array($GLOBALS['archive_video'], $GLOBALS['archive_published']));
}
function surfside_tools_public_messages_years($master) {
    $GLOBALS['archive_calls'][] = 'years';
    return $GLOBALS['archive_years'];
}
function surfside_tools_public_messages_series($master) {
    $GLOBALS['archive_calls'][] = 'series';
    return $GLOBALS['archive_visible_series']; // hidden series was already excluded by website helper
}
function surfside_tools_public_messages_detail($master, $id) {
    $GLOBALS['archive_calls'][] = 'detail';
    if ($id === 'abcdEF12_34') return $GLOBALS['archive_video'];
    if ($id === 'abcdEF12_35') return $GLOBALS['archive_published'];
    return null; // excluded or non-master ID
}
function surfside_tools_public_messages_video_series($master, $id, $series_rows) {
    $GLOBALS['archive_calls'][] = 'memberships';
    return array(array('playlist_id'=>'PLVISIBLE1234', 'title'=>'Made for More'));
}
function surfside_tools_public_messages_featured_heading($title) {
    if (str_contains($title, ' – Made for More – Week 2 – ')) {
        return array('heading'=>'Discovering God’s Calling', 'context'=>'Made for More · Week 2');
    }
    return array('heading'=>$title, 'context'=>'');
}
function surfside_tools_public_messages_date_label($date, $source = 'sermon') {
    if (!$date) return 'Date not available';
    $label = $date === '2026-09-20' ? 'September 20, 2026' : 'March 5, 2014';
    return $source === 'youtube' ? 'Published on YouTube: ' . $label : $label;
}
require dirname(__DIR__) . '/includes/mobile-message-archive-api.php';
function check($value, $label) { if (!$value) throw new RuntimeException($label); }

check($GLOBALS['archive_calls'] === array(), 'Module inclusion performs no archive reads');
check(isset($GLOBALS['hooks']['rest_api_init']), 'REST registration hook exists');
surfside_tools_mobile_messages_register_routes();
check(count($GLOBALS['routes']) === 3, 'Exactly three archive read routes are registered');
foreach ($GLOBALS['routes'] as $name=>$entry) {
    check($entry['methods'] === WP_REST_Server::READABLE
        && $entry['permission_callback'] === '__return_true',
        'All mobile archive routes are public and GET-only: ' . $name);
}
check(isset($GLOBALS['routes']['surfside/v1/messages'])
    && isset($GLOBALS['routes']['surfside/v1/messages/series'])
    && isset($GLOBALS['routes']['surfside/v1/messages/(?P<video_id>[A-Za-z0-9_-]{11})']),
    'List, series and 11-character video detail paths are registered');

$home = surfside_tools_mobile_messages_list(new WP_REST_Request());
check($home['available'] === true && $home['api_version'] === 1, 'Archive is available when synced');
check(count($home['items']) === 2, 'Master videos returned as a bounded page');
check($home['pagination'] === array('page'=>1,'page_size'=>12,'total'=>26,
    'total_pages'=>3,'has_more'=>true), 'Pagination communicates bounded 12-item page and continuation');
check($home['years'] === array(2026,2014), 'Only years from visible archive are included');
check($home['latest']['id'] === 'abcdEF12_34'
    && $home['latest']['display_title'] === 'Discovering God’s Calling',
    'Latest sermon uses shared website title/selection logic');
check($home['items'][0]['subtitle'] === 'Made for More · Week 2',
    'Sermon subtitle is available to native cards');
check($home['items'][1]['date'] === array('value'=>'2014-03-05','source'=>'youtube',
    'label'=>'Published on YouTube: March 5, 2014'),
    'Included undated old video retains accurately labeled YouTube publication date');
check($home['items'][0]['embed_url'] === 'https://www.youtube-nocookie.com/embed/abcdEF12_34'
    && $home['items'][0]['watch_url'] === 'https://www.youtube.com/watch?v=abcdEF12_34',
    'Video uses official YouTube URLs, not extracted media streams');
check(!array_key_exists('api_key', $home)
    && !array_key_exists('notes', $home['items'][0])
    && !array_key_exists('email', $home['items'][0]),
    'Public responses do not expose credentials, weekly notes or private data');

$GLOBALS['archive_calls'] = array();
$filtered = surfside_tools_mobile_messages_list(new WP_REST_Request(array(
    'search'=>'Calling', 'year'=>'2026', 'series'=>'PLVISIBLE1234', 'page'=>'2',
)));
check($GLOBALS['list_filters'] === array(
    'q'=>'Calling','year'=>'2026','series'=>'PLVISIBLE1234','page'=>2
), 'Search/year/series/page filters reach existing website query logic');
check($filtered['pagination']['page'] === 2
    && $filtered['pagination']['has_more'] === true, 'Page 2 returns continuation metadata');
check($filtered['latest'] === null, 'Only the unfiltered first page features Latest Sermon');
check(in_array('series', $GLOBALS['archive_calls'], true), 'Visible-series membership verified before filtered list');

$GLOBALS['archive_calls'] = array();
$hidden = surfside_tools_mobile_messages_list(new WP_REST_Request(array('series'=>'PLHIDDEN12345')));
check(is_wp_error($hidden) && $hidden->get_error_data()['status'] === 404,
    'Direct filtering of a hidden series returns 404');
check(!in_array('list', $GLOBALS['archive_calls'], true), 'Hidden series never reaches the list query');

foreach (array(
    array('year'=>'2026<script>'), array('year'=>'not-a-year'),
    array('series'=>'invalid!'), array('page'=>'0'),
    array('page'=>'-1'), array('page'=>'100001'),
    array('page'=>'1 OR 1=1'), array('search'=>array('bad')),
) as $bad) {
    $result = surfside_tools_mobile_messages_list(new WP_REST_Request($bad));
    check(is_wp_error($result) && $result->get_error_data()['status'] === 400,
        'Malformed query parameters do not silently widen public access');
}
$long = surfside_tools_mobile_messages_list(new WP_REST_Request(array('search'=>str_repeat('x',200))));
check(strlen($GLOBALS['list_filters']['q']) === 100, 'Search is bounded to website archive maximum');
check($long['available'], 'Long text search can be truncated safely');

$series = surfside_tools_mobile_messages_series(new WP_REST_Request());
check($series['available'] && count($series['series']) === 1
    && $series['series'][0]['message_count'] === 5,
    'Series response uses existing visible series and message counts');
check($series['series'][0]['id'] === 'PLVISIBLE1234'
    && $series['series'][0]['thumbnail_url'] === 'https://i.ytimg.com/series.jpg',
    'Series artwork is provided for native Browse by Series cards');

$detail = surfside_tools_mobile_messages_detail(new WP_REST_Request(array('video_id'=>'abcdEF12_34')));
check($detail['message']['id'] === 'abcdEF12_34'
    && $detail['series'] === array(array('id'=>'PLVISIBLE1234','title'=>'Made for More')),
    'Video detail returns one publicly visible message plus permitted series membership');
$excluded = surfside_tools_mobile_messages_detail(new WP_REST_Request(array('video_id'=>'excluded123')));
check(is_wp_error($excluded) && $excluded->get_error_data()['status'] === 404,
    'Excluded video ID cannot be opened directly through mobile API');
$bad_id = surfside_tools_mobile_messages_detail(new WP_REST_Request(array('video_id'=>'invalid')));
check(is_wp_error($bad_id) && $bad_id->get_error_data()['status'] === 400,
    'Malformed video IDs are rejected');

$GLOBALS['archive_master'] = '';
$GLOBALS['archive_calls'] = array();
$missing = surfside_tools_mobile_messages_list(new WP_REST_Request());
check($missing['available'] === false && $missing['items'] === array()
    && $missing['years'] === array() && $missing['latest'] === null,
    'Before first sync, list presents a safe empty archive');
$missing_series = surfside_tools_mobile_messages_series(new WP_REST_Request());
check($missing_series['available'] === false && $missing_series['series'] === array(),
    'Before sync, series has an empty response');
$missing_detail = surfside_tools_mobile_messages_detail(new WP_REST_Request(array('video_id'=>'abcdEF12_34')));
check(is_wp_error($missing_detail) && $missing_detail->get_error_data()['status'] === 404,
    'Before sync, message detail is unavailable');
check($GLOBALS['archive_calls'] === array('filters'),
    'No metadata queries occur when archive is not initialized');

$source = file_get_contents(dirname(__DIR__) . '/includes/mobile-message-archive-api.php');
check(!str_contains($source, 'surfside_tools_youtube_start_sync(')
    && !str_contains($source, 'wp_insert_post(')
    && !str_contains($source, 'dbDelta(')
    && !str_contains($source, 'wp_remote_get('),
    'Mobile reads never initiate sync, migration, publishing, or upstream API requests');

echo "Mobile Message Archive public REST contract and visibility checks passed.\n";
