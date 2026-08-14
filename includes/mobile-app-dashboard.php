<?php
/** Front-end Mobile App management for the Surfside staff dashboard. */
if (!defined('ABSPATH')) { exit; }

function surfside_tools_staff_mobile_app_shortcode() {
    surfside_tools_prevent_cache();
    surfside_tools_staff_enqueue_styles();
    if (!is_user_logged_in()) return surfside_tools_staff_login_box('Please log in to manage the mobile app.');
    if (!current_user_can('upload_files')) return '<div class="surfside-staff-shell"><p>You do not have permission to manage the mobile app.</p></div>';

    $saved = false;
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['surfside_mobile_app_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['surfside_mobile_app_nonce'])), 'surfside_mobile_app_save')) {
        $settings = function_exists('surfside_tools_app_settings') ? surfside_tools_app_settings() : array();
        $settings['home_hero_image_id'] = absint($_POST['home_hero_image_id'] ?? 0);
        update_option('surfside_tools_app_settings', $settings);
        $saved = true;
    }

    $settings = function_exists('surfside_tools_app_settings') ? surfside_tools_app_settings() : array();
    $image_id = absint($settings['home_hero_image_id'] ?? 0);
    $image_url = $image_id ? wp_get_attachment_image_url($image_id, 'large') : '';
    wp_enqueue_media();
    wp_register_script('surfside-mobile-app-dashboard', '', array('jquery'), SURFSIDE_TOOLS_VERSION, true);
    wp_enqueue_script('surfside-mobile-app-dashboard');
    wp_add_inline_script('surfside-mobile-app-dashboard', "jQuery(function($){var frame;$('#surfside-mobile-hero-select').on('click',function(e){e.preventDefault();if(frame){frame.open();return;}frame=wp.media({title:'Choose App Home Hero',button:{text:'Use this image'},multiple:false,library:{type:'image'}});frame.on('select',function(){var a=frame.state().get('selection').first().toJSON();$('#surfside-mobile-hero-id').val(a.id);$('#surfside-mobile-hero-preview').attr('src',a.url).show();$('#surfside-mobile-hero-remove').show();});frame.open();});$('#surfside-mobile-hero-remove').on('click',function(e){e.preventDefault();$('#surfside-mobile-hero-id').val('0');$('#surfside-mobile-hero-preview').hide().attr('src','');$(this).hide();});});");

    ob_start(); ?>
    <div class="surfside-staff-shell surfside-mobile-app-management">
      <div class="surfside-staff-back"><a href="<?php echo esc_url(surfside_tools_staff_page_url('')); ?>">← Back to Dashboard</a></div>
      <section class="surfside-staff-hero"><p class="surfside-staff-eyebrow">Mobile App</p><h1>Manage Mobile App</h1><p class="surfside-staff-muted">Control app-specific presentation now, with notifications and additional mobile tools added here as the app grows.</p></section>
      <?php if ($saved) : ?><div class="surfside-mobile-notice">App settings saved.</div><?php endif; ?>
      <form method="post">
        <?php wp_nonce_field('surfside_mobile_app_save', 'surfside_mobile_app_nonce'); ?>
        <section class="surfside-staff-panel">
          <h2>Home Experience</h2>
          <p class="surfside-staff-muted">Choose the congregation or worship photo used as the app Home hero. A wide landscape image works best; the app will crop it responsively.</p>
          <input type="hidden" id="surfside-mobile-hero-id" name="home_hero_image_id" value="<?php echo esc_attr($image_id); ?>">
          <img id="surfside-mobile-hero-preview" src="<?php echo esc_url($image_url); ?>" alt="" style="<?php echo $image_url ? '' : 'display:none;'; ?>width:100%;max-width:720px;max-height:340px;object-fit:cover;border-radius:14px;margin:22px 0 14px;">
          <div class="surfside-mobile-actions"><button type="button" class="surfside-staff-button-secondary" id="surfside-mobile-hero-select"><?php echo $image_id ? 'Replace Hero Image' : 'Select Hero Image'; ?></button><button type="button" class="surfside-staff-button-secondary" id="surfside-mobile-hero-remove" style="<?php echo $image_id ? '' : 'display:none;'; ?>">Remove</button></div>
          <p class="surfside-staff-muted">If no image is selected, the app uses its standard branded hero.</p>
        </section>
        <button type="submit" class="surfside-staff-button surfside-mobile-save">Save App Settings</button>
      </form>
      <section class="surfside-staff-panel surfside-mobile-future"><h2>Coming Later</h2><p class="surfside-staff-muted">Push notifications and other app-only controls will live here rather than being buried inside Website Management.</p></section>
    </div>
    <style>.surfside-mobile-actions{display:flex;gap:10px;flex-wrap:wrap;margin:8px 0 12px}.surfside-mobile-actions .surfside-staff-button-secondary{width:auto}.surfside-mobile-save{width:auto;min-width:190px;border:0;cursor:pointer}.surfside-mobile-notice{padding:14px 18px;margin-bottom:20px;border-radius:10px;background:#eaf7ef;color:#126b36;font-weight:700}.surfside-mobile-future{margin-top:28px}</style>
    <?php return ob_get_clean();
}
add_shortcode('surfside_staff_mobile_app', 'surfside_tools_staff_mobile_app_shortcode');

function surfside_tools_ensure_mobile_app_page() {
    if (!function_exists('surfside_tools_ensure_staff_page')) return;
    $dashboard = get_page_by_path('dashboard');
    if (!$dashboard) return;
    surfside_tools_ensure_staff_page('Manage Mobile App', 'mobile-app', '[surfside_staff_mobile_app]', (int) $dashboard->ID);
}
add_action('init', 'surfside_tools_ensure_mobile_app_page', 80);

add_filter('do_shortcode_tag', function ($output, $tag) {
    if ($tag !== 'surfside_staff_dashboard' || !is_user_logged_in() || !current_user_can('upload_files')) return $output;

    $mobile_button = '<a class="surfside-staff-button" href="' . esc_url(surfside_tools_staff_page_url('mobile-app')) . '">Manage Mobile App <span class="surfside-staff-arrow">›</span></a>';

    // Put Mobile App immediately after Manage Website inside the exact same dashboard action container.
    $website_pattern = '(<a class="surfside-staff-button"[^>]*>Manage Website <span class="surfside-staff-arrow">›</span></a>)';
    if (preg_match('~' . $website_pattern . '~', $output)) {
        return preg_replace('~' . $website_pattern . '~', '$1' . $mobile_button, $output, 1);
    }

    return $output;
}, 10, 2);
