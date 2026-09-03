<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * MM6 Surfside Tools permissions.
 *
 * Firebase remains the identity provider. Surfside Tools owns authorization
 * by Firebase UID, while locked bridge users supply the legacy WordPress
 * capabilities that existing dashboard modules still expect during front-end
 * requests.
 */

function surfside_tools_permissions_option_name() {
    return 'surfside_tools_firebase_permissions';
}

function surfside_tools_get_permissions() {
    $permissions = get_option(surfside_tools_permissions_option_name(), array());
    return is_array($permissions) ? $permissions : array();
}

function surfside_tools_get_permission($uid) {
    $permissions = surfside_tools_get_permissions();
    return isset($permissions[$uid]) && is_array($permissions[$uid]) ? $permissions[$uid] : null;
}

function surfside_tools_permission_role_is_active($role) {
    return in_array($role, array('admin', 'staff'), true);
}

function surfside_tools_save_permission($uid, $record) {
    $uid = sanitize_text_field($uid);
    if (!$uid) {
        return false;
    }

    $permissions = surfside_tools_get_permissions();
    $permissions[$uid] = array(
        'uid' => $uid,
        'email' => sanitize_email($record['email'] ?? ''),
        'name' => sanitize_text_field($record['name'] ?? ''),
        'role' => in_array(($record['role'] ?? ''), array('admin', 'staff', 'pending', 'disabled'), true) ? $record['role'] : 'pending',
        'updated' => time(),
    );
    update_option(surfside_tools_permissions_option_name(), $permissions, false);
    return $permissions[$uid];
}

function surfside_tools_current_firebase_permission() {
    $session = function_exists('surfside_tools_get_firebase_staff_session') ? surfside_tools_get_firebase_staff_session() : null;
    if (!$session || empty($session['uid'])) {
        return null;
    }
    return surfside_tools_get_permission($session['uid']);
}

function surfside_tools_current_user_is_tools_admin() {
    $permission = surfside_tools_current_firebase_permission();
    if ($permission && ($permission['role'] ?? '') === 'admin') {
        return true;
    }

    if (function_exists('surfside_tools_wp_staff_session_user') && function_exists('surfside_tools_wp_tools_role')) {
        $session_user = surfside_tools_wp_staff_session_user();
        if ($session_user instanceof WP_User && surfside_tools_wp_tools_role($session_user) === 'admin') {
            return true;
        }
    }

    if (is_user_logged_in() && function_exists('surfside_tools_wp_tools_role')) {
        $current_user = wp_get_current_user();
        if ($current_user instanceof WP_User && $current_user->exists() && surfside_tools_wp_tools_role($current_user) === 'admin') {
            return true;
        }
    }

    return false;
}

function surfside_tools_bridge_role_for_permission($role) {
    return $role === 'admin' ? 'surfside_tools_bridge_admin' : 'surfside_tools_bridge_staff';
}

add_action('init', function () {
    add_role('surfside_tools_bridge_staff', 'Surfside Tools Staff', array(
        'read' => true,
        'upload_files' => true,
    ));
    add_role('surfside_tools_bridge_admin', 'Surfside Tools Admin', array(
        'read' => true,
        'upload_files' => true,
        'manage_options' => true,
    ));
}, 2);

function surfside_tools_bridge_username($uid) {
    return 'surfside_firebase_' . substr(hash('sha256', $uid), 0, 24);
}

function surfside_tools_get_or_create_bridge_user($permission) {
    if (!$permission || empty($permission['uid']) || !surfside_tools_permission_role_is_active($permission['role'] ?? '')) {
        return null;
    }

    $username = surfside_tools_bridge_username($permission['uid']);
    $user = get_user_by('login', $username);
    $role = surfside_tools_bridge_role_for_permission($permission['role']);

    if (!$user) {
        $user_id = wp_insert_user(array(
            'user_login' => $username,
            'user_pass' => wp_generate_password(64, true, true),
            'display_name' => !empty($permission['name']) ? $permission['name'] : 'Surfside Staff',
            'role' => $role,
        ));
        if (is_wp_error($user_id)) {
            return null;
        }
        update_user_meta($user_id, 'surfside_tools_firebase_uid', $permission['uid']);
        $user = get_user_by('id', $user_id);
    }

    if ($user instanceof WP_User && !in_array($role, (array) $user->roles, true)) {
        $user->set_role($role);
    }

    return $user instanceof WP_User ? $user : null;
}

add_filter('authenticate', function ($user, $username, $password) {
    if (is_string($username) && strpos($username, 'surfside_firebase_') === 0) {
        return new WP_Error('surfside_bridge_login_blocked', 'This account can only be used through Surfside Tools.');
    }
    return $user;
}, 5, 3);

function surfside_tools_permissions_page_url() {
    $page = get_page_by_path('dashboard/access');
    return $page ? get_permalink($page) : home_url('/dashboard/access/');
}

function surfside_tools_permissions_count_admins($permissions) {
    $count = 0;
    foreach ($permissions as $record) {
        if (($record['role'] ?? '') === 'admin') {
            $count++;
        }
    }
    return $count;
}

add_filter('the_content', function ($content) {
    if (!is_page('dashboard') || !surfside_tools_current_user_is_tools_admin()) {
        return $content;
    }
    $card = '<div class="surfside-staff-shell" style="padding-top:0"><div class="surfside-staff-card" style="min-height:auto"><h2>Staff Access</h2><p>Manage Firebase-based Tools administrators and staff.</p><div class="surfside-staff-actions"><a class="surfside-staff-button-secondary" href="' . esc_url(surfside_tools_permissions_page_url()) . '">Manage Access <span class="surfside-staff-arrow">→</span></a></div></div></div>';
    return $content . $card;
}, 30);
