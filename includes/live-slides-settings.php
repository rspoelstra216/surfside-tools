<?php
/**
 * Live Slides presentation defaults and feature availability for the Surfside app.
 *
 * Cloudflare serves the live content. WordPress stores only lightweight defaults
 * and never fetches slides, opens WebSockets, or holds the publisher token.
 */
if (!defined('ABSPATH')) { exit; }

function surfside_tools_live_slides_defaults() {
    return array(
        'enabled' => false,
        'theme' => 'system',
        'font_size' => 32,
        'text_align' => 'center',
    );
}

/** Normalize stored settings and submitted values against a small allow-list. */
function surfside_tools_live_slides_normalize($values) {
    $values = is_array($values) ? $values : array();
    $defaults = surfside_tools_live_slides_defaults();
    $theme = (string) ($values['theme'] ?? $defaults['theme']);
    $alignment = (string) ($values['text_align'] ?? $defaults['text_align']);

    return array(
        'enabled' => !empty($values['enabled']),
        'theme' => in_array($theme, array('system', 'light', 'dark'), true) ? $theme : $defaults['theme'],
        'font_size' => max(20, min(60, (int) ($values['font_size'] ?? $defaults['font_size']))),
        'text_align' => in_array($alignment, array('left', 'center'), true) ? $alignment : $defaults['text_align'],
    );
}

function surfside_tools_live_slides_settings() {
    $app_settings = function_exists('surfside_tools_app_settings')
        ? surfside_tools_app_settings() : array();

    return surfside_tools_live_slides_normalize(array(
        'enabled' => $app_settings['live_slides_enabled'] ?? false,
        'theme' => $app_settings['live_slides_theme'] ?? 'system',
        'font_size' => $app_settings['live_slides_font_size'] ?? 32,
        'text_align' => $app_settings['live_slides_text_align'] ?? 'center',
    ));
}

/** Public, non-sensitive configuration consumed by the mobile app. */
function surfside_tools_live_slides_public_settings() {
    $settings = surfside_tools_live_slides_settings();
    return array(
        'enabled' => $settings['enabled'],
        'viewer_url' => 'https://surfside-live-slides.randal-spoelstra.workers.dev/viewer',
        'websocket_url' => 'wss://surfside-live-slides.randal-spoelstra.workers.dev/api/live',
        'default_theme' => $settings['theme'],
        'default_font_size' => $settings['font_size'],
        'default_text_align' => $settings['text_align'],
    );
}

function surfside_tools_live_slides_mobile_config($payload) {
    if (!is_array($payload)) {
        return $payload;
    }
    if (!isset($payload['app']) || !is_array($payload['app'])) {
        $payload['app'] = array();
    }
    $payload['app']['live_slides'] = surfside_tools_live_slides_public_settings();
    return $payload;
}
add_filter('surfside_tools_mobile_api_app', 'surfside_tools_live_slides_mobile_config');

