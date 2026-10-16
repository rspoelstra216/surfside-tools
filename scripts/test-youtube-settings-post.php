<?php
/**
 * Regression test: YouTube Integration forms must work on the front-end
 * dashboard without visiting /wp-admin/admin-post.php.
 * Run: php scripts/test-youtube-settings-post.php
 */
define('ABSPATH', __DIR__ . '/');

$GLOBALS['test_actions'] = array();
$GLOBALS['test_transients'] = array();
$GLOBALS['test_permission'] = true;
$GLOBALS['test_nonce_ok'] = true;
$GLOBALS['test_is_settings_page'] = true;
$GLOBALS['test_sync_count'] = 0;
$GLOBALS['test_test_count'] = 0;
$GLOBALS['test_redirect_count'] = 0;
$GLOBALS['test_redirect_fails'] = false;

class WP_Error {
    private $message;
    public function __construct($code, $message) { $this->message = $message; }
    public function get_error_message() { return $this->message; }
}
function is_wp_error($value) { return $value instanceof WP_Error; }
function add_action($hook, $callback, $priority = 10) { $GLOBALS['test_actions'][$hook] = $callback; }
function current_user_can($cap) { return $GLOBALS['test_permission'] && $cap === 'manage_options'; }
function check_admin_referer($name, $field) {
    if (!$GLOBALS['test_nonce_ok'] || $name !== 'surfside_youtube_settings' || $field !== 'surfside_youtube_nonce') {
        throw new RuntimeException('Invalid nonce');
    }
}
function is_page($slug) { return $slug === 'settings' && $GLOBALS['test_is_settings_page']; }
function sanitize_key($value) { return preg_replace('/[^a-z0-9_-]/', '', strtolower((string) $value)); }
function sanitize_text_field($value) { return trim(strip_tags((string) $value)); }
function wp_unslash($value) { return stripslashes($value); }
function get_current_user_id() { return 7; }
function set_transient($name, $value, $seconds) { $GLOBALS['test_transients'][$name] = $value; }
function surfside_tools_staff_page_url($path = '') { return 'https://example.org/dashboard/' . trim($path, '/') . '/'; }
function esc_url($value) { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
function wp_safe_redirect($url, $status = 302, $by = 'WordPress') {
    $GLOBALS['test_redirect_count']++;
    $GLOBALS['test_redirect'] = array('url'=>$url, 'status'=>$status, 'by'=>$by);
    if ($GLOBALS['test_redirect_fails']) return false;
    throw new RuntimeException('Redirect sent');
}
function wp_die($message, $title = '', $args = array()) { throw new RuntimeException('WP_DIE: ' . $message); }
function surfside_tools_youtube_config() { return array('channel_ref'=>'@addpastor', 'master_playlist_id'=>''); }
function surfside_tools_youtube_resolve_channel($channel) {
    $GLOBALS['test_test_count']++;
    return array('title'=>'Surfside', 'id'=>'UC'.str_repeat('0',22));
}
function surfside_tools_youtube_start_sync() { $GLOBALS['test_sync_count']++; return true; }

require dirname(__DIR__) . '/includes/youtube-sermon-settings.php';
function check($yes, $why) { if (!$yes) throw new RuntimeException($why); }

check(isset($GLOBALS['test_actions']['template_redirect'])
    && $GLOBALS['test_actions']['template_redirect'] === 'surfside_tools_youtube_settings_frontend_post',
    'Dashboard template handler must be registered');
check(isset($GLOBALS['test_actions']['admin_post_surfside_youtube_settings']),
    'Existing admin-post hook retained for compatibility');

$source = file_get_contents(dirname(__DIR__) . '/includes/youtube-sermon-settings.php');
check(strpos($source, "$" . "form_action = surfside_tools_staff_page_url('settings')") !== false,
    'YouTube controls must POST to the front-end dashboard, not admin-post.php');

$_SERVER['REQUEST_METHOD'] = 'GET';
$_POST = array('action'=>'surfside_youtube_settings', 'youtube_action'=>'sync');
surfside_tools_youtube_settings_frontend_post();
check($GLOBALS['test_sync_count'] === 0, 'No action runs for GET');

$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = array('action'=>'other_feature', 'youtube_action'=>'sync');
surfside_tools_youtube_settings_frontend_post();
check($GLOBALS['test_sync_count'] === 0, 'Other dashboard forms are unaffected');

$_POST['action'] = 'surfside_youtube_settings';
$GLOBALS['test_is_settings_page'] = false;
surfside_tools_youtube_settings_frontend_post();
check($GLOBALS['test_sync_count'] === 0, 'Request only processed on Settings page');
$GLOBALS['test_is_settings_page'] = true;

$_POST['youtube_action'] = 'test';
try {
    surfside_tools_youtube_settings_frontend_post();
    throw new RuntimeException('Test connection handler returned without redirect');
} catch (RuntimeException $e) {
    check($e->getMessage() === 'Redirect sent', 'Test connection action should redirect');
}
check($GLOBALS['test_test_count'] === 1 && $GLOBALS['test_sync_count'] === 0,
    'Test connection is called and does not start a sync');
check($GLOBALS['test_redirect']['status'] === 303
    && $GLOBALS['test_redirect']['url'] === 'https://example.org/dashboard/settings/#surfside-youtube',
    'Redirect targets front-end Integrations using POST-redirect-GET');
check(strpos($GLOBALS['test_transients']['surfside_youtube_notice_7']['message'], 'Connected') !== false,
    'Success feedback is preserved in staff transient');

$_POST['youtube_action'] = 'sync';
try {
    surfside_tools_youtube_settings_frontend_post();
    throw new RuntimeException('Sync handler returned without redirect');
} catch (RuntimeException $e) {
    check($e->getMessage() === 'Redirect sent', 'Sync should redirect');
}
check($GLOBALS['test_sync_count'] === 1, 'Sync Now schedules exactly one new sync');
check(strpos($GLOBALS['test_transients']['surfside_youtube_notice_7']['message'], 'queued') !== false,
    'Sync message is preserved');

$GLOBALS['test_permission'] = false;
try {
    surfside_tools_youtube_settings_frontend_post();
    throw new RuntimeException('Unauthorized request was accepted');
} catch (RuntimeException $e) {
    check(strpos($e->getMessage(), 'do not have permission') !== false, 'Unauthorized request denied');
}
check($GLOBALS['test_sync_count'] === 1, 'Unauthorized request did not start sync');
$GLOBALS['test_permission'] = true;

$GLOBALS['test_nonce_ok'] = false;
try {
    surfside_tools_youtube_settings_frontend_post();
    throw new RuntimeException('Invalid nonce accepted');
} catch (RuntimeException $e) {
    check($e->getMessage() === 'Invalid nonce', 'CSRF nonce checked');
}
check($GLOBALS['test_sync_count'] === 1, 'Invalid nonce did not start sync');
$GLOBALS['test_nonce_ok'] = true;

$GLOBALS['test_redirect_fails'] = true;
try {
    surfside_tools_youtube_settings_frontend_post();
    throw new RuntimeException('Failed redirect silently exited');
} catch (RuntimeException $e) {
    check(strpos($e->getMessage(), 'WordPress could not redirect') !== false,
        'Failed redirect must show recoverable error instead of blank screen');
}
check($GLOBALS['test_sync_count'] === 2, 'Processed action is not silently discarded when redirect fails');

echo "YouTube Integrations front-end POST and redirect checks passed.\n";
