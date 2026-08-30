<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * MM6 Surfside Tools permissions.
 *
 * Firebase remains the identity provider. Surfside Tools now owns authorization
 * by Firebase UID. WordPress roles are used only once to seed existing staff,
 * then a locked bridge user supplies the legacy capabilities that existing
 * dashboard modules still expect during front-end requests.
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

function surfside_tools_seed_permission_from_wordpress($uid, $email, $name = '') {
    $existing = surfside_tools_get_permission($uid);
    if ($existing) {
        return $existing;
    }

    $role = 'pending';
    $wp_user = $email ? get_user_by('email', $email) : false;
    if ($wp_user instanceof WP_User) {
        if (in_array('administrator', (array) $wp_user->roles, true)) {
            $role = 'admin';
        } elseif (user_can($wp_user, 'upload_files')) {
            $role = 'staff';
        }
    }

    return surfside_tools_save_permission($uid, array(
        'email' => $email,
        'name' => $name ?: ($wp_user instanceof WP_User ? $wp_user->display_name : ''),
        'role' => $role,
    ));
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

/**
 * Supersede the MM5 temporary authorization callback for Firebase session POSTs.
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

    $permission = surfside_tools_seed_permission_from_wordpress($uid, $email, $name);
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

add_shortcode('surfside_tools_permissions', function () {
    if (!surfside_tools_current_user_is_tools_admin()) {
        return '<div class="surfside-staff-login"><h2>Admin access required</h2><p>You do not have permission to manage Surfside Tools access.</p></div>';
    }

    $permissions = surfside_tools_get_permissions();
    $notice = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['surfside_permissions_nonce'])) {
        $nonce = sanitize_text_field(wp_unslash($_POST['surfside_permissions_nonce']));
        if (wp_verify_nonce($nonce, 'surfside_tools_permissions')) {
            $uid = sanitize_text_field(wp_unslash($_POST['uid'] ?? ''));
            $role = sanitize_key(wp_unslash($_POST['role'] ?? ''));
            if ($uid && in_array($role, array('admin', 'staff', 'disabled'), true) && isset($permissions[$uid])) {
                $current = $permissions[$uid];
                if (($current['role'] ?? '') === 'admin' && $role !== 'admin' && surfside_tools_permissions_count_admins($permissions) <= 1) {
                    $notice = 'Surfside Tools must always have at least one administrator.';
                } else {
                    $current['role'] = $role;
                    surfside_tools_save_permission($uid, $current);
                    $permissions = surfside_tools_get_permissions();
                    $notice = 'Access updated.';
                }
            }
        }
    }

    ob_start();
    ?>
    <div class="surfside-staff-shell">
        <div class="surfside-staff-hero surfside-staff-panel">
            <p class="surfside-staff-eyebrow">Administration</p>
            <h1>Staff Access</h1>
            <p class="surfside-staff-muted">Approve Firebase accounts and assign Surfside Tools roles. These permissions are tied to Firebase UID, not WordPress login credentials.</p>
        </div>
        <?php if ($notice) : ?><div class="surfside-staff-panel"><strong><?php echo esc_html($notice); ?></strong></div><?php endif; ?>
        <div class="surfside-staff-panel">
            <h2>People</h2>
            <p class="surfside-staff-muted">A new person appears here after they attempt to sign in once with their Firebase account.</p>
            <div style="overflow-x:auto;margin-top:20px">
                <table style="width:100%;border-collapse:collapse;min-width:680px">
                    <thead><tr><th style="text-align:left;padding:10px;border-bottom:1px solid #dbe3ec">Person</th><th style="text-align:left;padding:10px;border-bottom:1px solid #dbe3ec">Firebase UID</th><th style="text-align:left;padding:10px;border-bottom:1px solid #dbe3ec">Access</th></tr></thead>
                    <tbody>
                    <?php if (!$permissions) : ?>
                        <tr><td colspan="3" style="padding:18px 10px">No Firebase staff identities have been recorded yet.</td></tr>
                    <?php else : foreach ($permissions as $uid => $record) : ?>
                        <tr>
                            <td style="padding:12px 10px;border-bottom:1px solid #edf1f5"><strong><?php echo esc_html($record['name'] ?: $record['email']); ?></strong><br><span class="surfside-staff-muted"><?php echo esc_html($record['email']); ?></span></td>
                            <td style="padding:12px 10px;border-bottom:1px solid #edf1f5;font-family:monospace"><?php echo esc_html(substr($uid, 0, 12) . '…'); ?></td>
                            <td style="padding:12px 10px;border-bottom:1px solid #edf1f5">
                                <form method="post" style="display:flex;gap:8px;align-items:center">
                                    <?php wp_nonce_field('surfside_tools_permissions', 'surfside_permissions_nonce'); ?>
                                    <input type="hidden" name="uid" value="<?php echo esc_attr($uid); ?>">
                                    <select name="role" aria-label="Access role">
                                        <option value="admin" <?php selected($record['role'], 'admin'); ?>>Tools Admin</option>
                                        <option value="staff" <?php selected($record['role'], 'staff'); ?>>Tools Staff</option>
                                        <option value="disabled" <?php selected($record['role'], 'disabled'); ?>>Disabled</option>
                                    </select>
                                    <button class="surfside-staff-button-secondary" type="submit" style="width:auto;min-height:38px;padding:7px 12px">Save</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
});

add_action('init', function () {
    if (get_page_by_path('dashboard/access')) {
        return;
    }
    $dashboard = get_page_by_path('dashboard');
    if (!$dashboard) {
        return;
    }
    wp_insert_post(array(
        'post_title' => 'Staff Access',
        'post_name' => 'access',
        'post_status' => 'publish',
        'post_type' => 'page',
        'post_parent' => $dashboard->ID,
        'post_content' => '[surfside_tools_permissions]',
        'comment_status' => 'closed',
    ));
}, 87);

add_filter('the_content', function ($content) {
    if (!is_page('dashboard') || !surfside_tools_current_user_is_tools_admin()) {
        return $content;
    }
    $card = '<div class="surfside-staff-shell" style="padding-top:0"><div class="surfside-staff-card" style="min-height:auto"><h2>Staff Access</h2><p>Manage Firebase-based Tools administrators and staff.</p><div class="surfside-staff-actions"><a class="surfside-staff-button-secondary" href="' . esc_url(surfside_tools_permissions_page_url()) . '">Manage Access <span class="surfside-staff-arrow">→</span></a></div></div></div>';
    return $content . $card;
}, 30);
