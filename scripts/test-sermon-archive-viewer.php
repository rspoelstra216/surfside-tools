<?php
/**
 * Regression checks for read-only dashboard sermon archive browsing.
 * Run: php scripts/test-sermon-archive-viewer.php
 */
define('ABSPATH', __DIR__ . '/');
define('SURFSIDE_TOOLS_VERSION', 'test');
const SURFSIDE_TOOLS_SERMON_ARCHIVE_SCHEMA_OPTION = 'surfside_tools_sermon_archive_schema';
const SURFSIDE_TOOLS_SERMON_ARCHIVE_SCHEMA_VERSION = 1;
define('ARRAY_A', 'ARRAY_A');

$GLOBALS['viewer_logged_in'] = false;
$GLOBALS['viewer_can_upload'] = false;
$GLOBALS['viewer_archive_ready'] = 1;

function surfside_tools_sermon_archive_table() { return 'wp_surfside_sermon_archive'; }
function surfside_tools_staff_page_url($path = '') { return 'https://example.org/dashboard/'; }
function surfside_tools_staff_login_box($message = '') { return 'STAFF LOGIN ' . $message; }
function surfside_tools_prevent_cache() {}
function surfside_tools_staff_enqueue_styles() {}
function wp_register_style(...$args) {}
function wp_enqueue_style(...$args) {}
function wp_add_inline_style(...$args) {}
function is_user_logged_in() { return $GLOBALS['viewer_logged_in']; }
function current_user_can($cap) { return $cap === 'upload_files' && $GLOBALS['viewer_can_upload']; }
function get_option($key, $fallback = false) { return $GLOBALS['viewer_archive_ready']; }
function absint($v) { return abs((int) $v); }
function sanitize_text_field($v) { return trim(strip_tags((string) $v)); }
function wp_unslash($v) { return stripslashes($v); }
function esc_html($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }
function esc_attr($v) { return esc_html($v); }
function esc_url($v) { return esc_html($v); }
function wp_kses_post($v) { return strip_tags($v, '<p><strong><em><h2><h3><a>'); }
function add_query_arg($args, $value, $url = null) {
    // WordPress accepts both (array $args, $url) and ($key, $value, $url).
    if (is_array($args)) return $value . '?' . http_build_query($args, '', '&', PHP_QUERY_RFC3986);
    return $url . '?' . http_build_query(array($args => $value), '', '&', PHP_QUERY_RFC3986);
}
function surfside_tools_notes_without_main_heading($notes, $heading) { return $notes; }
function surfside_tools_render_sermon_notes_html($text) { return '<p>' . esc_html($text) . '</p>'; }

class SermonViewerTestDB {
    public $reads = 0;
    public $last_sql = '';
    public $rows = array();
    public $count = 0;
    public function esc_like($value) { return addcslashes($value, '_%\\'); }
    public function prepare($query, $args = array()) {
        if (!is_array($args)) $args = array_slice(func_get_args(), 1);
        $index = 0;
        $result = preg_replace_callback('/%[ds]/', function ($match) use ($args, &$index) {
            $value = $args[$index++];
            return $match[0] === '%d' ? (string) (int) $value
                : "'" . str_replace("'", "''", (string) $value) . "'";
        }, $query);
        return $result;
    }
    public function get_var($sql) {
        $this->reads++;
        $this->last_sql = $sql;
        return $this->count;
    }
    public function get_results($sql, $output = null) {
        $this->reads++;
        $this->last_sql = $sql;
        return $this->rows;
    }
    public function get_row($sql, $output = null) {
        $this->reads++;
        $this->last_sql = $sql;
        return array(
            'id' => 8,
            'title' => 'Made to <script>alert(1)</script> Serve',
            'series' => 'Made for More',
            'message_date' => 'OCTOBER 18, 2026',
            'main_heading' => '3 Keys',
            'notes' => 'Serve in Love',
            'prayer' => 'Help us serve.',
        );
    }
}
$GLOBALS['wpdb'] = new SermonViewerTestDB();
$GLOBALS['wpdb']->count = 23;
$GLOBALS['wpdb']->rows = array(
    array('id' => 8, 'title' => 'Made to Serve', 'series' => 'Made for More', 'message_date' => 'OCTOBER 18, 2026'),
);

require dirname(__DIR__) . '/includes/sermon-archive-viewer.php';

function check($ok, $label) {
    if (!$ok) throw new RuntimeException($label);
}
$_GET = array('archive_id' => '8');
$out = surfside_tools_staff_sermon_archive_view();
check(strpos($out, 'STAFF LOGIN') !== false, 'Anonymous users must log in');
check($GLOBALS['wpdb']->reads === 0, 'Anonymous access must not query archive');
$GLOBALS['viewer_logged_in'] = true;
$out = surfside_tools_staff_sermon_archive_view();
check(strpos($out, 'do not have permission') !== false, 'Users without staff rights denied');
check($GLOBALS['wpdb']->reads === 0, 'Unauthorized access must not query archive');

$GLOBALS['viewer_can_upload'] = true;
$GLOBALS['viewer_archive_ready'] = 0;
$out = surfside_tools_staff_sermon_archive_view();
check(strpos($out, 'No sermon archive has been initialized') !== false &&
    strpos($out, 'Archive not ready yet') !== false,
    'Uninitialized archive shows clear empty state');
check($GLOBALS['wpdb']->reads === 0, 'Viewer must not initialize archive');

$GLOBALS['viewer_archive_ready'] = 1;
$_GET = array('archive_q' => 'Serve%_');
$out = surfside_tools_staff_sermon_archive_view();
check(strpos($out, 'Made to Serve') !== false, 'Archived row can be opened from list');
check(strpos($out, 'Saved weekly sermon notes') !== false &&
    strpos($out, 'Video Archive Manager') !== false &&
    strpos($out, 'Weekly Update') !== false,
    'Archive explains written notes and links to other publishing tools');
check(strpos($out, 'Showing results for') !== false &&
    strpos($out, 'Saved Messages') !== false,
    'Archive displays search term and read-only saved messages list');
check(strpos($GLOBALS['wpdb']->last_sql, 'Serve\\%\\_') !== false, 'LIKE search wildcards are escaped');
check($GLOBALS['wpdb']->reads === 2, 'A list view fetches count and metadata only');
check(strpos($out, 'Next') !== false, 'List supports pagination');

$_GET = array('archive_id' => '8');
$out = surfside_tools_staff_sermon_archive_view();
check(strpos($out, 'Serve in Love') !== false, 'Detail displays archived notes');
check(strpos($out, '<script>') === false, 'Detail does not inject untrusted markup');
check(strpos($out, '&lt;script&gt;') !== false, 'Sermon title is escaped');
check(strpos($out, 'Closing Prayer') !== false, 'Closing prayer displayed');
check(strpos($out, 'All Archived Notes') !== false &&
    strpos($out, 'Archived notes · Read only') !== false,
    'Detail is explicitly read-only and returns to archive');
check(strpos($out, '<form method="post"') === false,
    'Read-only archive never adds a write form');

echo "Read-only sermon archive dashboard regression checks passed.\n";
