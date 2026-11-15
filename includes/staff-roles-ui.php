<?php
/**
 * Role matrix editor, embedded in Staff Access. Admin-only, POST + nonce.
 * Kept separate from account assignment forms so the role catalog has one owner.
 */
if (!defined('ABSPATH')) { exit; }

function surfside_tools_role_management_handle_post() {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !surfside_tools_current_user_is_tools_admin()) return '';
    if (isset($_POST['surfside_role_add_nonce'])) {
        $nonce = sanitize_text_field(wp_unslash($_POST['surfside_role_add_nonce']));
        if (!wp_verify_nonce($nonce, 'surfside_role_add')) return 'Security check failed.';
        $label = trim(sanitize_text_field(wp_unslash($_POST['new_tools_role'] ?? '')));
        $key = sanitize_key(sanitize_title($label));
        $custom = surfside_tools_custom_roles();
        if (!$label || strlen($label) > 55 || !$key || strlen($key) > 40
            || in_array($key, array('admin', 'staff', 'disabled', 'pending'), true)
            || surfside_tools_role_is_assignable($key)) return 'Choose a unique role name (up to 55 characters).';
        if (count($custom) >= 12) return 'Maximum of 12 custom roles reached.';
        $custom[$key] = $label;
        update_option('surfside_tools_custom_roles', $custom, false);
        return 'Custom role added. Select its permissions below, then assign it to a person.';
    }

    if (isset($_POST['surfside_role_remove_nonce'])) {
        $nonce = sanitize_text_field(wp_unslash($_POST['surfside_role_remove_nonce']));
        if (!wp_verify_nonce($nonce, 'surfside_role_remove')) return 'Security check failed.';
        $key = sanitize_key(wp_unslash($_POST['remove_tools_role'] ?? ''));
        $custom = surfside_tools_custom_roles();
        if (!isset($custom[$key])) return 'This role cannot be removed.';
        if (surfside_tools_custom_role_is_assigned($key)) return 'Reassign everyone using this role before removing it.';
        unset($custom[$key]);
        update_option('surfside_tools_custom_roles', $custom, false);
        $saved = get_option('surfside_tools_role_matrix', array());
        if (is_array($saved)) {
            unset($saved[$key]);
            update_option('surfside_tools_role_matrix', $saved, false);
        }
        return 'Custom role removed.';
    }

    if (isset($_POST['surfside_role_matrix_nonce'])) {
        $nonce = sanitize_text_field(wp_unslash($_POST['surfside_role_matrix_nonce']));
        if (!wp_verify_nonce($nonce, 'surfside_role_matrix')) return 'Security check failed.';
        $submitted = isset($_POST['role_permissions']) && is_array($_POST['role_permissions'])
            ? wp_unslash($_POST['role_permissions']) : array();
        $matrix = array();
        foreach (surfside_tools_role_definitions() as $role => $name) {
            if ($role === 'admin') continue;
            $matrix[$role] = array();
            foreach (surfside_tools_staff_permission_definitions() as $permission => $definition) {
                $matrix[$role][$permission] = !empty($submitted[$role][$permission])
                    && $submitted[$role][$permission] === '1';
            }
        }
        update_option('surfside_tools_role_matrix', $matrix, false);
        return 'Role permissions saved.';
    }
    return '';
}

