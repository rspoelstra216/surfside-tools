<?php
/** Staff dashboard and login must bypass page caches even before login. */
define('ABSPATH', __DIR__ . '/');
$GLOBALS['hooks'] = array();
$GLOBALS['actions'] = array();
$GLOBALS['prevent_cache_calls'] = 0;
$GLOBALS['nocache_calls'] = 0;
function add_action($tag, $callback, $priority=10) {
    $GLOBALS['hooks'][$tag][] = array('function'=>$callback, 'priority'=>$priority);
}
function do_action($tag, ...$args) { $GLOBALS['actions'][] = array($tag, $args); }
function wp_unslash($value) { return stripslashes((string)$value); }
function wp_parse_url($url, $component) { return parse_url($url, $component); }
function surfside_tools_prevent_cache() { $GLOBALS['prevent_cache_calls']++; }
function nocache_headers() { $GLOBALS['nocache_calls']++; }
function check($value, $description) {
    if (!$value) throw new RuntimeException($description);
}
function registered($name, $priority) {
    foreach ($GLOBALS['hooks'][$name] ?? array() as $item) {
        if (is_string($item['function']) &&
            $item['priority'] === $priority) return $item['function'];
    }
    return null;
}

require dirname(__DIR__) . '/includes/direct-dashboard-login.php';
check(registered('init', 0) === 'surfside_tools_staff_mark_uncacheable',
    'Staff no-cache flag is set at early init');
check(registered('send_headers', 100) === 'surfside_tools_staff_send_no_store_headers',
    'No-store headers are applied to staff responses');
check(registered('template_redirect', 0) === 'surfside_tools_disable_dashboard_page_cache',
    'Existing LiteSpeed bypass runs before login redirects');

foreach (array('/','/sermons/', '/dashboarding/', '/wp-json/surfside/v1/messages') as $url) {
    $_SERVER['REQUEST_URI'] = $url;
    check(!surfside_tools_dashboard_request_is_staff_path(), 'Public route does not qualify: ' . $url);
}
$_SERVER['REQUEST_URI'] = '/';
surfside_tools_staff_mark_uncacheable();
surfside_tools_staff_send_no_store_headers();
surfside_tools_disable_dashboard_page_cache();
check(!defined('DONOTCACHEPAGE') && $GLOBALS['prevent_cache_calls'] === 0
    && $GLOBALS['nocache_calls'] === 0 && $GLOBALS['actions'] === array(),
    'Public pages receive no cache override or cache-plugin bypass');

foreach (array(
    '/dashboard/',
    '/dashboard?view=member-engagement',
    '/dashboard/login/?redirect_to=%2Fdashboard%2F',
    '/dashboard/site-management/?view=roku-media',
    '/dashboard/site-settings/streaming/',
) as $url) {
    $_SERVER['REQUEST_URI'] = $url;
    check(surfside_tools_dashboard_request_is_staff_path(), 'Staff route covered: ' . $url);
}
$_SERVER['REQUEST_URI'] = '/dashboard/login/';
surfside_tools_staff_mark_uncacheable();
check(defined('DONOTCACHEPAGE') && DONOTCACHEPAGE === true,
    'Anonymous login page is flagged uncacheable before a session exists');
surfside_tools_staff_send_no_store_headers();
check($GLOBALS['nocache_calls'] === 1,
    'WordPress no-cache headers applied to login page');
surfside_tools_disable_dashboard_page_cache();
check($GLOBALS['prevent_cache_calls'] === 1 && $GLOBALS['nocache_calls'] === 2,
    'Early template redirect reinforces no-store cache headers');
check($GLOBALS['actions'][0][0] === 'litespeed_control_set_nocache',
    'LiteSpeed full-page cache is explicitly bypassed for custom staff sessions');

// Header-related assertions remain source-level: PHP CLI does not reliably
// expose header() entries through headers_list().
$source = file_get_contents(dirname(__DIR__) . '/includes/direct-dashboard-login.php');
foreach (array(
    "header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0'",
    "header('Surrogate-Control: no-store'",
    "header('Pragma: no-cache'",
    "header('Expires: Wed, 11 Jan 1984 05:00:00 GMT'",
) as $header) {
    check(strpos($source, $header) !== false, 'Staff responses send explicit ' . $header);
}
echo "PASS: Staff login/dashboard early no-store headers and cache bypass\n";
