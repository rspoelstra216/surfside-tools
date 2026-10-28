<?php
/** Regression: Surfside custom staff sessions work for WP's media modal AJAX only. */
define('ABSPATH', __DIR__ . '/');
define('LOGGED_IN_COOKIE', 'wordpress_logged_in_for_test');
$GLOBALS['test_filters'] = array();
$GLOBALS['test_permission'] = 'admin';
$GLOBALS['test_session'] = true;
$GLOBALS['test_bridge_id'] = 123;
class WP_User {
    public $ID;
    public function __construct($id) { $this->ID = $id; }
}
function add_filter($name, $handler, $priority=10, $args=1) {
    $GLOBALS['test_filters'][$name][$priority][] = $handler;
}
function add_action(...$args) {}
function add_shortcode(...$args) {}
function wp_parse_url($url, $component= -1) { return parse_url($url, $component); }
function wp_unslash($value) { return stripslashes((string) $value); }
function sanitize_text_field($v) { return trim(strip_tags((string)$v)); }
function sanitize_key($v) { return preg_replace('/[^a-z0-9_-]/','',strtolower((string)$v)); }
function surfside_tools_get_firebase_staff_session() {
    return $GLOBALS['test_session'] ? array('uid' => 'test-staff-uid') : null;
}
function surfside_tools_get_permission($uid) {
    return $GLOBALS['test_permission'] ? array('role' => $GLOBALS['test_permission']) : null;
}
function surfside_tools_permission_role_is_active($role) { return in_array($role, array('admin', 'staff'), true); }
function surfside_tools_get_or_create_bridge_user($permission) {
    return new WP_User($GLOBALS['test_bridge_id']);
}
function check($ok, $msg) { if (!$ok) throw new RuntimeException($msg); }

require dirname(__DIR__) . '/includes/firebase-staff-login.php';

/** Test path and WordPress action together, because either alone must be insufficient. */
function scoped($uri, $action='', $rest_route='') {
    $_SERVER['REQUEST_URI'] = $uri;
    $_REQUEST = array();
    $_GET = array();
    if ($action !== '') $_REQUEST['action'] = $action;
    if ($rest_route !== '') $_GET['rest_route'] = $rest_route;
    return surfside_tools_firebase_request_is_scoped();
}

check(scoped('/dashboard/site-settings/streaming/'), 'Existing dashboard session scope intact');
check(scoped('/wp-json/surfside-tools/v1/staff-auth/session'), 'Existing staff REST scope intact');
check(scoped('/index.php', '', '/surfside-tools/v1/staff-auth/session'), 'Existing REST query route scope intact');
check(scoped('/wp-admin/admin-ajax.php', 'query-attachments'), 'WP media grid requests get staff session');
check(scoped('/wp-admin/admin-ajax.php', 'upload-attachment'), 'Media modal admin-ajax upload gets staff session');
check(scoped('/wp-admin/async-upload.php', 'upload-attachment'), 'WP media uploader gets staff session');
check(scoped('/subdir/wp-admin/admin-ajax.php', 'query-attachments'), 'Subdirectory WP installations supported');
foreach (array(
    array('/wp-admin/admin-ajax.php',''),
    array('/wp-admin/admin-ajax.php','delete-post'),
    array('/wp-admin/admin-ajax.php','save-attachment'),
    array('/wp-admin/admin-ajax.php','surfside_slideshow_save'),
    array('/wp-admin/async-upload.php','query-attachments'),
    array('/wp-admin/async-upload.php','delete-post'),
    array('/wp-admin/upload.php','query-attachments'),
    array('/wp-admin/admin.php','upload-attachment'),
    array('/wp-admin/admin-ajax.php/extra','query-attachments'),
    array('/wp-json/wp/v2/media','upload-attachment'),
    array('/dashboarding','upload-attachment'),
    array('/sermons','query-attachments')
) as $case) {
    check(!scoped($case[0], $case[1]), 'Unrelated admin/public request denied: ' . implode(' ', $case));
}

// Both Firebase and WordPress custom session resolvers rely on the same scope
// predicate. Exercise the actual Firebase determine_current_user hook too.
$resolve = $GLOBALS['test_filters']['determine_current_user'][30][0] ?? null;
check(is_callable($resolve), 'Firebase session current-user resolver registered');
scoped('/wp-admin/admin-ajax.php', 'query-attachments');
check($resolve(0) === 123, 'Active Firebase session supplies bridge identity for query-attachments');
scoped('/wp-admin/async-upload.php', 'upload-attachment');
check($resolve(0) === 123, 'Active Firebase session supplies bridge identity for image uploads');
scoped('/wp-admin/admin-ajax.php', 'delete-post');
check($resolve(0) === 0, 'Media session cannot authenticate unrelated admin action');
scoped('/wp-admin/admin-ajax.php', 'upload-attachment');
check($resolve(44) === 44, 'Existing authenticated WordPress identity is not overridden');
$_COOKIE[LOGGED_IN_COOKIE] = 'wp-cookie-present';
check($resolve(0) === 0, 'WordPress cookie takes precedence over virtual bridge login');
unset($_COOKIE[LOGGED_IN_COOKIE]);
$GLOBALS['test_permission'] = 'disabled';
check($resolve(0) === 0, 'Revoked/disabled Firebase permission does not authenticate');
$GLOBALS['test_permission'] = 'admin';
$GLOBALS['test_session'] = false;
check($resolve(0) === 0, 'Signed-out custom session cannot authenticate');
echo "PASS: Staff media library AJAX scope and authentication safety\n";
