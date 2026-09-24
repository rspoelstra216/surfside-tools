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

function surfside_tools_disable_dashboard_page_cache() {
    if (!surfside_tools_dashboard_request_is_staff_path()) {
        return;
    }

    if (function_exists('surfside_tools_prevent_cache')) {
        surfside_tools_prevent_cache();
    }

    // LiteSpeed does not know that Surfside's custom staff-session cookies are
    // authenticated sessions, so explicitly prevent it from caching these pages.
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
