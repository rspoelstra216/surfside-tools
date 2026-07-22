<?php

if (!defined('ABSPATH')) {
    exit;
}

function surfside_tools_site_information_capability() {
    return apply_filters('surfside_tools_site_information_capability', 'manage_options');
}

function surfside_tools_site_information_manager_notice($message, $type = 'success') {
    return '<div class="surfside-information-notice surfside-information-notice-' . esc_attr($type) . '" role="status">' . esc_html($message) . '</div>';
}

function surfside_tools_site_information_manager_handle_post() {
    if (
        ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' ||
        empty($_POST['surfside_information_action'])
    ) {
        return '';
    }

    if (!current_user_can(surfside_tools_site_information_capability())) {
        return surfside_tools_site_information_manager_notice('You do not have permission to update Surfside Information.', 'error');
    }

    $nonce = isset($_POST['surfside_information_nonce'])
        ? sanitize_text_field(wp_unslash($_POST['surfside_information_nonce']))
        : '';
    if (!wp_verify_nonce($nonce, 'surfside_information_update')) {
        return surfside_tools_site_information_manager_notice('Security check failed. Please refresh and try again.', 'error');
    }

    $service_keys = isset($_POST['service_key']) ? (array) wp_unslash($_POST['service_key']) : array();
    $service_weekdays = isset($_POST['service_weekday']) ? (array) wp_unslash($_POST['service_weekday']) : array();
    $service_labels = isset($_POST['service_label']) ? (array) wp_unslash($_POST['service_label']) : array();
    $service_times = isset($_POST['service_time']) ? (array) wp_unslash($_POST['service_time']) : array();
    $services = array();

    $weekday_names = array(
        1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday',
        5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday',
    );
    foreach ($service_keys as $index => $key) {
        $weekday = absint($service_weekdays[$index] ?? 0);
        $services[] = array(
            'key' => $key,
            'weekday' => $weekday,
            'day' => $weekday_names[$weekday] ?? '',
            'label' => $service_labels[$index] ?? '',
            'time' => $service_times[$index] ?? '',
        );
    }

    $defaults = surfside_tools_site_information_defaults();
    $navigation = array();
    foreach ($defaults['navigation'] as $key => $link) {
        $navigation[$key] = array(
            'label' => $link['label'],
            'url' => isset($_POST['navigation'][$key])
                ? wp_unslash($_POST['navigation'][$key])
                : $link['url'],
        );
    }

    $social = array();
    foreach ($defaults['social'] as $key => $link) {
        $social[$key] = array(
            'label' => $link['label'],
            'url' => isset($_POST['social'][$key])
                ? wp_unslash($_POST['social'][$key])
                : $link['url'],
        );
    }

    surfside_tools_update_site_information(array(
        'identity' => array(
            'name' => isset($_POST['church_name']) ? wp_unslash($_POST['church_name']) : '',
            'tagline' => isset($_POST['tagline']) ? wp_unslash($_POST['tagline']) : '',
            'phone' => isset($_POST['phone']) ? wp_unslash($_POST['phone']) : '',
            'contact_url' => isset($_POST['contact_url']) ? wp_unslash($_POST['contact_url']) : '',
        ),
        'location' => array(
            'venue' => isset($_POST['venue']) ? wp_unslash($_POST['venue']) : '',
            'street' => isset($_POST['street']) ? wp_unslash($_POST['street']) : '',
            'city' => isset($_POST['city']) ? wp_unslash($_POST['city']) : '',
            'state' => isset($_POST['state']) ? wp_unslash($_POST['state']) : '',
            'postal_code' => isset($_POST['postal_code']) ? wp_unslash($_POST['postal_code']) : '',
        ),
        'services' => $services,
        'navigation' => $navigation,
        'social' => $social,
    ));

    return surfside_tools_site_information_manager_notice('Surfside Information saved.');
}

