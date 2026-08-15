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
        $settings['home_hero_focal_x'] = max(0, min(100, absint($_POST['home_hero_focal_x'] ?? 50)));
        $settings['home_hero_focal_y'] = max(0, min(100, absint($_POST['home_hero_focal_y'] ?? 50)));
        update_option('surfside_tools_app_settings', $settings);
        $saved = true;
    }

    $settings = function_exists('surfside_tools_app_settings') ? surfside_tools_app_settings() : array();
    $image_id = absint($settings['home_hero_image_id'] ?? 0);
    $image_url = $image_id ? wp_get_attachment_image_url($image_id, 'large') : '';
    $focal_x = max(0, min(100, absint($settings['home_hero_focal_x'] ?? 50)));
    $focal_y = max(0, min(100, absint($settings['home_hero_focal_y'] ?? 50)));
    $information = function_exists('surfside_tools_get_site_information') ? surfside_tools_get_site_information() : array();
    $identity = (array) ($information['identity'] ?? array());
    $tagline = (string) ($identity['tagline'] ?? 'The Perfect Church for Imperfect People.');

    wp_enqueue_media();
    wp_register_script('surfside-mobile-app-dashboard', '', array('jquery'), SURFSIDE_TOOLS_VERSION, true);
    wp_enqueue_script('surfside-mobile-app-dashboard');
    wp_add_inline_script('surfside-mobile-app-dashboard', "jQuery(function($){var frame;function sync(){var x=$('#surfside-mobile-focal-x').val(),y=$('#surfside-mobile-focal-y').val();$('#surfside-mobile-focal-x-value').text(x+'%');$('#surfside-mobile-focal-y-value').text(y+'%');$('#surfside-mobile-hero-stage').css('background-position',x+'% '+y+'%');$('#surfside-mobile-focal-dot').css({left:x+'%',top:y+'%'});}$('#surfside-mobile-focal-x,#surfside-mobile-focal-y').on('input change',sync);$('#surfside-mobile-focal-reset').on('click',function(e){e.preventDefault();$('#surfside-mobile-focal-x,#surfside-mobile-focal-y').val(50);sync();});$('#surfside-mobile-hero-select').on('click',function(e){e.preventDefault();if(frame){frame.open();return;}frame=wp.media({title:'Choose App Home Hero',button:{text:'Use this image'},multiple:false,library:{type:'image'}});frame.on('select',function(){var a=frame.state().get('selection').first().toJSON();$('#surfside-mobile-hero-id').val(a.id);$('#surfside-mobile-hero-stage').css('background-image','url('+JSON.stringify(a.url)+')').addClass('has-image');$('#surfside-mobile-hero-remove').show();});frame.open();});$('#surfside-mobile-hero-remove').on('click',function(e){e.preventDefault();$('#surfside-mobile-hero-id').val('0');$('#surfside-mobile-hero-stage').css('background-image','none').removeClass('has-image');$(this).hide();});sync();});");

    ob_start(); ?>
    <div class="surfside-staff-shell surfside-mobile-app-management">
      <div class="surfside-staff-back"><a href="<?php echo esc_url(surfside_tools_staff_page_url('')); ?>">← Back to Dashboard</a></div>
      <section class="surfside-staff-hero"><p class="surfside-staff-eyebrow">Mobile App</p><h1>Manage Mobile App</h1><p class="surfside-staff-muted">Control app-specific presentation now, with notifications and additional mobile tools added here as the app grows.</p></section>
      <?php if ($saved) : ?><div class="surfside-mobile-notice">App settings saved.</div><?php endif; ?>
      <form method="post">
        <?php wp_nonce_field('surfside_mobile_app_save', 'surfside_mobile_app_nonce'); ?>
        <section class="surfside-staff-panel">
          <h2>Home Experience</h2>
          <p class="surfside-staff-muted">Choose the Home hero image, then use the focal point controls to position the important part of the photo inside the app template.</p>
          <input type="hidden" id="surfside-mobile-hero-id" name="home_hero_image_id" value="<?php echo esc_attr($image_id); ?>">
          <div class="surfside-mobile-template-wrap">
            <div class="surfside-mobile-template-label">APP HOME PREVIEW</div>
            <div id="surfside-mobile-hero-stage" class="surfside-mobile-hero-stage<?php echo $image_url ? ' has-image' : ''; ?>" style="<?php echo $image_url ? 'background-image:url(' . esc_url($image_url) . ');' : ''; ?>background-position:<?php echo esc_attr($focal_x); ?>% <?php echo esc_attr($focal_y); ?>%;">
              <div class="surfside-mobile-hero-shade"></div>
              <div id="surfside-mobile-focal-dot" class="surfside-mobile-focal-dot" aria-hidden="true"></div>
              <div class="surfside-mobile-preview-brand"><div class="surfside-mobile-preview-welcome">Welcome home.</div><div class="surfside-mobile-preview-tagline"><?php echo esc_html($tagline); ?></div></div>
              <div class="surfside-mobile-preview-card"><span>COMING UP</span><strong>Upcoming Event</strong><small>Event date · time</small><b>View Events</b></div>
            </div>
            <p class="surfside-staff-muted surfside-mobile-template-help">The preview represents the portrait Home hero and the area covered by the Coming Up card. The crosshair marks the image focal point.</p>
          </div>
          <div class="surfside-mobile-focal-controls">
            <div><label for="surfside-mobile-focal-x"><strong>Horizontal focus</strong> <span id="surfside-mobile-focal-x-value"><?php echo esc_html($focal_x); ?>%</span></label><input id="surfside-mobile-focal-x" name="home_hero_focal_x" type="range" min="0" max="100" value="<?php echo esc_attr($focal_x); ?>"></div>
            <div><label for="surfside-mobile-focal-y"><strong>Vertical focus</strong> <span id="surfside-mobile-focal-y-value"><?php echo esc_html($focal_y); ?>%</span></label><input id="surfside-mobile-focal-y" name="home_hero_focal_y" type="range" min="0" max="100" value="<?php echo esc_attr($focal_y); ?>"></div>
            <button type="button" class="surfside-staff-button-secondary" id="surfside-mobile-focal-reset">Center Image</button>
          </div>
          <div class="surfside-mobile-actions"><button type="button" class="surfside-staff-button-secondary" id="surfside-mobile-hero-select"><?php echo $image_id ? 'Replace Hero Image' : 'Select Hero Image'; ?></button><button type="button" class="surfside-staff-button-secondary" id="surfside-mobile-hero-remove" style="<?php echo $image_id ? '' : 'display:none;'; ?>">Remove</button></div>
          <p class="surfside-staff-muted">If no image is selected, the app uses its standard branded hero.</p>
        </section>
        <button type="submit" class="surfside-staff-button surfside-mobile-save">Save App Settings</button>
      </form>
      <section class="surfside-staff-panel surfside-mobile-future"><h2>Coming Later</h2><p class="surfside-staff-muted">Push notifications and other app-only controls will live here rather than being buried inside Website Management.</p></section>
    </div>
    <style>
    .surfside-mobile-template-wrap{max-width:430px;margin:24px auto}.surfside-mobile-template-label{font-size:11px;font-weight:800;letter-spacing:1.5px;color:#176a9a;margin-bottom:8px}.surfside-mobile-hero-stage{position:relative;aspect-ratio:9/12.5;background:#0b3f60;background-size:cover;background-repeat:no-repeat;border-radius:28px;overflow:hidden;box-shadow:0 12px 30px rgba(15,45,65,.18)}.surfside-mobile-hero-shade{position:absolute;inset:0;background:rgba(5,39,61,.54)}.surfside-mobile-preview-brand{position:absolute;left:7%;right:7%;top:20%;text-align:center;color:#fff;text-shadow:0 1px 5px rgba(0,0,0,.35)}.surfside-mobile-preview-welcome{font-size:30px;line-height:1.1;font-weight:800}.surfside-mobile-preview-tagline{font-size:14px;margin-top:8px}.surfside-mobile-preview-card{position:absolute;left:5%;right:5%;bottom:7%;padding:20px;border-radius:22px;background:#fff;color:#17212b;box-shadow:0 8px 22px rgba(0,0,0,.14);display:flex;flex-direction:column;align-items:flex-start}.surfside-mobile-preview-card span{font-size:10px;font-weight:800;letter-spacing:1.3px;color:#176a9a}.surfside-mobile-preview-card strong{font-size:22px;margin:8px 0}.surfside-mobile-preview-card small{font-size:13px;color:#61717d;margin-top:4px}.surfside-mobile-preview-card b{font-size:12px;color:#fff;background:#176a9a;border-radius:999px;padding:9px 13px;margin-top:14px}.surfside-mobile-focal-dot{position:absolute;width:24px;height:24px;border:2px solid #fff;border-radius:50%;transform:translate(-50%,-50%);z-index:4;box-shadow:0 0 0 2px rgba(11,63,96,.7)}.surfside-mobile-focal-dot:before,.surfside-mobile-focal-dot:after{content:'';position:absolute;background:#fff}.surfside-mobile-focal-dot:before{width:32px;height:2px;left:-6px;top:9px}.surfside-mobile-focal-dot:after{width:2px;height:32px;left:9px;top:-6px}.surfside-mobile-template-help{font-size:13px;margin-top:10px}.surfside-mobile-focal-controls{max-width:720px;margin:20px 0 22px;display:grid;grid-template-columns:1fr 1fr auto;gap:18px;align-items:end}.surfside-mobile-focal-controls label{display:flex;justify-content:space-between;margin-bottom:7px}.surfside-mobile-focal-controls input[type=range]{width:100%}.surfside-mobile-focal-controls .surfside-staff-button-secondary{width:auto;white-space:nowrap}.surfside-mobile-actions{display:flex;gap:10px;flex-wrap:wrap;margin:8px 0 12px}.surfside-mobile-actions .surfside-staff-button-secondary{width:auto}.surfside-mobile-save{width:auto;min-width:190px;border:0;cursor:pointer}.surfside-mobile-notice{padding:14px 18px;margin-bottom:20px;border-radius:10px;background:#eaf7ef;color:#126b36;font-weight:700}.surfside-mobile-future{margin-top:28px}@media(max-width:700px){.surfside-mobile-focal-controls{grid-template-columns:1fr}.surfside-mobile-template-wrap{max-width:360px}.surfside-mobile-preview-welcome{font-size:26px}}
    </style>
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
    $mobile_button = '<a class="surfside-staff-button-secondary surfside-mobile-dashboard-action" href="' . esc_url(surfside_tools_staff_page_url('mobile-app')) . '">Manage Mobile App <span class="surfside-staff-arrow">›</span></a>';
    $website_pattern = '(<a class="surfside-staff-button"[^>]*>Manage Website <span class="surfside-staff-arrow">›</span></a>)';
    if (preg_match('~' . $website_pattern . '~', $output)) {
        $mobile_action = '<style>.surfside-mobile-dashboard-action{display:flex!important;width:100%!important;box-sizing:border-box;align-items:center;justify-content:center;margin-top:14px!important;text-align:center;text-decoration:none!important;box-shadow:0 8px 20px rgba(15,45,65,.10)!important}</style>' . $mobile_button;
        return preg_replace('~' . $website_pattern . '~', '$1' . $mobile_action, $output, 1);
    }
    return $output;
}, 10, 2);
