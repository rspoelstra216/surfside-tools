<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Complete the MM5 bridge without changing the existing staff dashboard's
 * WordPress capability checks. A verified Firebase identity is mapped to the
 * existing WordPress user by email, then receives a normal WordPress auth
 * cookie. The user never needs to enter a WordPress password.
 */

add_filter('rest_post_dispatch', function ($response, $server, $request) {
    if ($request->get_route() !== '/surfside-tools/v1/staff-auth/session' || $request->get_method() !== 'POST') {
        return $response;
    }

    if (is_wp_error($response)) {
        return $response;
    }

    $data = $response instanceof WP_REST_Response ? $response->get_data() : $response;
    if (!is_array($data) || empty($data['authenticated']) || empty($data['email'])) {
        return $response;
    }

    $user = get_user_by('email', sanitize_email($data['email']));
    if (!$user || !user_can($user, 'upload_files')) {
        return $response;
    }

    wp_set_current_user($user->ID);
    wp_set_auth_cookie($user->ID, true, is_ssl());
    return $response;
}, 10, 3);

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

add_shortcode('surfside_firebase_staff_login', function () {
    if (is_user_logged_in() && current_user_can('upload_files')) {
        $redirect = isset($_GET['redirect_to']) ? esc_url_raw(wp_unslash($_GET['redirect_to'])) : home_url('/dashboard/');
        return '<div class="surfside-staff-login"><h2>You are signed in</h2><p><a class="wp-block-button__link wp-element-button" href="' . esc_url($redirect) . '">Continue to Surfside Tools</a></p></div>';
    }

    $markup = surfside_tools_firebase_staff_login_markup('Sign in with the same Firebase account you use with the Surfside mobile app.');
    $redirect = isset($_GET['redirect_to']) ? esc_url_raw(wp_unslash($_GET['redirect_to'])) : home_url('/dashboard/');

    return str_replace(
        'data-redirect="' . esc_attr(get_permalink() ?: home_url('/dashboard/')) . '"',
        'data-redirect="' . esc_attr($redirect) . '"',
        $markup
    );
});

add_action('init', function () {
    if (get_page_by_path('dashboard/login')) {
        return;
    }

    $dashboard = get_page_by_path('dashboard');
    if (!$dashboard) {
        return;
    }

    wp_insert_post(array(
        'post_title' => 'Staff Login',
        'post_name' => 'login',
        'post_status' => 'publish',
        'post_type' => 'page',
        'post_parent' => $dashboard->ID,
        'post_content' => '[surfside_firebase_staff_login]',
        'comment_status' => 'closed',
    ));
}, 86);
