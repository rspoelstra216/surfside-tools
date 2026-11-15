<?php
/**
 * Shared, role-level Surfside Tools permissions.
 *
 * Only Tools Admin can change this option. Tools Staff members share one set
 * of permissions. WordPress roles and integration credentials are unaffected.
 */
if (!defined('ABSPATH')) { exit; }

function surfside_tools_staff_permission_definitions() {
    return array(
        'weekly' => array('label' => 'Weekly Updates & Featured Announcements', 'description' => 'Prepare weekly notes and announcements and manage the featured app announcement.', 'default' => true),
        'messages' => array('label' => 'Messages & Media', 'description' => 'Manage the sermon archive, sermon series, weekly media and live slides.', 'default' => true),
        'calendar' => array('label' => 'Calendar & Volunteers', 'description' => 'Manage calendar events and volunteer needs. Calendar defaults and saved-place administration stay admin-only.', 'default' => true),
        'community' => array('label' => 'Prayers & Communications', 'description' => 'Review prayer requests and send app push notifications. Contact routing stays admin-only.', 'default' => true),
        'appearance' => array('label' => 'Homepage & App Appearance', 'description' => 'Manage homepage photos and the app Home experience. Global website navigation stays separately controlled.', 'default' => true),
        'ministries' => array('label' => 'Ministries', 'description' => 'Create, edit and publish ministries shown on the website and app.', 'default' => false),
        'information' => array('label' => 'Church Information & Navigation', 'description' => 'Edit church information, service details and website navigation. Does not grant integrations, API keys, or general WordPress settings.', 'default' => false),
    );
}

function surfside_tools_staff_role_permissions() {
    $saved = get_option('surfside_tools_staff_role_permissions', array());
    $saved = is_array($saved) ? $saved : array();
    $values = array();
    foreach (surfside_tools_staff_permission_definitions() as $key => $definition) {
        $values[$key] = array_key_exists($key, $saved) ? (bool) $saved[$key] : (bool) $definition['default'];
    }
    return $values;
}

function surfside_tools_staff_has_permission($key) {
    if (function_exists('surfside_tools_current_user_is_tools_admin') && surfside_tools_current_user_is_tools_admin()) {
        return true;
    }
    if (!is_user_logged_in() || !current_user_can('upload_files')) {
        return false;
    }
    $user = wp_get_current_user();
    if (!function_exists('surfside_tools_wp_tools_role') || surfside_tools_wp_tools_role($user) !== 'staff') {
        return false;
    }
    $permissions = surfside_tools_staff_role_permissions();
    return !empty($permissions[$key]);
}

function surfside_tools_staff_permission_for_route($path) {
    $paths = array(
        'dashboard/weekly-update' => 'weekly',
        'dashboard/mobile-app/featured-announcement' => 'weekly',
        'dashboard/site-management' => 'messages',
        'dashboard/mobile-app/live-slides' => 'messages',
        'dashboard/calendar' => 'calendar',
        'dashboard/mobile-app/push-notifications' => 'community',
        'dashboard/homepage' => 'appearance',
        'dashboard/mobile-app/home-experience' => 'appearance',
        'dashboard/site-ministries' => 'ministries',
        'dashboard/surfside-information' => 'information',
        'dashboard/site-navigation' => 'information',
        'dashboard/site-streaming' => 'information',
    );
    if (isset($paths[$path])) {
        return $paths[$path];
    }
    if ($path === 'dashboard') {
        $view = isset($_GET['view']) && is_string($_GET['view']) ? sanitize_key(wp_unslash($_GET['view'])) : '';
        $tool = isset($_GET['tool']) && is_string($_GET['tool']) ? sanitize_key(wp_unslash($_GET['tool'])) : '';
        if ($view === 'sermon-archive') return 'messages';
        if ($view === 'member-engagement') return $tool === 'volunteer-needs' ? 'calendar' : 'community';
    }
    return '';
}

/** Protect direct page requests and POST submissions, not just menu links. */
add_action('template_redirect', function () {
    if (!is_page() || is_admin()) return;
    $post_id = get_queried_object_id();
    if (!$post_id) return;
    $path = trim((string) get_page_uri($post_id), '/');
    $permission = surfside_tools_staff_permission_for_route($path);
    if ($permission && is_user_logged_in() && current_user_can('upload_files')
        && !surfside_tools_staff_has_permission($permission)) {
        wp_die(esc_html__('You do not have permission to use this Surfside Tools feature.', 'surfside-tools'), '', array('response' => 403));
    }
}, 5);

/** Shortcodes remain guarded even if rendered outside their normal dashboard URL. */
add_filter('pre_do_shortcode_tag', function ($result, $tag) {
    $map = array(
        'surfside_staff_weekly_update' => 'weekly',
        'surfside_staff_featured_announcement' => 'weekly',
        'surfside_staff_site_management' => 'messages',
        'surfside_staff_live_slides' => 'messages',
        'surfside_staff_calendar' => 'calendar',
        'surfside_staff_push_notifications' => 'community',
        'surfside_staff_homepage' => 'appearance',
        'surfside_staff_mobile_app_home' => 'appearance',
        'surfside_staff_ministries_manager' => 'ministries',
        'surfside_staff_site_information' => 'information',
    );
    if (isset($map[$tag]) && !surfside_tools_staff_has_permission($map[$tag])) {
        return '<p>You do not have permission to use this Surfside Tools feature.</p>';
    }
    return $result;
}, 10, 2);
