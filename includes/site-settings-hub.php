<?php
/** Shared and infrequently changed church configuration hub. */
if (!defined('ABSPATH')) { exit; }

function surfside_tools_staff_site_settings_shortcode() {
    if (function_exists('surfside_tools_prevent_cache')) {
        surfside_tools_prevent_cache();
    }
    if (function_exists('surfside_tools_staff_enqueue_styles')) {
        surfside_tools_staff_enqueue_styles();
    }
    if (!is_user_logged_in()) {
        return function_exists('surfside_tools_staff_login_box')
            ? surfside_tools_staff_login_box('Please log in to manage church settings.')
            : '<p>Please log in.</p>';
    }
    if (!current_user_can('manage_options')) {
        return '<div class="surfside-staff-shell"><p>You do not have permission to manage Church Settings.</p></div>';
    }

    $cards = array(
        array(
            'title' => 'Surfside Information',
            'description' => 'Church identity, location, service schedule, contact information, and social links.',
            'path' => 'surfside-information',
            'icon' => 'document',
        ),
        array(
            'title' => 'Ministries',
            'description' => 'Manage ministry information shared by the website and mobile app.',
            'path' => 'site-ministries',
            'icon' => 'document',
        ),
        array(
            'title' => 'Contact Routing',
            'description' => 'Message recipients for website and app contact forms.',
            'path' => 'contact-routing',
            'icon' => 'settings',
        ),
        array(
            'title' => 'Integrations',
            'description' => 'External services and technical connections used across Surfside.',
            'path' => 'settings',
            'icon' => 'settings',
        ),
    );

    ob_start();
    ?>
    <div class="surfside-staff-shell surfside-site-settings">
        <div class="surfside-staff-back"><a href="<?php echo esc_url(surfside_tools_staff_page_url('')); ?>">← Back to Dashboard</a></div>
        <section class="surfside-staff-hero">
            <p class="surfside-staff-eyebrow">Shared Configuration</p>
            <h1>Church Settings</h1>
            <p class="surfside-staff-muted">Information and services shared by the Surfside website and mobile app.</p>
        </section>
        <div class="surfside-staff-grid">
            <?php foreach ($cards as $card) : ?>
                <article class="surfside-staff-card">
                    <span class="surfside-staff-icon"><?php echo surfside_tools_staff_icon($card['icon']); ?></span>
                    <h2><?php echo esc_html($card['title']); ?></h2>
                    <p><?php echo esc_html($card['description']); ?></p>
                    <div class="surfside-staff-actions">
                        <a class="surfside-staff-button-secondary" href="<?php echo esc_url(surfside_tools_staff_page_url($card['path'])); ?>">Open <?php echo esc_html($card['title']); ?> <span class="surfside-staff-arrow">›</span></a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('surfside_staff_site_settings', 'surfside_tools_staff_site_settings_shortcode');

function surfside_tools_ensure_site_settings_page() {
    if (!function_exists('surfside_tools_ensure_staff_page')) {
        return;
    }
    $dashboard = get_page_by_path('dashboard');
    if (!$dashboard) {
        return;
    }
    surfside_tools_ensure_staff_page(
        'Church Settings',
        'site-settings',
        '[surfside_staff_site_settings]',
        (int) $dashboard->ID
    );
}
