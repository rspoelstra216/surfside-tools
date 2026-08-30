<?php

if (!defined('ABSPATH')) {
    exit;
}

function surfside_tools_wp_tools_role_meta_key() {
    return 'surfside_tools_access_role';
}

function surfside_tools_wp_tools_role($user) {
    if (!$user instanceof WP_User) {
        return 'disabled';
    }

    $role = sanitize_key((string) get_user_meta($user->ID, surfside_tools_wp_tools_role_meta_key(), true));
    if (in_array($role, array('admin', 'staff', 'disabled'), true)) {
        return $role;
    }

    if (in_array('administrator', (array) $user->roles, true)) {
        $role = 'admin';
    } elseif (user_can($user, 'upload_files')) {
        $role = 'staff';
    } else {
        $role = 'disabled';
    }

    update_user_meta($user->ID, surfside_tools_wp_tools_role_meta_key(), $role);
    return $role;
}

function surfside_tools_set_wp_tools_role($user_id, $role) {
    $user = get_user_by('id', absint($user_id));
    if (!$user instanceof WP_User || !in_array($role, array('admin', 'staff', 'disabled'), true)) {
        return false;
    }

    update_user_meta($user->ID, surfside_tools_wp_tools_role_meta_key(), $role);

    $uid = surfside_tools_permission_uid_for_wp_user($user->ID);
    if ($uid) {
        $permission = surfside_tools_get_permission($uid);
        if ($permission) {
            $permission['role'] = $role;
            surfside_tools_save_permission($uid, $permission);
        }
    }

    return true;
}

function surfside_tools_link_identity_to_wp_user($uid, $wp_user_id) {
    if (!surfside_tools_set_firebase_wp_link($uid, $wp_user_id)) {
        return false;
    }

    if ($wp_user_id) {
        $user = get_user_by('id', absint($wp_user_id));
        $permission = surfside_tools_get_permission($uid);
        if ($user instanceof WP_User && $permission) {
            $permission['role'] = surfside_tools_wp_tools_role($user);
            surfside_tools_save_permission($uid, $permission);
        }
    }

    return true;
}

