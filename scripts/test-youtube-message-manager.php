<?php
/**
 * Regression checks for the staff-only YouTube Message Archive manager.
 * Run: php scripts/test-youtube-message-manager.php
 */
define('ABSPATH', __DIR__ . '/');
define('SURFSIDE_TOOLS_VERSION', 'test');
define('ARRAY_A', 'ARRAY_A');
const SURFSIDE_YT_SCHEMA_OPTION = 'surfside_tools_youtube_schema';
const SURFSIDE_YT_SCHEMA_VERSION = 1;

$GLOBALS['test_options'] = array(
    SURFSIDE_YT_SCHEMA_OPTION => 1,
    'surfside_tools_youtube_review_schema' => 0,
);
$GLOBALS['test_logged_in'] = false;
$GLOBALS['test_staff'] = false;
$GLOBALS['test_master'] = 'PLMESSAGES1234';
$GLOBALS['test_video_id'] = 'abcdEF12_34';
$GLOBALS['test_playlist_id'] = 'PLSERIES123456';

class WP_Error {
    public function __construct(private $code, private $message) {}
    public function get_error_message() { return $this->message; }
}
function is_wp_error($value) { return $value instanceof WP_Error; }
function add_action($hook, $callback, $priority = 10) { $GLOBALS['test_hooks'][$hook] = $callback; }
function add_shortcode(...$args) {}
function is_user_logged_in() { return $GLOBALS['test_logged_in']; }
function current_user_can($cap) { return $cap === 'upload_files' && $GLOBALS['test_staff']; }
function surfside_tools_prevent_cache() {}
function surfside_tools_staff_enqueue_styles() {}
function surfside_tools_staff_login_box($message = '') { return 'LOGIN REQUIRED ' . $message; }
function surfside_tools_staff_page_url($path = '') { return 'https://example.org/dashboard/' . ($path ? trim($path,'/') . '/' : ''); }
function surfside_tools_youtube_state() { return array('master_id' => $GLOBALS['test_master']); }
function surfside_tools_youtube_valid_playlist_id($value) { return is_string($value) && preg_match('/^[a-zA-Z0-9_-]{10,100}$/', $value); }
function surfside_tools_youtube_tables() {
    return array(
        'videos' => 'wp_surfside_yt_videos',
        'members' => 'wp_surfside_yt_members',
        'playlists' => 'wp_surfside_yt_playlists',
    );
}
function get_option($name, $default = false) {
    return array_key_exists($name, $GLOBALS['test_options']) ? $GLOBALS['test_options'][$name] : $default;
}
function update_option($name, $value, $autoload = false) {
    $GLOBALS['test_options'][$name] = $value;
    return true;
}
function wp_register_style(...$args) {}
function wp_enqueue_style(...$args) {}
function wp_add_inline_style(...$args) {}
function esc_html($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }
function esc_attr($v) { return esc_html($v); }
function esc_url($v) { return esc_html($v); }
function add_query_arg($args, $url) { return $url . '?' . http_build_query($args, '', '&', PHP_QUERY_RFC3986); }
function is_admin() { return false; }
function sanitize_key($v) { return preg_replace('/[^a-z0-9_-]/', '', strtolower((string)$v)); }
function sanitize_text_field($v) { return trim(strip_tags((string)$v)); }
function wp_unslash($v) { return stripslashes($v); }
function number_format_i18n($n) { return number_format($n); }
function absint($n) { return abs((int) $n); }
function selected($a, $b) { echo $a === $b ? 'selected="selected"' : ''; }
function admin_url($path = '') { return 'https://example.org/wp-admin/' . $path; }
function wp_nonce_field(...$args) {}
function current_time($type, $gmt = false) { return '2026-10-15 20:00:00'; }
function dbDelta($sql) { $GLOBALS['test_db']->created++; return array(); }
function wp_die($message, $title = '', $args = array()) { throw new RuntimeException('WP_DIE: ' . $message); }
function check_admin_referer($name, $field) { if (!($GLOBALS['test_nonce_valid'] ?? true) || $name !== 'surfside_youtube_review' || $field !== 'surfside_youtube_review_nonce') throw new RuntimeException('Bad nonce'); }
function wp_safe_redirect($url, $status = 302, $by = 'WordPress') { $GLOBALS['test_redirect'] = array('url'=>$url, 'status'=>$status, 'by'=>$by); if ($GLOBALS['test_redirect_blocked'] ?? false) return false; throw new RuntimeException('Redirect sent'); }
function is_page($slug) { return $slug === 'site-management' && ($GLOBALS['test_site_management_page'] ?? true); }

