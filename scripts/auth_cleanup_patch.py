from pathlib import Path
import re


def replace_once(text, old, new, label):
    count = text.count(old)
    if count != 1:
        raise SystemExit(f"{label}: expected 1 occurrence, found {count}")
    return text.replace(old, new, 1)


def regex_once(text, pattern, replacement, label, flags=0):
    updated, count = re.subn(pattern, replacement, text, count=1, flags=flags)
    if count != 1:
        raise SystemExit(f"{label}: expected 1 regex match, found {count}")
    return updated


# Firebase session route: make UID/Tools-role authorization authoritative and
# retire the older email-to-WordPress capability generation.
path = Path('includes/firebase-staff-auth.php')
text = path.read_text()
text = replace_once(
    text,
    """/**\n * MM5 Firebase staff authentication bridge.\n *\n * Firebase proves identity. Until MM6 introduces native Surfside permissions,\n * authorization is deliberately bridged to the existing WordPress user whose\n * email matches the verified Firebase email and who can upload_files.\n */""",
    """/**\n * Firebase identity verification and Surfside Tools session management.\n *\n * Firebase proves identity. Surfside Tools authorizes by verified Firebase UID\n * using the canonical Tools permission record.\n */""",
    'firebase auth docblock',
)
text = regex_once(
    text,
    r"\nfunction surfside_tools_firebase_staff_wp_user\(\$session = null\) \{.*?\n\}\n\nfunction surfside_tools_firebase_staff_is_authorized\(\) \{.*?\n\}\n",
    "\n",
    'obsolete email authorization helpers',
    re.S,
)
old_post = """            $token = (string) $request->get_param('idToken');
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
            ));"""
new_post = """            $token = (string) $request->get_param('idToken');
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
            ));"""
text = replace_once(text, old_post, new_post, 'firebase POST authorization callback')
text = replace_once(
    text,
    """        'callback' => function () {
            surfside_tools_clear_firebase_staff_session();
            return rest_ensure_response(array('authenticated' => false));
        },""",
    """        'callback' => function () {
            surfside_tools_clear_firebase_staff_session();
            if (function_exists('surfside_tools_clear_wp_staff_session')) {
                surfside_tools_clear_wp_staff_session();
            }
            return rest_ensure_response(array('authenticated' => false));
        },""",
    'shared custom-session logout',
)
marker = "\nfunction surfside_tools_firebase_staff_login_markup("
if text.count(marker) != 1:
    raise SystemExit(f"legacy Firebase login markup: expected 1 marker, found {text.count(marker)}")
text = text.split(marker, 1)[0].rstrip() + "\n"
path.write_text(text)


# WordPress Tools session: clear helper, failed-login throttle, and normal WP
# logout integration.
path = Path('includes/staff-login-wordpress.php')
text = path.read_text()
set_function = """function surfside_tools_set_wp_staff_session($user_id) {
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
"""
clear_functions = set_function + """
function surfside_tools_clear_wp_staff_session() {
    setcookie(
        surfside_tools_wp_staff_session_cookie_name(),
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

function surfside_tools_clear_custom_staff_sessions() {
    surfside_tools_clear_wp_staff_session();
    if (function_exists('surfside_tools_clear_firebase_staff_session')) {
        surfside_tools_clear_firebase_staff_session();
    }
}
add_action('wp_logout', 'surfside_tools_clear_custom_staff_sessions');
"""
text = replace_once(text, set_function, clear_functions, 'WordPress staff-session clear helper')

rate_limit = """
function surfside_tools_wp_login_rate_limit_key($username) {
    $identity = strtolower(trim((string) $username));
    return 'surfside_tools_login_' . substr(hash_hmac('sha256', $identity, wp_salt('auth')), 0, 32);
}

function surfside_tools_wp_login_failed_attempts($username) {
    return absint(get_transient(surfside_tools_wp_login_rate_limit_key($username)));
}

function surfside_tools_wp_login_is_rate_limited($username) {
    return surfside_tools_wp_login_failed_attempts($username) >= 5;
}

function surfside_tools_wp_login_record_failure($username) {
    $key = surfside_tools_wp_login_rate_limit_key($username);
    $attempts = surfside_tools_wp_login_failed_attempts($username) + 1;
    set_transient($key, $attempts, 15 * MINUTE_IN_SECONDS);
}

function surfside_tools_wp_login_clear_failures($username) {
    delete_transient(surfside_tools_wp_login_rate_limit_key($username));
}

"""
needle = "add_action('rest_api_init', function () {\n"
text = replace_once(text, needle, rate_limit + needle, 'WordPress login throttle helpers')
text = replace_once(
    text,
    """            if (!$username || !$password) {
                return new WP_Error('surfside_tools_credentials', 'Enter your username and password.', array('status' => 400));
            }

            $user = wp_authenticate($username, $password);
            if (is_wp_error($user) || !$user instanceof WP_User) {
                return new WP_Error('surfside_tools_credentials', 'The username or password is incorrect.', array('status' => 401));
            }

            $role = surfside_tools_wp_tools_role($user);""",
    """            if (!$username || !$password) {
                return new WP_Error('surfside_tools_credentials', 'Enter your username and password.', array('status' => 400));
            }

            if (surfside_tools_wp_login_is_rate_limited($username)) {
                return new WP_Error(
                    'surfside_tools_login_rate_limited',
                    'Too many sign-in attempts. Please wait 15 minutes and try again.',
                    array('status' => 429)
                );
            }

            $user = wp_authenticate($username, $password);
            if (is_wp_error($user) || !$user instanceof WP_User) {
                surfside_tools_wp_login_record_failure($username);
                return new WP_Error('surfside_tools_credentials', 'The username or password is incorrect.', array('status' => 401));
            }

            surfside_tools_wp_login_clear_failures($username);
            $role = surfside_tools_wp_tools_role($user);""",
    'WordPress login throttle callback',
)
path.write_text(text)


# Retire the pre-dispatch authorization generation from bootstrap; the file is
# deleted by the workflow after this assertion.
path = Path('surfside-tools.php')
text = path.read_text()
line = "require_once SURFSIDE_TOOLS_PATH . 'includes/firebase-permission-seeding.php';\n"
text = replace_once(text, line, '', 'bootstrap permission-seeding include')
path.write_text(text)

obsolete = Path('includes/firebase-permission-seeding.php')
if not obsolete.exists():
    raise SystemExit('firebase-permission-seeding.php was expected to exist')
obsolete.unlink()
