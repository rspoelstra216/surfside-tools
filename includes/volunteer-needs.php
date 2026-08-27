<?php
/** Current volunteer needs for the Surfside mobile app. */
if (!defined('ABSPATH')) { exit; }

const SURFSIDE_TOOLS_VOLUNTEER_NEEDS_OPTION = 'surfside_tools_volunteer_needs';

function surfside_tools_sanitize_volunteer_needs($needs) {
    $needs = is_array($needs) ? $needs : array();
    $clean = array();
    foreach ($needs as $index => $need) {
        if (!is_array($need)) continue;
        $title = sanitize_text_field($need['title'] ?? '');
        if ($title === '') continue;
        $key = sanitize_key($need['key'] ?? '');
        if ($key === '') $key = 'need-' . substr(md5(wp_json_encode(array($title, $index))), 0, 12);
        $clean[] = array(
            'key' => $key,
            'title' => $title,
            'ministry_key' => sanitize_key($need['ministry_key'] ?? ''),
            'description' => sanitize_textarea_field($need['description'] ?? ''),
            'commitment' => sanitize_text_field($need['commitment'] ?? ''),
            'active' => !empty($need['active']),
        );
    }
    return $clean;
}

function surfside_tools_get_volunteer_needs() {
    return surfside_tools_sanitize_volunteer_needs(get_option(SURFSIDE_TOOLS_VOLUNTEER_NEEDS_OPTION, array()));
}

function surfside_tools_update_volunteer_needs($needs) {
    $clean = surfside_tools_sanitize_volunteer_needs($needs);
    $updated = update_option(SURFSIDE_TOOLS_VOLUNTEER_NEEDS_OPTION, $clean, false);
    if (function_exists('surfside_tools_purge_cache')) surfside_tools_purge_cache();
    return $updated;
}

function surfside_tools_volunteer_ministry_map() {
    $map = array();
    if (!function_exists('surfside_tools_get_published_ministries')) return $map;
    foreach ((array) surfside_tools_get_published_ministries() as $ministry) {
        $key = sanitize_key($ministry['key'] ?? '');
        if ($key === '') continue;
        $map[$key] = array(
            'key' => $key,
            'name' => sanitize_text_field($ministry['name'] ?? ''),
            'icon' => sanitize_text_field($ministry['icon'] ?? ''),
        );
    }
    return $map;
}

function surfside_tools_register_volunteer_needs_route() {
    register_rest_route('surfside/v1', '/volunteer-needs', array(
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'surfside_tools_mobile_api_volunteer_needs',
        'permission_callback' => '__return_true',
    ));
}
add_action('rest_api_init', 'surfside_tools_register_volunteer_needs_route');

function surfside_tools_mobile_api_volunteer_needs() {
    $ministries = surfside_tools_volunteer_ministry_map();
    $items = array();
    foreach (surfside_tools_get_volunteer_needs() as $need) {
        if (empty($need['active'])) continue;
        $ministry = isset($ministries[$need['ministry_key']]) ? $ministries[$need['ministry_key']] : null;
        $items[] = array(
            'key' => (string) $need['key'],
            'title' => (string) $need['title'],
            'description' => (string) $need['description'],
            'commitment' => (string) $need['commitment'],
            'ministry' => $ministry,
        );
    }
    return rest_ensure_response(array(
        'api_version' => 1,
        'generated_at' => current_datetime()->format(DATE_ATOM),
        'count' => count($items),
        'needs' => $items,
    ));
}