class YouTubeReviewDB {
    public $prefix = 'wp_';
    public $reads = 0;
    public $created = 0;
    public $overrides = array();
    public $videos = array();
    public function __construct() {
        $this->videos[$GLOBALS['test_video_id']] = array(
            'video_id' => $GLOBALS['test_video_id'],
            'title' => 'Made <script>unsafe</script> to Serve',
            'thumbnail_url' => 'https://i.ytimg.com/vi/abcdEF12_34/default.jpg',
            'uploaded_at' => '2026-10-20T00:00:00Z',
            'sermon_date' => null,
        );
    }
    public function get_charset_collate() { return 'DEFAULT CHARACTER SET utf8mb4'; }
    public function esc_like($s) { return addcslashes($s, '_%\\'); }
    public function prepare($query, ...$values) {
        if (count($values) === 1 && is_array($values[0])) $values = $values[0];
        $i = 0;
        return preg_replace_callback('/%[ds]/', function ($match) use (&$i, $values) {
            $v = $values[$i++];
            return $match[0] === '%d' ? (string)(int)$v
                : "'" . str_replace("'", "''", (string)$v) . "'";
        }, $query);
    }
    public function get_var($sql) {
        $this->reads++;
        if (strpos($sql, 'SHOW TABLES LIKE') !== false) return 'wp_surfside_yt_video_review';
        if (strpos($sql, 'category = \'series\'') !== false && strpos($sql, 'SELECT playlist_id') !== false) {
            return strpos($sql, "'" . $GLOBALS['test_playlist_id'] . "'") !== false ? $GLOBALS['test_playlist_id'] : null;
        }
        if (strpos($sql, 'SELECT COUNT(*)') !== false) {
            return !empty($GLOBALS['test_queue_mode']) ? count($GLOBALS['test_queue_items']) : 1;
        }
        throw new RuntimeException('Unexpected scalar SQL: ' . $sql);
    }
    public function get_row($sql, $mode = null) {
        $this->reads++;
        if (strpos($sql, 'AS total') !== false) {
            return array('total' => 1, 'needs_review' => count($this->overrides) ? 0 : 1,
                'excluded' => 0, 'undated' => count($this->overrides) ? 0 : 1);
        }
        if (strpos($sql, "'" . $GLOBALS['test_video_id'] . "'") === false) return null;
        $base = $this->videos[$GLOBALS['test_video_id']];
        $override = $this->overrides[$GLOBALS['test_video_id']] ?? array();
        return array_merge($base, array(
            'manual_date' => $override['manual_date'] ?? null,
            'disposition' => $override['disposition'] ?? null,
            'effective_date' => $override['manual_date'] ?? $base['sermon_date'],
        ));
    }
    public function get_results($sql, $mode = null) {
        $this->reads++;
        if (!empty($GLOBALS['test_queue_mode']) && strpos($sql, ' LIMIT ') !== false) {
            if (!preg_match('/LIMIT (\d+) OFFSET (\d+)/', $sql, $m)) {
                throw new RuntimeException('Expected a bounded review queue query');
            }
            return array_slice($GLOBALS['test_queue_items'], (int) $m[2], (int) $m[1]);
        }
        if (strpos($sql, 'category = \'series\'') !== false && strpos($sql, 'GROUP BY') !== false) {
            return array(array('playlist_id'=>$GLOBALS['test_playlist_id'], 'title'=>'Made for More', 'video_count'=>1));
        }
        return array($this->get_row("WHERE video_id = '" . $GLOBALS['test_video_id'] . "'"));
    }
    public function get_col($sql) { $this->reads++; return array('Made for More'); }
    public function replace($table, $record) {
        if ($table !== 'wp_surfside_yt_video_review') throw new RuntimeException('Unexpected write to imported metadata!');
        $this->overrides[$record['video_id']] = $record;
        return 1;
    }
}
$GLOBALS['test_db'] = new YouTubeReviewDB();
$GLOBALS['wpdb'] = $GLOBALS['test_db'];
require dirname(__DIR__) . '/includes/youtube-message-manager.php';

function check($passed, $message) {
    if (!$passed) throw new RuntimeException($message);
}
check($GLOBALS['test_db']->reads === 0, 'Module inclusion performs no database reads');
check($GLOBALS['test_db']->created === 0, 'Module inclusion performs no table migrations');

