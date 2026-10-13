<?php
/**
 * Regression: Cloudflare Streaming Management page setup must never write to
 * WordPress posts during public requests. Run: php scripts/test-streaming-page-provisioning.php
 */
define('ABSPATH', __DIR__);

$GLOBALS['registered_actions'] = array();
$GLOBALS['removed_actions'] = array();
$GLOBALS['is_admin'] = false;
$GLOBALS['database_option_reads'] = 0;

function add_action($hook, $callback, $priority = 10) {
    $GLOBALS['registered_actions'][] = array($hook, $callback, $priority);
}
function remove_action($hook, $callback, $priority = 10) {
    $GLOBALS['removed_actions'][] = array($hook, $callback, $priority);
}
function add_shortcode($shortcode, $callback) {}
function is_admin() { return $GLOBALS['is_admin']; }
function get_option($key, $default = null) {
    $GLOBALS['database_option_reads']++;
    return $default;
}
function sanitize_title($value) { return strtolower(trim($value)); }
function get_page_uri($id) {
    return $id === 42 ? 'dashboard/site-settings' : '';
}

require dirname(__DIR__) . '/includes/cloudflare-stream.php';
require dirname(__DIR__) . '/includes/staff-page-provisioning.php';

function check($value, $description) {
    if (!$value) throw new RuntimeException($description);
}

$definitions = surfside_tools_staff_page_definitions();
check(SURFSIDE_TOOLS_STAFF_PAGES_SCHEMA_VERSION >= 3,
    'Page schema version must advance to run the new migration');
check(isset($definitions['streaming-management']), 'Migration must own Streaming Management');
check($definitions['streaming-management']['parent'] === 'site-settings',
    'Streaming Management parent must be the nested Church Settings page');
check($definitions['streaming-management']['slug'] === 'streaming',
    'Existing Streaming Management URL is preserved');
check($definitions['streaming-management']['content'] === '[surfside_staff_streaming]',
    'The existing staff shortcode must be preserved');
check(array_search('site-settings', array_keys($definitions), true) <
    array_search('streaming-management', array_keys($definitions), true),
    'The parent must be provisioned before the streaming page');

check(surfside_tools_staff_page_path('streaming', 42) ===
    'dashboard/site-settings/streaming', 'Nested page lookup must use its full path');

$creation_hooks = array_values(array_filter($GLOBALS['registered_actions'], function($entry) {
    return $entry[0] === 'init' && is_callable($entry[1]);
}));
check(count($creation_hooks) === 0,
    'The Stream integration must not provision staff pages on every init request');

$admin_migrations = array_values(array_filter($GLOBALS['registered_actions'], function($entry) {
    return $entry[0] === 'admin_init' && $entry[1] === 'surfside_tools_provision_staff_pages';
}));
check(count($admin_migrations) === 1,
    'The versioned admin-only provisioning hook must be registered exactly once');

surfside_tools_provision_staff_pages();
check($GLOBALS['database_option_reads'] === 0,
    'Public requests must not even enter staff-page migration logic');

echo "Streaming page setup is versioned, admin-only, and uses the correct nested path.\n";
