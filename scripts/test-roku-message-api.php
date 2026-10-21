<?php
/** Regression contract for the Roku-ready sermon catalog. */
define('ABSPATH', __DIR__ . '/');
define('SURFSIDE_R2_SERMON_SCHEMA_OPTION', 'surfside_r2_sermon_schema');
define('SURFSIDE_R2_SERMON_SCHEMA_VERSION', 1);
define('SURFSIDE_PUBLIC_MESSAGES_PER_PAGE', 12);
define('SURFSIDE_R2_SERMON_PREFIX', 'sermons/');

$GLOBALS['roku_routes'] = array();
$GLOBALS['roku_hooks'] = array();
$GLOBALS['roku_master'] = 'PLMESSAGES1234';
$GLOBALS['roku_base'] = 'https://video.surfsidesermons.com';
$GLOBALS['roku_table_ready'] = true;
$GLOBALS['roku_calls'] = array();
$GLOBALS['roku_row'] = array(
    'video_id'=>'OTt1dfYhlDY', 'title'=>'Made to Serve – Made for More Series – Week 6 – 18 October 2026',
    'thumbnail_url'=>'https://i.ytimg.com/vi/OTt1dfYhlDY/hqdefault.jpg',
    'message_date'=>'2026-10-18', 'date_source'=>'sermon',
);
class WP_REST_Server { const READABLE = 'GET'; }
class WP_REST_Request {
    private $data;
    public function __construct($data = array()) { $this->data = $data; }
    public function get_param($key) { return $this->data[$key] ?? null; }
}
class WP_Error {
    public function __construct(public $code, public $message, public $data = array()) {}
    public function get_error_data() { return $this->data; }
}
function is_wp_error($x) { return $x instanceof WP_Error; }
function rest_ensure_response($r) { return $r; }
function add_action($hook, $fn) { $GLOBALS['roku_hooks'][$hook] = $fn; }
function register_rest_route($ns, $route, $args) { $GLOBALS['roku_routes'][$ns.$route] = $args; }
function esc_url_raw($url, $protocols = array('https')) {
    if (!str_starts_with($url, 'https://')) throw new RuntimeException('Non-HTTPS URL');
    return $url;
}
function get_option($key, $fallback = false) {
    return $key === SURFSIDE_R2_SERMON_SCHEMA_OPTION ? (int) $GLOBALS['roku_table_ready'] : $fallback;
}
function surfside_tools_r2_config() {
    return array('public_base' => $GLOBALS['roku_base']);
}
function surfside_tools_r2_video_id($id) {
    return is_string($id) && (bool) preg_match('/^[A-Za-z0-9_-]{11}$/D', $id);
}
function surfside_tools_r2_sermon_key($id) { return SURFSIDE_R2_SERMON_PREFIX . $id . '.mp4'; }
function surfside_tools_public_messages_master_id() { return $GLOBALS['roku_master']; }
function surfside_tools_mobile_messages_filters($request) {
    $page = $request->get_param('page');
    $year = $request->get_param('year');
    $month = $request->get_param('month');
    $series = $request->get_param('series');
    $search = $request->get_param('search');
    if ($page !== null && (!preg_match('/^[1-9][0-9]{0,5}$/', (string) $page)
        || (int) $page > 100000)) return new WP_Error('bad_filter','Bad page',array('status'=>400));
    if ($year !== null && $year !== '' && !preg_match('/^(19|20)\d{2}$/', (string) $year))
        return new WP_Error('bad_filter','Bad year',array('status'=>400));
    if ($month !== null && $month !== ''
        && (!preg_match('/^(?:0?[1-9]|1[0-2])$/', (string) $month) || $year === null || $year === ''))
        return new WP_Error('bad_filter','Bad month',array('status'=>400));
    if ($search !== null && !is_string($search)) return new WP_Error('bad_filter','Bad search',array('status'=>400));
    return array('q'=>substr((string)($search ?? ''),0,100),
        'year'=>(string) ($year ?? ''), 'month'=>(string) ($month ?? ''),
        'series'=>(string) ($series ?? ''), 'page'=>(int) ($page ?? 1));
}
function surfside_tools_public_messages_list($master, $filters, $r2_only = false) {
    $GLOBALS['roku_calls'][] = array('list',$master,$filters,$r2_only);
    if (!$r2_only) throw new RuntimeException('Roku must request R2-ready filtered SQL');
    return array('total'=>1,'pages'=>1,'page'=>1,
        'rows'=>array($GLOBALS['roku_row']));
}
function surfside_tools_public_messages_series($master, $r2_only = false) {
    $GLOBALS['roku_calls'][] = array('series',$master,$r2_only);
    if (!$r2_only) throw new RuntimeException('Roku series counts must use R2-ready SQL');
    return array(array('playlist_id'=>'PLSERIES12345','title'=>'Made for More',
        'message_count'=>1,'sample_thumbnail'=>'https://i.ytimg.com/series.jpg'));
}
function surfside_tools_public_messages_years($master, $r2_only = false) {
    $GLOBALS['roku_calls'][] = array('years',$master,$r2_only);
    if (!$r2_only) throw new RuntimeException('Roku year facets must use R2-ready SQL');
    return array(2026, 2025, 2024);
}
function surfside_tools_public_messages_months($master, $year, $r2_only = false) {
    $GLOBALS['roku_calls'][] = array('months',$master,$year,$r2_only);
    if (!$r2_only) throw new RuntimeException('Roku month facets must use R2-ready SQL');
    return array(1, 2, 3, 9, 10, 11, 12);
}
function surfside_tools_public_messages_detail($master, $id, $r2_only = false) {
    $GLOBALS['roku_calls'][] = array('detail',$master,$id,$r2_only);
    if (!$r2_only) throw new RuntimeException('Roku detail must use R2-ready SQL');
    if ($id === 'OTt1dfYhlDY') return $GLOBALS['roku_row'];
    return null; // unregistered, staff-excluded, or non-master
}
function surfside_tools_public_messages_video_series($master,$id,$series) {
    return $series ? array(array('playlist_id'=>'PLSERIES12345','title'=>'Made for More')) : array();
}
function surfside_tools_mobile_messages_serialize_video($row) {
    return array('id'=>$row['video_id'],'title'=>$row['title'],
        'date'=>array('value'=>$row['message_date'],'source'=>$row['date_source']),
        'thumbnail_url'=>$row['thumbnail_url'], 'watch_url'=>'https://www.youtube.com/watch?v='.$row['video_id']);
}
function surfside_tools_mobile_messages_serialize_series($row) {
    return array('id'=>$row['playlist_id'],'title'=>$row['title'],
        'message_count'=>(int)$row['message_count'], 'thumbnail_url'=>$row['sample_thumbnail']);
}
function check($ok,$label) { if (!$ok) throw new RuntimeException($label); }

