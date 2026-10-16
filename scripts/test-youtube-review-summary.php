<?php
/**
 * Test YouTube Integration summary counts reflect staff review decisions.
 * Run: php scripts/test-youtube-review-summary.php
 */
define('ABSPATH', __DIR__ . '/');
define('SURFSIDE_YT_SCHEMA_OPTION', 'surfside_tools_youtube_schema');
define('SURFSIDE_YT_SCHEMA_VERSION', 1);
$GLOBALS['test_schema_ready'] = true;
$GLOBALS['test_review_available'] = true;
$GLOBALS['test_pending_reviews'] = 3;
$GLOBALS['test_calls'] = array();
$GLOBALS['test_options'] = array();

function add_action(...$args) {}
function is_user_logged_in() { return true; }
function current_user_can($cap) { return $cap === 'manage_options'; }
function surfside_tools_youtube_config() { return array('channel_ref'=>'@addpastor','master_playlist_id'=>''); }
function surfside_tools_youtube_api_key() { return 'saved_server_key'; }
function surfside_tools_youtube_state() { return array('master_id'=>'PLMESSAGES1234','status'=>'complete','last_success'=>1792200000,'message'=>'Metadata synchronized.'); }
function get_option($name, $default = false) { return $name === SURFSIDE_YT_SCHEMA_OPTION ? ($GLOBALS['test_schema_ready'] ? 1 : 0) : $default; }
function get_current_user_id() { return 3; }
function get_transient($key) { return false; }
function wp_nonce_field(...$args) {}
function esc_html($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function esc_attr($value) { return esc_html($value); }
function esc_url($value) { return esc_html($value); }
function number_format_i18n($value) { return number_format($value); }
function wp_date($format, $timestamp) { return gmdate($format, $timestamp); }
function disabled($disabled) { if ($disabled) echo 'disabled="disabled"'; }
function surfside_tools_staff_page_url($path = '') { return 'https://example.org/dashboard/' . trim($path, '/') . '/'; }
function surfside_tools_youtube_valid_playlist_id($value) { return is_string($value) && preg_match('/^[A-Za-z0-9_-]{10,100}$/', $value); }
function surfside_tools_youtube_tables() { return array(
    'videos'=>'wp_surfside_yt_videos',
    'members'=>'wp_surfside_yt_members',
    'playlists'=>'wp_surfside_yt_playlists'
); }
function surfside_tools_youtube_review_summary($master) {
    if ($master !== 'PLMESSAGES1234') throw new RuntimeException('Wrong master list');
    return array('total'=>368,'needs_review'=>$GLOBALS['test_pending_reviews'],'undated'=>30,'excluded'=>6);
}
function surfside_tools_youtube_manager_url($args = array()) {
    return 'https://example.org/dashboard/site-management/?view=message-archive&'.http_build_query($args);
}
class StubSummaryDB {
    public $queries = array();
    public function prepare($sql, $arg) {
        if (!str_contains($sql, '%s') || $arg !== 'PLMESSAGES1234') throw new RuntimeException('Missing master filter');
        return str_replace('%s', "'" . $arg . "'", $sql);
    }
    public function get_var($sql) {
        $this->queries[] = $sql;
        if (str_contains($sql, 'v.sermon_date IS NULL')) return 30;
        if (str_contains($sql, "category = 'series'")) return 50;
        if (str_contains($sql, 'COUNT(*)')) return 368;
        throw new RuntimeException('Unexpected SQL');
    }
}
$GLOBALS['wpdb'] = new StubSummaryDB();

require dirname(__DIR__) . '/includes/youtube-sermon-settings.php';
function check($condition, $message) { if (!$condition) throw new RuntimeException($message); }

$markup = surfside_tools_youtube_settings_panel();
check(str_contains($markup, 'Master playlist videos:</strong> 368'), 'Master count displayed');
check(str_contains($markup, 'Series playlists:</strong> 50'), 'Series count displayed');
check(str_contains($markup, 'Needs Review:</strong> 3'), 'Manager pending-review count displayed');
check(str_contains($markup, 'Excluded videos:</strong> 6'), 'Excluded decisions counted');
check(str_contains($markup, 'Dates not recognized in YouTube titles:</strong> 30'), 'Unparsed source titles distinctly labeled');
check(!str_contains($markup, 'Dates needing review:'), 'Old misleading status label removed');
check(str_contains($markup, 'archive_status=review'), 'Direct link to filtered Needs Review list');
check(str_contains($markup, 'include videos already included, excluded, or given a manual date'),
    'Informational count explains reviewed videos remain undated');
check(count($GLOBALS['wpdb']->queries) === 2, 'Only two simple count queries in Integration panel');

$GLOBALS['test_pending_reviews'] = 0;
$GLOBALS['wpdb']->queries = array();
$markup = surfside_tools_youtube_settings_panel();
check(str_contains($markup, 'Needs Review:</strong> 0'), 'Pending count reaches zero without sync');
check(str_contains($markup, 'All imported videos have been reviewed or have a recognized date.'),
    'Zero outstanding reviews shown as completed');
check(!str_contains($markup, 'Review remaining videos'), 'No unnecessary link after reviews complete');
check(str_contains($markup, 'Dates not recognized in YouTube titles:</strong> 30'),
    'Raw source title count remains independent of staff decisions');

$GLOBALS['test_schema_ready'] = false;
$GLOBALS['wpdb']->queries = array();
$markup = surfside_tools_youtube_settings_panel();
check(str_contains($markup, 'Import status'), 'Uninitialized importer status still displays');
check(!str_contains($markup, 'Needs Review:</strong>'), 'No fake review counts before import tables exist');
check(count($GLOBALS['wpdb']->queries) === 0, 'No metadata reads before import schema initialization');

echo "YouTube review summary and import-label regression checks passed.\n";