check(surfside_tools_youtube_review_validate_date('2026-10-18') === '2026-10-18', 'Date accepts ISO date');
check(surfside_tools_youtube_review_validate_date('') === '', 'Blank date clears override');
check(surfside_tools_youtube_review_validate_date('2026-02-30') === false, 'Impossible date rejected');
check(surfside_tools_youtube_review_validate_date('10/18/2026') === false, 'Ambiguous input rejected');
check(surfside_tools_youtube_review_disposition(array('effective_date'=>null)) === 'review', 'Undated messages default to Needs Review');
check(surfside_tools_youtube_review_disposition(array('effective_date'=>null, 'disposition'=>'include')) === 'include', 'Staff inclusion overrides missing-date review');
check(surfside_tools_youtube_review_disposition(array('effective_date'=>null, 'disposition'=>'exclude')) === 'exclude', 'Explicit exclusion is respected');

$_GET = array('view'=>'message-archive');
$out = surfside_tools_staff_youtube_message_manager_shortcode();
check(strpos($out, 'LOGIN REQUIRED') !== false, 'Unauthenticated users cannot browse archive');
check($GLOBALS['test_db']->reads === 0, 'No unauthenticated DB reads');
$GLOBALS['test_logged_in'] = true;
$out = surfside_tools_staff_youtube_message_manager_shortcode();
check(strpos($out, 'do not have permission') !== false, 'Nonstaff users cannot browse');
check($GLOBALS['test_db']->reads === 0, 'No nonstaff DB reads');

$GLOBALS['test_staff'] = true;
$_GET = array('archive_status'=>'review', 'archive_q'=>'Serve%_');
$filters = surfside_tools_youtube_review_filters();
check($filters['status'] === 'review', 'Needs-review filter accepted');
check($filters['search'] === 'Serve%_', 'Search value retains literal wildcards for escaping');
$_GET = array('view'=>'message-archive', 'video'=>$GLOBALS['test_video_id']);
$out = surfside_tools_staff_youtube_message_manager_shortcode();
check(strpos($out, 'Made &lt;script&gt;unsafe&lt;/script&gt; to Serve') !== false, 'Video title HTML escaped');
check(strpos($out, 'www.youtube-nocookie.com/embed/') !== false, 'Official embedded YouTube player provided');
check(strpos($out, 'Not found in video title') !== false, 'Missing sermon date displayed honestly');
check(strpos($out, 'Needs Review') !== false, 'Missing-date video is flagged for review');

$base = $GLOBALS['test_db']->videos[$GLOBALS['test_video_id']];
$save = surfside_tools_youtube_review_save_video($GLOBALS['test_master'], $GLOBALS['test_video_id'], '2026-10-18', 'include');
check($save === true, 'Manual sermon date saved');
check($GLOBALS['test_db']->created === 1, 'Review table created only when staff save');
check(count($GLOBALS['test_db']->overrides) === 1, 'One override saved keyed by video ID');
check($GLOBALS['test_db']->videos[$GLOBALS['test_video_id']] === $base, 'Imported YouTube video metadata was not modified');
$edited = surfside_tools_youtube_review_video($GLOBALS['test_master'], $GLOBALS['test_video_id']);
check($edited['manual_date'] === '2026-10-18' && $edited['effective_date'] === '2026-10-18', 'Corrected date wins over imported date');
check(surfside_tools_youtube_review_disposition($edited) === 'include', 'Included reviewed video no longer needs review');

check(surfside_tools_youtube_review_save_video($GLOBALS['test_master'], $GLOBALS['test_video_id'], '', 'exclude') === true,
    'Staff can exclude an imported video');
check(count($GLOBALS['test_db']->overrides) === 1, 'Re-review updates same row, does not duplicate');
check(surfside_tools_youtube_review_disposition(surfside_tools_youtube_review_video($GLOBALS['test_master'], $GLOBALS['test_video_id'])) === 'exclude',
    'Explicit exclusion persists');
check(is_wp_error(surfside_tools_youtube_review_save_video($GLOBALS['test_master'], $GLOBALS['test_video_id'], '2026-02-30', 'include')),
    'Invalid correction cannot be saved');
check(is_wp_error(surfside_tools_youtube_review_save_video($GLOBALS['test_master'], 'notvalid', '', 'exclude')),
    'Unknown video cannot be saved');

check(surfside_tools_youtube_review_save_series($GLOBALS['test_playlist_id'], true) === true,
    'Staff can exclude discovered sermon series');
