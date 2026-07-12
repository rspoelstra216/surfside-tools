<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Surfside Tools Admin Application - v1.1.10
 * Adds native admin pages while preserving all existing shortcodes.
 */
add_action('admin_menu', function () {
    add_menu_page(
        'Surfside Tools',
        'Surfside Tools',
        'upload_files',
        'surfside-tools',
        'surfside_tools_admin_dashboard_page',
        'dashicons-admin-site-alt3',
        26
    );

    add_submenu_page('surfside-tools', 'Dashboard', 'Dashboard', 'upload_files', 'surfside-tools', 'surfside_tools_admin_dashboard_page');
    add_submenu_page('surfside-tools', 'Weekly Update', 'Weekly Update', 'upload_files', 'surfside-tools-weekly-update', 'surfside_tools_admin_weekly_update_page');
    add_submenu_page('surfside-tools', 'Calendar Manager', 'Calendar Manager', 'upload_files', 'surfside-tools-calendar', 'surfside_tools_admin_calendar_page');
    add_submenu_page('surfside-tools', 'Settings', 'Settings', 'manage_options', 'surfside-tools-settings', 'surfside_tools_admin_settings_page');
});

add_action('admin_enqueue_scripts', function ($hook) {
    if (strpos($hook, 'surfside-tools') === false) {
        return;
    }

    wp_register_style('surfside-tools-admin', false);
    wp_enqueue_style('surfside-tools-admin');

    wp_add_inline_style('surfside-tools-admin', '
        .surfside-admin-wrap { max-width: 1200px; }
        .surfside-admin-hero {
            background:#fff;
            border:1px solid #dcdcde;
            border-radius:14px;
            padding:24px 28px;
            margin:20px 0;
            box-shadow:0 2px 8px rgba(0,0,0,.04);
        }
        .surfside-admin-hero h1 { margin:0 0 8px; font-size:32px; line-height:1.15; }
        .surfside-admin-grid {
            display:grid;
            grid-template-columns:repeat(3,minmax(0,1fr));
            gap:18px;
            margin-top:18px;
        }
        .surfside-admin-card {
            background:#fff;
            border:1px solid #dcdcde;
            border-radius:14px;
            padding:22px;
            box-shadow:0 2px 8px rgba(0,0,0,.04);
        }
        .surfside-admin-card h2,
        .surfside-admin-card h3 { margin-top:0; }
        .surfside-admin-status {
            display:grid;
            grid-template-columns:repeat(2,minmax(0,1fr));
            gap:14px;
            margin-top:18px;
        }
        .surfside-admin-status-item {
            background:#f6f7f7;
            border-radius:10px;
            padding:14px;
        }
        .surfside-admin-muted { color:#646970; }
        .surfside-admin-coming-soon { opacity:.78; }
        .surfside-admin-list { list-style:disc; padding-left:20px; }
        .surfside-weekly-update-tool { max-width:1200px; }
        .surfside-weekly-update-tool h2:first-child { display:none; }
        @media (max-width:900px) {
            .surfside-admin-grid,
            .surfside-admin-status { grid-template-columns:1fr; }
        }
    ');
});

function surfside_tools_admin_last_update_label($option_name) {
    $data = get_option($option_name);

    if (!is_array($data) || empty($data['timestamp'])) {
        return 'Not published yet';
    }

    return date_i18n('F j, Y g:i A', (int) $data['timestamp']);
}

function surfside_tools_admin_dashboard_page() {
    if (!current_user_can('upload_files')) {
        wp_die('You do not have permission to access Surfside Tools.');
    }

    $announcements = function_exists('surfside_tools_get_announcements_data') ? surfside_tools_get_announcements_data() : array();
    $message = function_exists('surfside_tools_get_message_data') ? surfside_tools_get_message_data() : array();

    $announcements_date = !empty($announcements['announcement_date']) ? $announcements['announcement_date'] : 'Not set';
    $message_title = !empty($message['title']) ? $message['title'] : 'Not set';
    $message_date = !empty($message['date']) ? $message['date'] : '';
    ?>
    <div class="wrap surfside-admin-wrap">
        <div class="surfside-admin-hero">
            <h1>Surfside Tools</h1>
            <p class="surfside-admin-muted">Church website management for weekly updates, announcements, sermon notes, and future calendar tools.</p>
            <p><strong>Version:</strong> <?php echo esc_html(SURFSIDE_TOOLS_VERSION); ?></p>
        </div>

        <div class="surfside-admin-status">
            <div class="surfside-admin-status-item">
                <strong>Announcements</strong><br>
                <?php echo esc_html($announcements_date); ?><br>
                <span class="surfside-admin-muted">Last published: <?php echo esc_html(surfside_tools_admin_last_update_label('surfside_tools_announcements_current')); ?></span>
            </div>

            <div class="surfside-admin-status-item">
                <strong>Sermon Notes</strong><br>
                <?php echo esc_html($message_title); ?><?php echo $message_date ? ' — ' . esc_html($message_date) : ''; ?><br>
                <span class="surfside-admin-muted">Last published: <?php echo esc_html(surfside_tools_admin_last_update_label('surfside_tools_message_current')); ?></span>
            </div>
        </div>

        <div class="surfside-admin-grid">
            <div class="surfside-admin-card">
                <h2>Weekly Update</h2>
                <p>Upload this week&apos;s announcements and sermon notes, review the previews, and publish.</p>
                <a class="button button-primary" href="<?php echo esc_url(admin_url('admin.php?page=surfside-tools-weekly-update')); ?>">Open Weekly Update</a>
            </div>

            <div class="surfside-admin-card surfside-admin-coming-soon">
                <h2>Calendar Manager</h2>
                <p>Add and edit Google Calendar events from a simplified Surfside interface.</p>
                <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=surfside-tools-calendar')); ?>">View Roadmap</a>
            </div>

            <div class="surfside-admin-card surfside-admin-coming-soon">
                <h2>Settings</h2>
                <p>Configure Google Calendar credentials, page links, cache behavior, and plugin options.</p>
                <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=surfside-tools-settings')); ?>">Open Settings</a>
            </div>
        </div>
    </div>
    <?php
}

function surfside_tools_admin_weekly_update_page() {
    if (!current_user_can('upload_files')) {
        wp_die('You do not have permission to access Weekly Update.');
    }

    echo '<div class="wrap surfside-admin-wrap">';
    echo '<div class="surfside-admin-hero">';
    echo '<h1>Weekly Update</h1>';
    echo '<p class="surfside-admin-muted">Upload announcements and sermon notes together, or update only one section when needed.</p>';
    echo '</div>';

    if (function_exists('surfside_tools_weekly_update_shortcode')) {
        echo surfside_tools_weekly_update_shortcode();
    } else {
        echo '<div class="notice notice-error"><p>Weekly Update module is not available.</p></div>';
    }

    echo '</div>';
}

function surfside_tools_admin_calendar_page() {
    if (!current_user_can('upload_files')) {
        wp_die('You do not have permission to access Calendar Manager.');
    }
    ?>
    <div class="wrap surfside-admin-wrap">
        <div class="surfside-admin-hero">
            <h1>Calendar Manager</h1>
            <p class="surfside-admin-muted">Phase 1 event manager. The secretary-facing version is available at /dashboard/calendar.</p>
        </div>
        <?php echo do_shortcode('[surfside_tools_calendar_manager]'); ?>
    </div>
    <?php
}

function surfside_tools_sanitize_settings($input) {
    $input = is_array($input) ? $input : array();
    $mode = isset($input['this_week_mode']) ? sanitize_key($input['this_week_mode']) : 'next7';
    if (!in_array($mode, array('next7', 'sunday'), true)) {
        $mode = 'next7';
    }

    return array(
        'google_maps_api_key' => isset($input['google_maps_api_key']) ? sanitize_text_field($input['google_maps_api_key']) : '',
        'this_week_mode' => $mode,
        'default_event_duration' => max(15, min(480, isset($input['default_event_duration']) ? absint($input['default_event_duration']) : 60)),
    );
}

add_action('admin_init', function () {
    register_setting(
        'surfside_tools_settings_group',
        'surfside_tools_settings',
        array(
            'type' => 'array',
            'sanitize_callback' => 'surfside_tools_sanitize_settings',
            'default' => array(
                'google_maps_api_key' => '',
                'this_week_mode' => 'next7',
                'default_event_duration' => 60,
            ),
        )
    );
});

function surfside_tools_get_setting($key, $default = '') {
    $settings = get_option('surfside_tools_settings', array());
    return is_array($settings) && array_key_exists($key, $settings) ? $settings[$key] : $default;
}

function surfside_tools_admin_settings_page() {
    if (!current_user_can('manage_options')) {
        wp_die('You do not have permission to access Surfside Tools settings.');
    }

    $api_key = (string) surfside_tools_get_setting('google_maps_api_key', '');
    $week_mode = (string) surfside_tools_get_setting('this_week_mode', 'next7');
    $duration = (int) surfside_tools_get_setting('default_event_duration', 60);
    ?>
    <div class="wrap surfside-admin-wrap">
        <div class="surfside-admin-hero">
            <h1>Surfside Tools Settings</h1>
            <p class="surfside-admin-muted">Configure integrations and calendar defaults.</p>
        </div>

        <?php settings_errors(); ?>

        <form method="post" action="options.php">
            <?php settings_fields('surfside_tools_settings_group'); ?>

            <div class="surfside-admin-card" style="margin-bottom:18px;">
                <h2>Google Maps Integration</h2>
                <p class="surfside-admin-muted">Used for Google Places location search in the Calendar Manager.</p>

                <label for="surfside-google-maps-key"><strong>Google Maps API Key</strong></label>
                <div style="display:flex;gap:10px;align-items:center;max-width:760px;margin-top:8px;">
                    <input id="surfside-google-maps-key" class="regular-text" style="flex:1;max-width:none;" type="password" autocomplete="off" name="surfside_tools_settings[google_maps_api_key]" value="<?php echo esc_attr($api_key); ?>">
                    <button type="button" class="button" id="surfside-test-google-maps">Test Connection</button>
                </div>
                <p class="description">The key should allow Maps JavaScript API and Places API for this website.</p>
                <div id="surfside-google-maps-status" style="margin-top:10px;font-weight:600;" aria-live="polite">
                    <?php echo $api_key ? '<span style="color:#646970;">Key saved — connection not tested in this browser.</span>' : '<span style="color:#b32d2e;">No API key saved.</span>'; ?>
                </div>
            </div>

            <div class="surfside-admin-card" style="margin-bottom:18px;">
                <h2>Calendar Defaults</h2>

                <fieldset style="margin-bottom:20px;">
                    <legend><strong>This Week at Surfside</strong></legend>
                    <label style="display:block;margin:10px 0;">
                        <input type="radio" name="surfside_tools_settings[this_week_mode]" value="next7" <?php checked($week_mode, 'next7'); ?>>
                        Next 7 days starting today
                    </label>
                    <label style="display:block;margin:10px 0;">
                        <input type="radio" name="surfside_tools_settings[this_week_mode]" value="sunday" <?php checked($week_mode, 'sunday'); ?>>
                        Current Sunday–Saturday week
                    </label>
                </fieldset>

                <label for="surfside-default-duration"><strong>Default event duration</strong></label><br>
                <input id="surfside-default-duration" type="number" min="15" max="480" step="15" name="surfside_tools_settings[default_event_duration]" value="<?php echo esc_attr($duration); ?>" style="width:100px;margin-top:8px;"> minutes
                <p class="description">Used to suggest an end time after a start time is entered for a new event.</p>
            </div>

            <?php submit_button('Save Settings'); ?>
        </form>
    </div>

    <script>
    (function () {
        var button = document.getElementById('surfside-test-google-maps');
        var keyInput = document.getElementById('surfside-google-maps-key');
        var status = document.getElementById('surfside-google-maps-status');
        if (!button || !keyInput || !status) return;

        button.addEventListener('click', function () {
            var key = keyInput.value.trim();
            if (!key) {
                status.innerHTML = '<span style="color:#b32d2e;">Enter and save an API key first.</span>';
                return;
            }

            button.disabled = true;
            status.innerHTML = '<span style="color:#646970;">Testing Google Maps and Places…</span>';

            window.surfsideGoogleMapsReady = function () {
                button.disabled = false;
                if (window.google && google.maps && google.maps.places) {
                    status.innerHTML = '<span style="color:#008a20;">Connected. Maps JavaScript API and Places are available.</span>';
                } else {
                    status.innerHTML = '<span style="color:#b32d2e;">Google Maps loaded, but Places is unavailable. Check API restrictions.</span>';
                }
            };

            var old = document.getElementById('surfside-google-maps-test-script');
            if (old) old.remove();
            var script = document.createElement('script');
            script.id = 'surfside-google-maps-test-script';
            script.async = true;
            script.defer = true;
            script.onerror = function () {
                button.disabled = false;
                status.innerHTML = '<span style="color:#b32d2e;">Connection failed. Verify the key, website restrictions, billing, and enabled APIs.</span>';
            };
            script.src = 'https://maps.googleapis.com/maps/api/js?key=' + encodeURIComponent(key) + '&libraries=places&callback=surfsideGoogleMapsReady';
            document.head.appendChild(script);
        });
    }());
    </script>
    <?php
}

