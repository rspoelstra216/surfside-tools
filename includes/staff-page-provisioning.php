<?php
/**
 * Versioned provisioning for the Surfside Tools front-end staff page tree.
 *
 * Staff pages are installation structure, not request-time state. Keep their
 * creation and repair in one admin-only migration instead of checking pages on
 * every normal WordPress request.
 */
if (!defined('ABSPATH')) {
    exit;
}

const SURFSIDE_TOOLS_STAFF_PAGES_SCHEMA_VERSION = 1;
const SURFSIDE_TOOLS_STAFF_PAGES_SCHEMA_OPTION = 'surfside_tools_staff_pages_schema';

/**
 * Canonical staff page tree. Parent keys must appear before their children.
 */
function surfside_tools_staff_page_definitions() {
    return array(
        'dashboard' => array(
            'title' => 'Staff Dashboard',
            'slug' => 'dashboard',
            'content' => '[surfside_staff_dashboard]',
            'parent' => '',
        ),
        'login' => array(
            'title' => 'Staff Login',
            'slug' => 'login',
            'content' => '[surfside_firebase_staff_login]',
            'parent' => 'dashboard',
        ),
        'weekly-update' => array(
            'title' => 'Weekly Update',
            'slug' => 'weekly-update',
            'content' => '[surfside_staff_weekly_update]',
            'parent' => 'dashboard',
        ),
        'calendar' => array(
            'title' => 'Calendar',
            'slug' => 'calendar',
            'content' => '[surfside_staff_calendar]',
            'parent' => 'dashboard',
        ),
        'access' => array(
            'title' => 'Staff Access',
            'slug' => 'access',
            'content' => '[surfside_tools_permissions]',
            'parent' => 'dashboard',
        ),
        'mobile-app' => array(
            'title' => 'Manage Mobile App',
            'slug' => 'mobile-app',
            'content' => '[surfside_staff_mobile_app]',
            'parent' => 'dashboard',
        ),
        'mobile-app-home' => array(
            'title' => 'Home Experience',
            'slug' => 'home-experience',
            'content' => '[surfside_staff_mobile_app_home]',
            'parent' => 'mobile-app',
        ),
        'mobile-app-featured' => array(
            'title' => 'Featured Announcement',
            'slug' => 'featured-announcement',
            'content' => '[surfside_staff_featured_announcement]',
            'parent' => 'mobile-app',
        ),
        'mobile-app-push' => array(
            'title' => 'Push Notifications',
            'slug' => 'push-notifications',
            'content' => '[surfside_staff_push_notifications]',
            'parent' => 'mobile-app',
        ),
        'site-management' => array(
            'title' => 'Site Management',
            'slug' => 'site-management',
            'content' => '[surfside_staff_site_management]',
            'parent' => 'dashboard',
        ),
        'site-streaming' => array(
            'title' => 'Streaming',
            'slug' => 'site-streaming',
            'content' => '[surfside_staff_site_information section="streaming"]',
            'parent' => 'dashboard',
        ),
        'site-navigation' => array(
            'title' => 'Navigation',
            'slug' => 'site-navigation',
            'content' => '[surfside_staff_site_information section="navigation"]',
            'parent' => 'dashboard',
        ),
        'site-ministries' => array(
            'title' => 'Ministries',
            'slug' => 'site-ministries',
            'content' => '[surfside_staff_ministries_manager]',
            'parent' => 'dashboard',
        ),
        'homepage' => array(
            'title' => 'Manage Homepage',
            'slug' => 'homepage',
            'content' => '[surfside_staff_homepage]',
            'parent' => 'dashboard',
        ),
        'site-settings' => array(
            'title' => 'Church Settings',
            'slug' => 'site-settings',
            'content' => '[surfside_staff_site_settings]',
            'parent' => 'dashboard',
        ),
        'surfside-information' => array(
            'title' => 'Surfside Information',
            'slug' => 'surfside-information',
            'content' => '[surfside_staff_site_information]',
            'parent' => 'dashboard',
        ),
        'contact-routing' => array(
            'title' => 'Manage Contact Routing',
            'slug' => 'contact-routing',
            'content' => '[surfside_staff_contact_management]',
            'parent' => 'dashboard',
        ),
        'settings' => array(
            'title' => 'Settings',
            'slug' => 'settings',
            'content' => '[surfside_staff_settings]',
            'parent' => 'dashboard',
        ),
    );
}

