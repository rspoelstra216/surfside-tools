<?php
/** Standalone tests for shared Tools permissions, migration and custom roles. */
define('ABSPATH', __DIR__ . '/');
$GLOBALS['role_options'] = array();
$GLOBALS['role_logged_in'] = true;
$GLOBALS['role_upload'] = true;
$GLOBALS['role_firebase'] = array('role'=>'staff');
$_POST = array();
$_GET = array();
$_SERVER['REQUEST_METHOD'] = 'GET';

function add_action(...$args) {}
function add_filter(...$args) {}
function get_option($name, $default = false) { return $GLOBALS['role_options'][$name] ?? $default; }
function update_option($name, $value, $autoload = false) { $GLOBALS['role_options'][$name] = $value; return true; }
function sanitize_key($v) { return strtolower(preg_replace('/[^a-z0-9_-]/i', '', (string) $v)); }
function sanitize_title($v) { return preg_replace('/[^a-z0-9-]/', '', str_replace(' ', '-', strtolower($v))); }
function sanitize_text_field($v) { return trim(strip_tags((string) $v)); }
function wp_unslash($v) { return $v; }
function wp_verify_nonce($nonce, $action) { return $nonce === 'valid'; }
function is_user_logged_in() { return $GLOBALS['role_logged_in']; }
function current_user_can($cap) { return $cap === 'upload_files' && $GLOBALS['role_upload']; }
function surfside_tools_current_firebase_permission() { return $GLOBALS['role_firebase']; }
function surfside_tools_current_user_is_tools_admin() { return ($GLOBALS['role_firebase']['role'] ?? '') === 'admin'; }
function verify_role($assertion, $label) { if (!$assertion) throw new RuntimeException($label); }

require dirname(__DIR__) . '/includes/staff-role-permissions.php';
require dirname(__DIR__) . '/includes/staff-roles-ui.php';

verify_role(surfside_tools_role_definitions() === array('admin'=>'Tools Admin','staff'=>'Tools Staff'),
    'Built-in Tools roles remain intact');
verify_role(count(surfside_tools_staff_permission_definitions()) >= 15, 'All feature-level permissions available');
verify_role(count(surfside_tools_staff_advanced_permission_definitions()) >= 11, 'Additional capabilities independently assignable');
verify_role(count(surfside_tools_staff_protected_permission_definitions()) === 1, 'Role management remains nondelegable');
verify_role(surfside_tools_staff_has_permission('calendar'), 'Existing Staff features default to allowed');
verify_role(!surfside_tools_staff_has_permission('integrations') && !surfside_tools_staff_has_permission('saved_places'), 'Advanced permissions remain disabled by default');

$GLOBALS['role_options']['surfside_tools_staff_role_permissions'] = array(
    'weekly'=>false, 'messages'=>true, 'information'=>false
);
$staff = surfside_tools_staff_role_permissions();
verify_role(!$staff['weekly'] && !$staff['featured'] && !$staff['information'] && !$staff['website_navigation'],
    'Unmerged v507 Staff choices remain effective');
verify_role($staff['message_archive'] && $staff['weekly_media'], 'Old group permissions migrate to matching features');

$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = array('surfside_role_add_nonce'=>'valid', 'new_tools_role'=>'Calendar Editor');
$GLOBALS['role_firebase'] = array('role'=>'admin');
verify_role(str_contains(surfside_tools_role_management_handle_post(), 'added'), 'Admin creates named custom role');
verify_role(surfside_tools_role_is_assignable('calendar-editor'), 'New role becomes assignable');

$_POST = array('surfside_role_matrix_nonce'=>'valid',
    'role_permissions'=>array(
        'staff'=>array('weekly'=>'1', 'calendar'=>'1'),
        'calendar-editor'=>array('calendar'=>'1', 'volunteers'=>'1', 'integrations'=>'1', 'staff_access'=>'1')
    ));
verify_role(str_contains(surfside_tools_role_management_handle_post(), 'saved'), 'Admin saves matrix');
$GLOBALS['role_firebase'] = array('role'=>'calendar-editor');
verify_role(surfside_tools_staff_has_permission('calendar'), 'Custom role receives allowed calendar feature');
verify_role(surfside_tools_staff_has_permission('volunteers'), 'Custom role receives allowed volunteers feature');
verify_role(!surfside_tools_staff_has_permission('weekly'), 'Custom role does not inherit Staff permissions');
verify_role(surfside_tools_staff_has_permission('integrations'), 'Assigned role can receive All Integrations');
verify_role(surfside_tools_staff_has_permission('youtube_integration') && surfside_tools_staff_has_permission('giving'),
    'All Integrations grants its service-specific permissions');
verify_role(!surfside_tools_staff_has_permission('not_a_permission'), 'Unknown permissions denied');
verify_role(!surfside_tools_staff_has_permission('staff_access'), 'Staff management protected');

// A role with just one integration can open its page without other credentials.
$GLOBALS['role_options']['surfside_tools_role_matrix']['staff']['google_maps'] = true;
$GLOBALS['role_firebase'] = array('role'=>'staff');
verify_role(surfside_tools_staff_has_permission('google_maps') && surfside_tools_staff_has_permission('integrations_page'),
    'Maps-only role can access the relevant Integrations editor');
verify_role(!surfside_tools_staff_has_permission('youtube_integration') && !surfside_tools_staff_has_permission('turnstile'),
    'Maps-only permission does not unlock other integration credentials');

$GLOBALS['role_firebase'] = array('role'=>'admin');
verify_role(surfside_tools_staff_has_permission('integrations'), 'Admin retains integrations');
verify_role(surfside_tools_staff_has_permission('staff_access'), 'Admin retains staff management');
verify_role(surfside_tools_staff_permission_for_route('dashboard/settings')==='integrations_page',
    'Settings direct URL protected');
verify_role(surfside_tools_staff_permission_for_route('dashboard/access')==='staff_access',
    'Staff management direct URL protected');
$_GET = array('view'=>'places');
verify_role(surfside_tools_staff_permission_for_route('dashboard/calendar')==='saved_places',
    'Saved Places cannot bypass the calendar-level gate');
$_GET = array('view'=>'roku-media');
verify_role(surfside_tools_staff_permission_for_route('dashboard/site-management')==='weekly_media',
    'Weekly Media has separate authorization from Message Archive');
$_GET = array();

$_POST = array('surfside_role_remove_nonce'=>'valid', 'remove_tools_role'=>'calendar-editor');
verify_role(str_contains(surfside_tools_role_management_handle_post(), 'removed'),
    'Unused custom role can be removed');
$GLOBALS['role_firebase'] = array('role'=>'calendar-editor');
verify_role(!surfside_tools_role_is_assignable('calendar-editor'), 'Removed role becomes inactive');
verify_role(!surfside_tools_staff_has_permission('calendar'), 'Removed role fails closed');

$GLOBALS['role_logged_in'] = false;
$GLOBALS['role_firebase'] = array('role'=>'admin');
verify_role(!surfside_tools_staff_has_permission('staff_access'), 'Signed-out users never inherit Admin');
echo "Role matrix, migration, direct-route scopes and custom roles passed.\n";
