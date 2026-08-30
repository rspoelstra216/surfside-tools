<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Staff Access: manage WordPress access alongside Firebase-based Tools roles.
 *
 * Tools authorization remains tied to Firebase UID. WordPress access is a
 * separate control plane and can be granted, changed, or disabled here.
 */

function surfside_tools_wp_access_disabled_meta_key() {
    return 'surfside_tools_wp_access_disabled';
}

function surfside_tools_wp_user_for_permission($record) {
    $email = sanitize_email($record['email'] ?? '');
    if (!$email) {
        return null;
    }

    $user = get_user_by('email', $email);
    if (!$user instanceof WP_User) {
        return null;
    }

    if (strpos((string) $user->user_login, 'surfside_firebase_') === 0) {
        return null;
    }

    return $user;
}

function surfside_tools_wp_access_is_disabled($user) {
    return $user instanceof WP_User && (bool) get_user_meta($user->ID, surfside_tools_wp_access_disabled_meta_key(), true);
}

function surfside_tools_wp_public_roles() {
    $roles = wp_roles()->roles;
    unset($roles['surfside_tools_bridge_staff'], $roles['surfside_tools_bridge_admin']);
    return $roles;
}

function surfside_tools_wp_active_admin_count() {
    $count = 0;
    $admins = get_users(array('role' => 'administrator'));
    foreach ($admins as $admin) {
        if ($admin instanceof WP_User && !surfside_tools_wp_access_is_disabled($admin)) {
            $count++;
        }
    }
    return $count;
}

function surfside_tools_wp_unique_username($record) {
    $email = sanitize_email($record['email'] ?? '');
    $base = $email ? strstr($email, '@', true) : '';
    $base = sanitize_user($base ?: ($record['name'] ?? 'surfside-user'), true);
    $base = $base ?: 'surfside-user';
    $candidate = $base;
    $suffix = 2;

    while (username_exists($candidate)) {
        $candidate = $base . $suffix;
        $suffix++;
    }

    return $candidate;
}

function surfside_tools_wp_create_for_permission($record, $role) {
    $email = sanitize_email($record['email'] ?? '');
    if (!$email || email_exists($email)) {
        return new WP_Error('surfside_wp_user', 'A WordPress account could not be created for this person.');
    }

    $user_id = wp_insert_user(array(
        'user_login' => surfside_tools_wp_unique_username($record),
        'user_email' => $email,
        'display_name' => sanitize_text_field($record['name'] ?? $email),
        'user_pass' => wp_generate_password(32, true, true),
        'role' => $role,
    ));

    if (is_wp_error($user_id)) {
        return $user_id;
    }

    if (function_exists('wp_new_user_notification')) {
        wp_new_user_notification($user_id, null, 'user');
    }

    return get_user_by('id', $user_id);
}

function surfside_tools_wp_update_access($record, $requested_role) {
    $roles = surfside_tools_wp_public_roles();
    $user = surfside_tools_wp_user_for_permission($record);

    if ($requested_role === 'none') {
        if (!$user) {
            return 'No WordPress account exists for this person.';
        }

        if (in_array('administrator', (array) $user->roles, true) && !surfside_tools_wp_access_is_disabled($user) && surfside_tools_wp_active_admin_count() <= 1) {
            return 'WordPress must always have at least one active administrator.';
        }

        update_user_meta($user->ID, surfside_tools_wp_access_disabled_meta_key(), 1);
        if (class_exists('WP_Session_Tokens')) {
            WP_Session_Tokens::get_instance($user->ID)->destroy_all();
        }
        return 'WordPress access disabled.';
    }

    if (!isset($roles[$requested_role])) {
        return 'That WordPress role is not available.';
    }

    if (!$user) {
        $user = surfside_tools_wp_create_for_permission($record, $requested_role);
        if (is_wp_error($user)) {
            return $user->get_error_message();
        }
        return 'WordPress account created. A password setup email was sent.';
    }

    $is_admin = in_array('administrator', (array) $user->roles, true);
    if ($is_admin && $requested_role !== 'administrator' && !surfside_tools_wp_access_is_disabled($user) && surfside_tools_wp_active_admin_count() <= 1) {
        return 'WordPress must always have at least one active administrator.';
    }

    delete_user_meta($user->ID, surfside_tools_wp_access_disabled_meta_key());
    $user->set_role($requested_role);
    return 'WordPress access updated.';
}

add_filter('authenticate', function ($user, $username, $password) {
    if ($user instanceof WP_User && surfside_tools_wp_access_is_disabled($user)) {
        return new WP_Error('surfside_wp_access_disabled', 'Your WordPress access has been disabled.');
    }
    return $user;
}, 100, 3);