function surfside_tools_staff_page_managed_shortcode_tags() {
    static $tags = null;
    if ($tags !== null) {
        return $tags;
    }

    $tags = array();
    foreach (surfside_tools_staff_page_definitions() as $definition) {
        if (preg_match('/^\[([A-Za-z0-9_-]+)/', trim((string) $definition['content']), $matches)) {
            $tags[] = $matches[1];
        }
    }

    return array_values(array_unique($tags));
}

function surfside_tools_staff_page_content_is_managed($content) {
    $content = (string) $content;
    if (trim($content) === '') {
        return true;
    }

    foreach (surfside_tools_staff_page_managed_shortcode_tags() as $tag) {
        if (has_shortcode($content, $tag)) {
            return true;
        }
    }

    return false;
}

function surfside_tools_staff_page_path($slug, $parent_id = 0) {
    $slug = sanitize_title($slug);
    if (!$parent_id) {
        return $slug;
    }

    $parent_uri = trim((string) get_page_uri($parent_id), '/');
    return trim($parent_uri . '/' . $slug, '/');
}

/**
 * Create or reconcile one plugin-managed staff page.
 *
 * Custom page content is left untouched if a site owner has replaced the
 * plugin shortcode manually.
 */
function surfside_tools_provision_staff_page($definition, $parent_id, &$changed) {
    $path = surfside_tools_staff_page_path($definition['slug'], $parent_id);
    $existing = get_page_by_path($path, OBJECT, 'page');

    if ($existing instanceof WP_Post) {
        if (!surfside_tools_staff_page_content_is_managed($existing->post_content)) {
            return (int) $existing->ID;
        }

        $needs_update =
            (string) $existing->post_title !== (string) $definition['title'] ||
            trim((string) $existing->post_content) !== trim((string) $definition['content']) ||
            (string) $existing->post_status !== 'publish' ||
            (int) $existing->post_parent !== (int) $parent_id;

        if ($needs_update) {
            $result = wp_update_post(array(
                'ID' => $existing->ID,
                'post_title' => $definition['title'],
                'post_content' => $definition['content'],
                'post_status' => 'publish',
                'post_parent' => $parent_id,
            ), true);

            if (is_wp_error($result)) {
                return $result;
            }
            $changed = true;
        }

        return (int) $existing->ID;
    }

    $result = wp_insert_post(array(
        'post_title' => $definition['title'],
        'post_name' => $definition['slug'],
        'post_content' => $definition['content'],
        'post_status' => 'publish',
        'post_type' => 'page',
        'post_parent' => $parent_id,
    ), true);

    if (is_wp_error($result)) {
        return $result;
    }

    $changed = true;
    return (int) $result;
}

/**
 * Run only when the staff-page schema changes.
 */
function surfside_tools_provision_staff_pages() {
    if (!is_admin()) {
        return;
    }

    $installed_schema = (int) get_option(SURFSIDE_TOOLS_STAFF_PAGES_SCHEMA_OPTION, 0);
    if ($installed_schema >= SURFSIDE_TOOLS_STAFF_PAGES_SCHEMA_VERSION) {
        return;
    }

    $ids = array();
    $changed = false;

    foreach (surfside_tools_staff_page_definitions() as $key => $definition) {
        $parent_key = (string) ($definition['parent'] ?? '');
        $parent_id = $parent_key !== '' ? (int) ($ids[$parent_key] ?? 0) : 0;

        if ($parent_key !== '' && !$parent_id) {
            return;
        }

        $page_id = surfside_tools_provision_staff_page($definition, $parent_id, $changed);
        if (is_wp_error($page_id) || !$page_id) {
            return;
        }
        $ids[$key] = (int) $page_id;
    }

    update_option(SURFSIDE_TOOLS_STAFF_PAGES_SCHEMA_OPTION, SURFSIDE_TOOLS_STAFF_PAGES_SCHEMA_VERSION, false);

    if ($changed) {
        flush_rewrite_rules(false);
    }
}

/* Retire the older per-module page creation/repair hooks. */
remove_action('admin_init', 'surfside_tools_ensure_staff_dashboard_pages');
remove_action('admin_init', 'surfside_tools_ensure_frontend_settings_page', 25);
remove_action('admin_init', 'surfside_tools_ensure_homepage_staff_page', 30);
remove_action('init', 'surfside_tools_repair_site_information_staff_page', 70);
remove_action('init', 'surfside_tools_ensure_site_management_pages', 75);
remove_action('init', 'surfside_tools_ensure_mobile_app_page', 80);
remove_action('init', 'surfside_tools_ensure_contact_management_page', 81);

add_action('admin_init', 'surfside_tools_provision_staff_pages', 5);
