<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Authoritative front-end Integrations page.
 * Keeps the WordPress admin settings screen as a fallback while normal staff
 * workflows remain inside /dashboard.
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
        // Google Maps remains in Integrations, while calendar preferences now
        // have their own form. Never reset duration, week mode, or unknown keys.
        $stored = get_option('surfside_tools_settings', array());
        $settings = is_array($stored) ? $stored : array();
        $settings['google_maps_api_key'] = isset($_POST['google_maps_api_key']) && is_string($_POST['google_maps_api_key'])
            ? sanitize_text_field(wp_unslash($_POST['google_maps_api_key'])) : '';
        update_option('surfside_tools_settings', $settings, false);
        return surfside_tools_frontend_settings_notice('Google Maps settings saved.');
    }

    if ($action === 'delete_saved_place') {
        $place_id = isset($_POST['place_id']) ? absint($_POST['place_id']) : 0;
        $result = function_exists('surfside_tools_delete_saved_place_record')
            ? surfside_tools_delete_saved_place_record($place_id)
            : new WP_Error('surfside_saved_places_unavailable', 'Saved Places support is unavailable.');
        return surfside_tools_frontend_settings_notice(
            is_wp_error($result) ? $result->get_error_message() : $result,
            is_wp_error($result) ? 'error' : 'success'
        );
    }

    if ($action === 'hide_calendar_place') {
        $name = isset($_POST['place_name']) ? sanitize_text_field(wp_unslash($_POST['place_name'])) : '';
        $result = function_exists('surfside_tools_hide_calendar_place_name')
            ? surfside_tools_hide_calendar_place_name($name)
            : new WP_Error('surfside_saved_places_unavailable', 'Saved Places support is unavailable.');
        return surfside_tools_frontend_settings_notice(
            is_wp_error($result) ? $result->get_error_message() : $result,
            is_wp_error($result) ? 'error' : 'success'
        );
    }

    if ($action === 'restore_calendar_place') {
        $name = isset($_POST['place_name']) ? sanitize_text_field(wp_unslash($_POST['place_name'])) : '';
        $result = function_exists('surfside_tools_restore_calendar_place_name')
            ? surfside_tools_restore_calendar_place_name($name)
            : new WP_Error('surfside_saved_places_unavailable', 'Saved Places support is unavailable.');
        return surfside_tools_frontend_settings_notice(
            is_wp_error($result) ? $result->get_error_message() : $result,
            is_wp_error($result) ? 'error' : 'success'
        );
    }

    return '';
}

