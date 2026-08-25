<?php
/** Church Settings information-architecture and integrations polish. */
if (!defined('ABSPATH')) { exit; }

function surfside_tools_church_settings_back_link($output) {
    $url = function_exists('surfside_tools_staff_page_url') ? surfside_tools_staff_page_url('site-settings') : home_url('/dashboard/site-settings/');
    return preg_replace(
        '~<div class="surfside-staff-back"><a href="[^"]+">← Back to Site Management</a></div>~',
        '<div class="surfside-staff-back"><a href="' . esc_url($url) . '">← Back to Church Settings</a></div>',
        $output,
        1
    );
}

function surfside_tools_church_settings_shared_save() {
    if (
        ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' ||
        empty($_POST['surfside_church_integrations_action'])
    ) {
        return '';
    }
    if (!current_user_can('manage_options')) {
        return '<div class="surfside-front-settings-notice surfside-front-settings-error">You do not have permission to change integration settings.</div>';
    }
    $nonce = isset($_POST['surfside_church_integrations_nonce']) ? sanitize_text_field(wp_unslash($_POST['surfside_church_integrations_nonce'])) : '';
    if (!wp_verify_nonce($nonce, 'surfside_church_integrations_save')) {
        return '<div class="surfside-front-settings-notice surfside-front-settings-error">Security check failed. Please refresh and try again.</div>';
    }

    $app = function_exists('surfside_tools_app_settings') ? surfside_tools_app_settings() : array();
    $app['giving_url'] = esc_url_raw(trim((string) wp_unslash($_POST['giving_url'] ?? '')));
    update_option('surfside_tools_app_settings', $app);

    $contact = get_option('surfside_tools_contact_settings', array());
    $contact = is_array($contact) ? $contact : array();
    $contact['recipients'] = is_array($contact['recipients'] ?? null) ? $contact['recipients'] : array();
    $contact['turnstile_site_key'] = sanitize_text_field(wp_unslash($_POST['turnstile_site_key'] ?? ''));
    $secret = sanitize_text_field(wp_unslash($_POST['turnstile_secret_key'] ?? ''));
    if ($secret !== '') {
        $contact['turnstile_secret_key'] = $secret;
    }
    update_option('surfside_tools_contact_settings', $contact);

    return '<div class="surfside-front-settings-notice surfside-front-settings-success">Integration settings saved.</div>';
}

function surfside_tools_church_settings_compact_card($matches) {
    $title = trim(wp_strip_all_tags($matches[1]));
    $body = $matches[2];
    return '<details class="surfside-front-settings-card surfside-integration-card"><summary><span>' . esc_html($title) . '</span><span class="surfside-integration-summary-action">Configure</span></summary><div class="surfside-integration-body">' . $body . '</div></details>';
}

function surfside_tools_church_settings_shared_integrations_panel() {
    $app = function_exists('surfside_tools_app_settings') ? surfside_tools_app_settings() : array();
    $giving_url = esc_url($app['giving_url'] ?? '');
    $contact = function_exists('surfside_tools_contact_settings') ? surfside_tools_contact_settings() : array();
    $site_key = sanitize_text_field($contact['turnstile_site_key'] ?? '');
    $has_secret = !empty($contact['turnstile_secret_key']);
    $giving_status = $giving_url ? 'Configured' : 'Not configured';
    $turnstile_status = ($site_key && $has_secret) ? 'Configured' : 'Not configured';

    ob_start();
    ?>
    <form method="post" class="surfside-shared-integrations-form">
        <?php wp_nonce_field('surfside_church_integrations_save', 'surfside_church_integrations_nonce'); ?>
        <input type="hidden" name="surfside_church_integrations_action" value="save_shared_integrations">

        <details class="surfside-front-settings-card surfside-integration-card">
            <summary><span>Giving</span><span class="surfside-integration-status"><?php echo esc_html($giving_status); ?></span></summary>
            <div class="surfside-integration-body">
                <p class="surfside-staff-muted">Secure giving destination used by the website and mobile app.</p>
                <label for="surfside-integrations-giving"><strong>Giving Form URL</strong></label>
                <input id="surfside-integrations-giving" name="giving_url" type="url" value="<?php echo esc_attr($giving_url); ?>" placeholder="https://give.tithe.ly/?formId=...">
                <p class="surfside-front-description">Use the direct Tithely Giving Form URL, not the kiosk URL or embed code.</p>
            </div>
        </details>

        <details class="surfside-front-settings-card surfside-integration-card">
            <summary><span>Cloudflare Turnstile</span><span class="surfside-integration-status"><?php echo esc_html($turnstile_status); ?></span></summary>
            <div class="surfside-integration-body">
                <p class="surfside-staff-muted">Bot protection for public Surfside contact forms.</p>
                <label for="surfside-integrations-turnstile-site"><strong>Site key</strong></label>
                <input id="surfside-integrations-turnstile-site" name="turnstile_site_key" type="text" value="<?php echo esc_attr($site_key); ?>" autocomplete="off">
                <label for="surfside-integrations-turnstile-secret"><strong>Secret key</strong></label>
                <input id="surfside-integrations-turnstile-secret" name="turnstile_secret_key" type="password" value="" autocomplete="new-password" placeholder="<?php echo $has_secret ? 'Saved — leave blank to keep current secret' : 'Enter Turnstile secret key'; ?>">
                <p class="surfside-front-description">The secret remains server-side and is never exposed in the public form or mobile app.</p>
            </div>
        </details>

        <p class="surfside-shared-integrations-save"><button type="submit" class="surfside-front-primary-button">Save Giving & Turnstile</button></p>
    </form>
    <?php
    return ob_get_clean();
}

