<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Firebase identity verification and Surfside Tools session management.
 *
 * Firebase proves identity. Surfside Tools authorizes by verified Firebase UID
 * using the canonical Tools permission record.
 */

function surfside_tools_firebase_config() {
    return array(
        'apiKey' => 'AIzaSyBC7_xTHkGZMxqrkrYYU1PJt7mO0syHj8c',
        'authDomain' => 'surfside-community-fellowship.firebaseapp.com',
        'projectId' => 'surfside-community-fellowship',
        'appId' => '',
    );
}

function surfside_tools_firebase_base64url_decode($value) {
    $remainder = strlen($value) % 4;
    if ($remainder) {
        $value .= str_repeat('=', 4 - $remainder);
    }
    return base64_decode(strtr($value, '-_', '+/'));
}

function surfside_tools_firebase_certificates() {
    $cached = get_transient('surfside_tools_firebase_certs');
    if (is_array($cached) && $cached) {
        return $cached;
    }

    $response = wp_remote_get(
        'https://www.googleapis.com/robot/v1/metadata/x509/securetoken@system.gserviceaccount.com',
        array('timeout' => 10)
    );

    if (is_wp_error($response)) {
        return $response;
    }

    $certs = json_decode(wp_remote_retrieve_body($response), true);
    if (!is_array($certs) || !$certs) {
        return new WP_Error('firebase_certs', 'Unable to load Firebase signing certificates.');
    }

    set_transient('surfside_tools_firebase_certs', $certs, HOUR_IN_SECONDS);
    return $certs;
}

function surfside_tools_verify_firebase_id_token($token) {
    if (!is_string($token) || substr_count($token, '.') !== 2) {
        return new WP_Error('firebase_token', 'Invalid Firebase token.');
    }

    list($header64, $payload64, $signature64) = explode('.', $token, 3);
    $header = json_decode(surfside_tools_firebase_base64url_decode($header64), true);
    $payload = json_decode(surfside_tools_firebase_base64url_decode($payload64), true);
    $signature = surfside_tools_firebase_base64url_decode($signature64);

    if (!is_array($header) || !is_array($payload) || ($header['alg'] ?? '') !== 'RS256' || empty($header['kid'])) {
        return new WP_Error('firebase_token', 'Firebase token header is invalid.');
    }

    $certs = surfside_tools_firebase_certificates();
    if (is_wp_error($certs)) {
        return $certs;
    }

    if (empty($certs[$header['kid']])) {
        delete_transient('surfside_tools_firebase_certs');
        $certs = surfside_tools_firebase_certificates();
    }

    if (is_wp_error($certs) || empty($certs[$header['kid']])) {
        return new WP_Error('firebase_token', 'Firebase signing key is unavailable.');
    }

    $verified = openssl_verify(
        $header64 . '.' . $payload64,
        $signature,
        $certs[$header['kid']],
        OPENSSL_ALGO_SHA256
    );

    if ($verified !== 1) {
        return new WP_Error('firebase_token', 'Firebase token signature is invalid.');
    }

    $project_id = surfside_tools_firebase_config()['projectId'];
    $now = time();
    $issuer = 'https://securetoken.google.com/' . $project_id;

    if (($payload['aud'] ?? '') !== $project_id || ($payload['iss'] ?? '') !== $issuer) {
        return new WP_Error('firebase_token', 'Firebase token was issued for a different project.');
    }

    if (empty($payload['sub']) || empty($payload['exp']) || (int) $payload['exp'] <= $now) {
        return new WP_Error('firebase_token', 'Firebase token is expired or incomplete.');
    }

    if (!empty($payload['iat']) && (int) $payload['iat'] > $now + 300) {
        return new WP_Error('firebase_token', 'Firebase token issue time is invalid.');
    }

    return $payload;
}

function surfside_tools_firebase_session_cookie_name() {
    return 'surfside_tools_staff_session';
}

function surfside_tools_firebase_session_signature($encoded) {
    return hash_hmac('sha256', $encoded, wp_salt('auth'));
}

