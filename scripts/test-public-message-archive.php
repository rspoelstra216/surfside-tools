<?php
/**
 * Regression tests for public WordPress Messages archive.
 * Run: php scripts/test-public-message-archive.php
 */
define('ABSPATH', __DIR__ . '/');
define('ARRAY_A', 'ARRAY_A');
define('OBJECT', 'OBJECT');
const SURFSIDE_YT_SCHEMA_OPTION = 'surfside_tools_youtube_schema';
const SURFSIDE_YT_SCHEMA_VERSION = 1;

$GLOBALS['test_actions'] = array();
$GLOBALS['test_shortcodes'] = array();
$GLOBALS['test_options'] = array(SURFSIDE_YT_SCHEMA_OPTION=>2, 'surfside_tools_youtube_review_schema'=>1);
$GLOBALS['test_is_admin'] = false;
$GLOBALS['test_draft'] = true;
$GLOBALS['test_can_edit'] = true;
$GLOBALS['test_existing_page'] = null;
$GLOBALS['test_inserted_pages'] = array();
$GLOBALS['test_api_requests'] = 0;

class WP_Post {
    public $ID = 22;
    public $post_content = '';
    public function __construct($id = 22, $content = '') { $this->ID = $id; $this->post_content = $content; }
}
class WP_Error {
    public function __construct(private $code, private $message) {}
    public function get_error_message() { return $this->message; }
}
function is_wp_error($v) { return $v instanceof WP_Error; }
function add_action($hook, $func, $priority=10) { $GLOBALS['test_actions'][$hook] = $func; }
function add_shortcode($name, $callback) { $GLOBALS['test_shortcodes'][$name] = $callback; }
function is_admin() { return $GLOBALS['test_is_admin']; }
function current_user_can($permission, $id = null) {
    return $permission === 'manage_options' || ($permission === 'edit_post' && $GLOBALS['test_can_edit']);
}
function surfside_tools_youtube_tables() {
    return array('videos'=>'wp_surfside_yt_videos','playlists'=>'wp_surfside_yt_playlists','members'=>'wp_surfside_yt_members');
}
function surfside_tools_youtube_state() { return array('master_id'=>'PLMESSAGES1234','status'=>'complete'); }
function surfside_tools_youtube_sermon_date($title) {
    if (!preg_match('/\\b([0-3]?\\d)\\s+(January|February|March|April|May|June|July|August|September|October|November|December)(?:\\s*,\\s*|\\s+)(\\d{4}|\\d{2})\\b/i', (string) $title, $m)) return '';
    $year = strlen($m[3]) === 2 ? 2000 + (int)$m[3] : (int)$m[3];
    $date = DateTimeImmutable::createFromFormat('!j F Y', $m[1] . ' ' . ucfirst(strtolower($m[2])) . ' ' . $year);
    return $date && (int)$date->format('j') === (int)$m[1] ? $date->format('Y-m-d') : '';
}
function surfside_tools_youtube_valid_playlist_id($v) { return is_string($v) && preg_match('/^[A-Za-z0-9_-]{10,100}$/', $v); }
function surfside_tools_youtube_review_is_ready() {
    return get_option('surfside_tools_youtube_review_schema', 0) >= 1;
}
function surfside_tools_youtube_review_table() { return 'wp_surfside_yt_video_review'; }
function surfside_tools_youtube_review_excluded_series() { return array('PLHIDDEN123456'=>1); }
function get_option($name, $default = false) { return $GLOBALS['test_options'][$name] ?? $default; }
function update_option($name, $value, $autoload = false) { $GLOBALS['test_options'][$name] = $value; return true; }
function is_stringish($v) { return is_string($v); }
function sanitize_text_field($v) { return trim(strip_tags((string) $v)); }
function sanitize_key($v) { return preg_replace('/[^a-z0-9_-]/', '', strtolower((string) $v)); }
function wp_unslash($v) { return stripslashes($v); }
function absint($v) { return abs((int)$v); }
function get_queried_object_id() { return 22; }
function get_permalink($id) { return 'https://example.org/messages/'; }
function get_post_status($id) { return $GLOBALS['test_draft'] ? 'draft' : 'publish'; }
function get_post($id) { return new WP_Post($id); }
function get_preview_post_link($post) { return 'https://example.org/?page_id=22&preview=true'; }
function home_url($path = '') { return 'https://example.org' . $path; }
function add_query_arg($args, $url) {
    $parts = parse_url($url);
    parse_str($parts['query'] ?? '', $query);
    $query = array_merge($query, $args);
    $base = $parts['scheme'].'://'.$parts['host'].($parts['path'] ?? '/');
    return $base.'?'.http_build_query($query,'','&',PHP_QUERY_RFC3986);
}
function esc_html($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function esc_attr($v) { return esc_html($v); }
function esc_url($v) { return esc_html($v); }
function number_format_i18n($v) { return number_format($v); }
function selected($value, $match) { echo $value === $match ? ' selected="selected"' : ''; }
function wp_date($format, $timestamp = null, $timezone = null) {
    return gmdate($format, $timestamp ?? strtotime('2026-10-16T12:00:00Z'));
}
function get_page_by_path($slug, $output, $type) { return $GLOBALS['test_existing_page']; }
function wp_insert_post($args, $error = false) { $GLOBALS['test_inserted_pages'][] = $args; return 22; }

class PublicMessagesDB {
    public $sql = array();
    public $list_rows = array();
    public function __construct() {
        $this->list_rows = array(
            array('video_id'=>'aaaaaaaaaaa','title'=>'Made to Serve 18 October 26',
                'thumbnail_url'=>'https://i.ytimg.com/vi/aaaaaaaaaaa/mqdefault.jpg',
                'message_date'=>'2026-10-18','date_source'=>'sermon'),
            array('video_id'=>'bbbbbbbbbbb','title'=>'Blessed <script>alert(1)</script>',
                'thumbnail_url'=>'', 'message_date'=>'2014-05-12','date_source'=>'youtube'),
        );
    }
    public function esc_like($value) { return addcslashes($value, '_%\\'); }
    public function prepare($query, ...$params) {
        if (count($params) === 1 && is_array($params[0])) $params = $params[0];
        $i = 0;
        return preg_replace_callback('/%[ds]/', function ($m) use (&$i, $params) {
            $v = $params[$i++];
            return $m[0] === '%d' ? (string) (int) $v
                : "'" . str_replace("'", "''", (string)$v) . "'";
        }, $query);
    }
    public function get_var($sql) {
        $this->sql[] = $sql;
        if (str_contains($sql, 'COUNT(*)')) return 2;
        throw new RuntimeException('Unexpected scalar SQL '.$sql);
    }
    public function get_results($sql, $mode = null) {
        $this->sql[] = $sql;
        if (str_contains($sql, 'AS message_count')) {
            return array(
                array('playlist_id'=>'PLSERIES12345','title'=>'Made for More','message_count'=>2,'newest_date'=>'2026-10-18','sample_thumbnail'=>'https://i.ytimg.com/series.jpg'),
                array('playlist_id'=>'PLHIDDEN123456','title'=>'Hidden Series','message_count'=>1,'newest_date'=>'2026-09-10','sample_thumbnail'=>''),
            );
        }
        if (str_contains($sql, 'LIMIT 12 OFFSET')) return $this->list_rows;
        if (str_contains($sql, 'SELECT p.playlist_id, p.title')) return array(
            array('playlist_id'=>'PLSERIES12345','title'=>'Made for More')
        );
        throw new RuntimeException('Unexpected list SQL '.$sql);
    }
    public function get_row($sql, $mode = null) {
        $this->sql[] = $sql;
        if (str_contains($sql, "'ccccccccccc'")) return null; // excluded video never displayed
        if (str_contains($sql, "'aaaaaaaaaaa'")) return $this->list_rows[0];
        if (str_contains($sql, "'bbbbbbbbbbb'")) return $this->list_rows[1];
        if (str_contains($sql, "'sermon' AS date_source")) return $this->list_rows[0];
        return null;
    }
    public function get_col($sql) {
        $this->sql[] = $sql;
        if (str_contains($sql, 'SELECT DISTINCT YEAR(')) {
            return array('2026','2014');
        }
        throw new RuntimeException('Unexpected year query '. $sql);
    }
}
$GLOBALS['wpdb'] = new PublicMessagesDB();

require dirname(__DIR__) . '/includes/public-message-archive.php';
function check($condition, $label) { if (!$condition) throw new RuntimeException($label); }

// Homepage text can be visually concise without mutating the YouTube title.
$featured_title = "Discovering God's Calling for Your Life – Made for More Series – Week 2 – 20 September 2026";
$short = surfside_tools_public_messages_featured_heading($featured_title);
check($short['heading'] === "Discovering God's Calling for Your Life"
    && $short['context'] === 'Made for More Series · Week 2',
    'Featured hero separates a long series/week/date title into a readable headline and context');
$comma_title = 'What We Have Learned – Finish Well – Week 5 – 23 August, 2026';
$short_comma = surfside_tools_public_messages_featured_heading($comma_title);
check($short_comma['heading'] === 'What We Have Learned'
    && $short_comma['context'] === 'Finish Well · Week 5',
    'Featured hero supports comma-separated YouTube sermon dates');
$plain_title = 'Words – Death and Life';
check(surfside_tools_public_messages_featured_heading($plain_title) ===
    array('heading'=>$plain_title,'context'=>''),
    'Meaningful dashes in a normal title are not truncated');
$no_week = 'Made for More – 20 September 2026';
check(surfside_tools_public_messages_featured_heading($no_week) ===
    array('heading'=>$no_week,'context'=>''),
    'A date alone is not sufficient to strip a YouTube title');

check($GLOBALS['wpdb']->sql === array(), 'Module inclusion does not query database');
check($GLOBALS['test_inserted_pages'] === array(), 'Module inclusion does not create pages');
check(isset($GLOBALS['test_shortcodes']['surfside_message_archive']), 'Shortcode registered');
check(isset($GLOBALS['test_actions']['admin_init']), 'Draft provisioning registered on admin_init');

$url = surfside_tools_public_messages_page_url(array('sm_series'=>'PLSERIES12345','sm_page'=>2));
check(str_contains($url, 'preview=true') && str_contains($url, 'page_id=22') &&
    str_contains($url, 'sm_series=PLSERIES12345') && str_contains($url, 'sm_page=2'),
    'Draft preview links retain original WP preview URL and filters');
$GLOBALS['test_draft'] = false;
check(str_starts_with(surfside_tools_public_messages_page_url(), 'https://example.org/messages/'),
    'Published archive uses canonical permalink');
$GLOBALS['test_draft'] = true;

$filters = surfside_tools_public_messages_filters(array(
    'sm_search'=>'Serve%_', 'sm_year'=>'2026', 'sm_series'=>'PLSERIES12345','sm_page'=>'3','sm_video'=>'aaaaaaaaaaa'
));
check($filters['q']==='Serve%_' && $filters['page']===3 && $filters['message']==='aaaaaaaaaaa',
    'Search, series, date and video filter values accepted');
$bad = surfside_tools_public_messages_filters(array(
    'sm_search'=>str_repeat('x',200),'sm_year'=>'2026<script>','sm_series'=>'invalid!','sm_video'=>'evil',
    'sm_view'=>'hacked', 'sm_page'=>'999999999999'
));
check(strlen($bad['q'])===100 && $bad['year']==='' && $bad['series']==='' &&
    $bad['message']==='' && $bad['browse']==='messages' && $bad['page']<=100000,
    'Public inputs sanitized and bounded');

$parts = surfside_tools_public_messages_sql();
check(str_contains($parts['join'], 'surfside_yt_video_review') &&
    str_contains($parts['date'], 'COALESCE') && str_contains($parts['visible'], "r.disposition <> 'exclude'"),
    'Staff exclusion and manual date are authoritative');
check(str_contains($parts['date'], "r.disposition = 'include'") &&
    str_contains($parts['date'], 'v.published_date') &&
    str_contains($parts['source'], "'youtube'"),
    'True YouTube publication date is fallback only for explicitly included undated videos');
check(!str_contains($parts['sermon_date'], 'published_date'),
    'Actual sermon date stays separate from YouTube publication fallback');
$GLOBALS['test_options'][SURFSIDE_YT_SCHEMA_OPTION] = 1;
check(!str_contains(surfside_tools_public_messages_sql()['date'], 'published_date'),
    'Old importer schemas never reuse playlist-added dates as publication fallbacks');
$GLOBALS['test_options'][SURFSIDE_YT_SCHEMA_OPTION] = 2;
$GLOBALS['test_options']['surfside_tools_youtube_review_schema']=0;
check(surfside_tools_public_messages_sql()['join']==='', 'No invalid JOIN before review table exists');
$GLOBALS['test_options']['surfside_tools_youtube_review_schema']=1;

$archive = surfside_tools_public_messages_list('PLMESSAGES1234', $filters);
check($archive['total']===2 && $archive['page']===1 && count($archive['rows'])===2,
    'Video list is bounded and paginated');
$sql_text = implode("\n", $GLOBALS['wpdb']->sql);
check(str_contains($sql_text, "r.disposition <> 'exclude'"), 'Public list omits excluded videos');
check(str_contains($sql_text, 'COALESCE(r.manual_date, v.sermon_date)'), 'Public sort and filters use manual dates');
check(str_contains($sql_text, 'Serve\\%\\_'), 'User search safely escapes SQL LIKE wildcards');
check(str_contains($sql_text, 'LIMIT 12 OFFSET 0'), 'Only one bounded page of metadata is loaded');
check(str_contains($sql_text, "playlist_id = 'PLMESSAGES1234'"), 'Only master-playlist sermons are listed');

$GLOBALS['wpdb']->sql = array();
$series = surfside_tools_public_messages_series('PLMESSAGES1234');
check(count($series)===1 && $series[0]['title']==='Made for More',
    'Hidden series removed from public series list');
check(str_contains(implode("\n", $GLOBALS['wpdb']->sql), "r.disposition <> 'exclude'"),
    'Series counts exclude hidden videos');
$detail = surfside_tools_public_messages_detail('PLMESSAGES1234', 'aaaaaaaaaaa');
check($detail['title']==='Made to Serve 18 October 26', 'Allowed detail fetched');
check(surfside_tools_public_messages_detail('PLMESSAGES1234', 'ccccccccccc')===null,
    'Excluded video cannot be opened directly');
check(surfside_tools_public_messages_detail('PLMESSAGES1234', 'invalid')===null,
    'Bad video IDs do not reach database');

$_GET = array();
$html = surfside_tools_public_message_archive_shortcode();
check(str_contains($html, 'Latest message') && str_contains($html,'Made to Serve'),
    'Latest dated message appears in featured hero');
check(substr_count($html, 'class="surfside-public-messages__video"')===1,
    'Featured video does not repeat in video grid');
check(!str_contains($html, 'Hidden Series'), 'Hidden series never in public HTML');
check(str_contains($html, 'Made for More'), 'Public series browsing provided');
check(str_contains($html, 'surfside-public-messages__grid--series-preview')
    && str_contains($html, 'class="surfside-public-messages__series"')
    && str_contains($html, 'https://i.ytimg.com/series.jpg'),
    'Homepage uses series artwork cards instead of text-only quick links');
check(!str_contains($html, 'surfside-public-messages__quick-series'),
    'Obsolete pill-only homepage series preview removed');
check(str_contains($html, '.surfside-public-messages__featured-art img{object-fit:contain}')
    && str_contains($html, 'height:auto;min-height:0;aspect-ratio:16/9'),
    'Featured thumbnail keeps its full 16:9 contents instead of cropping to a tall hero');

$GLOBALS['wpdb']->list_rows[0]['title'] = 'Made to Serve – Made for More Series – Week 2 – 18 October 26';
$condensed_home = surfside_tools_public_message_archive_shortcode();
check(str_contains($condensed_home, '<h2 title="Made to Serve – Made for More Series – Week 2 – 18 October 26">Made to Serve</h2>')
    && str_contains($condensed_home, 'Made for More Series · Week 2'),
    'Homepage renders concise hero heading with full original preserved in title attribute');
check(str_contains($condensed_home, 'aria-label="Watch Made to Serve – Made for More Series – Week 2 – 18 October 26"'),
    'Featured video link remains accessible by its complete YouTube title');
$GLOBALS['wpdb']->list_rows[0]['title'] = 'Made to Serve 18 October 26';
check(str_contains($html, 'Blessed &lt;script&gt;alert(1)&lt;/script&gt;'),
    'Video title is HTML-escaped');
check(str_contains($html, 'Published on YouTube: May 12, 2014'),
    'Staff-included undated videos show labeled YouTube publication date');
check(surfside_tools_public_messages_date_label(null) === 'Date not available',
    'Undated and unreviewed videos still show Date not available');

$year_sql = implode("\n", $GLOBALS['wpdb']->sql);
check(str_contains($html, 'value="2014"') && str_contains($html, 'value="2026"'),
    'Year dropdown includes actual public archive years');
check(!str_contains($html, 'value="1990"') && !str_contains($html, 'value="1995"'),
    'Year dropdown omits empty years rather than showing 1990 to current');
check(str_contains($year_sql, 'SELECT DISTINCT YEAR(') &&
    str_contains($year_sql, 'v.published_date') &&
    str_contains($year_sql, "r.disposition <> 'exclude'"),
    'Available-year query uses visible effective dates including staff publication fallback');

// WordPress treats ?year= as a native post-archive query variable.
// All form fields AND generated archive navigation must use unique names.
check(str_contains($html, 'name="sm_search"') && str_contains($html, 'name="sm_year"')
    && str_contains($html, 'name="sm_series"'),
    'Public filter form never submits reserved WordPress year/series variables');
check(!preg_match('/\bname="(?:q|year|series|s)"\b/', $html),
    'Search fields cannot overwrite WordPress routing');
check(str_contains($html, 'sm_video=aaaaaaaaaaa'),
    'Latest-message detail link uses namespaced URL key');
$archive_args = surfside_tools_public_messages_args($filters);
check($archive_args === array(
    'sm_search'=>'Serve%_', 'sm_year'=>'2026', 'sm_series'=>'PLSERIES12345', 'sm_page'=>3
), 'All active public filters and page position produce safe namespaced query args');
check(count(array_intersect(array_keys($archive_args), array(
    'year','monthnum','pagename','page_id','s','q','series','message','browse','archive_page'
))) === 0, 'Archive URL keys do not overlap WordPress core query vars');

$GLOBALS['wpdb']->sql = array();
$_GET=array('sm_search'=>'Serve');
$search_html=surfside_tools_public_message_archive_shortcode();
check(str_contains($search_html, 'messages found')
    && str_contains($search_html, 'value="Serve"'),
    'Title search stays inside the Messages shortcode rather than WordPress post search');
check(str_contains(implode("\n", $GLOBALS['wpdb']->sql), "v.title LIKE '%Serve%'"),
    'Search filter reaches the archive metadata query');

$GLOBALS['wpdb']->sql = array();
$_GET=array('sm_year'=>'2026');
$year_html=surfside_tools_public_message_archive_shortcode();
check(str_contains($year_html, 'messages found')
    && str_contains(implode("\n", $GLOBALS['wpdb']->sql), 'YEAR(COALESCE(')
    && str_contains(implode("\n", $GLOBALS['wpdb']->sql), "r.disposition = 'include'")
    && str_contains(implode("\n", $GLOBALS['wpdb']->sql), '= 2026'),
    'Year filter reaches metadata including staff-included YouTube publication fallback');

$GLOBALS['wpdb']->sql = array();
$_GET=array('sm_series'=>'PLSERIES12345');
$series_filter_html=surfside_tools_public_message_archive_shortcode();
check(str_contains($series_filter_html, 'messages found')
    && str_contains(implode("\n", $GLOBALS['wpdb']->sql), "selected_m.playlist_id = 'PLSERIES12345'"),
    'Series filter is handled by the local sermon metadata query');

// A message opened from filtered results should link back with those filters.
$_GET=array('sm_search'=>'Serve', 'sm_year'=>'2026',
    'sm_series'=>'PLSERIES12345', 'sm_page'=>'2', 'sm_video'=>'aaaaaaaaaaa');
$filtered_detail=surfside_tools_public_message_archive_shortcode();
check(str_contains($filtered_detail, 'sm_search=Serve')
    && str_contains($filtered_detail, 'sm_year=2026')
    && str_contains($filtered_detail, 'sm_series=PLSERIES12345')
    && str_contains($filtered_detail, 'sm_page=2'),
    'Filtered message detail preserves search, year, series and page when returning');

$_GET=array('sm_view'=>'series');
$series_html = surfside_tools_public_message_archive_shortcode();
check(str_contains($series_html, 'Sermon Series') && !str_contains($series_html, 'Hidden Series'),
    'Series tab renders with hidden series excluded');
check(str_contains($series_html, 'surfside-public-messages__grid--series')
    && str_contains($series_html, 'https://i.ytimg.com/series.jpg'),
    'Full Browse by Series layout and thumbnails are unchanged');
$_GET=array('sm_video'=>'aaaaaaaaaaa');
$detail_html=surfside_tools_public_message_archive_shortcode();
check(str_contains($detail_html,'www.youtube-nocookie.com/embed/aaaaaaaaaaa') &&
    str_contains($detail_html, 'Watch on YouTube'),
    'Single message renders official YouTube embed and playback fallback');
$_GET=array('sm_video'=>'ccccccccccc');
$hidden_html=surfside_tools_public_message_archive_shortcode();
check(str_contains($hidden_html, 'Message unavailable') &&
    !str_contains($hidden_html, 'youtube-nocookie.com/embed'),
    'Excluded video is not embeddable through direct URL');
$_GET=array('sm_series'=>'PLHIDDEN123456');
$hidden_series=surfside_tools_public_message_archive_shortcode();
check(str_contains($hidden_series,'No messages match your search'), 'Hidden series cannot be browsed via URL');

$GLOBALS['test_is_admin']=false;
surfside_tools_public_messages_provision_page();
check(count($GLOBALS['test_inserted_pages'])===0,'No draft page creation on public requests');
$GLOBALS['test_is_admin']=true;
surfside_tools_public_messages_provision_page();
check(count($GLOBALS['test_inserted_pages'])===1 &&
    $GLOBALS['test_inserted_pages'][0]['post_status']==='draft' &&
    $GLOBALS['test_inserted_pages'][0]['post_content']==='[surfside_message_archive]',
    'First admin visit creates a DRAFT shortcode page, not a live public page');
surfside_tools_public_messages_provision_page();
check(count($GLOBALS['test_inserted_pages'])===1, 'Schema provisioning is one-time');
unset($GLOBALS['test_options'][SURFSIDE_PUBLIC_MESSAGES_PAGE_OPTION]);
$GLOBALS['test_existing_page']=new WP_Post(43, 'Existing custom content');
surfside_tools_public_messages_provision_page();
check(count($GLOBALS['test_inserted_pages'])===1,
    'Existing user-authored /messages/ pages are not overwritten');

check($GLOBALS['test_api_requests']===0,
    'Public browsing does not call YouTube or download media');
echo "Public YouTube Messages archive and draft provisioning checks passed.\n";
