<?php
/**
 * Front-end Site Settings -> Integrations UI for YouVersion.
 */
if (!defined('ABSPATH')) { exit; }

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

    $redirect = add_query_arg('youversion_saved', '1', wp_get_referer() ?: home_url('/dashboard/settings/'));
    wp_safe_redirect($redirect);
    exit;
}
add_action('template_redirect', 'surfside_tools_youversion_settings_handle_post', 5);

add_filter('do_shortcode_tag', function ($output, $tag) {
    if ($tag !== 'surfside_staff_settings' || !is_user_logged_in() || !current_user_can('manage_options')) {
        return $output;
    }

    $configured = function_exists('surfside_tools_youversion_is_configured') && surfside_tools_youversion_is_configured();
    ob_start();
    ?>
    <section class="surfside-front-settings-card surfside-youversion-settings-card">
        <h2>YouVersion Integration</h2>
        <p class="surfside-staff-muted">Server-side credentials for Scripture integration in Surfside experiences.</p>
        <?php if (isset($_GET['youversion_saved'])) : ?>
            <div class="surfside-front-settings-notice surfside-front-settings-success">YouVersion settings saved.</div>
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
