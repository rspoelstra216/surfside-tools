<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Keep every Surfside Tools staff route out of full-page caches.
 *
 * Staff authentication may be backed by a Surfside-specific session cookie
 * rather than WordPress's standard logged-in cookie. Cache layers therefore
 * cannot safely infer whether a /dashboard response is public or authenticated.
 */
function surfside_tools_dashboard_request_is_staff_path() {
    $request_uri = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '';
    if (!$request_uri) {
        return false;
    }

    $path = (string) wp_parse_url($request_uri, PHP_URL_PATH);
    return $path !== '' && (bool) preg_match('#/dashboard(?:/|$)#', $path);
}

/**
 * Mark staff paths uncacheable before normal page rendering. Both the login
 * page and redirect responses must bypass full-page caches even when there is
 * no standard WordPress logged-in cookie (Surfside uses its own sessions).
 */
function surfside_tools_staff_mark_uncacheable() {
    if (!surfside_tools_dashboard_request_is_staff_path()) return;
    if (!defined('DONOTCACHEPAGE')) define('DONOTCACHEPAGE', true);
}
add_action('init', 'surfside_tools_staff_mark_uncacheable', 0);

/**
 * Explicit browser and intermediary no-store headers. These also apply to
 * unauthenticated staff login and dashboard redirect responses. They cannot
 * retroactively clear a response previously cached upstream of WordPress.
 */
function surfside_tools_staff_send_no_store_headers() {
    if (!surfside_tools_dashboard_request_is_staff_path() || headers_sent()) return;
    nocache_headers();
    header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0', true);
    header('Surrogate-Control: no-store', true);
    header('Pragma: no-cache', true);
    header('Expires: Wed, 11 Jan 1984 05:00:00 GMT', true);
}
add_action('send_headers', 'surfside_tools_staff_send_no_store_headers', 100);

function surfside_tools_disable_dashboard_page_cache() {
    if (!surfside_tools_dashboard_request_is_staff_path()) {
        return;
    }

    surfside_tools_staff_mark_uncacheable();
    if (function_exists('surfside_tools_prevent_cache')) {
        surfside_tools_prevent_cache();
    }

    // Surfside's existing helper sets no-store; reinforce it after the normal
    // WordPress header helpers so authenticated and redirect paths agree.
    surfside_tools_staff_send_no_store_headers();

    // LiteSpeed cannot infer custom Surfside staff sessions from WordPress's
    // regular auth cookie, so staff paths must bypass its full-page cache.
    do_action('litespeed_control_set_nocache', 'Surfside Tools staff dashboard');
}
add_action('template_redirect', 'surfside_tools_disable_dashboard_page_cache', 0);

/**
 * Skip the legacy dashboard login splash and send signed-out visitors directly
 * to the dedicated Surfside Staff Login page.
 */
add_action('template_redirect', function () {
    if (!is_page('dashboard')) {
        return;
    }

    if (function_exists('surfside_tools_staff_can_access') && surfside_tools_staff_can_access()) {
        return;
    }

    $redirect = get_permalink();
    if (!$redirect) {
        $redirect = home_url('/dashboard/');
    }

    wp_safe_redirect(surfside_tools_firebase_login_page_url($redirect));
    exit;
}, 1);
