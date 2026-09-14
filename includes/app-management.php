<?php
/**
 * Mobile app presentation and integration settings.
 */
if (!defined('ABSPATH')) { exit; }

function surfside_tools_app_settings() {
    $settings = get_option('surfside_tools_app_settings', array());
    return is_array($settings) ? $settings : array();
}

function surfside_tools_app_hero_image_url() {
    $settings = surfside_tools_app_settings();
    $id = absint($settings['home_hero_image_id'] ?? 0);
    $url = $id ? wp_get_attachment_image_url($id, 'full') : '';
    return $url ? esc_url_raw($url) : '';
}

function surfside_tools_app_giving_url() {
    $settings = surfside_tools_app_settings();
    return esc_url_raw($settings['giving_url'] ?? '');
}

/**
 * Keep the legacy wp-admin menu as a safe bridge to the authoritative
 * front-end management surfaces. App settings are intentionally not saved
 * from this screen so one partial form cannot overwrite fields owned by
 * Home Experience, Featured Announcement, or Integrations.
 */
function surfside_tools_admin_app_page() {
    if (!current_user_can('upload_files')) {
        wp_die('You do not have permission to manage the Surfside app.');
    }

    $mobile_app_url = function_exists('surfside_tools_staff_page_url')
        ? surfside_tools_staff_page_url('mobile-app')
        : home_url('/dashboard/mobile-app/');
    $integrations_url = function_exists('surfside_tools_staff_page_url')
        ? surfside_tools_staff_page_url('settings')
        : home_url('/dashboard/settings/');
    ?>
    <div class="wrap surfside-admin-wrap">
        <div class="surfside-admin-hero">
            <h1>Mobile App</h1>
            <p class="surfside-admin-muted">Mobile App settings are managed from the Surfside Staff Dashboard so each setting has one authoritative owner.</p>
        </div>

        <div class="surfside-admin-grid">
            <div class="surfside-admin-card">
                <h2>Mobile App Tools</h2>
                <p>Manage Home Experience, Featured Announcement, and Push Notifications from the current Mobile App workspace.</p>
                <a class="button button-primary" href="<?php echo esc_url($mobile_app_url); ?>">Open Manage Mobile App</a>
            </div>

            <div class="surfside-admin-card">
                <h2>Giving</h2>
                <p>Giving is a shared integration used by the website and mobile app.</p>
                <?php if (current_user_can('manage_options')) : ?>
                    <a class="button" href="<?php echo esc_url($integrations_url); ?>">Open Integrations</a>
                <?php else : ?>
                    <p class="surfside-admin-muted">A Tools administrator manages Giving and other shared integrations.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
}