function surfside_tools_staff_volunteer_needs_view() {
    surfside_tools_prevent_cache();
    surfside_tools_staff_enqueue_styles();
    if (!is_user_logged_in()) return surfside_tools_staff_login_box('Please log in to manage volunteer needs.');
    if (!current_user_can('upload_files')) return '<div class="surfside-staff-shell"><p>You do not have permission to manage volunteer needs.</p></div>';

    $saved = false;
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['surfside_volunteer_needs_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['surfside_volunteer_needs_nonce'])), 'surfside_volunteer_needs_save')) {
        $posted = isset($_POST['volunteer_needs']) && is_array($_POST['volunteer_needs']) ? wp_unslash($_POST['volunteer_needs']) : array();
        surfside_tools_update_volunteer_needs($posted);
        $saved = true;
    }

    $needs = surfside_tools_get_volunteer_needs();
    $ministries = function_exists('surfside_tools_get_published_ministries') ? surfside_tools_get_published_ministries() : array();
    $back_url = remove_query_arg('view', surfside_tools_staff_page_url('mobile-app'));
    ob_start(); ?>
    <div class="surfside-staff-shell surfside-volunteer-needs-manager">
      <div class="surfside-staff-back"><a href="<?php echo esc_url($back_url); ?>">← Back to Manage Mobile App</a></div>
      <section class="surfside-staff-hero">
        <p class="surfside-staff-eyebrow">Mobile App</p>
        <h1>Current Volunteer Needs</h1>
        <p class="surfside-staff-muted">Publish timely serving opportunities in the Surfside app. Keep only current needs active; members will use the app’s existing Connect form to respond.</p>
      </section>
      <?php if ($saved): ?><div class="surfside-mobile-notice">Volunteer needs saved.</div><?php endif; ?>
      <form method="post" class="surfside-volunteer-needs-form">
        <?php wp_nonce_field('surfside_volunteer_needs_save', 'surfside_volunteer_needs_nonce'); ?>
        <div data-volunteer-needs-list>
          <?php foreach ($needs as $index => $need): ?>
            <section class="surfside-staff-panel surfside-volunteer-need" data-volunteer-need>
              <input type="hidden" name="volunteer_needs[<?php echo esc_attr($index); ?>][key]" value="<?php echo esc_attr($need['key']); ?>">
              <div class="surfside-volunteer-need-head"><h2>Volunteer Need</h2><label class="surfside-volunteer-active"><input type="checkbox" name="volunteer_needs[<?php echo esc_attr($index); ?>][active]" value="1" <?php checked(!empty($need['active'])); ?>> Active</label></div>
              <div class="surfside-volunteer-grid">
                <label class="surfside-volunteer-field surfside-volunteer-wide"><span>Title</span><input type="text" name="volunteer_needs[<?php echo esc_attr($index); ?>][title]" value="<?php echo esc_attr($need['title']); ?>" maxlength="90" required placeholder="Children’s Ministry Check-In Helper"></label>
                <label class="surfside-volunteer-field"><span>Ministry</span><select name="volunteer_needs[<?php echo esc_attr($index); ?>][ministry_key]"><option value="">No specific ministry</option><?php foreach ($ministries as $ministry): $ministry_key = sanitize_key($ministry['key'] ?? ''); ?><option value="<?php echo esc_attr($ministry_key); ?>" <?php selected($need['ministry_key'], $ministry_key); ?>><?php echo esc_html($ministry['name'] ?? ''); ?></option><?php endforeach; ?></select></label>
                <label class="surfside-volunteer-field"><span>When / commitment</span><input type="text" name="volunteer_needs[<?php echo esc_attr($index); ?>][commitment]" value="<?php echo esc_attr($need['commitment']); ?>" maxlength="120" placeholder="Sunday mornings · once a month"></label>
                <label class="surfside-volunteer-field surfside-volunteer-wide"><span>Short description</span><textarea name="volunteer_needs[<?php echo esc_attr($index); ?>][description]" rows="3" maxlength="400" placeholder="Help welcome families and check children in before service."><?php echo esc_textarea($need['description']); ?></textarea></label>
              </div>
              <div class="surfside-volunteer-actions"><button type="button" class="surfside-information-remove" data-volunteer-up>↑</button><button type="button" class="surfside-information-remove" data-volunteer-down>↓</button><button type="button" class="surfside-information-remove" data-volunteer-remove>Remove</button></div>
            </section>
          <?php endforeach; ?>
        </div>
        <button type="button" class="surfside-information-add" data-volunteer-add>+ Add Volunteer Need</button>
        <template data-volunteer-template>
          <section class="surfside-staff-panel surfside-volunteer-need" data-volunteer-need>
            <input type="hidden" name="volunteer_needs[__INDEX__][key]" value="">
            <div class="surfside-volunteer-need-head"><h2>Volunteer Need</h2><label class="surfside-volunteer-active"><input type="checkbox" name="volunteer_needs[__INDEX__][active]" value="1" checked> Active</label></div>
            <div class="surfside-volunteer-grid">
              <label class="surfside-volunteer-field surfside-volunteer-wide"><span>Title</span><input type="text" name="volunteer_needs[__INDEX__][title]" maxlength="90" required placeholder="Children’s Ministry Check-In Helper"></label>
              <label class="surfside-volunteer-field"><span>Ministry</span><select name="volunteer_needs[__INDEX__][ministry_key]"><option value="">No specific ministry</option><?php foreach ($ministries as $ministry): ?><option value="<?php echo esc_attr(sanitize_key($ministry['key'] ?? '')); ?>"><?php echo esc_html($ministry['name'] ?? ''); ?></option><?php endforeach; ?></select></label>
              <label class="surfside-volunteer-field"><span>When / commitment</span><input type="text" name="volunteer_needs[__INDEX__][commitment]" maxlength="120" placeholder="Sunday mornings · once a month"></label>
              <label class="surfside-volunteer-field surfside-volunteer-wide"><span>Short description</span><textarea name="volunteer_needs[__INDEX__][description]" rows="3" maxlength="400" placeholder="Help welcome families and check children in before service."></textarea></label>
            </div>
            <div class="surfside-volunteer-actions"><button type="button" class="surfside-information-remove" data-volunteer-up>↑</button><button type="button" class="surfside-information-remove" data-volunteer-down>↓</button><button type="button" class="surfside-information-remove" data-volunteer-remove>Remove</button></div>
          </section>
        </template>
        <div class="surfside-information-actions"><button type="submit" class="surfside-staff-button">Save Volunteer Needs</button></div>
      </form>
    </div>
    <style>
      .surfside-volunteer-needs-form{display:grid;gap:16px}.surfside-volunteer-need{margin-bottom:16px}.surfside-volunteer-need-head{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:16px}.surfside-volunteer-need-head h2{margin:0}.surfside-volunteer-active{display:flex;align-items:center;gap:7px;font-weight:800}.surfside-volunteer-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}.surfside-volunteer-field{display:grid;gap:7px}.surfside-volunteer-field>span{font-weight:800;color:#26323d}.surfside-volunteer-field input,.surfside-volunteer-field select,.surfside-volunteer-field textarea{box-sizing:border-box;width:100%;padding:10px 12px;border:1px solid #aeb9c4;border-radius:9px;background:#fff;color:#26323d;font:inherit}.surfside-volunteer-field textarea{resize:vertical}.surfside-volunteer-wide{grid-column:1/-1}.surfside-volunteer-actions{display:flex;gap:8px;margin-top:16px}.surfside-mobile-notice{padding:14px 18px;margin-bottom:20px;border-radius:10px;background:#eaf7ef;color:#126b36;font-weight:700}@media(max-width:720px){.surfside-volunteer-grid{grid-template-columns:1fr}.surfside-volunteer-wide{grid-column:auto}.surfside-volunteer-need-head{align-items:flex-start}}
    </style>
    <script>
      (function(){const list=document.querySelector('[data-volunteer-needs-list]'),add=document.querySelector('[data-volunteer-add]'),template=document.querySelector('[data-volunteer-template]');if(!list||!add||!template)return;let next=1000;function renumber(){Array.from(list.children).forEach((item,i)=>item.querySelectorAll('[name]').forEach(el=>{el.name=el.name.replace(/volunteer_needs\[[^\]]+\]/,`volunteer_needs[${i}]`);}));}add.addEventListener('click',()=>{const html=template.innerHTML.replaceAll('__INDEX__',String(next++));list.insertAdjacentHTML('beforeend',html);renumber();});list.addEventListener('click',e=>{const button=e.target.closest('button');if(!button)return;const item=button.closest('[data-volunteer-need]');if(!item)return;if(button.matches('[data-volunteer-remove]'))item.remove();if(button.matches('[data-volunteer-up]')&&item.previousElementSibling)list.insertBefore(item,item.previousElementSibling);if(button.matches('[data-volunteer-down]')&&item.nextElementSibling)list.insertBefore(item.nextElementSibling,item);renumber();});})();
    </script>
    <?php return ob_get_clean();
}