function surfside_tools_render_role_permissions_matrix() {
    if (!surfside_tools_current_user_is_tools_admin()) return '';
    $notice = surfside_tools_role_management_handle_post();
    $roles = surfside_tools_role_definitions();
    $sections = array();
    foreach (surfside_tools_staff_permission_definitions() as $key => $definition) {
        $definition['key'] = $key;
        $definition['locked'] = false;
        $sections[$definition['group']][] = $definition;
    }
    foreach (surfside_tools_staff_protected_permission_definitions() as $key => $definition) {
        $definition['key'] = $key;
        $definition['locked'] = true;
        $sections[$definition['group']][] = $definition;
    }
    $values = array();
    foreach ($roles as $key => $name) $values[$key] = surfside_tools_staff_role_permissions($key);
    ob_start();
    ?>
    <style>
      .surfside-role-matrix-scroll{max-width:100%;overflow-x:auto;border:1px solid #dce5ed;border-radius:12px;margin:18px 0}
      .surfside-role-matrix{border-collapse:collapse;width:100%;min-width:600px}
      .surfside-role-matrix th,.surfside-role-matrix td{padding:11px 12px;border-bottom:1px solid #e4eaf0;vertical-align:middle}
      .surfside-role-matrix th:first-child{min-width:250px;text-align:left}
      .surfside-role-matrix td,.surfside-role-matrix thead th:not(:first-child){text-align:center;min-width:112px}
      .surfside-role-matrix tbody th small{display:block;color:#53677a;font-size:12px;font-weight:400;margin-top:3px}
      .surfside-role-matrix input[type=checkbox]{width:19px;height:19px;accent-color:#0b4f9c}
      .surfside-role-matrix .surfside-role-group th{background:#eaf3ff;padding:4px 8px}
      .surfside-role-group button{display:block;width:100%;padding:10px;border:0;background:transparent;text-align:left;font:inherit;font-weight:700;cursor:pointer}
      .surfside-role-permission-row[hidden]{display:none}
      .surfside-role-pill{font-size:11px;font-weight:700;color:#53677a;background:#e9edf2;border-radius:5px;padding:2px 6px;margin-left:5px;white-space:nowrap}
      .surfside-role-locked{color:#7b8796}
      .surfside-role-creator{border-top:1px solid #e3e9ef;margin-top:24px;padding-top:16px}
      .surfside-role-creator form{display:flex;gap:9px;align-items:center;flex-wrap:wrap;margin:12px 0}
      .surfside-role-creator input{max-width:260px;padding:9px 11px;border:1px solid #aebbc9;border-radius:7px}
      .surfside-role-creator .surfside-staff-button-secondary{width:auto}
      .surfside-role-custom-list{display:grid;gap:6px;max-width:520px}
      .surfside-role-custom-list form{justify-content:space-between}
      .surfside-role-matrix-form{display:block!important}
    </style>
    <div class="surfside-staff-panel surfside-role-management">
      <h2>Role Permissions</h2>
      <p class="surfside-staff-muted"><strong>Tools Admin</strong> is the protected full-access role. Configure any number of Staff and custom-role permissions below (up to 12 custom roles). Existing Staff permissions are preserved.</p>
      <p class="surfside-staff-muted">Integration credentials, contact routing, staff access and other protected settings are displayed for clarity but cannot be delegated through this screen. WordPress site roles remain separate.</p>
      <?php if ($notice) : ?><p role="status"><strong><?php echo esc_html($notice); ?></strong></p><?php endif; ?>
      <form method="post" class="surfside-role-matrix-form">
        <?php wp_nonce_field('surfside_role_matrix', 'surfside_role_matrix_nonce'); ?>
        <div class="surfside-role-matrix-scroll" role="region" aria-label="Tools role permissions" tabindex="0">
          <table class="surfside-role-matrix">
            <thead><tr><th scope="col">Permission</th>
              <?php foreach ($roles as $role => $label) : ?>
                <th scope="col"><?php echo esc_html($label); ?><?php if ($role === 'admin') : ?><span class="surfside-role-pill">Fixed</span><?php endif; ?></th>
              <?php endforeach; ?>
            </tr></thead>
            <tbody>
              <?php foreach ($sections as $group => $rows) : ?>
                <tr class="surfside-role-group"><th colspan="<?php echo esc_attr(count($roles) + 1); ?>" scope="rowgroup"><button type="button" aria-expanded="true"><span aria-hidden="true">▾</span> <?php echo esc_html($group); ?></button></th></tr>
                <?php foreach ($rows as $definition) : ?>
                  <tr class="surfside-role-permission-row">
                    <th scope="row"><?php echo esc_html($definition['label']); ?>
                      <?php if ($definition['locked']) : ?><span class="surfside-role-pill">Admin only</span><?php endif; ?>
                      <small><?php echo esc_html($definition['description']); ?></small>
                    </th>
                    <?php foreach ($roles as $role => $label) : ?>
                      <td>
                        <?php if ($role === 'admin') : ?>
                          <input type="checkbox" checked disabled aria-label="<?php echo esc_attr($label . ': ' . $definition['label'] . ' (always allowed)'); ?>">
                        <?php elseif ($definition['locked']) : ?>
                          <span class="surfside-role-locked" title="Reserved for Tools Admin">—</span>
                        <?php else : ?>
                          <input type="checkbox"
                            name="role_permissions[<?php echo esc_attr($role); ?>][<?php echo esc_attr($definition['key']); ?>]" value="1"
                            <?php checked(!empty($values[$role][$definition['key']])); ?>
                            aria-label="<?php echo esc_attr($label . ': ' . $definition['label']); ?>">
                        <?php endif; ?>
                      </td>
                    <?php endforeach; ?>
                  </tr>
                <?php endforeach; ?>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <p class="surfside-staff-muted">Unchecked items are denied. All people assigned to a role share its permissions.</p>
        <button class="surfside-staff-button-secondary" type="submit" style="max-width:240px">Save Role Permissions</button>
      </form>
      <div class="surfside-role-creator">
        <h3>Add a Role</h3>
        <form method="post">
          <?php wp_nonce_field('surfside_role_add', 'surfside_role_add_nonce'); ?>
          <label for="surfside-new-role">Role name</label>
          <input type="text" id="surfside-new-role" name="new_tools_role" maxlength="55" placeholder="e.g. Ministry Leader" required>
          <button class="surfside-staff-button-secondary" type="submit">Add Role</button>
        </form>
        <?php if (surfside_tools_custom_roles()) : ?>
          <h3>Custom Roles</h3>
          <div class="surfside-role-custom-list">
            <?php foreach (surfside_tools_custom_roles() as $key => $name) : ?>
              <form method="post" onsubmit="return window.confirm('Remove this role? It must have no assigned people.');">
                <?php wp_nonce_field('surfside_role_remove', 'surfside_role_remove_nonce'); ?>
                <input type="hidden" name="remove_tools_role" value="<?php echo esc_attr($key); ?>">
                <span><?php echo esc_html($name); ?></span>
                <button class="surfside-staff-button-secondary" type="submit">Remove</button>
              </form>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
    <script>
    document.addEventListener('click', function (event) {
      var button = event.target.closest('.surfside-role-group button');
      if (!button) return;
      var collapsed = button.getAttribute('aria-expanded') === 'true';
      button.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
      button.querySelector('span').textContent = collapsed ? '▸' : '▾';
      var row = button.closest('tr').nextElementSibling;
      while (row && row.classList.contains('surfside-role-permission-row')) {
        row.hidden = collapsed;
        row = row.nextElementSibling;
      }
    });
    </script>
    <?php
    return ob_get_clean();
}