add_filter('do_shortcode_tag', function ($output, $tag) {
    if (!is_user_logged_in() || !current_user_can('manage_options')) {
        return $output;
    }

    if ($tag === 'surfside_staff_site_settings') {
        $output = preg_replace('~<form method="post" class="surfside-site-settings-giving">.*?</form>~s', '', $output, 1);
        $output = str_replace(
            'Message recipients and Cloudflare Turnstile protection for website and app contact forms.',
            'Message recipients for website and app contact forms.',
            $output
        );
        $output = str_replace(
            'Google Maps, calendar defaults, Saved Places, and other shared integrations.',
            'External services and technical connections used across Surfside.',
            $output
        );
        return $output;
    }

    if ($tag === 'surfside_staff_contact_management') {
        $output = surfside_tools_church_settings_back_link($output);
        $settings = function_exists('surfside_tools_contact_settings') ? surfside_tools_contact_settings() : array();
        $hidden = '<input type="hidden" name="turnstile_site_key" value="' . esc_attr($settings['turnstile_site_key'] ?? '') . '"><input type="hidden" name="turnstile_secret_key" value="">';
        $output = preg_replace('~<section class="surfside-staff-panel"><h2>Cloudflare Turnstile</h2>.*?</section>~s', $hidden, $output, 1);
        return $output;
    }

    if ($tag === 'surfside_staff_site_information') {
        return surfside_tools_church_settings_back_link($output);
    }

    if ($tag !== 'surfside_staff_settings') {
        return $output;
    }

    $output = surfside_tools_church_settings_back_link($output);
    $output = str_replace('<p class="surfside-staff-eyebrow">Settings</p>', '<p class="surfside-staff-eyebrow">Technical Configuration</p>', $output);
    $output = str_replace('<h1>Surfside Tools Settings</h1>', '<h1>Integrations</h1>', $output);
    $output = str_replace('Manage Google Maps, calendar defaults, and saved places without opening WordPress administration.', 'External services and connection settings used by Surfside.', $output);
    $output = str_replace('<h2>Google Maps Integration</h2>', '<h2>Google Maps</h2>', $output);
    $output = str_replace('>Save Settings</button>', '>Save Map & Calendar Settings</button>', $output);

    $output = preg_replace_callback(
        '~<section class="surfside-front-settings-card">\s*<h2>(.*?)</h2>(.*?)</section>~s',
        'surfside_tools_church_settings_compact_card',
        $output
    );

    $shared_notice = surfside_tools_church_settings_shared_save();
    $shared_panel = $shared_notice . surfside_tools_church_settings_shared_integrations_panel();
    $output = preg_replace('~</div>\s*<style>~', $shared_panel . '</div><style>', $output, 1);

    $css = '<style>
        .surfside-front-settings .surfside-integration-card{padding:0;overflow:hidden;margin-bottom:12px;box-shadow:none}
        .surfside-integration-card>summary{display:flex;align-items:center;justify-content:space-between;gap:18px;padding:17px 20px;cursor:pointer;list-style:none;font-size:1.05rem;font-weight:800;color:#071b3a;background:#fff}
        .surfside-integration-card>summary::-webkit-details-marker{display:none}
        .surfside-integration-card>summary:after{content:"+";font-size:1.3rem;color:#0b5fa5;margin-left:auto}
        .surfside-integration-card[open]>summary:after{content:"−"}
        .surfside-integration-summary-action,.surfside-integration-status{margin-left:auto;color:#61717d;font-size:.82rem;font-weight:700}
        .surfside-integration-body{padding:0 20px 20px;border-top:1px solid #e3e8ed}
        .surfside-integration-body>p:first-child{margin-top:16px}
        .surfside-integration-body label{display:block;margin-top:16px}
        .surfside-integration-body input[type=url],.surfside-integration-body input[type=text],.surfside-integration-body input[type=password]{box-sizing:border-box;width:100%;max-width:720px;margin-top:7px;padding:10px 12px;border:1px solid #9aa9b8;border-radius:7px;font:inherit}
        .surfside-shared-integrations-form{margin-top:12px}.surfside-shared-integrations-save{margin:14px 0 24px}.surfside-front-settings-form>p{margin:14px 0 24px}
        @media(max-width:720px){.surfside-integration-card>summary{padding:15px 16px}.surfside-integration-body{padding:0 16px 16px}}
    </style>';
    return $output . $css;
}, 40, 2);
