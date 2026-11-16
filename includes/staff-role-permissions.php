<?php
/**
 * Surfside Tools role permissions. These are Tools roles, not WordPress roles.
 *
 * Built-in Admin is immutable; Staff inherits the v507 option until the first
 * role-matrix save. Custom roles start with no permissions. Unknown/removed
 * roles fail closed rather than inheriting the Staff permissions.
 */
if (!defined('ABSPATH')) { exit; }

function surfside_tools_staff_permission_definitions() {
    return array(
        'weekly'             => array('label'=>'Weekly Update', 'group'=>'Content & Publishing', 'legacy'=>'weekly', 'description'=>'Prepare and publish weekly notes and announcements.'),
        'featured'           => array('label'=>'Featured Announcement', 'group'=>'Content & Publishing', 'legacy'=>'weekly', 'description'=>'Schedule featured content for the app home screen.'),
        'archived_notes'     => array('label'=>'Archived Sermon Notes', 'group'=>'Content & Publishing', 'legacy'=>'messages', 'description'=>'Review earlier weekly sermon notes.'),
        'message_archive'    => array('label'=>'Message Archive & Sermon Series', 'group'=>'Messages & Media', 'legacy'=>'messages', 'description'=>'Manage messages and sermon series.'),
        'weekly_media'       => array('label'=>'Weekly Media', 'group'=>'Messages & Media', 'legacy'=>'messages', 'description'=>'Manage weekly sermon and podcast media.'),
        'live_slides'        => array('label'=>'Live Slides', 'group'=>'Messages & Media', 'legacy'=>'messages', 'description'=>'Configure the mobile live-slide viewer.'),
        'calendar'           => array('label'=>'Calendar Manager', 'group'=>'Events & Serving', 'legacy'=>'calendar', 'description'=>'Manage church events and event details.'),
        'volunteers'         => array('label'=>'Volunteer Needs', 'group'=>'Events & Serving', 'legacy'=>'calendar', 'description'=>'Maintain volunteer opportunities.'),
        'ministries'         => array('label'=>'Ministries', 'group'=>'Events & Serving', 'legacy'=>'ministries', 'description'=>'Edit and publish ministries.'),
        'prayers'            => array('label'=>'Prayer Requests', 'group'=>'Community & Communications', 'legacy'=>'community', 'description'=>'Review prayer requests and praises.'),
        'push'               => array('label'=>'Push Notifications', 'group'=>'Community & Communications', 'legacy'=>'community', 'description'=>'Send announcements through the app.'),
        'homepage'           => array('label'=>'Homepage Photos', 'group'=>'Appearance', 'legacy'=>'appearance', 'description'=>'Manage website home images.'),
        'app_home'           => array('label'=>'App Home Experience', 'group'=>'Appearance', 'legacy'=>'appearance', 'description'=>'Change the mobile home image and crop.'),
        'information'        => array('label'=>'Surfside Information', 'group'=>'Church Information', 'legacy'=>'information', 'description'=>'Edit church contact, service and location information.'),
        'website_navigation' => array('label'=>'Website Navigation', 'group'=>'Church Information', 'legacy'=>'information', 'description'=>'Change website header/footer links.'),
    );
}

/**
 * Additional Tools capabilities are delegable and independently enforced.
 * They default OFF for non-admins; legacy #507 roles never inherit them.
 */
function surfside_tools_staff_advanced_permission_definitions() {
    return array(
        'streaming' => array('label'=>'Streaming Configuration', 'group'=>'Messages & Media', 'description'=>'Cloudflare recording, Watch Live and Twitch settings.', 'default'=>false),
        'calendar_defaults' => array('label'=>'Calendar Defaults', 'group'=>'Events & Serving', 'description'=>'Calendar-wide behavior and suggested duration.', 'default'=>false),
        'saved_places' => array('label'=>'Saved Places Administration', 'group'=>'Events & Serving', 'description'=>'Add, hide, remove and restore saved locations.', 'default'=>false),
        'contact_routing' => array('label'=>'Contact Routing', 'group'=>'Community & Communications', 'description'=>'Manage contact and confidential pastoral recipient addresses.', 'default'=>false),
        'design_settings' => array('label'=>'Reveal & Countdown Styling', 'group'=>'Appearance', 'description'=>'Edit global custom CSS overrides.', 'default'=>false),
        'integrations' => array('label'=>'All Integrations', 'group'=>'Administration & Integrations', 'description'=>'Manage every integration below, including credentials (but not role management).', 'default'=>false),
        'google_maps' => array('label'=>'Google Maps / Places', 'group'=>'Administration & Integrations', 'description'=>'Google Maps API key and location configuration.', 'default'=>false),
        'youtube_integration' => array('label'=>'YouTube Integration', 'group'=>'Administration & Integrations', 'description'=>'YouTube API keys and archive synchronization settings.', 'default'=>false),
        'youversion_integration' => array('label'=>'YouVersion Integration', 'group'=>'Administration & Integrations', 'description'=>'Bible API and translation integration settings.', 'default'=>false),
        'giving' => array('label'=>'Giving Settings', 'group'=>'Administration & Integrations', 'description'=>'Giving destination used by the website and app.', 'default'=>false),
        'turnstile' => array('label'=>'Cloudflare Turnstile', 'group'=>'Administration & Integrations', 'description'=>'Anti-spam site and secret keys.', 'default'=>false),
    );
}

