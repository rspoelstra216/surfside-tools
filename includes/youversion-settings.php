<?php
/**
 * Front-end Site Settings -> Integrations UI for YouVersion.
 */
if (!defined('ABSPATH')) { exit; }

function surfside_tools_youversion_test_transient_key() {
    return 'surfside_youversion_test_' . get_current_user_id();
}

function surfside_tools_youversion_settings_handle_post() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['surfside_youversion_settings_action'])) {
        return;
    }
    if (!current_user_can('manage_options')) {
        return;
    }
    if (empty($_POST['surfside_youversion_settings_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['surfside_youversion_settings_nonce'])), 'surfside_youversion_settings')) {
        return;
    }

    $action = sanitize_key(wp_unslash($_POST['surfside_youversion_settings_action']));
    $referer = wp_get_referer() ?: home_url('/dashboard/settings/');

    if ($action === 'test') {
        // Keep the validation request identical to YouVersion's documented first
        // request. Query filtering can be added only after basic authentication
        // and Bible access have been confirmed.
        $result = function_exists('surfside_tools_youversion_request')
            ? surfside_tools_youversion_request('bibles')
            : new WP_Error('surfside_youversion_client_missing', 'YouVersion client is unavailable.');

        if (is_wp_error($result)) {
            $data = $result->get_error_data();
            $status = is_array($data) ? absint($data['status'] ?? 0) : 0;
            $message = $result->get_error_message();
            if ($status) {
                $message = 'HTTP ' . $status . ' — ' . $message;
            }
            $test = array(
                'success' => false,
                'message' => $message,
            );
        } else {
            $bibles = isset($result['data']) && is_array($result['data']) ? $result['data'] : array();
            $versions = array();
            foreach ($bibles as $bible) {
                if (!is_array($bible)) { continue; }
                $abbr = trim((string)($bible['abbreviation'] ?? $bible['localized_abbreviation'] ?? ''));
                $title = trim((string)($bible['title'] ?? $bible['localized_title'] ?? ''));
                if ($abbr === '' && $title === '') { continue; }
                $versions[] = array('abbreviation' => $abbr, 'title' => $title);
            }
            $test = array(
                'success' => true,
                'message' => 'YouVersion connection successful.',
                'count' => isset($result['total_size']) ? absint($result['total_size']) : count($versions),
                'versions' => array_slice($versions, 0, 20),
            );
        }

        set_transient(surfside_tools_youversion_test_transient_key(), $test, 5 * MINUTE_IN_SECONDS);
        $redirect = add_query_arg('youversion_tested', '1', $referer) . '#surfside-youversion';
        wp_safe_redirect($redirect);
        exit;
    }

    if ($action !== 'save') {
        return;
    }

    $current = trim((string)get_option('surfside_tools_youversion_app_key', ''));
    $incoming = trim((string)wp_unslash($_POST['youversion_app_key'] ?? ''));
    $clear = !empty($_POST['clear_youversion_app_key']);

    if ($clear) {
        delete_option('surfside_tools_youversion_app_key');
    } elseif ($incoming !== '') {
        update_option('surfside_tools_youversion_app_key', sanitize_text_field($incoming), false);
    } elseif ($current === '') {
        $legacy = function_exists('surfside_tools_app_settings') ? surfside_tools_app_settings() : array();
        $legacy_key = trim((string)($legacy['youversion_app_key'] ?? ''));
        if ($legacy_key !== '') {
            update_option('surfside_tools_youversion_app_key', sanitize_text_field($legacy_key), false);
        }
    }

    $legacy = function_exists('surfside_tools_app_settings') ? surfside_tools_app_settings() : array();
    if (is_array($legacy) && array_key_exists('youversion_app_key', $legacy)) {
        $legacy['youversion_app_key'] = '';
        update_option('surfside_tools_app_settings', $legacy, false);
    }

    $redirect = add_query_arg('youversion_saved', '1', $referer) . '#surfside-youversion';
    wp_safe_redirect($redirect);
    exit;
}
add_action('template_redirect', 'surfside_tools_youversion_settings_handle_post', 5);

add_filter('do_shortcode_tag', function ($output, $tag) {
    if ($tag !== 'surfside_staff_settings' || !is_user_logged_in() || !current_user_can('manage_options')) {
        return $output;
    }

    $configured = function_exists('surfside_tools_youversion_is_configured') && surfside_tools_youversion_is_configured();
    $test = isset($_GET['youversion_tested']) ? get_transient(surfside_tools_youversion_test_transient_key()) : false;
    if ($test !== false) {
        delete_transient(surfside_tools_youversion_test_transient_key());
    }

    ob_start();
    ?>
    <section id="surfside-youversion" class="surfside-front-settings-card surfside-youversion-settings-card">
        <div class="surfside-youversion-heading">
            <div>
                <h2>YouVersion</h2>
                <p class="surfside-front-description">Scripture API credential and connection status.</p>
            </div>
            <span class="surfside-youversion-status <?php echo $configured ? 'is-configured' : ''; ?>"><?php echo $configured ? 'Configured' : 'Not configured'; ?></span>
        </div>

        <?php if (isset($_GET['youversion_saved'])) : ?>
            <div class="surfside-front-settings-notice surfside-front-settings-success surfside-youversion-notice">YouVersion settings saved.</div>
        <?php endif; ?>
        <?php if (is_array($test)) : ?>
            <div class="surfside-front-settings-notice <?php echo !empty($test['success']) ? 'surfside-front-settings-success' : 'surfside-front-settings-error'; ?> surfside-youversion-notice">
                <?php echo esc_html($test['message'] ?? 'YouVersion connection test completed.'); ?>
                <?php if (!empty($test['success'])) : ?>
                    <?php $count = absint($test['count'] ?? 0); ?>
                    <?php if ($count) : ?> Accessible Bible versions: <?php echo esc_html(number_format_i18n($count)); ?>.<?php endif; ?>
                <?php endif; ?>
            </div>
            <?php if (!empty($test['success']) && !empty($test['versions']) && is_array($test['versions'])) : ?>
                <details class="surfside-youversion-versions">
                    <summary>Available version sample</summary>
                    <ul>
                        <?php foreach ($test['versions'] as $version) : ?>
                            <li><?php echo esc_html(trim(($version['abbreviation'] ?? '') . (($version['abbreviation'] ?? '') && ($version['title'] ?? '') ? ' — ' : '') . ($version['title'] ?? ''))); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </details>
            <?php endif; ?>
        <?php endif; ?>

        <form method="post" class="surfside-youversion-form">
            <?php wp_nonce_field('surfside_youversion_settings', 'surfside_youversion_settings_nonce'); ?>
            <label class="screen-reader-text" for="surfside-youversion-app-key">YouVersion App Key</label>
            <div class="surfside-youversion-row">
                <input id="surfside-youversion-app-key" type="password" autocomplete="new-password" name="youversion_app_key" value="" placeholder="<?php echo $configured ? 'App Key saved — enter a new key to replace' : 'Paste YouVersion App Key'; ?>">
                <button type="submit" name="surfside_youversion_settings_action" value="save" class="surfside-front-primary-button surfside-youversion-button">Save</button>
                <?php if ($configured) : ?>
                    <button type="submit" name="surfside_youversion_settings_action" value="test" class="surfside-front-secondary-button surfside-youversion-button">Test</button>
                <?php endif; ?>
            </div>
            <div class="surfside-youversion-meta">
                <span>The saved key is never displayed or included in mobile API responses.</span>
                <?php if ($configured) : ?>
                    <label><input type="checkbox" name="clear_youversion_app_key" value="1"> Remove key on Save</label>
                <?php endif; ?>
            </div>
        </form>
    </section>
    <style>
        #surfside-youversion{scroll-margin-top:24px}.surfside-youversion-settings-card{padding:18px 20px!important}.surfside-youversion-heading{display:flex;align-items:flex-start;justify-content:space-between;gap:18px}.surfside-youversion-heading h2{margin:0 0 2px}.surfside-youversion-heading p{margin:0}.surfside-youversion-status{flex:0 0 auto;border:1px solid #cbd5df;border-radius:999px;padding:4px 9px;font-size:.8rem;font-weight:700;color:#526279;background:#f7f9fb}.surfside-youversion-status.is-configured{border-color:#b9dcc4;background:#edf7ed;color:#245f2a}.surfside-youversion-notice{margin:12px 0 0!important;padding:9px 11px!important;font-size:.92rem}.surfside-youversion-form{margin-top:14px}.surfside-youversion-row{display:grid;grid-template-columns:minmax(220px,1fr) auto auto;gap:8px;align-items:center}.surfside-youversion-row input{width:100%;box-sizing:border-box;padding:9px 11px;border:1px solid #9aa9b8;border-radius:7px;font:inherit}.surfside-youversion-button{width:auto!important;min-width:0!important;margin:0!important;padding:9px 14px!important;white-space:nowrap}.surfside-youversion-meta{display:flex;justify-content:space-between;gap:16px;flex-wrap:wrap;margin-top:7px;color:#65758a;font-size:.82rem}.surfside-youversion-meta label{white-space:nowrap}.surfside-youversion-versions{margin-top:8px;font-size:.9rem}.surfside-youversion-versions ul{columns:2;column-gap:28px;margin:8px 0 0;padding-left:20px}@media(max-width:680px){.surfside-youversion-row{grid-template-columns:1fr auto}.surfside-youversion-row input{grid-column:1/-1}.surfside-youversion-meta{display:block}.surfside-youversion-meta label{display:block;margin-top:5px}.surfside-youversion-versions ul{columns:1}}
    </style>
    <?php
    $panel = ob_get_clean();

    $needle = '</div>';
    $pos = strrpos($output, $needle);
    if ($pos === false) {
        return $output . $panel;
    }
    return substr($output, 0, $pos) . $panel . substr($output, $pos);
}, 25, 2);