require dirname(__DIR__) . '/includes/roku-message-api.php';
check($GLOBALS['roku_calls'] === array(), 'Inclusion performs no DB queries');
check(isset($GLOBALS['roku_hooks']['rest_api_init']), 'Registers only when REST initialized');
surfside_tools_roku_messages_register_routes();
check(count($GLOBALS['roku_routes']) === 3, 'Three Roku routes registered');
foreach ($GLOBALS['roku_routes'] as $path=>$route) {
    check($route['methods'] === WP_REST_Server::READABLE
        && $route['permission_callback'] === '__return_true',
        'Roku endpoint is public and GET-only: '.$path);
}
check(isset($GLOBALS['roku_routes']['surfside/v1/roku/messages'])
    && isset($GLOBALS['roku_routes']['surfside/v1/roku/series'])
    && isset($GLOBALS['roku_routes']['surfside/v1/roku/messages/(?P<video_id>[A-Za-z0-9_-]{11})']),
    'Namespaced Roku list, series, detail');

$list = surfside_tools_roku_messages_list(new WP_REST_Request());
check($list['available'] === true && $list['api_version'] === 1, 'Ready catalog available');
check(count($list['items']) === 1 && $list['pagination']['total'] === 1,
    'List contains only one ready video and has accurate total');
check($list['years'] === array(2026, 2025, 2024),
    'List exposes only R2-ready archive years');
check($list['items'][0]['id'] === 'OTt1dfYhlDY'
    && $list['items'][0]['playback'] === array(
        'url'=>'https://video.surfsidesermons.com/sermons/OTt1dfYhlDY.mp4',
        'format'=>'mp4','content_type'=>'video/mp4'),
    'Ready message uses canonical YouTube ID and deterministic MP4 URL');
check($list['latest']['id'] === 'OTt1dfYhlDY' && $list['pagination']['has_more'] === false,
    'Newest playable recording used on first unfiltered page');
check(!isset($list['items'][0]['r2_access_key']) && !isset($list['items'][0]['secret']),
    'Public response reveals no credentials');
$filtered = surfside_tools_roku_messages_list(new WP_REST_Request(
    array('search'=>'Serve','year'=>'2026','month'=>'2','series'=>'PLSERIES12345','page'=>'2')));
