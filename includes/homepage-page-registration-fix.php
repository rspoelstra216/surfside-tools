<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Ensure the Manage Homepage staff page exists even when a feature is deployed
 * without an accompanying plugin version bump or wp-admin visit.
 */
function surfside_tools_repair_homepage_staff_page() {
    if (!function_exists('surfside_tools_ensure_staff_page')) {
        return;
    }

    $dashboard = get_page_by_path('dashboard');
    if (!$dashboard) {
        return;
    }

    $existing = get_page_by_path('dashboard/homepage');
    if ($existing && $existing->post_status === 'publish') {
        return;
    }

    $page_id = surfside_tools_ensure_staff_page(
        'Manage Homepage',
        'homepage',
        '[surfside_staff_homepage]',
        (int) $dashboard->ID
    );

    if ($page_id) {
        flush_rewrite_rules(false);
    }
}
add_action('init', 'surfside_tools_repair_homepage_staff_page', 60);
