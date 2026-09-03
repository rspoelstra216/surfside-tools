<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Staff Access account model.
 *
 * WordPress usernames/passwords are credentials that may later be used to enter
 * Surfside Tools, while WordPress roles only control WordPress site access.
 * Firebase identities remain a separate optional sign-in identity and are
 * explicitly linked to a WordPress person instead of being matched by email.
 */

function surfside_tools_firebase_wp_links_option_name() {
    return 'surfside_tools_firebase_wp_links';
}

function surfside_tools_get_firebase_wp_links() {
    $links = get_option(surfside_tools_firebase_wp_links_option_name(), array());
    return is_array($links) ? $links : array();
}

function surfside_tools_get_linked_wp_user_id($uid) {
    $links = surfside_tools_get_firebase_wp_links();
    return isset($links[$uid]) ? absint($links[$uid]) : 0;
}

function surfside_tools_set_firebase_wp_link($uid, $wp_user_id) {
    $uid = sanitize_text_field($uid);
    $wp_user_id = absint($wp_user_id);
    if (!$uid) {
        return false;
    }

    $links = surfside_tools_get_firebase_wp_links();
    if ($wp_user_id) {
        foreach ($links as $linked_uid => $linked_user_id) {
            if ($linked_uid !== $uid && absint($linked_user_id) === $wp_user_id) {
                unset($links[$linked_uid]);
            }
        }
        $links[$uid] = $wp_user_id;
    } else {
        unset($links[$uid]);
    }

    update_option(surfside_tools_firebase_wp_links_option_name(), $links, false);
    return true;
}

function surfside_tools_wp_user_for_permission($record) {
    $uid = sanitize_text_field($record['uid'] ?? '');
    if (!$uid) {
        return null;
    }

    $user_id = surfside_tools_get_linked_wp_user_id($uid);
    if (!$user_id) {
        return null;
    }

    $user = get_user_by('id', $user_id);
    if (!$user instanceof WP_User || strpos((string) $user->user_login, 'surfside_firebase_') === 0) {
        return null;
    }

    return $user;
}

function surfside_tools_wp_public_roles() {
    $roles = wp_roles()->roles;
    unset($roles['surfside_tools_bridge_staff'], $roles['surfside_tools_bridge_admin']);
    return $roles;
}

function surfside_tools_wp_real_users() {
    $users = get_users(array('orderby' => 'display_name', 'order' => 'ASC'));
    return array_values(array_filter($users, function ($user) {
        return $user instanceof WP_User && strpos((string) $user->user_login, 'surfside_firebase_') !== 0;
    }));
}

function surfside_tools_wp_active_admin_count() {
    return count(get_users(array('role' => 'administrator', 'fields' => 'ids')));
}

function surfside_tools_wp_primary_role($user) {
    if (!$user instanceof WP_User || empty($user->roles)) {
        return 'none';
    }
    return (string) reset($user->roles);
}

function surfside_tools_wp_update_access_by_user_id($user_id, $requested_role) {
    $user = get_user_by('id', absint($user_id));
    if (!$user instanceof WP_User || strpos((string) $user->user_login, 'surfside_firebase_') === 0) {
        return 'That WordPress user is not available.';
    }

    $roles = surfside_tools_wp_public_roles();
    $is_admin = in_array('administrator', (array) $user->roles, true);

    if ($requested_role === 'none') {
        if ($is_admin && surfside_tools_wp_active_admin_count() <= 1) {
            return 'WordPress must always have at least one administrator.';
        }

        // Keep the WordPress username/password credential intact, but remove all
        // WordPress site capabilities. Tools authorization remains independent.
        $user->set_role('');
        return 'WordPress site access removed. The username and password remain available for Surfside Tools authentication.';
    }

    if (!isset($roles[$requested_role])) {
        return 'That WordPress role is not available.';
    }

    if ($is_admin && $requested_role !== 'administrator' && surfside_tools_wp_active_admin_count() <= 1) {
        return 'WordPress must always have at least one administrator.';
    }

    $user->set_role($requested_role);
    return 'WordPress site access updated.';
}

function surfside_tools_permission_uid_for_wp_user($wp_user_id) {
    foreach (surfside_tools_get_firebase_wp_links() as $uid => $linked_user_id) {
        if (absint($linked_user_id) === absint($wp_user_id)) {
            return sanitize_text_field($uid);
        }
    }
    return '';
}