function surfside_tools_site_information_manager_assets() {
    wp_register_style(
        'surfside-tools-information-manager',
        false,
        array('surfside-tools-staff-dashboard'),
        defined('SURFSIDE_TOOLS_VERSION') ? SURFSIDE_TOOLS_VERSION : '2.3.1'
    );
    wp_enqueue_style('surfside-tools-information-manager');
    wp_add_inline_style('surfside-tools-information-manager', '
        .surfside-information-form{display:grid;gap:22px}.surfside-information-card{padding:clamp(20px,3vw,30px);border:1px solid rgba(6,27,51,.13);border-radius:18px;background:#fff;box-shadow:0 8px 24px rgba(6,27,51,.06)}.surfside-information-card h2{margin:0 0 6px;color:#061b33}.surfside-information-card>p{margin:0 0 20px;color:#56616d}.surfside-information-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px}.surfside-information-field{display:grid;gap:7px}.surfside-information-field-wide{grid-column:1/-1}.surfside-information-field span,.surfside-information-services legend{color:#26323d;font-weight:800}.surfside-information-field input,.surfside-information-field select{width:100%;min-height:46px;padding:10px 12px;border:1px solid #aeb9c4;border-radius:9px;background:#fff;color:#26323d;font:inherit}.surfside-information-field input:focus,.surfside-information-field select:focus{border-color:#0b5fa5;outline:3px solid rgba(11,95,165,.18);outline-offset:1px}.surfside-information-help{margin:0;color:#687480;font-size:.88rem;line-height:1.45}.surfside-information-services{display:grid;gap:16px;margin:0;padding:0;border:0}.surfside-information-service{display:grid;grid-template-columns:minmax(130px,.7fr) minmax(170px,1.2fr) minmax(130px,.7fr);gap:14px;padding:18px;border-radius:13px;background:#f6f1e8}.surfside-information-link-list{display:grid;gap:13px}.surfside-information-link{display:grid;grid-template-columns:minmax(130px,.35fr) minmax(0,1fr);align-items:center;gap:14px}.surfside-information-link strong{color:#26323d}.surfside-information-actions{position:sticky;bottom:14px;z-index:3;display:flex;justify-content:flex-end;padding:14px;border:1px solid rgba(6,27,51,.12);border-radius:14px;background:rgba(255,255,255,.94);box-shadow:0 10px 28px rgba(6,27,51,.12);backdrop-filter:blur(8px)}.surfside-information-save{min-height:48px;padding:11px 22px;border:0;border-radius:9px;background:#0b5fa5;color:#fff;font:inherit;font-weight:900;cursor:pointer}.surfside-information-save:hover,.surfside-information-save:focus-visible{background:#061b33}.surfside-information-save:focus-visible{outline:3px solid rgba(11,95,165,.28);outline-offset:3px}.surfside-information-notice{margin:0 0 20px;padding:14px 16px;border-radius:10px;font-weight:800}.surfside-information-notice-success{border:1px solid #9bd2a6;background:#edf9f0;color:#17682e}.surfside-information-notice-error{border:1px solid #e7aaaa;background:#fff0f0;color:#9b2020}@media(max-width:720px){.surfside-information-grid,.surfside-information-service,.surfside-information-link{grid-template-columns:1fr}.surfside-information-field-wide{grid-column:auto}.surfside-information-actions{bottom:8px}.surfside-information-save{width:100%}}
    ');
}

function surfside_tools_staff_site_information_shortcode() {
    if (function_exists('surfside_tools_prevent_cache')) {
        surfside_tools_prevent_cache();
    }
    if (function_exists('surfside_tools_staff_enqueue_styles')) {
        surfside_tools_staff_enqueue_styles();
    }

    if (!is_user_logged_in()) {
        return function_exists('surfside_tools_staff_login_box')
            ? surfside_tools_staff_login_box('Please log in to manage Surfside Information.')
            : '<p>Please log in.</p>';
    }

    if (!current_user_can(surfside_tools_site_information_capability())) {
        return '<div class="surfside-staff-shell"><p>You do not have permission to manage Surfside Information.</p></div>';
    }

    surfside_tools_site_information_manager_assets();
    $notice = surfside_tools_site_information_manager_handle_post();
    $information = surfside_tools_get_site_information();
    $identity = $information['identity'];
    $location = $information['location'];
    $weekdays = array(
        1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday',
        5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday',
    );

    ob_start();
    ?>
    <div class="surfside-staff-shell surfside-information-manager">
        <div class="surfside-staff-back"><a href="<?php echo esc_url(surfside_tools_staff_page_url('')); ?>">← Back to Dashboard</a></div>
        <section class="surfside-staff-hero">
            <p class="surfside-staff-eyebrow">Sitewide Information</p>
            <h1>Surfside Information</h1>
            <p class="surfside-staff-muted">Update the shared information used by Surfside Tools and future sitewide components.</p>
        </section>

        <?php echo $notice; ?>

        <form method="post" class="surfside-information-form">
            <?php wp_nonce_field('surfside_information_update', 'surfside_information_nonce'); ?>
            <input type="hidden" name="surfside_information_action" value="save">

            <section class="surfside-information-card">
                <h2>Church Identity</h2>
                <p>The public name, tagline, phone number, and Contact destination.</p>
                <div class="surfside-information-grid">
                    <label class="surfside-information-field"><span>Church name</span><input type="text" name="church_name" value="<?php echo esc_attr($identity['name']); ?>" required></label>
                    <label class="surfside-information-field"><span>Phone</span><input type="tel" name="phone" value="<?php echo esc_attr($identity['phone']); ?>" required></label>
                    <label class="surfside-information-field surfside-information-field-wide"><span>Tagline</span><input type="text" name="tagline" value="<?php echo esc_attr($identity['tagline']); ?>" required></label>
                    <label class="surfside-information-field surfside-information-field-wide"><span>Contact destination</span><input type="text" name="contact_url" value="<?php echo esc_attr($identity['contact_url']); ?>" required><small class="surfside-information-help">Use a site path such as /contact/#Contact or a complete URL.</small></label>
                </div>
            </section>

            <section class="surfside-information-card">
                <h2>Current Meeting Location</h2>
                <p>This address will generate the public Google Maps destination automatically.</p>
                <div class="surfside-information-grid">
                    <label class="surfside-information-field surfside-information-field-wide"><span>Venue</span><input type="text" name="venue" value="<?php echo esc_attr($location['venue']); ?>" required></label>
                    <label class="surfside-information-field surfside-information-field-wide"><span>Street address</span><input type="text" name="street" value="<?php echo esc_attr($location['street']); ?>" required></label>
                    <label class="surfside-information-field"><span>City</span><input type="text" name="city" value="<?php echo esc_attr($location['city']); ?>" required></label>
                    <label class="surfside-information-field"><span>State</span><input type="text" name="state" value="<?php echo esc_attr($location['state']); ?>" maxlength="2" required></label>
                    <label class="surfside-information-field"><span>ZIP code</span><input type="text" name="postal_code" value="<?php echo esc_attr($location['postal_code']); ?>" required></label>
                </div>
            </section>

            <section class="surfside-information-card">
                <h2>Service Schedule</h2>
                <p>Each service has a weekday, public label, and start time.</p>
                <fieldset class="surfside-information-services">
                    <legend class="screen-reader-text">Weekly services</legend>
                    <?php foreach ($information['services'] as $service) : ?>
                        <div class="surfside-information-service">
                            <input type="hidden" name="service_key[]" value="<?php echo esc_attr($service['key']); ?>">
                            <label class="surfside-information-field"><span>Day</span><select name="service_weekday[]"><?php foreach ($weekdays as $number => $day) : ?><option value="<?php echo esc_attr($number); ?>" <?php selected((int) $service['weekday'], $number); ?>><?php echo esc_html($day); ?></option><?php endforeach; ?></select></label>
                            <label class="surfside-information-field"><span>Public label</span><input type="text" name="service_label[]" value="<?php echo esc_attr($service['label']); ?>" required></label>
                            <label class="surfside-information-field"><span>Start time</span><input type="time" name="service_time[]" value="<?php echo esc_attr($service['time']); ?>" required></label>
                        </div>
                    <?php endforeach; ?>
                </fieldset>
            </section>

            <section class="surfside-information-card">
                <h2>Main Navigation</h2>
                <p>These stable destinations will be reused by the redesigned footer.</p>
                <div class="surfside-information-link-list">
                    <?php foreach ($information['navigation'] as $key => $link) : ?>
                        <label class="surfside-information-link"><strong><?php echo esc_html($link['label']); ?></strong><input type="text" name="navigation[<?php echo esc_attr($key); ?>]" value="<?php echo esc_attr($link['url']); ?>" required></label>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="surfside-information-card">
                <h2>Social Destinations</h2>
                <p>The footer will present these as accessible social icons.</p>
                <div class="surfside-information-link-list">
                    <?php foreach ($information['social'] as $key => $link) : ?>
                        <label class="surfside-information-link"><strong><?php echo esc_html($link['label']); ?></strong><input type="url" name="social[<?php echo esc_attr($key); ?>]" value="<?php echo esc_attr($link['url']); ?>" required></label>
                    <?php endforeach; ?>
                </div>
            </section>

            <div class="surfside-information-actions">
                <button type="submit" class="surfside-information-save">Save Surfside Information</button>
            </div>
        </form>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('surfside_staff_site_information', 'surfside_tools_staff_site_information_shortcode');

function surfside_tools_repair_site_information_staff_page() {
    if (!function_exists('surfside_tools_ensure_staff_page')) {
        return;
    }

    $dashboard = get_page_by_path('dashboard');
    if (!$dashboard) {
        return;
    }

    $existing = get_page_by_path('dashboard/surfside-information');
    if ($existing && $existing->post_status === 'publish') {
        return;
    }

    $page_id = surfside_tools_ensure_staff_page(
        'Surfside Information',
        'surfside-information',
        '[surfside_staff_site_information]',
        (int) $dashboard->ID
    );

    if ($page_id) {
        flush_rewrite_rules(false);
    }
}
add_action('init', 'surfside_tools_repair_site_information_staff_page', 70);