function surfside_tools_staff_live_slides_shortcode() {
    surfside_tools_prevent_cache();
    surfside_tools_staff_enqueue_styles();

    if (!is_user_logged_in()) {
        return surfside_tools_staff_login_box('Please log in to manage Live Slides.');
    }
    if (!current_user_can('upload_files')) {
        return '<div class="surfside-staff-shell"><p>You do not have permission to manage Live Slides.</p></div>';
    }

    $saved = false;
    $error = '';
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' &&
        isset($_POST['surfside_live_slides_action'])) {
        $nonce = isset($_POST['surfside_live_slides_nonce'])
            ? sanitize_text_field(wp_unslash($_POST['surfside_live_slides_nonce'])) : '';

        if (!wp_verify_nonce($nonce, 'surfside_live_slides_save')) {
            $error = 'The security check expired. Refresh this page and try again.';
        } else {
            $posted = wp_unslash($_POST);
            $normalized = surfside_tools_live_slides_normalize(array(
                'enabled' => !empty($posted['live_slides_enabled']),
                'theme' => sanitize_text_field((string) ($posted['live_slides_theme'] ?? 'system')),
                'font_size' => (int) ($posted['live_slides_font_size'] ?? 32),
                'text_align' => sanitize_text_field((string) ($posted['live_slides_text_align'] ?? 'center')),
            ));

            // Preserve Home, Featured Announcement and Giving settings.
            $app_settings = surfside_tools_app_settings();
            $app_settings['live_slides_enabled'] = $normalized['enabled'] ? 1 : 0;
            $app_settings['live_slides_theme'] = $normalized['theme'];
            $app_settings['live_slides_font_size'] = $normalized['font_size'];
            $app_settings['live_slides_text_align'] = $normalized['text_align'];
            update_option('surfside_tools_app_settings', $app_settings);
            $saved = true;
        }
    }

    $settings = surfside_tools_live_slides_settings();
    $url = surfside_tools_live_slides_public_settings()['viewer_url'];

    ob_start();
    ?>
    <div class="surfside-staff-shell surfside-mobile-app-management surfside-live-slides-settings">
      <div class="surfside-staff-back"><a href="<?php echo esc_url(surfside_tools_staff_page_url('mobile-app')); ?>">← Back to Manage Mobile App</a></div>
      <section class="surfside-staff-hero">
        <p class="surfside-staff-eyebrow">Mobile App</p>
        <h1>Live Slides</h1>
        <p class="surfside-staff-muted">Choose the app's default slide appearance. Slides are delivered directly from Cloudflare, not through WordPress.</p>
      </section>

      <?php if ($saved) : ?><p class="surfside-live-slides-feedback" role="status">Live Slides settings saved.</p><?php endif; ?>
      <?php if ($error) : ?><p class="surfside-live-slides-error" role="alert"><?php echo esc_html($error); ?></p><?php endif; ?>

      <form method="post" class="surfside-staff-panel">
        <?php wp_nonce_field('surfside_live_slides_save', 'surfside_live_slides_nonce'); ?>
        <input type="hidden" name="surfside_live_slides_action" value="save">
        <h2>App availability</h2>
        <label class="surfside-live-slides-switch">
          <input type="checkbox" name="live_slides_enabled" value="1" <?php checked($settings['enabled']); ?>>
          <strong>Show Live Slides in the mobile app</strong>
        </label>
        <p class="surfside-staff-muted">Leave off until the mobile app integration is installed and tested. This controls app visibility only; the public Cloudflare viewer URL remains accessible.</p>

        <h2>Default appearance</h2>
        <p class="surfside-staff-muted">People can override these defaults on their own devices.</p>
        <div class="surfside-live-slides-fields">
          <label for="surfside-slides-theme"><strong>Color mode</strong>
            <select id="surfside-slides-theme" name="live_slides_theme">
              <option value="system" <?php selected($settings['theme'], 'system'); ?>>Follow device</option>
              <option value="light" <?php selected($settings['theme'], 'light'); ?>>Light</option>
              <option value="dark" <?php selected($settings['theme'], 'dark'); ?>>Dark</option>
            </select>
          </label>
          <label for="surfside-slides-font"><strong>Text size (20–60 px)</strong>
            <input id="surfside-slides-font" type="number" min="20" max="60" step="2"
              name="live_slides_font_size" value="<?php echo esc_attr($settings['font_size']); ?>">
          </label>
          <label for="surfside-slides-align"><strong>Text alignment</strong>
            <select id="surfside-slides-align" name="live_slides_text_align">
              <option value="center" <?php selected($settings['text_align'], 'center'); ?>>Centered</option>
              <option value="left" <?php selected($settings['text_align'], 'left'); ?>>Left aligned</option>
            </select>
          </label>
        </div>

        <button class="surfside-staff-button surfside-live-slides-save" type="submit">Save Live Slides</button>
      </form>

      <section class="surfside-staff-panel">
        <h2>Cloudflare viewer</h2>
        <p class="surfside-staff-muted">Preview the live feed in your browser. Live status is determined by the publisher's Cloudflare heartbeats; these settings do not start or stop the Mac publisher.</p>
        <a class="surfside-staff-button-secondary surfside-live-slides-preview" href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener noreferrer">Open Live Slides Viewer ↗</a>
      </section>
    </div>
    <style>
      .surfside-live-slides-settings .surfside-staff-panel{margin-bottom:20px}
      .surfside-live-slides-settings .surfside-staff-panel h2:not(:first-child){margin-top:27px}
      .surfside-live-slides-fields{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:18px;margin:20px 0 28px}
      .surfside-live-slides-fields label{display:flex;flex-direction:column;gap:8px}
      .surfside-live-slides-fields input,.surfside-live-slides-fields select{width:100%;max-width:350px;min-height:43px;padding:9px;border:1px solid #b8cbd7;border-radius:8px;background:#fff;color:#17212b}
      .surfside-live-slides-switch{display:flex;align-items:center;gap:12px;margin:17px 0}
      .surfside-live-slides-switch input{width:20px;height:20px}
      .surfside-live-slides-save,.surfside-live-slides-preview{display:inline-flex;width:auto;min-width:190px;justify-content:center}
      .surfside-live-slides-feedback,.surfside-live-slides-error{padding:12px 15px;border-radius:8px;font-weight:700}
      .surfside-live-slides-feedback{background:#eaf7ef;color:#126b36}
      .surfside-live-slides-error{background:#fff0ed;color:#8d261d}
    </style>
    <?php
    return ob_get_clean();
}
add_shortcode('surfside_staff_live_slides', 'surfside_tools_staff_live_slides_shortcode');
