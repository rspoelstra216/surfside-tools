<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * MM5 Firebase staff authentication bridge.
 *
 * Firebase proves identity. Until MM6 introduces native Surfside permissions,
 * authorization is deliberately bridged to the existing WordPress user whose
 * email matches the verified Firebase email and who can upload_files.
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

function surfside_tools_firebase_staff_wp_user($session = null) {
    $session = $session ?: surfside_tools_get_firebase_staff_session();
    if (!$session || empty($session['email'])) {
        return null;
    }

    $user = get_user_by('email', $session['email']);
    return $user instanceof WP_User ? $user : null;
}

function surfside_tools_firebase_staff_is_authorized() {
    $session = surfside_tools_get_firebase_staff_session();
    $user = surfside_tools_firebase_staff_wp_user($session);
    return $user && user_can($user, 'upload_files');
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

            $email = sanitize_email($claims['email'] ?? '');
            if (!$email) {
                return new WP_Error('firebase_email', 'Your Firebase account does not provide an email address.', array('status' => 403));
            }

            $user = get_user_by('email', $email);
            if (!$user || !user_can($user, 'upload_files')) {
                return new WP_Error(
                    'firebase_not_staff',
                    'This Firebase account is not yet authorized for Surfside Tools.',
                    array('status' => 403)
                );
            }

            surfside_tools_set_firebase_staff_session($claims);
            return rest_ensure_response(array(
                'authenticated' => true,
                'uid' => sanitize_text_field($claims['sub']),
                'email' => $email,
                'name' => sanitize_text_field($claims['name'] ?? $user->display_name),
            ));
        },
    ));

    register_rest_route('surfside-tools/v1', '/staff-auth/session', array(
        'methods' => 'DELETE',
        'permission_callback' => '__return_true',
        'callback' => function () {
            surfside_tools_clear_firebase_staff_session();
            return rest_ensure_response(array('authenticated' => false));
        },
    ));
});

function surfside_tools_firebase_staff_login_markup($message = 'Sign in with your Surfside account to access staff tools.') {
    $config = surfside_tools_firebase_config();
    $rest_url = rest_url('surfside-tools/v1/staff-auth/session');
    $redirect = get_permalink() ?: home_url('/dashboard/');

    ob_start();
    ?>
    <div class="surfside-staff-login surfside-firebase-login" data-rest-url="<?php echo esc_url($rest_url); ?>" data-redirect="<?php echo esc_url($redirect); ?>">
        <h2>Surfside Staff Login</h2>
        <p><?php echo esc_html($message); ?></p>
        <div class="surfside-firebase-status" role="status" aria-live="polite"></div>
        <button type="button" class="wp-block-button__link wp-element-button surfside-google-login">Continue with Google</button>
        <div class="surfside-login-divider"><span>or use email</span></div>
        <form class="surfside-email-login">
            <label>Email<br><input type="email" name="email" autocomplete="email" required></label>
            <label>Password<br><input type="password" name="password" autocomplete="current-password" required></label>
            <button type="submit" class="wp-block-button__link wp-element-button">Sign In</button>
        </form>
        <p class="surfside-login-note">Use the same Firebase account you use with the Surfside mobile app.</p>
    </div>
    <script type="module">
        import { initializeApp } from 'https://www.gstatic.com/firebasejs/10.14.1/firebase-app.js';
        import { getAuth, GoogleAuthProvider, signInWithEmailAndPassword, signInWithPopup } from 'https://www.gstatic.com/firebasejs/10.14.1/firebase-auth.js';

        const root = document.currentScript.previousElementSibling;
        const config = <?php echo wp_json_encode($config); ?>;
        const app = initializeApp(config);
        const auth = getAuth(app);
        const status = root.querySelector('.surfside-firebase-status');
        const emailForm = root.querySelector('.surfside-email-login');
        const googleButton = root.querySelector('.surfside-google-login');

        const finish = async (user) => {
            status.textContent = 'Signing you in…';
            const idToken = await user.getIdToken(true);
            const response = await fetch(root.dataset.restUrl, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                credentials: 'same-origin',
                body: JSON.stringify({idToken}),
            });
            const body = await response.json();
            if (!response.ok) throw new Error(body.message || 'Unable to start your Surfside Tools session.');
            window.location.assign(root.dataset.redirect);
        };

        const showError = (error) => {
            status.textContent = error?.message || 'Unable to sign in. Please try again.';
        };

        emailForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            status.textContent = 'Signing you in…';
            const data = new FormData(emailForm);
            try {
                const credential = await signInWithEmailAndPassword(auth, String(data.get('email')), String(data.get('password')));
                await finish(credential.user);
            } catch (error) {
                showError(error);
            }
        });

        googleButton.addEventListener('click', async () => {
            status.textContent = 'Opening Google sign in…';
            try {
                const credential = await signInWithPopup(auth, new GoogleAuthProvider());
                await finish(credential.user);
            } catch (error) {
                showError(error);
            }
        });
    </script>
    <?php
    return ob_get_clean();
}
