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
        $result = function_exists('surfside_tools_youversion_request')
            ? surfside_tools_youversion_request('bibles', array('language_ranges' => 'en', 'page_size' => 99))
            : new WP_Error('surfside_youversion_client_missing', 'YouVersion client is unavailable.');

        if (is_wp_error($result)) {
            $test = array(
                'success' => false,
                'message' => $result->get_error_message(),
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
        wp_safe_redirect(add_query_arg('youversion_tested', '1', $referer));
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

    wp_safe_redirect(add_query_arg('youversion_saved', '1', $referer));
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
    <section class="surfside-front-settings-card surfside-youversion-settings-card">
        <h2>YouVersion Integration</h2>
        <p class="surfside-staff-muted">Server-side credentials for Scripture integration in Surfside experiences.</p>
        <?php if (isset($_GET['youversion_saved'])) : ?>
            <div class="surfside-front-settings-notice surfside-front-settings-success">YouVersion settings saved.</div>
        <?php endif; ?>
        <?php if (is_array($test)) : ?>
            <div class="surfside-front-settings-notice <?php echo !empty($test['success']) ? 'surfside-front-settings-success' : 'surfside-front-settings-error'; ?>">
                <?php echo esc_html($test['message'] ?? 'YouVersion connection test completed.'); ?>
                <?php if (!empty($test['success'])) : ?>
                    <?php $count = absint($test['count'] ?? 0); ?>
                    <?php if ($count) : ?> Accessible English Bible versions: <?php echo esc_html(number_format_i18n($count)); ?>.<?php endif; ?>
                <?php endif; ?>
            </div>
            <?php if (!empty($test['success']) && !empty($test['versions']) && is_array($test['versions'])) : ?>
                <details style="margin:0 0 18px;">
                    <summary><strong>Show available version sample</strong></summary>
                    <ul style="columns:2;column-gap:28px;margin-top:12px;">
                        <?php foreach ($test['versions'] as $version) : ?>
                            <li><?php echo esc_html(trim(($version['abbreviation'] ?? '') . (($version['abbreviation'] ?? '') && ($version['title'] ?? '') ? ' — ' : '') . ($version['title'] ?? ''))); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </details>
            <?php endif; ?>
        <?php endif; ?>
        <p><strong>Status:</strong> <?php echo $configured ? '<span style="color:#245f2a;font-weight:700;">Configured</span>' : 'Not configured'; ?></p>
        <form method="post">
            <?php wp_nonce_field('surfside_youversion_settings', 'surfside_youversion_settings_nonce'); ?>
            <input type="hidden" name="surfside_youversion_settings_action" value="save">
            <label for="surfside-youversion-app-key"><strong>YouVersion App Key</strong></label>
            <input id="surfside-youversion-app-key" type="password" autocomplete="new-password" name="youversion_app_key" value="" placeholder="<?php echo $configured ? 'Leave blank to keep the saved key' : 'Paste YouVersion App Key'; ?>" style="display:block;width:100%;max-width:720px;box-sizing:border-box;margin-top:8px;padding:10px 12px;border:1px solid #9aa9b8;border-radius:7px;font:inherit;">
            <p class="surfside-front-description">The saved key is not displayed again and is never included in mobile API responses.</p>
            <?php if ($configured) : ?>
                <p><label><input type="checkbox" name="clear_youversion_app_key" value="1"> Remove the saved YouVersion App Key</label></p>
            <?php endif; ?>
            <p><button type="submit" class="surfside-front-primary-button">Save YouVersion Settings</button></p>
        </form>
        <?php if ($configured) : ?>
            <form method="post" style="margin-top:10px;">
                <?php wp_nonce_field('surfside_youversion_settings', 'surfside_youversion_settings_nonce'); ?>
                <input type="hidden" name="surfside_youversion_settings_action" value="test">
                <button type="submit" class="surfside-front-secondary-button">Test YouVersion Connection</button>
            </form>
        <?php endif; ?>
    </section>
    <?php
    $panel = ob_get_clean();

    $needle = '</div>';
    $pos = strrpos($output, $needle);
    if ($pos === false) {
        return $output . $panel;
    }
    return substr($output, 0, $pos) . $panel . substr($output, $pos);
}, 25, 2);