check(!empty(surfside_tools_youtube_review_excluded_series()[$GLOBALS['test_playlist_id']]),
    'Series exclusion stored separately from imported playlist data');
check(surfside_tools_youtube_review_save_series($GLOBALS['test_playlist_id'], false) === true,
    'Staff can restore a hidden series');
check(empty(surfside_tools_youtube_review_excluded_series()[$GLOBALS['test_playlist_id']]),
    'Restored series no longer excluded');
check(is_wp_error(surfside_tools_youtube_review_save_series('PLNOTFOUND1234', true)),
    'Unknown playlist cannot be excluded');

// Regression: both review forms must POST to the staff dashboard, not WP Admin.
$source = file_get_contents(dirname(__DIR__) . '/includes/youtube-message-manager.php');
check(strpos($source, "admin_url('admin-post.php')") === false, 'Review forms must not POST through wp-admin/admin-post.php');
check(substr_count($source, 'action="<?php echo esc_url(surfside_tools_youtube_manager_url()); ?>"') === 2,
    'Video and series forms both target the front-end manager');
check(($GLOBALS['test_hooks']['template_redirect'] ?? null) === 'surfside_tools_youtube_review_frontend_post',
    'Front-end POST handler is registered before template output');
check(($GLOBALS['test_hooks']['admin_post_surfside_youtube_review'] ?? null) === 'surfside_tools_youtube_review_handle_post',
    'Legacy admin-post hook is retained for compatibility');

$_SERVER['REQUEST_METHOD'] = 'GET';
$_GET = array('view'=>'message-archive');
$_POST = array('action'=>'surfside_youtube_review','review_mode'=>'video',
    'video_id'=>$GLOBALS['test_video_id'],'disposition'=>'include','manual_date'=>'2026-10-18');
$reads = $GLOBALS['test_db']->reads;
surfside_tools_youtube_review_frontend_post();
check($GLOBALS['test_db']->reads === $reads, 'GET requests never perform review writes');

$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST['action'] = 'unrelated_action';
surfside_tools_youtube_review_frontend_post();
check($GLOBALS['test_db']->reads === $reads, 'Unrelated forms are ignored');
$_POST['action'] = 'surfside_youtube_review';
$_GET = array('view'=>'other');
surfside_tools_youtube_review_frontend_post();
check($GLOBALS['test_db']->reads === $reads, 'Unrelated dashboard views are ignored');
$_GET = array('view'=>'message-archive');
$GLOBALS['test_site_management_page'] = false;
surfside_tools_youtube_review_frontend_post();
check($GLOBALS['test_db']->reads === $reads, 'Other page requests are ignored');
$GLOBALS['test_site_management_page'] = true;

$_POST['disposition'] = 'exclude';
$_POST['manual_date'] = '';
try {
    surfside_tools_youtube_review_frontend_post();
    throw new RuntimeException('Video review returned without redirect');
} catch (RuntimeException $e) {
    check($e->getMessage() === 'Redirect sent', 'Video review must redirect');
}
check($GLOBALS['test_db']->overrides[$GLOBALS['test_video_id']]['disposition'] === 'exclude',
    'Dashboard video exclude action persisted');
check($GLOBALS['test_redirect']['status'] === 303, 'Review actions use HTTP 303 Post-Redirect-Get');
check(strpos($GLOBALS['test_redirect']['url'], 'view=message-archive') !== false
    && strpos($GLOBALS['test_redirect']['url'], 'review_notice=saved') !== false
    && strpos($GLOBALS['test_redirect']['url'], 'video=abcdEF12_34') !== false,
    'Video review redirects back to the message detail and shows saved result');

$_POST = array('action'=>'surfside_youtube_review','review_mode'=>'series',
    'playlist_id'=>$GLOBALS['test_playlist_id'], 'hide_series'=>'1');
try {
    surfside_tools_youtube_review_frontend_post();
    throw new RuntimeException('Series review returned without redirect');
} catch (RuntimeException $e) {
    check($e->getMessage() === 'Redirect sent', 'Series review must redirect');
}
check(!empty(surfside_tools_youtube_review_excluded_series()[$GLOBALS['test_playlist_id']]),
    'Dashboard Hide Series action persisted');
check(strpos($GLOBALS['test_redirect']['url'], 'tab=series') !== false,
    'Series review redirects to series tab');