/** Never grant role or identity management to a non-Admin Tools role. */
function surfside_tools_staff_protected_permission_definitions() {
    return array(
        'staff_access' => array('label'=>'Staff Access & Role Management', 'group'=>'Administration & Integrations', 'description'=>'Assign roles, link identities and change permissions. Tools Admin only.'),
    );
}

/** One source of truth for assignable permissions. */
function surfside_tools_staff_all_permission_definitions() {
    return array_merge(
        surfside_tools_staff_permission_definitions(),
        surfside_tools_staff_advanced_permission_definitions()
    );
}

function surfside_tools_custom_roles() {
    $stored = get_option('surfside_tools_custom_roles', array());
    if (!is_array($stored)) return array();
    $roles = array();
    foreach ($stored as $slug => $label) {
        $slug = sanitize_key((string) $slug);
        if (in_array($slug, array('admin','staff','disabled','pending'), true) || !$slug) continue;
        if (!is_string($label) || trim($label) === '') continue;
        $roles[$slug] = sanitize_text_field($label);
    }
    return $roles;
}

function surfside_tools_role_definitions() {
    return array_merge(array('admin'=>'Tools Admin', 'staff'=>'Tools Staff'), surfside_tools_custom_roles());
}

function surfside_tools_role_is_assignable($role) {
    return isset(surfside_tools_role_definitions()[sanitize_key((string) $role)]);
}

/** Returning false for removed roles prevents stale assignments from inheriting Staff. */
function surfside_tools_staff_role_permissions($role = 'staff') {
    $role = sanitize_key((string) $role);
    $definitions = surfside_tools_staff_all_permission_definitions();
    $values = array_fill_keys(array_keys($definitions), false);
    if ($role === 'admin') return array_fill_keys(array_keys($definitions), true);
    if (!surfside_tools_role_is_assignable($role)) return $values;

    $matrix = get_option('surfside_tools_role_matrix', array());
    if (is_array($matrix) && isset($matrix[$role]) && is_array($matrix[$role])) {
        foreach ($values as $key => $unused) $values[$key] = !empty($matrix[$role][$key]);
        return $values;
    }

    // Read-through migration: preserve v507 Staff choices without a write on page load.
    if ($role === 'staff') {
        $old = get_option('surfside_tools_staff_role_permissions', array());
        $old = is_array($old) ? $old : array();
        foreach ($definitions as $key => $def) {
            if (!isset($def['legacy'])) { $values[$key] = !empty($def['default']); continue; }
            $legacy = $def['legacy'];
            $values[$key] = array_key_exists($legacy, $old) ? (bool) $old[$legacy] : true;
        }
    }
    return $values;
}

function surfside_tools_current_tools_role() {
    if (!is_user_logged_in() || !current_user_can('upload_files')) return 'disabled';
    if (function_exists('surfside_tools_current_firebase_permission')) {
        $record = surfside_tools_current_firebase_permission();
        if ($record && isset($record['role'])) return sanitize_key((string) $record['role']);
    }
    if (function_exists('surfside_tools_wp_staff_session_user') && function_exists('surfside_tools_wp_tools_role')) {
        $user = surfside_tools_wp_staff_session_user();
        if ($user instanceof WP_User) return surfside_tools_wp_tools_role($user);
    }
    if (function_exists('surfside_tools_wp_tools_role')) {
        $user = wp_get_current_user();
        if ($user instanceof WP_User) return surfside_tools_wp_tools_role($user);
    }
    return 'disabled';
}

/** A Tools role only grants the explicitly checked capability (no WordPress manage_options). */
function surfside_tools_staff_has_permission($key) {
    $role = surfside_tools_current_tools_role();
    if ($role === 'admin') return true;
    if (!surfside_tools_role_is_assignable($role)) return false;
    $grants = surfside_tools_staff_role_permissions($role);
    if ($key === 'site_settings_page') {
        foreach (array('information','ministries','contact_routing','streaming','integrations',
            'google_maps','youtube_integration','youversion_integration','giving','turnstile',
            'design_settings') as $item) {
            if (!empty($grants[$item])) return true;
        }
        return false;
    }
    if ($key === 'integrations_page') {
        foreach (array('integrations','google_maps','youtube_integration','youversion_integration',
            'giving','turnstile','design_settings') as $item) {
            if (!empty($grants[$item])) return true;
        }
        return false;
    }
    if ($key === 'calendar_landing') {
        return !empty($grants['calendar']) || !empty($grants['calendar_defaults'])
            || !empty($grants['saved_places']);
    }
    if ($key === 'staff_access') return false;
    if (empty($grants[$key])) {
        return in_array($key, array('google_maps','youtube_integration','youversion_integration',
            'giving','turnstile'), true) && !empty($grants['integrations']);
    }
    return true;
}