function surfside_tools_set_firebase_staff_session($claims) {
    $session = array(
        'uid' => sanitize_text_field($claims['sub'] ?? ''),
        'email' => sanitize_email($claims['email'] ?? ''),
        'name' => sanitize_text_field($claims['name'] ?? ''),
        'exp' => min((int) ($claims['exp'] ?? time()), time() + HOUR_IN_SECONDS),
    );

    $encoded = rtrim(strtr(base64_encode(wp_json_encode($session)), '+/', '-_'), '=');
    $value = $encoded . '.' . surfside_tools_firebase_session_signature($encoded);

    setcookie(
        surfside_tools_firebase_session_cookie_name(),
        $value,
        array(
            'expires' => $session['exp'],
            'path' => COOKIEPATH ?: '/',
            'domain' => COOKIE_DOMAIN ?: '',
            'secure' => is_ssl(),
            'httponly' => true,
            'samesite' => 'Lax',
        )
    );
}

function surfside_tools_clear_firebase_staff_session() {
    setcookie(
        surfside_tools_firebase_session_cookie_name(),
        '',
        array(
            'expires' => time() - HOUR_IN_SECONDS,
            'path' => COOKIEPATH ?: '/',
            'domain' => COOKIE_DOMAIN ?: '',
            'secure' => is_ssl(),
            'httponly' => true,
            'samesite' => 'Lax',
        )
    );
}

function surfside_tools_get_firebase_staff_session() {
    $cookie = $_COOKIE[surfside_tools_firebase_session_cookie_name()] ?? '';
    if (!$cookie || substr_count($cookie, '.') !== 1) {
        return null;
    }

    list($encoded, $signature) = explode('.', $cookie, 2);
    if (!hash_equals(surfside_tools_firebase_session_signature($encoded), $signature)) {
        return null;
    }

    $decoded = surfside_tools_firebase_base64url_decode($encoded);
    $session = json_decode($decoded, true);
    if (!is_array($session) || empty($session['uid']) || empty($session['email']) || empty($session['exp']) || (int) $session['exp'] <= time()) {
        return null;
    }

    return $session;
}


add_action('rest_api_init', function () {
    register_rest_route('surfside-tools/v1', '/staff-auth/session', array(
        'methods' => 'POST',
        'permission_callback' => '__return_true',
        'callback' => function (WP_REST_Request $request) {
            $token = (string) $request->get_param('idToken');
            $claims = surfside_tools_verify_firebase_id_token($token);
            if (is_wp_error($claims)) {
                return $claims;
            }

            $uid = sanitize_text_field($claims['sub'] ?? '');
            $email = sanitize_email($claims['email'] ?? '');
            $name = sanitize_text_field($claims['name'] ?? '');
            if (!$uid || !$email) {
                return new WP_Error(
                    'firebase_identity',
                    'Your Firebase account is missing the identity information Surfside Tools needs.',
                    array('status' => 403)
                );
            }

            $permission = surfside_tools_get_permission($uid);
            if (!$permission) {
                $permission = surfside_tools_save_permission($uid, array(
                    'email' => $email,
                    'name' => $name,
                    'role' => 'pending',
                ));
            }

            $role = is_array($permission) ? ($permission['role'] ?? 'pending') : 'pending';
            if (!surfside_tools_permission_role_is_active($role)) {
                $message = $role === 'disabled'
                    ? 'Your Surfside Tools access has been disabled.'
                    : 'Your account is waiting for a Surfside Tools administrator to approve access.';
                return new WP_Error('surfside_tools_permission_required', $message, array('status' => 403));
            }

            $bridge_user = surfside_tools_get_or_create_bridge_user($permission);
            if (!$bridge_user instanceof WP_User) {
                return new WP_Error(
                    'surfside_tools_bridge_user',
                    'Unable to start your Surfside Tools session. Please try again.',
                    array('status' => 500)
                );
            }

            surfside_tools_set_firebase_staff_session($claims);
            return rest_ensure_response(array(
                'authenticated' => true,
                'uid' => $uid,
                'email' => $email,
                'name' => $name,
                'role' => $role,
            ));
        },
    ));

    register_rest_route('surfside-tools/v1', '/staff-auth/session', array(
        'methods' => 'DELETE',
        'permission_callback' => '__return_true',
        'callback' => function () {
            surfside_tools_clear_firebase_staff_session();
            if (function_exists('surfside_tools_clear_wp_staff_session')) {
                surfside_tools_clear_wp_staff_session();
            }
            return rest_ensure_response(array('authenticated' => false));
        },
    ));
});