remove_shortcode('surfside_tools_permissions');
add_shortcode('surfside_tools_permissions', function () {
    if (!surfside_tools_current_user_is_tools_admin()) {
        return '<div class="surfside-staff-login"><h2>Admin access required</h2><p>You do not have permission to manage Surfside Tools access.</p></div>';
    }

    $permissions = surfside_tools_get_permissions();
    $wp_users = surfside_tools_wp_real_users();
    $wp_roles = surfside_tools_wp_public_roles();
    $notice = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['surfside_wp_tools_nonce'])) {
        $nonce = sanitize_text_field(wp_unslash($_POST['surfside_wp_tools_nonce']));
        if (wp_verify_nonce($nonce, 'surfside_tools_wp_tools')) {
            $user_id = absint($_POST['wp_user_id'] ?? 0);
            $role = sanitize_key(wp_unslash($_POST['tools_role'] ?? 'disabled'));
            if ($user_id && surfside_tools_set_wp_tools_role($user_id, $role)) {
                $notice = 'Tools access updated.';
                $permissions = surfside_tools_get_permissions();
            }
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['surfside_permissions_nonce'])) {
        $nonce = sanitize_text_field(wp_unslash($_POST['surfside_permissions_nonce']));
        if (wp_verify_nonce($nonce, 'surfside_tools_permissions')) {
            $uid = sanitize_text_field(wp_unslash($_POST['uid'] ?? ''));
            $role = sanitize_key(wp_unslash($_POST['role'] ?? 'disabled'));
            if ($uid && isset($permissions[$uid]) && in_array($role, array('admin', 'staff', 'disabled'), true)) {
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
            $user_id = absint($_POST['wp_user_id'] ?? 0);
            $wp_role = sanitize_key(wp_unslash($_POST['wp_role'] ?? 'none'));
            if ($user_id) {
                $notice = surfside_tools_wp_update_access_by_user_id($user_id, $wp_role);
            }
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['surfside_identity_link_nonce'])) {
        $nonce = sanitize_text_field(wp_unslash($_POST['surfside_identity_link_nonce']));
        if (wp_verify_nonce($nonce, 'surfside_tools_identity_link')) {
            $uid = sanitize_text_field(wp_unslash($_POST['uid'] ?? ''));
            $wp_user_id = absint($_POST['linked_wp_user_id'] ?? 0);
            if ($uid && isset($permissions[$uid]) && surfside_tools_link_identity_to_wp_user($uid, $wp_user_id)) {
                $notice = $wp_user_id ? 'Google sign-in linked to the selected person.' : 'Google sign-in link removed.';
                $permissions = surfside_tools_get_permissions();
            }
        }
    }

    $unlinked_permissions = array_filter($permissions, function ($record, $uid) {
        return !surfside_tools_get_linked_wp_user_id($uid);
    }, ARRAY_FILTER_USE_BOTH);

    ob_start();
    ?>
    <style>
        .surfside-access-shell{max-width:1180px!important;width:min(1180px,calc(100vw - 48px))!important}
        .surfside-access-table{width:100%;border-collapse:collapse;table-layout:fixed}
        .surfside-access-table th,.surfside-access-table td{padding:12px 10px;border-bottom:1px solid #e3e9ef;text-align:left;vertical-align:top;overflow-wrap:anywhere}
        .surfside-access-table th:nth-child(1){width:24%}.surfside-access-table th:nth-child(2){width:12%}.surfside-access-table th:nth-child(3){width:20%}.surfside-access-table th:nth-child(4){width:24%}.surfside-access-table th:nth-child(5){width:20%}
        .surfside-access-table form{display:flex;gap:6px;align-items:center;flex-wrap:wrap;margin:0}
        .surfside-access-table select{max-width:100%;min-width:0}
        .surfside-access-table .surfside-staff-button-secondary{width:auto;min-height:36px;padding:6px 10px}
        .surfside-access-source{font-weight:600}
        @media(max-width:760px){.surfside-access-shell{width:calc(100vw - 28px)!important}.surfside-access-table,.surfside-access-table tbody,.surfside-access-table tr,.surfside-access-table td{display:block}.surfside-access-table thead{display:none}.surfside-access-table tr{padding:10px 0;border-bottom:1px solid #dbe3ec}.surfside-access-table td{border:0;padding:6px 0}.surfside-access-table td:before{content:attr(data-label);display:block;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;margin-bottom:3px}}
    </style>
    <div class="surfside-staff-shell surfside-access-shell">
        <div class="surfside-staff-hero surfside-staff-panel">
            <p class="surfside-staff-eyebrow">Administration</p>
            <h1>Staff Access</h1>
            <p class="surfside-staff-muted">Manage each person in one place. WordPress username/password and Google are sign-in sources; Tools access and WordPress site access remain separate permissions.</p>
        </div>
        <?php if ($notice) : ?><div class="surfside-staff-panel"><strong><?php echo esc_html($notice); ?></strong></div><?php endif; ?>
        <div class="surfside-staff-panel">
            <h2>People</h2>
            <p class="surfside-staff-muted">Existing WordPress users are listed here automatically. A Google identity appears after its first sign-in attempt and can be linked to the correct person.</p>
            <table class="surfside-access-table">
                <thead><tr><th>Person</th><th>Source</th><th>Tools Access</th><th>WordPress Site Access</th><th>Linked Account</th></tr></thead>
                <tbody>
                <?php foreach ($wp_users as $wp_user) :
                    $linked_uid = surfside_tools_permission_uid_for_wp_user($wp_user->ID);
                    $linked_permission = $linked_uid ? ($permissions[$linked_uid] ?? null) : null;
                    $source = $linked_permission ? 'WordPress + Google' : 'WordPress';
                    $tools_role = surfside_tools_wp_tools_role($wp_user);
                    $wp_role = surfside_tools_wp_primary_role($wp_user);
                ?>
                    <tr>
                        <td data-label="Person"><strong><?php echo esc_html($wp_user->display_name ?: $wp_user->user_login); ?></strong><br><span class="surfside-staff-muted"><?php echo esc_html($wp_user->user_email); ?></span><br><code><?php echo esc_html($wp_user->user_login); ?></code></td>
                        <td data-label="Source"><span class="surfside-access-source"><?php echo esc_html($source); ?></span></td>
                        <td data-label="Tools Access">
                            <form method="post">
                                <?php wp_nonce_field('surfside_tools_wp_tools', 'surfside_wp_tools_nonce'); ?>
                                <input type="hidden" name="wp_user_id" value="<?php echo esc_attr($wp_user->ID); ?>">
                                <select name="tools_role" aria-label="Tools access">
                                    <option value="admin" <?php selected($tools_role, 'admin'); ?>>Tools Admin</option>
                                    <option value="staff" <?php selected($tools_role, 'staff'); ?>>Tools Staff</option>
                                    <option value="disabled" <?php selected($tools_role, 'disabled'); ?>>Disabled</option>
                                </select>
                                <button class="surfside-staff-button-secondary" type="submit">Save</button>
                            </form>
                        </td>
                        <td data-label="WordPress Site Access">
                            <form method="post" data-current-role="<?php echo esc_attr($wp_role); ?>" onsubmit="if(this.wp_role.value==='administrator' && this.dataset.currentRole!=='administrator'){return window.confirm('Administrator grants full WordPress site access. Continue?');}">
                                <?php wp_nonce_field('surfside_tools_wp_access', 'surfside_wp_access_nonce'); ?>
                                <input type="hidden" name="wp_user_id" value="<?php echo esc_attr($wp_user->ID); ?>">
                                <select name="wp_role" aria-label="WordPress site access">
                                    <option value="none" <?php selected($wp_role, 'none'); ?>>No WordPress Site Access</option>
                                    <?php foreach ($wp_roles as $role_key => $role_data) : ?><option value="<?php echo esc_attr($role_key); ?>" <?php selected($wp_role, $role_key); ?>><?php echo esc_html($role_data['name']); ?></option><?php endforeach; ?>
                                </select>
                                <button class="surfside-staff-button-secondary" type="submit">Save</button>
                            </form>
                        </td>
                        <td data-label="Linked Account">
                            <?php if ($linked_permission) : ?><strong>Google linked</strong><br><span class="surfside-staff-muted"><?php echo esc_html($linked_permission['email'] ?? ''); ?></span><?php else : ?><span class="surfside-staff-muted">Not linked</span><?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <?php foreach ($unlinked_permissions as $uid => $record) : ?>
                    <tr>
                        <td data-label="Person"><strong><?php echo esc_html($record['name'] ?: $record['email']); ?></strong><br><span class="surfside-staff-muted"><?php echo esc_html($record['email']); ?></span></td>
                        <td data-label="Source"><span class="surfside-access-source">Google</span></td>
                        <td data-label="Tools Access">
                            <form method="post">
                                <?php wp_nonce_field('surfside_tools_permissions', 'surfside_permissions_nonce'); ?>
                                <input type="hidden" name="uid" value="<?php echo esc_attr($uid); ?>">
                                <select name="role" aria-label="Tools access">
                                    <?php if (($record['role'] ?? '') === 'pending') : ?><option value="pending" selected disabled>Pending</option><?php endif; ?>
                                    <option value="admin" <?php selected($record['role'], 'admin'); ?>>Tools Admin</option>
                                    <option value="staff" <?php selected($record['role'], 'staff'); ?>>Tools Staff</option>
                                    <option value="disabled" <?php selected($record['role'], 'disabled'); ?>>Disabled</option>
                                </select>
                                <button class="surfside-staff-button-secondary" type="submit">Save</button>
                            </form>
                        </td>
                        <td data-label="WordPress Site Access"><span class="surfside-staff-muted">Not linked</span></td>
                        <td data-label="Linked Account">
                            <form method="post">
                                <?php wp_nonce_field('surfside_tools_identity_link', 'surfside_identity_link_nonce'); ?>
                                <input type="hidden" name="uid" value="<?php echo esc_attr($uid); ?>">
                                <select name="linked_wp_user_id" aria-label="Link Google identity">
                                    <option value="0">Not linked</option>
                                    <?php foreach ($wp_users as $candidate) : ?><option value="<?php echo esc_attr($candidate->ID); ?>"><?php echo esc_html($candidate->user_login . ' — ' . ($candidate->display_name ?: $candidate->user_email)); ?></option><?php endforeach; ?>
                                </select>
                                <button class="surfside-staff-button-secondary" type="submit">Link</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php
    return ob_get_clean();
});