$_POST['hide_series'] = '0';
try {
    surfside_tools_youtube_review_frontend_post();
    throw new RuntimeException('Series restore returned without redirect');
} catch (RuntimeException $e) {
    check($e->getMessage() === 'Redirect sent', 'Series restore must redirect');
}
check(empty(surfside_tools_youtube_review_excluded_series()[$GLOBALS['test_playlist_id']]),
    'Dashboard Restore Series action persisted');

$GLOBALS['test_staff'] = false;
$prior_reads = $GLOBALS['test_db']->reads;
try {
    surfside_tools_youtube_review_frontend_post();
    throw new RuntimeException('Non-staff review unexpectedly processed');
} catch (RuntimeException $e) {
    check(strpos($e->getMessage(), 'do not have permission') !== false,
        'Non-staff review is rejected');
}
check($GLOBALS['test_db']->reads === $prior_reads, 'Unauthorized request performs no DB reads');
$GLOBALS['test_staff'] = true;

$GLOBALS['test_nonce_valid'] = false;
try {
    surfside_tools_youtube_review_frontend_post();
    throw new RuntimeException('Bad nonce unexpectedly accepted');
} catch (RuntimeException $e) {
    check($e->getMessage() === 'Bad nonce', 'Review POST still requires CSRF nonce');
}
$GLOBALS['test_nonce_valid'] = true;

$GLOBALS['test_redirect_blocked'] = true;
try {
    surfside_tools_youtube_review_frontend_post();
    throw new RuntimeException('Failed redirect silently exited');
} catch (RuntimeException $e) {
    check(strpos($e->getMessage(), 'WordPress could not redirect') !== false,
        'Blocked redirect shows recoverable error instead of blank screen');
}

// Review navigation: preserve all list filters and pagination.
$context = surfside_tools_youtube_review_filters(array(
    'archive_q' => 'Serve & share',
    'archive_status' => 'review',
    'archive_year' => '2026',
    'archive_series' => $GLOBALS['test_playlist_id'],
    'archive_page' => '2',
    'redirect_to' => 'https://attacker.invalid/', // never trusted or echoed
));
$args = surfside_tools_youtube_review_filter_args($context);
check($args === array(
    'archive_q'=>'Serve & share',
    'archive_status'=>'review',
    'archive_year'=>'2026',
    'archive_series'=>$GLOBALS['test_playlist_id'],
    'archive_page'=>2,
), 'List state round-trips through the review form, without arbitrary redirect URLs');

$bad = surfside_tools_youtube_review_filters(array(
    'archive_status'=>'malicious',
    'archive_year'=>'not-year',
    'archive_series'=>'bad!',
    'archive_page'=>'-20',
));
check($bad['status'] === 'all' && $bad['year'] === '' && $bad['series'] === '' && $bad['page'] >= 1,
    'Malformed filter values cannot become redirect parameters');

$_GET = array(
    'view'=>'message-archive',
    'archive_q'=>'Serve & share',
    'archive_status'=>'review',
    'archive_year'=>'2026',
    'archive_series'=>$GLOBALS['test_playlist_id'],
    'archive_page'=>'2',
    'archive_position'=>'21',
    'video'=>$GLOBALS['test_video_id'],
);
$GLOBALS['test_redirect_blocked'] = false;
$detail = surfside_tools_staff_youtube_message_manager_shortcode();
check(strpos($detail, 'Back to filtered videos') !== false, 'Detail shows a filter-preserving Back link');
check(strpos($detail, 'archive_status=review') !== false && strpos($detail, 'archive_page=2') !== false,
    'Detail Back link retains status and page');
check(strpos($detail, 'name="archive_status" value="review"') !== false,
    'Review form preserves the Needs Review filter');
check(strpos($detail, 'name="archive_page" value="2"') !== false,
    'Review form preserves result pagination');
check(strpos($detail, 'name="archive_position" value="21"') !== false,
    'Review form preserves the current queue position');
check(strpos($detail, 'Save &amp; Next Review') !== false,
    'Queue action available when arriving from Needs Review list');

// Simulated saved queue, after original entry is excluded: next entry slides to index 0.
$GLOBALS['test_queue_mode'] = true;
$GLOBALS['test_queue_items'] = array(
    array('video_id'=>'abcdEF12_35'),
    array('video_id'=>'abcdEF12_36'),
);
$queue_filters = surfside_tools_youtube_review_filters(array('archive_status'=>'review'));
$next = surfside_tools_youtube_review_next_in_queue($GLOBALS['test_master'], $queue_filters, 0, $GLOBALS['test_video_id']);
check($next['video'] === 'abcdEF12_35' && $next['position'] === 0 && $next['page'] === 1,
    'Excluding an item does not skip the next Needs Review message');

