<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Allow an existing WordPress username/password to authenticate a Surfside
 * Tools session without creating a general WordPress login session.
 */
function surfside_tools_wp_staff_session_cookie_name() {
    return 'surfside_tools_wp_staff_session';
}

function surfside_tools_wp_staff_session_signature($encoded) {
    return hash_hmac('sha256', $encoded, wp_salt('auth'));
}

function surfside_tools_set_wp_staff_session($user_id) {
    $session = array(
        'user_id' => absint($user_id),
        'exp' => time() + HOUR_IN_SECONDS,
    );
    $encoded = rtrim(strtr(base64_encode(wp_json_encode($session)), '+/', '-_'), '=');
    $value = $encoded . '.' . surfside_tools_wp_staff_session_signature($encoded);

    setcookie(
        surfside_tools_wp_staff_session_cookie_name(),
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

function surfside_tools_get_wp_staff_session() {
    $cookie = $_COOKIE[surfside_tools_wp_staff_session_cookie_name()] ?? '';
    if (!$cookie || substr_count($cookie, '.') !== 1) {
        return null;
    }

    list($encoded, $signature) = explode('.', $cookie, 2);
    if (!hash_equals(surfside_tools_wp_staff_session_signature($encoded), $signature)) {
        return null;
    }

    $decoded = surfside_tools_firebase_base64url_decode($encoded);
    $session = json_decode($decoded, true);
    if (!is_array($session) || empty($session['user_id']) || empty($session['exp']) || (int) $session['exp'] <= time()) {
        return null;
    }

    return $session;
}

function surfside_tools_wp_staff_session_user() {
    $session = surfside_tools_get_wp_staff_session();
    if (!$session) {
        return null;
    }

    $user = get_user_by('id', absint($session['user_id']));
    return $user instanceof WP_User ? $user : null;
}

function surfside_tools_wp_staff_session_is_active() {
    $user = surfside_tools_wp_staff_session_user();
    return $user instanceof WP_User && surfside_tools_permission_role_is_active(surfside_tools_wp_tools_role($user));
}

add_filter('determine_current_user', function ($user_id) {
    if ($user_id || surfside_tools_has_wordpress_auth_cookie() || !surfside_tools_firebase_request_is_scoped()) {
        return $user_id;
    }

    $user = surfside_tools_wp_staff_session_user();
    if (!$user instanceof WP_User || !surfside_tools_permission_role_is_active(surfside_tools_wp_tools_role($user))) {
        return $user_id;
    }

    return (int) $user->ID;
}, 25);

add_filter('user_has_cap', function ($allcaps, $caps, $args, $user) {
    if (!surfside_tools_firebase_request_is_scoped()) {
        return $allcaps;
    }

    $session_user = surfside_tools_wp_staff_session_user();
    if (!$session_user instanceof WP_User || !$user instanceof WP_User || (int) $session_user->ID !== (int) $user->ID) {
        return $allcaps;
    }

    $role = surfside_tools_wp_tools_role($session_user);
    if (!surfside_tools_permission_role_is_active($role)) {
        return $allcaps;
    }

    $allcaps['read'] = true;
    $allcaps['upload_files'] = true;
    if ($role === 'admin') {
        $allcaps['manage_options'] = true;
    }

    return $allcaps;
}, 20, 4);

add_action('rest_api_init', function () {
    register_rest_route('surfside-tools/v1', '/staff-auth/wordpress', array(
        'methods' => 'POST',
        'permission_callback' => '__return_true',
        'callback' => function (WP_REST_Request $request) {
            $username = sanitize_user((string) $request->get_param('username'));
            $password = (string) $request->get_param('password');

            if (!$username || !$password) {
                return new WP_Error('surfside_tools_credentials', 'Enter your username and password.', array('status' => 400));
            }

            $user = wp_authenticate($username, $password);
            if (is_wp_error($user) || !$user instanceof WP_User) {
                return new WP_Error('surfside_tools_credentials', 'The username or password is incorrect.', array('status' => 401));
            }

            $role = surfside_tools_wp_tools_role($user);
            if (!surfside_tools_permission_role_is_active($role)) {
                return new WP_Error('surfside_tools_access', 'This account does not have Surfside Tools access.', array('status' => 403));
            }

            surfside_tools_set_wp_staff_session($user->ID);

            return rest_ensure_response(array(
                'authenticated' => true,
                'username' => $user->user_login,
                'name' => $user->display_name,
                'role' => $role,
            ));
        },
    ));
});

remove_shortcode('surfside_firebase_staff_login');
add_shortcode('surfside_firebase_staff_login', function () {
    $redirect = isset($_GET['redirect_to']) ? esc_url_raw(wp_unslash($_GET['redirect_to'])) : home_url('/dashboard/');
    $permission = surfside_tools_current_firebase_permission();

    if (($permission && surfside_tools_permission_role_is_active($permission['role'] ?? '')) || surfside_tools_wp_staff_session_is_active()) {
        return '<div class="surfside-staff-login"><h2>You are signed in</h2><p><a class="wp-block-button__link wp-element-button" href="' . esc_url($redirect) . '">Continue to Surfside Tools</a></p></div>';
    }

    if (is_user_logged_in() && current_user_can('upload_files')) {
        return '<div class="surfside-staff-login"><h2>You are signed in</h2><p><a class="wp-block-button__link wp-element-button" href="' . esc_url($redirect) . '">Continue to Surfside Tools</a></p></div>';
    }

    $google_rest_url = rest_url('surfside-tools/v1/staff-auth/session');
    $wordpress_rest_url = rest_url('surfside-tools/v1/staff-auth/wordpress');

    ob_start();
    ?>
    <div class="surfside-staff-login surfside-staff-auth-login"
         data-google-rest-url="<?php echo esc_url($google_rest_url); ?>"
         data-wordpress-rest-url="<?php echo esc_url($wordpress_rest_url); ?>"
         data-redirect="<?php echo esc_url($redirect); ?>">
        <h2>Surfside Staff Login</h2>
        <p>Sign in with Google or your existing WordPress username and password.</p>
        <div class="surfside-staff-auth-status" role="status" aria-live="polite"></div>
        <button type="button" class="wp-block-button__link wp-element-button surfside-google-login">Continue with Google</button>
        <div class="surfside-login-divider"><span>or use WordPress</span></div>
        <form class="surfside-wordpress-login">
            <label>Username<br><input type="text" name="username" autocomplete="username" required></label>
            <label>Password<br><input type="password" name="password" autocomplete="current-password" required></label>
            <button type="submit" class="wp-block-button__link wp-element-button">Sign In</button>
        </form>
        <p class="surfside-login-note">Surfside Tools access is managed separately from WordPress site access.</p>
    </div>
    <?php
    return ob_get_clean();
});

add_action('wp_footer', function () {
    if (!is_page('login') || !get_page_by_path('dashboard/login')) {
        return;
    }

    $config = surfside_tools_firebase_config();
    ?>
    <style>
        .surfside-staff-auth-login{max-width:520px}
        .surfside-staff-auth-login .surfside-google-login{display:inline-flex;align-items:center;justify-content:center;gap:12px;min-height:44px;padding:0 18px;background:#fff!important;color:#1f1f1f!important;border:1px solid #747775!important;border-radius:4px!important;box-shadow:none!important;font-family:Arial,sans-serif;font-size:14px;font-weight:500;line-height:1;text-decoration:none;cursor:pointer}
        .surfside-staff-auth-login .surfside-google-login:hover,.surfside-staff-auth-login .surfside-google-login:focus{background:#f8faff!important;border-color:#747775!important;color:#1f1f1f!important}
        .surfside-google-mark{width:18px;height:18px;flex:0 0 18px}
        .surfside-staff-auth-status{margin:0 0 12px;color:#4b5872}.surfside-staff-auth-status:empty{display:none}
        .surfside-login-divider{display:flex;align-items:center;gap:12px;margin:18px 0;color:#667085;font-size:14px}.surfside-login-divider::before,.surfside-login-divider::after{content:'';height:1px;background:rgba(7,27,58,.16);flex:1}
        .surfside-wordpress-login{display:grid;gap:14px}.surfside-wordpress-login label{display:grid;gap:6px;font-weight:600}.surfside-wordpress-login input{width:100%;min-height:44px;padding:9px 11px;border:1px solid rgba(7,27,58,.25);border-radius:6px;font:inherit}.surfside-wordpress-login button{width:100%;min-height:44px}.surfside-login-note{margin-top:18px;color:#667085;font-size:14px}
    </style>
    <script type="module">
        import { initializeApp, getApps } from 'https://www.gstatic.com/firebasejs/10.14.1/firebase-app.js';
        import { getAuth, GoogleAuthProvider, signInWithPopup } from 'https://www.gstatic.com/firebasejs/10.14.1/firebase-auth.js';

        const root = document.querySelector('.surfside-staff-auth-login');
        if (root) {
            const config = <?php echo wp_json_encode($config); ?>;
            const app = getApps().length ? getApps()[0] : initializeApp(config);
            const auth = getAuth(app);
            const status = root.querySelector('.surfside-staff-auth-status');
            const wordpressForm = root.querySelector('.surfside-wordpress-login');
            const googleButton = root.querySelector('.surfside-google-login');

            googleButton.innerHTML = `<svg class="surfside-google-mark" viewBox="0 0 18 18" aria-hidden="true"><path fill="#4285F4" d="M17.64 9.205c0-.638-.057-1.252-.164-1.841H9v3.482h4.844a4.14 4.14 0 0 1-1.797 2.715v2.258h2.909c1.702-1.567 2.684-3.874 2.684-6.614Z"/><path fill="#34A853" d="M9 18c2.43 0 4.468-.806 5.956-2.181l-2.909-2.258c-.806.54-1.835.859-3.047.859-2.344 0-4.328-1.585-5.037-3.714H.956v2.332A9 9 0 0 0 9 18Z"/><path fill="#FBBC05" d="M3.963 10.706A5.42 5.42 0 0 1 3.682 9c0-.592.102-1.168.281-1.706V4.962H.956A9 9 0 0 0 0 9c0 1.452.347 2.827.956 4.038l3.007-2.332Z"/><path fill="#EA4335" d="M9 3.58c1.321 0 2.507.454 3.441 1.346l2.581-2.581C13.464.892 11.426 0 9 0A9 9 0 0 0 .956 4.962l3.007 2.332C4.672 5.165 6.656 3.58 9 3.58Z"/></svg><span>Continue with Google</span>`;

            const fail = (error) => {
                console.error('Surfside staff login:', error);
                status.textContent = error?.message || 'Unable to sign in. Please try again.';
            };

            wordpressForm.addEventListener('submit', async (event) => {
                event.preventDefault();
                status.textContent = 'Signing you in…';
                const data = new FormData(wordpressForm);
                try {
                    const response = await fetch(root.dataset.wordpressRestUrl, {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        credentials: 'same-origin',
                        body: JSON.stringify({username:String(data.get('username')), password:String(data.get('password'))}),
                    });
                    const body = await response.json();
                    if (!response.ok) throw new Error(body.message || 'Unable to sign in.');
                    window.location.assign(root.dataset.redirect);
                } catch (error) {
                    fail(error);
                }
            });

            googleButton.addEventListener('click', async () => {
                status.textContent = 'Opening Google sign in…';
                try {
                    const credential = await signInWithPopup(auth, new GoogleAuthProvider());
                    const idToken = await credential.user.getIdToken(true);
                    const response = await fetch(root.dataset.googleRestUrl, {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        credentials: 'same-origin',
                        body: JSON.stringify({idToken}),
                    });
                    const body = await response.json();
                    if (!response.ok) throw new Error(body.message || 'Unable to start your Surfside Tools session.');
                    window.location.assign(root.dataset.redirect);
                } catch (error) {
                    fail(error);
                }
            });
        }
    </script>
    <?php
}, 110);
