<?php

if (!defined('ABSPATH')) {
    exit;
}

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