// If current item remains Needs Review, skip over that current entry.
$GLOBALS['test_queue_items'] = array(
    array('video_id'=>$GLOBALS['test_video_id']),
    array('video_id'=>'abcdEF12_35'),
);
$next = surfside_tools_youtube_review_next_in_queue($GLOBALS['test_master'], $queue_filters, 0, $GLOBALS['test_video_id']);
check($next['video'] === 'abcdEF12_35' && $next['position'] === 1,
    'Save & Next moves past unchanged Needs Review item');

// Page boundary: next review moves from index 19 to index 20 on page two.
$GLOBALS['test_queue_items'] = array();
for ($i = 0; $i < 21; $i++) {
    $GLOBALS['test_queue_items'][] = array('video_id'=>sprintf('vid%08d', $i));
}
$next = surfside_tools_youtube_review_next_in_queue($GLOBALS['test_master'], $queue_filters, 19, 'vid00000019');
check($next['video'] === 'vid00000020' && $next['position'] === 20 && $next['page'] === 2,
    'Queue navigation crosses 20-item pagination boundary');
check(surfside_tools_youtube_review_next_in_queue($GLOBALS['test_master'], $queue_filters, 20, 'vid00000020') === null,
    'Queue navigation ends cleanly at final entry');

// End-to-end save-and-next: validated POST context survives redirect.
$GLOBALS['test_queue_items'] = array(array('video_id'=>'abcdEF12_35'));
$_SERVER['REQUEST_METHOD'] = 'POST';
$_GET = array('view'=>'message-archive');
$_POST = array(
    'action'=>'surfside_youtube_review',
    'review_mode'=>'video',
    'video_id'=>$GLOBALS['test_video_id'],
    'disposition'=>'exclude',
    'manual_date'=>'',
    'archive_status'=>'review',
    'archive_q'=>'Serve & share',
    'archive_year'=>'2026',
    'archive_series'=>$GLOBALS['test_playlist_id'],
    'archive_page'=>'1',
    'archive_position'=>'0',
    'review_after'=>'next',
);
try {
    surfside_tools_youtube_review_frontend_post();
    throw new RuntimeException('Save & Next did not redirect');
} catch (RuntimeException $e) {
    check($e->getMessage() === 'Redirect sent', 'Save & Next redirects after success');
}
$url = $GLOBALS['test_redirect']['url'];
check(strpos($url, 'archive_status=review') !== false
    && strpos($url, 'archive_q=Serve%20%26%20share') !== false
    && strpos($url, 'archive_year=2026') !== false
    && strpos($url, 'archive_series=PLSERIES123456') !== false,
    'Save & Next retains search, status, year and series');
check(strpos($url, 'video=abcdEF12_35') !== false
    && strpos($url, 'archive_position=0') !== false,
    'Save & Next opens the next eligible video in the same filtered queue');
check($GLOBALS['test_redirect']['status'] === 303, 'Save & Next keeps POST-redirect-GET');

$_POST['review_after'] = 'stay';
$_POST['archive_page'] = '2';
$_POST['archive_position'] = '21';
try {
    surfside_tools_youtube_review_frontend_post();
    throw new RuntimeException('Save Only did not redirect');
} catch (RuntimeException $e) {
    check($e->getMessage() === 'Redirect sent', 'Save Only redirects after success');
}
$url = $GLOBALS['test_redirect']['url'];
check(strpos($url, 'video=abcdEF12_34') !== false
    && strpos($url, 'archive_status=review') !== false
    && strpos($url, 'archive_page=2') !== false
    && strpos($url, 'archive_position=21') !== false,
    'Save Only retains the full list context and current detail');

$GLOBALS['test_queue_items'] = array();
$_POST['review_after'] = 'next';
$_POST['archive_page'] = '1';
$_POST['archive_position'] = '0';
try {
    surfside_tools_youtube_review_frontend_post();
    throw new RuntimeException('Empty queue did not redirect');
} catch (RuntimeException $e) {
    check($e->getMessage() === 'Redirect sent', 'Empty queue returns to filtered library');
}
$url = $GLOBALS['test_redirect']['url'];
check(strpos($url, 'review_notice=end_of_queue') !== false
    && strpos($url, 'video=') === false
    && strpos($url, 'archive_status=review') !== false,
    'End of queue shows a non-error success notice and keeps filters');

echo "YouTube Message Archive manager review, queue and front-end form checks passed.\n";
