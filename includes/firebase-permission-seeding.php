<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * New Google/Firebase identities must be explicitly approved in Staff Access.
 *
 * Existing UID-based permission records keep their current role. For a new UID,
 * create a pending record without inferring Tools access from a matching
 * WordPress email address or WordPress role.
 */
add_filter('rest_pre_dispatch', function ($result, $server, $request) {
    if ($result !== null || $request->get_route() !== '/surfside-tools/v1/staff-auth/session' || $request->get_method() !== 'POST') {
        return $result;
    }

    $token = (string) $request->get_param('idToken');
    $claims = surfside_tools_verify_firebase_id_token($token);
    if (is_wp_error($claims)) {
        return $claims;
    }

    $uid = sanitize_text_field($claims['sub'] ?? '');
    $email = sanitize_email($claims['email'] ?? '');
    $name = sanitize_text_field($claims['name'] ?? '');
    if (!$uid || !$email) {
        return new WP_Error('firebase_identity', 'Your Firebase account is missing the identity information Surfside Tools needs.', array('status' => 403));
    }

    $permission = surfside_tools_get_permission($uid);
    if (!$permission) {
        $permission = surfside_tools_save_permission($uid, array(
            'email' => $email,
            'name' => $name,
            'role' => 'pending',
        ));
    }

    $role = $permission['role'] ?? 'pending';
    if (!surfside_tools_permission_role_is_active($role)) {
        $message = $role === 'disabled'
            ? 'Your Surfside Tools access has been disabled.'
            : 'Your account is waiting for a Surfside Tools administrator to approve access.';
        return new WP_Error('surfside_tools_permission_required', $message, array('status' => 403));
    }

    surfside_tools_get_or_create_bridge_user($permission);
    surfside_tools_set_firebase_staff_session($claims);

    return rest_ensure_response(array(
        'authenticated' => true,
        'uid' => $uid,
        'email' => $email,
        'name' => $name,
        'role' => $role,
    ));
}, 4, 3);