remove_shortcode('surfside_tools_permissions');
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
                    $notice = 'Tools access updated.';
                }
            }
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['surfside_wp_access_nonce'])) {
        $nonce = sanitize_text_field(wp_unslash($_POST['surfside_wp_access_nonce']));
        if (wp_verify_nonce($nonce, 'surfside_tools_wp_access')) {
            $uid = sanitize_text_field(wp_unslash($_POST['uid'] ?? ''));
            $wp_role = sanitize_key(wp_unslash($_POST['wp_role'] ?? 'none'));
            if ($uid && isset($permissions[$uid])) {
                $notice = surfside_tools_wp_update_access($permissions[$uid], $wp_role);
            }
        }
    }

    $wp_roles = surfside_tools_wp_public_roles();

    ob_start();
    ?>
    <div class="surfside-staff-shell">
        <div class="surfside-staff-hero surfside-staff-panel">
            <p class="surfside-staff-eyebrow">Administration</p>
            <h1>Staff Access</h1>
            <p class="surfside-staff-muted">Manage Surfside Tools roles and separate WordPress access from one place. Tools permissions remain tied to Firebase UID.</p>
        </div>
        <?php if ($notice) : ?><div class="surfside-staff-panel"><strong><?php echo esc_html($notice); ?></strong></div><?php endif; ?>
        <div class="surfside-staff-panel">
            <h2>People</h2>
            <p class="surfside-staff-muted">A new person appears here after they attempt to sign in once with their Firebase account.</p>
            <div style="overflow-x:auto;margin-top:20px">
                <table style="width:100%;border-collapse:collapse;min-width:900px">
                    <thead><tr><th style="text-align:left;padding:10px;border-bottom:1px solid #dbe3ec">Person</th><th style="text-align:left;padding:10px;border-bottom:1px solid #dbe3ec">Firebase UID</th><th style="text-align:left;padding:10px;border-bottom:1px solid #dbe3ec">Tools Access</th><th style="text-align:left;padding:10px;border-bottom:1px solid #dbe3ec">WordPress Access</th></tr></thead>
                    <tbody>
                    <?php if (!$permissions) : ?>
                        <tr><td colspan="4" style="padding:18px 10px">No Firebase staff identities have been recorded yet.</td></tr>
                    <?php else : foreach ($permissions as $uid => $record) :
                        $wp_user = surfside_tools_wp_user_for_permission($record);
                        $wp_disabled = surfside_tools_wp_access_is_disabled($wp_user);
                        $current_wp_role = $wp_user && !$wp_disabled ? (string) reset($wp_user->roles) : 'none';
                    ?>
                        <tr>
                            <td style="padding:12px 10px;border-bottom:1px solid #edf1f5"><strong><?php echo esc_html($record['name'] ?: $record['email']); ?></strong><br><span class="surfside-staff-muted"><?php echo esc_html($record['email']); ?></span></td>
                            <td style="padding:12px 10px;border-bottom:1px solid #edf1f5;font-family:monospace"><?php echo esc_html(substr($uid, 0, 12) . '…'); ?></td>
                            <td style="padding:12px 10px;border-bottom:1px solid #edf1f5">
                                <form method="post" style="display:flex;gap:8px;align-items:center">
                                    <?php wp_nonce_field('surfside_tools_permissions', 'surfside_permissions_nonce'); ?>
                                    <input type="hidden" name="uid" value="<?php echo esc_attr($uid); ?>">
                                    <select name="role" aria-label="Tools access role">
                                        <?php if (($record['role'] ?? '') === 'pending') : ?><option value="pending" selected disabled>Pending</option><?php endif; ?>
                                        <option value="admin" <?php selected($record['role'], 'admin'); ?>>Tools Admin</option>
                                        <option value="staff" <?php selected($record['role'], 'staff'); ?>>Tools Staff</option>
                                        <option value="disabled" <?php selected($record['role'], 'disabled'); ?>>Disabled</option>
                                    </select>
                                    <button class="surfside-staff-button-secondary" type="submit" style="width:auto;min-height:38px;padding:7px 12px">Save</button>
                                </form>
                            </td>
                            <td style="padding:12px 10px;border-bottom:1px solid #edf1f5">
                                <form method="post" style="display:flex;gap:8px;align-items:center" data-current-role="<?php echo esc_attr($current_wp_role); ?>" onsubmit="if(this.wp_role.value==='administrator' && this.dataset.currentRole!=='administrator'){return window.confirm('Administrator grants full WordPress site access. Continue?');}">
                                    <?php wp_nonce_field('surfside_tools_wp_access', 'surfside_wp_access_nonce'); ?>
                                    <input type="hidden" name="uid" value="<?php echo esc_attr($uid); ?>">
                                    <select name="wp_role" aria-label="WordPress access role">
                                        <option value="none" <?php selected($current_wp_role, 'none'); ?>>No WordPress Access</option>
                                        <?php foreach ($wp_roles as $role_key => $role_data) : ?>
                                            <option value="<?php echo esc_attr($role_key); ?>" <?php selected($current_wp_role, $role_key); ?>><?php echo esc_html($role_data['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button class="surfside-staff-button-secondary" type="submit" style="width:auto;min-height:38px;padding:7px 12px">Save</button>
                                </form>
                                <?php if (!$wp_user) : ?><div class="surfside-staff-muted" style="font-size:13px;margin-top:6px">Selecting a role creates a WordPress account and sends password setup instructions.</div><?php endif; ?>
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
