<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Keep Firebase staff authentication scoped to Surfside Tools.
 *
 * MM6 authorization is based on the Firebase UID permission record. A locked
 * bridge user supplies only the capabilities required by the existing front-end
 * dashboard modules and never receives a WordPress authentication cookie.
 */

function surfside_tools_firebase_request_is_scoped() {
    $request_uri = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '';
    if (!$request_uri) {
        return false;
    }

    $path = (string) wp_parse_url($request_uri, PHP_URL_PATH);
    if ($path && preg_match('#/dashboard(?:/|$)#', $path)) {
        return true;
    }

    if ($path && strpos($path, '/wp-json/surfside-tools/') !== false) {
        return true;
    }

    if (isset($_GET['rest_route'])) {
        $rest_route = sanitize_text_field(wp_unslash($_GET['rest_route']));
        if (strpos($rest_route, '/surfside-tools/') === 0) {
            return true;
        }
    }

    return false;
}

function surfside_tools_has_wordpress_auth_cookie() {
    return defined('LOGGED_IN_COOKIE') && !empty($_COOKIE[LOGGED_IN_COOKIE]);
}

add_filter('determine_current_user', function ($user_id) {
    if ($user_id || surfside_tools_has_wordpress_auth_cookie() || !surfside_tools_firebase_request_is_scoped()) {
        return $user_id;
    }

    $session = surfside_tools_get_firebase_staff_session();
    if (!$session || empty($session['uid'])) {
        return $user_id;
    }

    $permission = surfside_tools_get_permission($session['uid']);
    if (!$permission || !surfside_tools_permission_role_is_active($permission['role'] ?? '')) {
        return $user_id;
    }

    $bridge_user = surfside_tools_get_or_create_bridge_user($permission);
    return $bridge_user instanceof WP_User ? (int) $bridge_user->ID : $user_id;
}, 30);

add_filter('show_admin_bar', function ($show) {
    if (
        surfside_tools_get_firebase_staff_session()
        && !surfside_tools_has_wordpress_auth_cookie()
        && surfside_tools_firebase_request_is_scoped()
    ) {
        return false;
    }

    return $show;
});

function surfside_tools_firebase_login_page_url($redirect = '') {
    $page = get_page_by_path('dashboard/login');
    $url = $page ? get_permalink($page) : home_url('/dashboard/login/');

    if ($redirect) {
        $url = add_query_arg('redirect_to', $redirect, $url);
    }

    return $url;
}

add_filter('login_url', function ($login_url, $redirect, $force_reauth) {
    if (!$redirect) {
        return $login_url;
    }

    $dashboard_url = home_url('/dashboard');
    if (strpos($redirect, $dashboard_url) !== 0) {
        return $login_url;
    }

    return surfside_tools_firebase_login_page_url($redirect);
}, 10, 3);
