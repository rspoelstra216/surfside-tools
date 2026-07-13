<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Front-end staff settings page.
 * Keeps the WordPress admin settings screen as a fallback while allowing normal
 * staff workflows to remain inside /dashboard.
 */
function surfside_tools_frontend_settings_notice($message, $type = 'success') {
    return '<div class="surfside-front-settings-notice surfside-front-settings-' . esc_attr($type) . '">' . esc_html($message) . '</div>';
}

function surfside_tools_frontend_settings_handle_post() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['surfside_front_settings_action'])) {
        return '';
    }

    if (!current_user_can('manage_options')) {
        return surfside_tools_frontend_settings_notice('You do not have permission to change these settings.', 'error');
    }

    $action = sanitize_key(wp_unslash($_POST['surfside_front_settings_action']));
    if (empty($_POST['surfside_front_settings_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['surfside_front_settings_nonce'])), 'surfside_front_settings')) {
        return surfside_tools_frontend_settings_notice('Security check failed. Please refresh and try again.', 'error');
    }

    if ($action === 'save_settings') {
        $mode = isset($_POST['this_week_mode']) ? sanitize_key(wp_unslash($_POST['this_week_mode'])) : 'next7';
        if (!in_array($mode, array('next7', 'sunday'), true)) {
            $mode = 'next7';
        }
        update_option('surfside_tools_settings', array(
            'google_maps_api_key' => isset($_POST['google_maps_api_key']) ? sanitize_text_field(wp_unslash($_POST['google_maps_api_key'])) : '',
            'this_week_mode' => $mode,
            'default_event_duration' => max(15, min(480, isset($_POST['default_event_duration']) ? absint($_POST['default_event_duration']) : 60)),
        ), false);
        return surfside_tools_frontend_settings_notice('Settings saved.');
    }

    if ($action === 'delete_saved_place') {
        $place_id = isset($_POST['place_id']) ? absint($_POST['place_id']) : 0;
        $post = $place_id ? get_post($place_id) : null;
        if (!$post || $post->post_type !== 'surfside_location') {
            return surfside_tools_frontend_settings_notice('That saved place could not be found.', 'error');
        }
        wp_trash_post($place_id);
        return surfside_tools_frontend_settings_notice('Saved place removed. Existing calendar events were not changed.');
    }

    if ($action === 'hide_calendar_place') {
        $name = isset($_POST['place_name']) ? sanitize_text_field(wp_unslash($_POST['place_name'])) : '';
        $normalized = function_exists('surfside_tools_normalize_place_name') ? surfside_tools_normalize_place_name($name) : strtolower(trim($name));
        if ($normalized === '') {
            return surfside_tools_frontend_settings_notice('That place name was empty.', 'error');
        }
        $hidden = function_exists('surfside_tools_get_hidden_place_names') ? surfside_tools_get_hidden_place_names() : array();
        if (!in_array($normalized, $hidden, true)) {
            $hidden[] = $normalized;
            update_option('surfside_tools_hidden_place_names', array_values($hidden), false);
        }
        return surfside_tools_frontend_settings_notice('Place removed from future suggestions. Existing events were not changed.');
    }

    if ($action === 'restore_calendar_place') {
        $name = isset($_POST['place_name']) ? sanitize_text_field(wp_unslash($_POST['place_name'])) : '';
        $hidden = function_exists('surfside_tools_get_hidden_place_names') ? surfside_tools_get_hidden_place_names() : array();
        update_option('surfside_tools_hidden_place_names', array_values(array_diff($hidden, array($name))), false);
        return surfside_tools_frontend_settings_notice('Place restored to location suggestions.');
    }

    return '';
}

function surfside_tools_frontend_saved_places_data() {
    $saved = function_exists('surfside_tools_calendar_get_saved_locations') ? surfside_tools_calendar_get_saved_locations() : array();
    $saved_names = array();
    foreach ($saved as $place) {
        $key = function_exists('surfside_tools_normalize_place_name') ? surfside_tools_normalize_place_name($place['name'] ?? '') : strtolower(trim((string) ($place['name'] ?? '')));
        $saved_names[$key] = true;
    }

    $calendar_places = array();
    if (function_exists('surfside_tools_calendar_get_all_events')) {
        foreach (surfside_tools_calendar_get_all_events() as $event) {
            $name = trim((string) ($event['location_name'] ?? ''));
            $key = function_exists('surfside_tools_normalize_place_name') ? surfside_tools_normalize_place_name($name) : strtolower($name);
            if ($name === '' || isset($saved_names[$key])) {
                continue;
            }
            if (!isset($calendar_places[$key])) {
                $calendar_places[$key] = array(
                    'name' => $name,
                    'address' => trim((string) ($event['location_address'] ?? '')),
                );
            }
        }
    }

    return array($saved, $calendar_places);
}

function surfside_tools_staff_settings_shortcode() {
    if (function_exists('surfside_tools_prevent_cache')) {
        surfside_tools_prevent_cache();
    }
    if (function_exists('surfside_tools_staff_enqueue_styles')) {
        surfside_tools_staff_enqueue_styles();
    }

    if (!is_user_logged_in()) {
        return function_exists('surfside_tools_staff_login_box') ? surfside_tools_staff_login_box('Please log in to access settings.') : '<p>Please log in.</p>';
    }
    if (!current_user_can('manage_options')) {
        return '<div class="surfside-staff-shell"><p>You do not have permission to manage Surfside Tools settings.</p></div>';
    }

    $notice = surfside_tools_frontend_settings_handle_post();
    $settings = get_option('surfside_tools_settings', array());
    $api_key = (string) ($settings['google_maps_api_key'] ?? '');
    $week_mode = (string) ($settings['this_week_mode'] ?? 'next7');
    $duration = (int) ($settings['default_event_duration'] ?? 60);
    list($saved, $calendar_places) = surfside_tools_frontend_saved_places_data();
    $hidden = function_exists('surfside_tools_get_hidden_place_names') ? surfside_tools_get_hidden_place_names() : array();

    ob_start();
    ?>
    <div class="surfside-staff-shell surfside-front-settings">
        <div class="surfside-staff-back"><a href="<?php echo esc_url(function_exists('surfside_tools_staff_page_url') ? surfside_tools_staff_page_url('') : home_url('/dashboard/')); ?>">← Back to Dashboard</a></div>
        <section class="surfside-staff-hero">
            <p class="surfside-staff-eyebrow">Settings</p>
            <h1>Surfside Tools Settings</h1>
            <p class="surfside-staff-muted">Manage Google Maps, calendar defaults, and saved places without opening WordPress administration.</p>
        </section>

        <?php echo $notice; ?>

        <form method="post" class="surfside-front-settings-form">
            <?php wp_nonce_field('surfside_front_settings', 'surfside_front_settings_nonce'); ?>
            <input type="hidden" name="surfside_front_settings_action" value="save_settings">

            <section class="surfside-front-settings-card">
                <h2>Google Maps Integration</h2>
                <p class="surfside-staff-muted">Used for Google Places search in Calendar Manager and Weekly Update suggestions.</p>
                <label for="surfside-front-google-key"><strong>Google Maps API Key</strong></label>
                <div class="surfside-front-key-row">
                    <input id="surfside-front-google-key" type="password" autocomplete="off" name="google_maps_api_key" value="<?php echo esc_attr($api_key); ?>">
                    <button type="button" class="surfside-front-secondary-button" id="surfside-front-test-maps">Test Connection</button>
                </div>
                <p class="surfside-front-description">The key should allow Maps JavaScript API and Places API for this website.</p>
                <div id="surfside-front-maps-status" aria-live="polite"><?php echo $api_key ? 'Key saved — connection not tested in this browser.' : 'No API key saved.'; ?></div>
            </section>

            <section class="surfside-front-settings-card">
                <h2>Calendar Defaults</h2>
                <fieldset>
                    <legend><strong>This Week at Surfside</strong></legend>
                    <label><input type="radio" name="this_week_mode" value="next7" <?php checked($week_mode, 'next7'); ?>> Next 7 days starting today</label>
                    <label><input type="radio" name="this_week_mode" value="sunday" <?php checked($week_mode, 'sunday'); ?>> Current Sunday–Saturday week</label>
                </fieldset>
                <label for="surfside-front-duration"><strong>Default event duration</strong></label>
                <div><input id="surfside-front-duration" type="number" min="15" max="480" step="15" name="default_event_duration" value="<?php echo esc_attr($duration); ?>"> minutes</div>
                <p class="surfside-front-description">Used to suggest an end time after a start time is entered.</p>
            </section>

            <p><button type="submit" class="surfside-front-primary-button">Save Settings</button></p>
        </form>

        <section class="surfside-front-settings-card">
            <h2>Saved Places</h2>
            <p class="surfside-staff-muted">Removing a place does not change existing calendar events.</p>
            <?php if (!$saved && !$calendar_places) : ?>
                <p>No saved or previously used places were found.</p>
            <?php else : ?>
                <div class="surfside-front-place-table">
                    <div class="surfside-front-place-head"><span>Place</span><span>Address</span><span>Source</span><span>Action</span></div>
                    <?php foreach ($saved as $place) : ?>
                        <div class="surfside-front-place-row">
                            <strong><?php echo esc_html($place['name'] ?? ''); ?></strong>
                            <span><?php echo esc_html($place['address'] ?? ''); ?></span>
                            <span>Saved place</span>
                            <form method="post" onsubmit="return confirm('Remove this saved place? Existing events will keep their current location.');">
                                <?php wp_nonce_field('surfside_front_settings', 'surfside_front_settings_nonce'); ?>
                                <input type="hidden" name="surfside_front_settings_action" value="delete_saved_place">
                                <input type="hidden" name="place_id" value="<?php echo (int) ($place['id'] ?? 0); ?>">
                                <button type="submit" class="surfside-front-delete-button">Delete</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                    <?php foreach ($calendar_places as $key => $place) : if (in_array($key, $hidden, true)) continue; ?>
                        <div class="surfside-front-place-row">
                            <strong><?php echo esc_html($place['name']); ?></strong>
                            <span><?php echo esc_html($place['address']); ?></span>
                            <span>Previously used</span>
                            <form method="post">
                                <?php wp_nonce_field('surfside_front_settings', 'surfside_front_settings_nonce'); ?>
                                <input type="hidden" name="surfside_front_settings_action" value="hide_calendar_place">
                                <input type="hidden" name="place_name" value="<?php echo esc_attr($place['name']); ?>">
                                <button type="submit" class="surfside-front-delete-button">Remove</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($hidden) : ?>
                <details class="surfside-front-removed"><summary><strong>Removed suggestions (<?php echo count($hidden); ?>)</strong></summary>
                    <div class="surfside-front-restore-list">
                        <?php foreach ($hidden as $name) : ?>
                            <form method="post">
                                <?php wp_nonce_field('surfside_front_settings', 'surfside_front_settings_nonce'); ?>
                                <input type="hidden" name="surfside_front_settings_action" value="restore_calendar_place">
                                <input type="hidden" name="place_name" value="<?php echo esc_attr($name); ?>">
                                <button type="submit" class="surfside-front-secondary-button">Restore <?php echo esc_html(ucwords($name)); ?></button>
                            </form>
                        <?php endforeach; ?>
                    </div>
                </details>
            <?php endif; ?>
        </section>
    </div>

    <style>
        .surfside-front-settings-card{background:#fff;border:1px solid rgba(7,27,58,.12);border-radius:18px;box-shadow:0 12px 32px rgba(7,27,58,.07);padding:clamp(20px,4vw,30px);margin-bottom:22px}.surfside-front-settings-card h2{margin-top:0}.surfside-front-settings-form fieldset{border:0;padding:0;margin:18px 0}.surfside-front-settings-form fieldset label{display:block;margin:10px 0}.surfside-front-key-row{display:flex;gap:10px;margin-top:8px}.surfside-front-key-row input{flex:1;min-width:0}.surfside-front-settings input[type=password],.surfside-front-settings input[type=number]{padding:10px 12px;border:1px solid #9aa9b8;border-radius:7px;font:inherit}.surfside-front-description{color:#526279;font-size:.92rem}.surfside-front-primary-button,.surfside-front-secondary-button,.surfside-front-delete-button{border-radius:8px;padding:10px 16px;font:inherit;font-weight:700;cursor:pointer}.surfside-front-primary-button{border:0;background:#0b4f9c;color:#fff}.surfside-front-secondary-button{border:1px solid #0b4f9c;background:#fff;color:#0b4f9c}.surfside-front-delete-button{border:0;background:transparent;color:#b42318;text-decoration:underline;padding:4px}.surfside-front-settings-notice{padding:13px 15px;border-radius:10px;margin-bottom:18px;font-weight:700}.surfside-front-settings-success{background:#edf7ed;color:#245f2a}.surfside-front-settings-error{background:#fdecec;color:#8b2323}.surfside-front-place-table{margin-top:16px;border:1px solid #d8e0e8;border-radius:10px;overflow:hidden}.surfside-front-place-head,.surfside-front-place-row{display:grid;grid-template-columns:1.2fr 1.5fr .8fr 90px;gap:12px;align-items:center;padding:12px 14px}.surfside-front-place-head{background:#edf3f8;font-weight:800}.surfside-front-place-row+ .surfside-front-place-row{border-top:1px solid #e3e8ed}.surfside-front-restore-list{display:flex;flex-wrap:wrap;gap:8px;margin-top:12px}@media(max-width:720px){.surfside-front-key-row{display:block}.surfside-front-key-row button{margin-top:8px}.surfside-front-place-head{display:none}.surfside-front-place-row{grid-template-columns:1fr;gap:5px}}
    </style>
    <script>
    document.addEventListener('DOMContentLoaded',function(){const button=document.getElementById('surfside-front-test-maps');const input=document.getElementById('surfside-front-google-key');const status=document.getElementById('surfside-front-maps-status');if(!button||!input||!status)return;button.addEventListener('click',function(){const key=input.value.trim();if(!key){status.textContent='Enter an API key first.';return}status.textContent='Testing Google Maps…';const callback='surfsideFrontMapsTest'+Date.now();window[callback]=function(){status.textContent=window.google&&google.maps&&google.maps.places?'Google Maps and Places connected successfully.':'Google Maps loaded, but Places was not available.';delete window[callback];script.remove()};const script=document.createElement('script');script.src='https://maps.googleapis.com/maps/api/js?key='+encodeURIComponent(key)+'&libraries=places&callback='+callback;script.onerror=function(){status.textContent='Google Maps could not connect. Check the key and its website restrictions.';delete window[callback];script.remove()};document.head.appendChild(script)})});
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode('surfside_staff_settings', 'surfside_tools_staff_settings_shortcode');

function surfside_tools_ensure_frontend_settings_page() {
    if (!is_admin() || !function_exists('surfside_tools_ensure_staff_page')) {
        return;
    }
    $dashboard = get_page_by_path('dashboard');
    if ($dashboard) {
        surfside_tools_ensure_staff_page('Settings', 'settings', '[surfside_staff_settings]', (int) $dashboard->ID);
    }
}
add_action('admin_init', 'surfside_tools_ensure_frontend_settings_page', 25);

add_filter('do_shortcode_tag', function ($output, $tag) {
    if ($tag !== 'surfside_staff_dashboard') {
        return $output;
    }
    $admin_url = admin_url('admin.php?page=surfside-tools-settings');
    $front_url = function_exists('surfside_tools_staff_page_url') ? surfside_tools_staff_page_url('settings') : home_url('/dashboard/settings/');
    return str_replace(esc_url($admin_url), esc_url($front_url), $output);
}, 10, 2);