function surfside_tools_staff_permission_for_route($path) {
    $paths = array(
        'dashboard/weekly-update'=>'weekly',
        'dashboard/mobile-app/featured-announcement'=>'featured',
        'dashboard/mobile-app/live-slides'=>'live_slides',
        'dashboard/mobile-app/push-notifications'=>'push',
        'dashboard/homepage'=>'homepage',
        'dashboard/mobile-app/home-experience'=>'app_home',
        'dashboard/site-ministries'=>'ministries',
        'dashboard/surfside-information'=>'information',
        'dashboard/site-navigation'=>'website_navigation',
        'dashboard/site-streaming'=>'streaming',
        'dashboard/site-settings/streaming'=>'streaming',
        'dashboard/site-settings'=>'site_settings_page',
        'dashboard/settings'=>'integrations_page',
        'dashboard/access'=>'staff_access',
        'dashboard/contact-routing'=>'contact_routing',
    );
    if (isset($paths[$path])) return $paths[$path];
    $view = isset($_GET['view']) && is_string($_GET['view']) ? sanitize_key(wp_unslash($_GET['view'])) : '';
    $tool = isset($_GET['tool']) && is_string($_GET['tool']) ? sanitize_key(wp_unslash($_GET['tool'])) : '';
    if ($path === 'dashboard/calendar') {
        if ($view === 'defaults') return 'calendar_defaults';
        if ($view === 'places') return 'saved_places';
        return 'calendar_landing';
    }
    if ($path === 'dashboard/site-management') {
        return $view === 'roku-media' ? 'weekly_media' : 'message_archive';
    }
    if ($path === 'dashboard') {
        if ($view === 'sermon-archive') return 'archived_notes';
        if ($view === 'member-engagement') return $tool === 'volunteer-needs' ? 'volunteers' : 'prayers';
    }
    return '';
}

/** Direct dashboard routes and POST actions use the same checks as navigation. */
add_action('template_redirect', function () {
    if (!is_page() || is_admin()) return;
    $id = get_queried_object_id();
    if (!$id) return;
    $required = surfside_tools_staff_permission_for_route(trim((string) get_page_uri($id), '/'));
    if ($required && is_user_logged_in() && !surfside_tools_staff_has_permission($required)) {
        wp_die(esc_html__('You do not have permission to use this Surfside Tools feature.', 'surfside-tools'), '', array('response'=>403));
    }
}, 5);

add_filter('pre_do_shortcode_tag', function ($result, $tag, $attr = array()) {
    $map = array(
        'surfside_staff_weekly_update'=>'weekly',
        'surfside_staff_featured_announcement'=>'featured',
        'surfside_staff_live_slides'=>'live_slides',
        'surfside_staff_calendar'=>'calendar_landing',
        'surfside_staff_push_notifications'=>'push',
        'surfside_staff_homepage'=>'homepage',
        'surfside_staff_mobile_app_home'=>'app_home',
        'surfside_staff_ministries_manager'=>'ministries',
        'surfside_staff_contact_management'=>'contact_routing',
        'surfside_staff_streaming'=>'streaming',
        'surfside_staff_site_settings'=>'site_settings_page',
        'surfside_staff_settings'=>'integrations_page',
        'surfside_tools_permissions'=>'staff_access',
    );
    if ($tag === 'surfside_staff_site_information') {
        $section = is_array($attr) ? sanitize_key((string) ($attr['section'] ?? 'information')) : 'information';
        $required = $section === 'streaming' ? 'streaming' : ($section === 'navigation' ? 'website_navigation' : 'information');
    } else {
        $required = $map[$tag] ?? '';
    }
    if ($required && !surfside_tools_staff_has_permission($required)) {
        return '<p>You do not have permission to use this Surfside Tools feature.</p>';
    }
    return $result;
}, 10, 3);

/** Administrators cannot remove a custom role while it is assigned. */
function surfside_tools_custom_role_is_assigned($role) {
    if (function_exists('surfside_tools_wp_real_users') && function_exists('surfside_tools_wp_tools_role')) {
        foreach (surfside_tools_wp_real_users() as $user) {
            if (surfside_tools_wp_tools_role($user) === $role) return true;
        }
    }
    if (function_exists('surfside_tools_get_permissions')) {
        foreach (surfside_tools_get_permissions() as $record) {
            if (($record['role'] ?? '') === $role) return true;
        }
    }
    return false;
}
