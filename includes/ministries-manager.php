<?php
/** Dedicated staff manager for ongoing ministries. */
if (!defined('ABSPATH')) { exit; }

function surfside_tools_staff_ministries_manager_shortcode() {
    if (function_exists('surfside_tools_prevent_cache')) surfside_tools_prevent_cache();
    if (function_exists('surfside_tools_staff_enqueue_styles')) surfside_tools_staff_enqueue_styles();
    if (function_exists('surfside_tools_site_information_manager_assets')) surfside_tools_site_information_manager_assets();
    if (!is_user_logged_in()) return function_exists('surfside_tools_staff_login_box') ? surfside_tools_staff_login_box('Please log in to manage ministries.') : '<p>Please log in.</p>';
    if (!current_user_can('manage_options')) return '<div class="surfside-staff-shell"><p>You do not have permission to manage ministries.</p></div>';

    $notice='';
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['surfside_ministries_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['surfside_ministries_nonce'])),'surfside_ministries_save')) {
        $posted=isset($_POST['ministries']) && is_array($_POST['ministries']) ? wp_unslash($_POST['ministries']) : array();
        surfside_tools_update_ministries($posted);
        $notice='<div class="surfside-mobile-notice">Ministries saved.</div>';
    }

    $ministries=surfside_tools_get_ministries();
    $audiences=surfside_tools_ministry_audience_choices();
    ob_start(); ?>
    <div class="surfside-staff-shell surfside-information-manager">
      <div class="surfside-staff-back"><a href="<?php echo esc_url(surfside_tools_staff_page_url('site-settings')); ?>">← Back to Site Settings</a></div>
      <section class="surfside-staff-hero"><p class="surfside-staff-eyebrow">Website &amp; App</p><h1>Ministries</h1><p class="surfside-staff-muted">Manage ongoing ministries shared by the website and mobile app. Choose every audience each ministry serves. Bible studies are managed separately through Calendar Manager.</p></section>
      <?php echo $notice; ?>
      <form method="post" class="surfside-information-form">
        <?php wp_nonce_field('surfside_ministries_save','surfside_ministries_nonce'); ?>
        <div class="surfside-information-ministries" data-surfside-ministries>
          <?php foreach($ministries as $index=>$ministry): ?>
          <section class="surfside-information-card surfside-information-ministry">
            <input type="hidden" name="ministries[<?php echo esc_attr($index); ?>][key]" value="<?php echo esc_attr($ministry['key'] ?? ''); ?>">
            <label class="surfside-information-field"><span>Icon</span><input type="text" name="ministries[<?php echo esc_attr($index); ?>][icon]" value="<?php echo esc_attr($ministry['icon'] ?? ''); ?>" maxlength="12" placeholder="🙏"></label>
            <label class="surfside-information-field"><span>Ministry name</span><input type="text" name="ministries[<?php echo esc_attr($index); ?>][name]" value="<?php echo esc_attr($ministry['name'] ?? ''); ?>" required></label>
            <label class="surfside-information-field"><span>Usual schedule</span><input type="text" name="ministries[<?php echo esc_attr($index); ?>][schedule]" value="<?php echo esc_attr($ministry['schedule'] ?? ''); ?>"></label>
            <label class="surfside-information-field"><span>Usual location</span><input type="text" name="ministries[<?php echo esc_attr($index); ?>][location]" value="<?php echo esc_attr($ministry['location'] ?? ''); ?>"></label>
            <fieldset class="surfside-ministry-audiences"><legend>Who is this ministry for?</legend><?php foreach($audiences as $key=>$label): ?><label class="surfside-information-checkbox"><input type="checkbox" name="ministries[<?php echo esc_attr($index); ?>][audiences][]" value="<?php echo esc_attr($key); ?>" <?php checked(in_array($key,(array)($ministry['audiences'] ?? array('adults')),true)); ?>> <?php echo esc_html($label); ?></label><?php endforeach; ?></fieldset>
            <label class="surfside-information-field surfside-information-ministry-description"><span>Description</span><textarea name="ministries[<?php echo esc_attr($index); ?>][description]" rows="3"><?php echo esc_textarea($ministry['description'] ?? ''); ?></textarea></label>
            <div class="surfside-information-ministry-actions"><button type="button" class="surfside-information-remove" data-ministry-up>↑</button><button type="button" class="surfside-information-remove" data-ministry-down>↓</button><button type="button" class="surfside-information-remove" data-ministry-remove>Remove</button></div>
          </section>
          <?php endforeach; ?>
        </div>
        <button type="button" class="surfside-information-add" data-ministry-add>+ Add ministry</button>
        <template data-ministry-template><section class="surfside-information-card surfside-information-ministry"><input type="hidden" name="ministries[__INDEX__][key]" value=""><label class="surfside-information-field"><span>Icon</span><input type="text" name="ministries[__INDEX__][icon]" maxlength="12" placeholder="🙏"></label><label class="surfside-information-field"><span>Ministry name</span><input type="text" name="ministries[__INDEX__][name]" required></label><label class="surfside-information-field"><span>Usual schedule</span><input type="text" name="ministries[__INDEX__][schedule]"></label><label class="surfside-information-field"><span>Usual location</span><input type="text" name="ministries[__INDEX__][location]"></label><fieldset class="surfside-ministry-audiences"><legend>Who is this ministry for?</legend><?php foreach($audiences as $key=>$label): ?><label class="surfside-information-checkbox"><input type="checkbox" name="ministries[__INDEX__][audiences][]" value="<?php echo esc_attr($key); ?>" <?php checked($key,'adults'); ?>> <?php echo esc_html($label); ?></label><?php endforeach; ?></fieldset><label class="surfside-information-field surfside-information-ministry-description"><span>Description</span><textarea name="ministries[__INDEX__][description]" rows="3"></textarea></label><div class="surfside-information-ministry-actions"><button type="button" class="surfside-information-remove" data-ministry-up>↑</button><button type="button" class="surfside-information-remove" data-ministry-down>↓</button><button type="button" class="surfside-information-remove" data-ministry-remove>Remove</button></div></section></template>
        <div class="surfside-information-actions"><button type="submit" class="surfside-information-save">Save Ministries</button></div>
      </form>
    </div>
    <style>.surfside-ministry-audiences{grid-column:1/-1;display:flex;gap:14px;flex-wrap:wrap;border:0;padding:0;margin:4px 0}.surfside-ministry-audiences legend{width:100%;font-weight:800;color:#26323d;margin-bottom:5px}.surfside-information-ministry{display:grid;grid-template-columns:64px repeat(3,minmax(0,1fr));gap:12px;align-items:end}.surfside-information-ministry-description,.surfside-information-ministry-actions{grid-column:1/-1}.surfside-information-ministry textarea{box-sizing:border-box;width:100%;padding:10px 12px;border:1px solid #aeb9c4;border-radius:9px;font:inherit}.surfside-information-ministry-actions{display:flex;gap:8px}@media(max-width:800px){.surfside-information-ministry{grid-template-columns:1fr}}</style>
    <script>(function(){var list=document.querySelector('[data-surfside-ministries]'),add=document.querySelector('[data-ministry-add]'),template=document.querySelector('[data-ministry-template]');if(!list||!add||!template)return;var next=1000;function renumber(){Array.from(list.children).forEach(function(item,i){item.querySelectorAll('[name]').forEach(function(el){el.name=el.name.replace(/ministries\[[^\]]+\]/,'ministries['+i+']');});});}add.addEventListener('click',function(){var wrap=document.createElement('div');wrap.innerHTML=template.innerHTML.replaceAll('__INDEX__','new-'+next++).trim();list.appendChild(wrap.firstElementChild);renumber();});list.addEventListener('click',function(e){var item=e.target.closest('.surfside-information-ministry');if(!item)return;if(e.target.closest('[data-ministry-remove]'))item.remove();else if(e.target.closest('[data-ministry-up]')&&item.previousElementSibling)list.insertBefore(item,item.previousElementSibling);else if(e.target.closest('[data-ministry-down]')&&item.nextElementSibling)list.insertBefore(item.nextElementSibling,item);renumber();});renumber();})();</script>
    <?php return ob_get_clean();
}
add_shortcode('surfside_staff_ministries_manager','surfside_tools_staff_ministries_manager_shortcode');
