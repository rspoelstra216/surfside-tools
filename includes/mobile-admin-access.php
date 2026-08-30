<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Authenticated mobile-app access lookup.
 *
 * The app sends its Firebase ID token as a Bearer token. Surfside Tools verifies
 * the token server-side and returns only the current Tools authorization state.
 */
function surfside_tools_mobile_admin_bearer_token(WP_REST_Request $request) {
    $header = trim((string) $request->get_header('authorization'));
    if ($header === '' || stripos($header, 'Bearer ') !== 0) {
        return '';
    }

    return trim(substr($header, 7));
}

function surfside_tools_mobile_admin_access(WP_REST_Request $request) {
    $token = surfside_tools_mobile_admin_bearer_token($request);
    if ($token === '') {
        return new WP_Error(
            'surfside_mobile_auth_required',
            'A Firebase sign-in token is required.',
            array('status' => 401)
        );
    }

    $claims = surfside_tools_verify_firebase_id_token($token);
    if (is_wp_error($claims)) {
        return new WP_Error(
            'surfside_mobile_auth_invalid',
            'Your Surfside sign-in could not be verified.',
            array('status' => 401)
        );
    }

    $uid = sanitize_text_field($claims['sub'] ?? '');
    if ($uid === '') {
        return new WP_Error(
            'surfside_mobile_identity_missing',
            'Your Surfside account is missing an identity identifier.',
            array('status' => 401)
        );
    }

    $permission = surfside_tools_get_permission($uid);
    $role = $permission['role'] ?? 'none';

    return rest_ensure_response(array(
        'authenticated' => true,
        'role' => in_array($role, array('admin', 'staff', 'pending', 'disabled'), true) ? $role : 'none',
        'is_staff' => in_array($role, array('admin', 'staff'), true),
        'is_admin' => $role === 'admin',
    ));
}

add_action('rest_api_init', function () {
    register_rest_route('surfside/v1', '/mobile-admin/access', array(
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'surfside_tools_mobile_admin_access',
        'permission_callback' => '__return_true',
    ));
});
