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
            $wp_user_id = absint($_POST['wp_user_id'] ?? 0);
            $wp_role = sanitize_key(wp_unslash($_POST['wp_role'] ?? 'none'));
            if ($wp_user_id) {
                $notice = surfside_tools_wp_update_access_by_user_id($wp_user_id, $wp_role);
            }
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['surfside_identity_link_nonce'])) {
        $nonce = sanitize_text_field(wp_unslash($_POST['surfside_identity_link_nonce']));
        if (wp_verify_nonce($nonce, 'surfside_tools_identity_link')) {
            $uid = sanitize_text_field(wp_unslash($_POST['uid'] ?? ''));
            $wp_user_id = absint($_POST['linked_wp_user_id'] ?? 0);
            if ($uid && isset($permissions[$uid])) {
                surfside_tools_set_firebase_wp_link($uid, $wp_user_id);
                $notice = $wp_user_id ? 'Google/Firebase identity linked to the selected WordPress user.' : 'Google/Firebase identity link removed.';
            }
        }
    }

    $wp_roles = surfside_tools_wp_public_roles();
    $wp_users = surfside_tools_wp_real_users();

    ob_start();
    ?>
    <div class="surfside-staff-shell">
        <div class="surfside-staff-hero surfside-staff-panel">
            <p class="surfside-staff-eyebrow">Administration</p>
            <h1>Staff Access</h1>
            <p class="surfside-staff-muted">Manage the people who can use Surfside Tools, their WordPress site access, and optional Google sign-in identities. WordPress roles and Tools access are independent.</p>
        </div>
        <?php if ($notice) : ?><div class="surfside-staff-panel"><strong><?php echo esc_html($notice); ?></strong></div><?php endif; ?>

        <div class="surfside-staff-panel">
            <h2>WordPress Users</h2>
            <p class="surfside-staff-muted">These are the site's existing WordPress accounts. Removing WordPress site access does not delete the username/password credential.</p>
            <div style="overflow-x:auto;margin-top:20px">
                <table style="width:100%;border-collapse:collapse;min-width:680px">
                    <thead><tr><th style="text-align:left;padding:10px;border-bottom:1px solid #dbe3ec">Person</th><th style="text-align:left;padding:10px;border-bottom:1px solid #dbe3ec">Username</th><th style="text-align:left;padding:10px;border-bottom:1px solid #dbe3ec">WordPress Site Access</th><th style="text-align:left;padding:10px;border-bottom:1px solid #dbe3ec">Google Sign-In</th></tr></thead>
                    <tbody>
                    <?php foreach ($wp_users as $wp_user) :
                        $current_wp_role = surfside_tools_wp_primary_role($wp_user);
                        $linked_uid = surfside_tools_permission_uid_for_wp_user($wp_user->ID);
                        $linked_permission = $linked_uid ? ($permissions[$linked_uid] ?? null) : null;
                    ?>
                        <tr>
                            <td style="padding:12px 10px;border-bottom:1px solid #edf1f5"><strong><?php echo esc_html($wp_user->display_name ?: $wp_user->user_login); ?></strong><br><span class="surfside-staff-muted"><?php echo esc_html($wp_user->user_email); ?></span></td>
                            <td style="padding:12px 10px;border-bottom:1px solid #edf1f5"><code><?php echo esc_html($wp_user->user_login); ?></code></td>
                            <td style="padding:12px 10px;border-bottom:1px solid #edf1f5">
                                <form method="post" style="display:flex;gap:8px;align-items:center" data-current-role="<?php echo esc_attr($current_wp_role); ?>" onsubmit="if(this.wp_role.value==='administrator' && this.dataset.currentRole!=='administrator'){return window.confirm('Administrator grants full WordPress site access. Continue?');}">
                                    <?php wp_nonce_field('surfside_tools_wp_access', 'surfside_wp_access_nonce'); ?>
                                    <input type="hidden" name="wp_user_id" value="<?php echo esc_attr($wp_user->ID); ?>">
                                    <select name="wp_role" aria-label="WordPress site access role">
                                        <option value="none" <?php selected($current_wp_role, 'none'); ?>>No WordPress Site Access</option>
                                        <?php foreach ($wp_roles as $role_key => $role_data) : ?>
                                            <option value="<?php echo esc_attr($role_key); ?>" <?php selected($current_wp_role, $role_key); ?>><?php echo esc_html($role_data['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button class="surfside-staff-button-secondary" type="submit" style="width:auto;min-height:38px;padding:7px 12px">Save</button>
                                </form>
                            </td>
                            <td style="padding:12px 10px;border-bottom:1px solid #edf1f5">
                                <?php if ($linked_permission) : ?>
                                    <strong>Linked</strong><br><span class="surfside-staff-muted"><?php echo esc_html($linked_permission['email'] ?? ''); ?></span>
                                <?php else : ?>
                                    <span class="surfside-staff-muted">Not linked</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="surfside-staff-panel">
            <h2>Google / Firebase Identities</h2>
            <p class="surfside-staff-muted">A Google identity appears after that person attempts to sign in once. Link it to the correct existing WordPress user explicitly; matching email addresses are not treated as proof that the accounts are the same person.</p>
            <div style="overflow-x:auto;margin-top:20px">
                <table style="width:100%;border-collapse:collapse;min-width:760px">
                    <thead><tr><th style="text-align:left;padding:10px;border-bottom:1px solid #dbe3ec">Person</th><th style="text-align:left;padding:10px;border-bottom:1px solid #dbe3ec">Tools Access</th><th style="text-align:left;padding:10px;border-bottom:1px solid #dbe3ec">Linked WordPress User</th></tr></thead>
                    <tbody>
                    <?php if (!$permissions) : ?>
                        <tr><td colspan="3" style="padding:18px 10px">No Google/Firebase identities have been recorded yet.</td></tr>
                    <?php else : foreach ($permissions as $uid => $record) :
                        $linked_wp_user_id = surfside_tools_get_linked_wp_user_id($uid);
                    ?>
                        <tr>
                            <td style="padding:12px 10px;border-bottom:1px solid #edf1f5"><strong><?php echo esc_html($record['name'] ?: $record['email']); ?></strong><br><span class="surfside-staff-muted"><?php echo esc_html($record['email']); ?></span><br><span class="surfside-staff-muted" style="font-size:12px">Firebase: <?php echo esc_html(substr($uid, 0, 12) . '…'); ?></span></td>
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
                                <form method="post" style="display:flex;gap:8px;align-items:center">
                                    <?php wp_nonce_field('surfside_tools_identity_link', 'surfside_identity_link_nonce'); ?>
                                    <input type="hidden" name="uid" value="<?php echo esc_attr($uid); ?>">
                                    <select name="linked_wp_user_id" aria-label="Linked WordPress user">
                                        <option value="0">Not linked</option>
                                        <?php foreach ($wp_users as $wp_user) : ?>
                                            <option value="<?php echo esc_attr($wp_user->ID); ?>" <?php selected($linked_wp_user_id, $wp_user->ID); ?>><?php echo esc_html($wp_user->user_login . ' — ' . ($wp_user->display_name ?: $wp_user->user_email)); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button class="surfside-staff-button-secondary" type="submit" style="width:auto;min-height:38px;padding:7px 12px">Link</button>
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