check($filtered['latest'] === null, 'Filtered page never claims to be homepage latest');
$lastList = null;
for ($i = count($GLOBALS['roku_calls']) - 1; $i >= 0; $i--) {
    if (($GLOBALS['roku_calls'][$i][0] ?? '') === 'list') {
        $lastList = $GLOBALS['roku_calls'][$i];
        break;
    }
}
check($lastList !== null && $lastList[3] === true
    && $lastList[2]['series'] === 'PLSERIES12345'
    && $lastList[2]['year'] === '2026'
    && $lastList[2]['month'] === '2'
    && $lastList[2]['page'] === 2,
    'Year/month/series filtering sent to SQL before pagination');
check($filtered['months'] === array(1, 2, 3, 9, 10, 11, 12),
    'Selected year exposes only R2-ready archive months');
$hidden = surfside_tools_roku_messages_list(new WP_REST_Request(array('series'=>'PLHIDDEN12345')));
check(is_wp_error($hidden) && $hidden->get_error_data()['status'] === 404,
    'Directly requested hidden or unavailable series is rejected');
foreach (array(
    array('page'=>'0'), array('page'=>'bad'), array('year'=>'test'),
    array('month'=>'2'), array('year'=>'2026','month'=>'0'), array('year'=>'2026','month'=>'13')
) as $params) {
    $bad = surfside_tools_roku_messages_list(new WP_REST_Request($params));
    check(is_wp_error($bad) && $bad->get_error_data()['status'] === 400,
        'Roku query enforces shared mobile filter validation');
}
$series = surfside_tools_roku_messages_series(new WP_REST_Request());
check($series['series'][0]['message_count'] === 1,
    'Series count only includes ready R2 sermons');
$detail = surfside_tools_roku_messages_detail(new WP_REST_Request(array('video_id'=>'OTt1dfYhlDY')));
check($detail['message']['playback']['format'] === 'mp4'
    && $detail['series'] === array(array('id'=>'PLSERIES12345','title'=>'Made for More')),
    'Detail gives playable URL and permitted series');
$unavailable = surfside_tools_roku_messages_detail(new WP_REST_Request(array('video_id'=>'abcdefghijk')));
check(is_wp_error($unavailable) && $unavailable->get_error_data()['status'] === 404,
    'Missing and excluded R2 videos give 404');
$bad_id = surfside_tools_roku_messages_detail(new WP_REST_Request(array('video_id'=>'wrong')));
check(is_wp_error($bad_id) && $bad_id->get_error_data()['status'] === 400, 'Rejects bad IDs');

$GLOBALS['roku_base'] = 'http://unsafe.example';
check(surfside_tools_roku_playback_url('OTt1dfYhlDY') === '',
    'Never expose non-HTTPS video URL');
$GLOBALS['roku_base'] = 'https://video.surfsidesermons.com/';
check(surfside_tools_roku_playback_url('OTt1dfYhlDY') === 'https://video.surfsidesermons.com/sermons/OTt1dfYhlDY.mp4',
    'Trailing slash normalized');
$GLOBALS['roku_base'] = 'https://video.surfsidesermons.com/?token=abc';
check(surfside_tools_roku_public_base() === '', 'Rejects query parameters in playback base URL');
$GLOBALS['roku_base'] = 'https://video.surfsidesermons.com';
$GLOBALS['roku_table_ready'] = false;
$GLOBALS['roku_calls'] = array();
$empty = surfside_tools_roku_messages_list(new WP_REST_Request());
check(!$empty['available'] && $empty['years'] === array() && $empty['months'] === array()
    && $empty['items'] === array() && $empty['pagination']['total'] === 0,
    'Missing table yields empty catalog without DB reads');
check(!surfside_tools_roku_messages_series(new WP_REST_Request())['available'],
    'Missing R2 schema makes series unavailable');
check(is_wp_error(surfside_tools_roku_messages_detail(new WP_REST_Request(array('video_id'=>'OTt1dfYhlDY')))),
    'Missing R2 schema prevents detail');
check($GLOBALS['roku_calls'] === array(), 'No queries against nonexistent R2 table');

$GLOBALS['roku_table_ready'] = true;
$GLOBALS['roku_master'] = '';
check(!surfside_tools_roku_messages_list(new WP_REST_Request())['available'],
    'Unsynced master yields empty Roku catalog');
$source = file_get_contents(dirname(__DIR__).'/includes/roku-message-api.php');
foreach (array('wp_remote_get(', 'wp_remote_head(', 'dbDelta(', 'surfside_tools_youtube_start_sync(', 'wp_insert_post(') as $forbidden) {
    check(!str_contains($source, $forbidden), 'Roku GETs do no remote work or schema changes: '.$forbidden);
}
echo "Roku ready-only message, series, details, security and empty-catalog tests passed.\n";