function surfside_tools_frontend_saved_places_data() {
    return function_exists('surfside_tools_saved_places_data')
        ? surfside_tools_saved_places_data()
        : array(array(), array());
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
    $shared_notice = function_exists('surfside_tools_church_settings_shared_save')
        ? surfside_tools_church_settings_shared_save()
        : '';
    // Process legacy submissions from stale Integrations forms, but only
    // render the current Twitch editor in the Streaming workspace.
    $twitch_notice = surfside_tools_twitch_settings_handle_post();
    $settings = get_option('surfside_tools_settings', array());
    $api_key = (string) ($settings['google_maps_api_key'] ?? '');
    $church_settings_url = function_exists('surfside_tools_staff_page_url')
        ? surfside_tools_staff_page_url('site-settings')
        : home_url('/dashboard/site-settings/');
    $legacy_redirects = array(
        '#surfside-focus-calendar-defaults' => add_query_arg('view', 'defaults', surfside_tools_staff_page_url('calendar')),
        '#surfside-focus-saved-places' => add_query_arg('view', 'places', surfside_tools_staff_page_url('calendar')),
        '#surfside-focus-streaming' => surfside_tools_staff_page_url('site-settings/streaming'),
        '#surfside-focus-twitch' => add_query_arg('stream_tab', 'twitch', surfside_tools_staff_page_url('site-settings/streaming')),
        '#surfside-twitch' => add_query_arg('stream_tab', 'twitch', surfside_tools_staff_page_url('site-settings/streaming')),
    );

    ob_start();
    ?>
    <div class="surfside-staff-shell surfside-front-settings">
        <div class="surfside-staff-back"><a href="<?php echo esc_url($church_settings_url); ?>">← Back to Church Settings</a></div>
        <section class="surfside-staff-hero">
            <p class="surfside-staff-eyebrow">Technical Configuration</p>
            <h1>Integrations</h1>
            <p class="surfside-staff-muted">Connect the services Surfside uses. Expand a service to view its settings, save changes, or test the connection.</p>
        </section>

        <?php echo $notice; ?>
        <?php echo $shared_notice; ?>
        <?php echo $twitch_notice; ?>
        <section class="surfside-integrations-section" aria-labelledby="surfside-integrations-connections">
          <div class="surfside-integrations-section-heading">
            <h2 id="surfside-integrations-connections">External connections</h2>
            <p>API credentials and metadata synchronization for content and locations.</p>
          </div>
        <form method="post" class="surfside-front-settings-form">
            <?php wp_nonce_field('surfside_front_settings', 'surfside_front_settings_nonce'); ?>
            <input type="hidden" name="surfside_front_settings_action" value="save_settings">

            <details class="surfside-front-settings-card surfside-integration-card">
                <summary><span>Google Maps</span></summary>
                <div class="surfside-integration-body">
                    <p class="surfside-staff-muted">Used for Google Places search in Calendar Manager and Weekly Update suggestions.</p>
                    <label for="surfside-front-google-key"><strong>Google Maps API Key</strong></label>
                    <div class="surfside-front-key-row">
                        <input id="surfside-front-google-key" type="password" autocomplete="off" name="google_maps_api_key" value="<?php echo esc_attr($api_key); ?>">
                        <button type="button" class="surfside-front-secondary-button" id="surfside-front-test-maps">Test Connection</button>
                    </div>
                    <p class="surfside-front-description">The key should allow Maps JavaScript API and Places API for this website.</p>
                    <div id="surfside-front-maps-status" aria-live="polite"><?php echo $api_key ? 'Key saved — connection not tested in this browser.' : 'No API key saved.'; ?></div>
                </div>
            </details>

            <p><button type="submit" class="surfside-front-primary-button">Save Google Maps Settings</button></p>
        </form>

        <?php if (function_exists('surfside_tools_youtube_settings_panel')) echo surfside_tools_youtube_settings_panel(); ?>
        <?php if (function_exists('surfside_tools_youversion_settings_panel')) echo surfside_tools_youversion_settings_panel(); ?>
        </section>

        <section class="surfside-integrations-section" aria-labelledby="surfside-integrations-services">
          <div class="surfside-integrations-section-heading">
            <h2 id="surfside-integrations-services">Church services</h2>
            <p>Giving and contact-form protection, shared by Surfside's public channels.</p>
          </div>
        <?php if (function_exists('surfside_tools_church_settings_shared_integrations_panel')) echo surfside_tools_church_settings_shared_integrations_panel(); ?>

        </section>

        <section class="surfside-integrations-section" aria-labelledby="surfside-integrations-advanced">
          <div class="surfside-integrations-section-heading">
            <h2 id="surfside-integrations-advanced">Advanced appearance</h2>
            <p>Optional reveal and countdown styling overrides. Leave unchanged to use the built-in design.</p>
          </div>
        <?php if (function_exists('surfside_tools_visual_css_settings_panel')) echo surfside_tools_visual_css_settings_panel(); ?>
        </section>
    </div>

    <style>
        .surfside-front-settings-card{background:#fff;border:1px solid rgba(7,27,58,.12);border-radius:18px;box-shadow:0 12px 32px rgba(7,27,58,.07);box-sizing:border-box}
        .surfside-front-settings .surfside-integration-card{padding:0;overflow:hidden;margin:0 0 10px;box-shadow:none}
        .surfside-integration-card>summary{display:grid;grid-template-columns:minmax(0,1fr) 24px;align-items:center;column-gap:14px;min-height:56px;box-sizing:border-box;padding:14px 18px;cursor:pointer;list-style:none;font-size:1.05rem;font-weight:800;color:#071b3a;background:#fff}
        .surfside-integration-card>summary::-webkit-details-marker{display:none}.surfside-integration-card>summary:after{content:"+";font-size:1.3rem;color:#0b5fa5;margin:0;justify-self:end}.surfside-integration-card[open]>summary:after{content:"−"}
        .surfside-integration-summary-action,.surfside-integration-status,.surfside-youversion-status{display:none!important}
        .surfside-integration-body{padding:0 20px 20px;border-top:1px solid #e3e8ed}.surfside-integration-body>p:first-child{margin-top:16px}.surfside-integration-body label{display:block;margin-top:16px}
        .surfside-integration-body input[type=url],.surfside-integration-body input[type=text],.surfside-integration-body input[type=password]{box-sizing:border-box;width:100%;max-width:720px;margin-top:7px;padding:10px 12px;border:1px solid #9aa9b8;border-radius:7px;font:inherit}
        .surfside-front-settings-form fieldset{border:0;padding:0;margin:18px 0}.surfside-front-settings-form fieldset label{display:block;margin:10px 0}.surfside-front-key-row{display:flex;gap:10px;margin-top:8px}.surfside-front-key-row input{flex:1;min-width:0}.surfside-front-settings input[type=number]{padding:10px 12px;border:1px solid #9aa9b8;border-radius:7px;font:inherit}
        .surfside-front-description{color:#526279;font-size:.92rem}.surfside-front-primary-button,.surfside-front-secondary-button,.surfside-front-delete-button{border-radius:8px;padding:10px 16px;font:inherit;font-weight:700;cursor:pointer}.surfside-front-primary-button{border:0;background:#0b4f9c;color:#fff}.surfside-front-secondary-button{border:1px solid #0b4f9c;background:#fff;color:#0b4f9c}.surfside-front-delete-button{border:0;background:transparent;color:#b42318;text-decoration:underline;padding:4px}
        .surfside-front-settings-notice{padding:13px 15px;border-radius:10px;margin-bottom:18px;font-weight:700}.surfside-front-settings-success{background:#edf7ed;color:#245f2a}.surfside-front-settings-error{background:#fdecec;color:#8b2323}
        .surfside-front-settings .surfside-integrations-section{margin:0 0 28px;display:grid;gap:10px}
        .surfside-integrations-section-heading{margin:0 0 6px}
        .surfside-integrations-section-heading h2{margin:0 0 5px!important;font-size:20px!important;color:#071b3a}
        .surfside-integrations-section-heading p{margin:0;color:#526279;font-size:.94rem}
        .surfside-front-settings .surfside-integrations-section .surfside-integration-card{margin:0}
        .surfside-front-settings .surfside-integration-card>summary{display:flex;align-items:center;gap:10px}
        .surfside-front-settings .surfside-integration-card>summary:after{margin-left:auto;flex:none}
        .surfside-front-settings .surfside-integration-card>summary .surfside-integration-status{display:inline-flex!important;margin-left:auto;color:#526279;font-size:.78rem;font-weight:700;line-height:1.3}
        .surfside-front-settings .surfside-integration-card form>p{margin:14px 0}
        .surfside-front-settings .surfside-integration-card button[type=submit]{min-height:42px}
        .surfside-front-settings .surfside-shared-integrations-save{display:block!important;margin:17px 0 0;padding:0 20px 18px}
        .surfside-front-settings .surfside-visual-css-settings-card form>p{display:block!important}
        .surfside-front-settings .surfside-youversion-row button[name="surfside_youversion_settings_action"][value="save"]{display:inline-flex!important}
        .surfside-front-settings .surfside-front-settings-form>p{display:block!important;margin:6px 0 12px;padding:0 20px}
        .surfside-shared-integrations-form{margin:0}
        .surfside-front-settings input[type=number]{padding:10px 12px;border:1px solid #9aa9b8;border-radius:7px;font:inherit}
        @media(max-width:720px){.surfside-front-key-row{display:block}.surfside-front-key-row button{margin-top:8px}.surfside-integration-card>summary{padding:13px 14px}.surfside-integration-body{padding:0 16px 16px}.surfside-front-settings .surfside-shared-integrations-save{padding:0 16px 16px}.surfside-front-settings .surfside-front-settings-form>p{padding:0 16px}.surfside-front-settings .surfside-front-primary-button{width:100%;justify-content:center}}
    </style>
    <script>
    // Old fragment bookmarks cannot be redirected by PHP because fragments
    // never reach the server. Forward them to their canonical staff workspaces.
    (function(){
      const aliases = <?php echo wp_json_encode($legacy_redirects); ?>;
      const target = Object.prototype.hasOwnProperty.call(aliases,window.location.hash)
        ? aliases[window.location.hash] : '';
      if(target){window.location.replace(target)}
    })();
    document.addEventListener('DOMContentLoaded',function(){
        // Action-specific YouTube/YouVersion redirects should reopen the
        // corresponding accordion to make success/error feedback visible.
        const returnedHash = window.location.hash;
        const returnedId = {
          '#surfside-youtube': 'surfside-youtube',
          '#surfside-youversion': 'surfside-youversion'
        }[returnedHash];
        if(returnedId){
          const card=document.getElementById(returnedId);
          if(card){card.open=true;card.scrollIntoView({block:'start'})}
        }
        const mapsButton=document.getElementById('surfside-front-test-maps');
        const mapsInput=document.getElementById('surfside-front-google-key');
        const mapsStatus=document.getElementById('surfside-front-maps-status');
        if(mapsButton&&mapsInput&&mapsStatus){
            mapsButton.addEventListener('click',function(){
                const key=mapsInput.value.trim();
                if(!key){mapsStatus.textContent='Enter an API key first.';return}
                mapsStatus.textContent='Testing Google Maps…';
                const callback='surfsideFrontMapsTest'+Date.now();
                window[callback]=function(){mapsStatus.textContent=window.google&&google.maps&&google.maps.places?'Google Maps and Places connected successfully.':'Google Maps loaded, but Places was not available.';delete window[callback];script.remove()};
                const script=document.createElement('script');
                script.src='https://maps.googleapis.com/maps/api/js?key='+encodeURIComponent(key)+'&libraries=places&callback='+callback;
                script.onerror=function(){mapsStatus.textContent='Google Maps could not connect. Check the key and its website restrictions.';delete window[callback];script.remove()};
                document.head.appendChild(script);
            });
        }

    });
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

