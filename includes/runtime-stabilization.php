<?php
/**
 * Runtime containment for staff-only helpers.
 *
 * Several ministry/calendar enhancements were originally attached to broad
 * init/footer hooks. On shared hosting that means staff-only database work can
 * run during unrelated requests. Keep the features, but only execute them on
 * the pages that actually use them.
 */
if (!defined('ABSPATH')) { exit; }

/** Match the current front-end request without issuing another database query. */
function surfside_tools_runtime_request_matches($content_marker, $uri_marker = '') {
    if (is_admin()) return false;

    $object = get_queried_object();
    if ($object instanceof WP_Post && strpos((string) $object->post_content, $content_marker) !== false) {
        return true;
    }

    if ($uri_marker !== '') {
        $uri = isset($_SERVER['REQUEST_URI']) ? (string) wp_unslash($_SERVER['REQUEST_URI']) : '';
        if ($uri !== '' && strpos($uri, $uri_marker) !== false) return true;
    }

    return false;
}

/** The legacy Ministries page migration completed during the rollout; do not poll for it on every request. */
remove_action('init', 'surfside_tools_migrate_site_ministries_page', 90);

/** Featured Ministry manager UI belongs only on the dedicated Ministry Manager. */
remove_action('wp_footer', 'surfside_tools_ministry_manager_featured_field', 101);
function surfside_tools_runtime_ministry_featured_field() {
    if (!surfside_tools_runtime_request_matches('surfside_staff_ministries_manager', '/dashboard/site-ministries')) return;
    surfside_tools_ministry_manager_featured_field();
}
add_action('wp_footer', 'surfside_tools_runtime_ministry_featured_field', 101);

/** Bible Study manager helpers belong only on Calendar Manager, not every staff-visible page. */
remove_action('wp_footer', 'surfside_tools_bible_study_manager_fields', 98);
remove_action('admin_footer', 'surfside_tools_bible_study_manager_fields', 98);
function surfside_tools_runtime_bible_study_manager_fields() {
    if (!surfside_tools_runtime_request_matches('surfside_calendar', '/dashboard/calendar')) return;
    surfside_tools_bible_study_manager_fields();
}
add_action('wp_footer', 'surfside_tools_runtime_bible_study_manager_fields', 98);

/** Shared audience controls use the same Calendar Manager scope. */
remove_action('wp_footer', 'surfside_tools_event_audience_manager_fields', 99);
remove_action('admin_footer', 'surfside_tools_event_audience_manager_fields', 99);
function surfside_tools_runtime_event_audience_manager_fields() {
    if (!surfside_tools_runtime_request_matches('surfside_calendar', '/dashboard/calendar')) return;
    surfside_tools_event_audience_manager_fields();
}
add_action('wp_footer', 'surfside_tools_runtime_event_audience_manager_fields', 99);
