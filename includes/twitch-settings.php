<?php
/** Twitch credentials and Integrations panel. */
if (!defined('ABSPATH')) { exit; }

function surfside_tools_twitch_credentials() {
    return array(
        'client_id' => defined('SURFSIDE_TWITCH_CLIENT_ID') ? trim((string)SURFSIDE_TWITCH_CLIENT_ID) : trim((string)get_option('surfside_tools_twitch_client_id', '')),
        'secret' => defined('SURFSIDE_TWITCH_CLIENT_SECRET') ? trim((string)SURFSIDE_TWITCH_CLIENT_SECRET) : trim((string)get_option('surfside_tools_twitch_client_secret', '')),
    );
}

/** Validate both fields before writing; a blank secret preserves the saved secret. */
function surfside_tools_twitch_save_credentials($id, $secret, $clear) {
    if (!is_string($id) || !is_string($secret)) return false;
    $id = trim($id); $secret = trim($secret);
    if (($id !== '' && !preg_match('/^[a-zA-Z0-9]+$/', $id)) || ($secret !== '' && !preg_match('/^[a-zA-Z0-9]+$/', $secret))) return false;
    if (!defined('SURFSIDE_TWITCH_CLIENT_ID')) update_option('surfside_tools_twitch_client_id', $id, false);
    if (!defined('SURFSIDE_TWITCH_CLIENT_SECRET')) {
        if ($clear) delete_option('surfside_tools_twitch_client_secret');
        elseif ($secret !== '') update_option('surfside_tools_twitch_client_secret', $secret, false);
    }
    return true;
}

function surfside_tools_twitch_settings_handle_post() {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || empty($_POST['surfside_twitch_settings_action'])) return '';
    if (!current_user_can('manage_options') || empty($_POST['surfside_twitch_settings_nonce']) || !is_string($_POST['surfside_twitch_settings_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['surfside_twitch_settings_nonce'])), 'surfside_twitch_settings')) {
        status_header(403);
        return surfside_tools_frontend_settings_notice('Twitch security check failed. Refresh and try again.', 'error');
    }
    $action = is_string($_POST['surfside_twitch_settings_action']) ? sanitize_key(wp_unslash($_POST['surfside_twitch_settings_action'])) : '';
    if ($action === 'save') {
        $saved = surfside_tools_twitch_save_credentials(wp_unslash($_POST['twitch_client_id'] ?? ''), wp_unslash($_POST['twitch_client_secret'] ?? ''), !empty($_POST['clear_twitch_client_secret']));
        if (!$saved) status_header(400);
        return surfside_tools_frontend_settings_notice($saved ? 'Twitch settings saved.' : 'Enter a valid Twitch Client ID and Client Secret.', $saved ? 'success' : 'error');
    }
    if ($action === 'test') {
        $diagnostic = '';
        $status = surfside_tools_twitch_live_status($diagnostic);
        $ok = in_array($status['status'], array('live', 'offline'), true);
        return surfside_tools_frontend_settings_notice($ok ? 'Twitch connection successful. Stream is '.$status['status'].'.' : ($diagnostic !== '' ? $diagnostic : 'Twitch status is unknown. Wait 30 seconds and test again.'), $ok ? 'success' : 'error');
    }
    status_header(400);
    return surfside_tools_frontend_settings_notice('Unknown Twitch settings action.', 'error');
}

function surfside_tools_twitch_settings_panel() {
    if (!is_user_logged_in() || !current_user_can('manage_options')) return '';
    $credentials = surfside_tools_twitch_credentials();
    $id_locked = defined('SURFSIDE_TWITCH_CLIENT_ID');
    $secret_locked = defined('SURFSIDE_TWITCH_CLIENT_SECRET');
    ob_start();
    ?>
    <details id="surfside-twitch" class="surfside-front-settings-card surfside-integration-card">
        <summary><span>Twitch</span></summary>
        <div class="surfside-integration-body">
            <p class="surfside-front-description">Detects when the configured Twitch channel is live. Channel settings remain in Streaming Settings.</p>
            <p><?php echo $credentials['client_id'] !== '' && $credentials['secret'] !== '' ? 'Credentials configured.' : 'Credentials incomplete.'; ?></p>
            <?php if ($id_locked || $secret_locked) : ?><p class="surfside-front-description">Fields configured in wp-config.php override dashboard settings and cannot be changed here.</p><?php endif; ?>
            <form method="post" class="surfside-twitch-form">
                <?php wp_nonce_field('surfside_twitch_settings', 'surfside_twitch_settings_nonce'); ?>
                <input type="hidden" name="surfside_twitch_settings_action" value="save">
                <label for="surfside-twitch-client-id"><strong>Client ID</strong></label>
                <input id="surfside-twitch-client-id" type="text" name="twitch_client_id" autocomplete="off" value="<?php echo esc_attr($credentials['client_id']); ?>" <?php disabled($id_locked); ?>>
                <label for="surfside-twitch-client-secret"><strong>Client Secret</strong></label>
                <input id="surfside-twitch-client-secret" type="password" name="twitch_client_secret" autocomplete="new-password" value="" placeholder="<?php echo $credentials['secret'] !== '' ? 'Secret saved — leave blank to keep' : 'Paste Client Secret'; ?>" <?php disabled($secret_locked); ?>>
                <p class="surfside-front-description">The saved secret is never displayed. Save Integrations before testing.</p>
                <?php if (!$secret_locked && $credentials['secret'] !== '') : ?><label><input type="checkbox" name="clear_twitch_client_secret" value="1"> Remove secret on Save</label><?php endif; ?>
            </form>
            <form method="post">
                <?php wp_nonce_field('surfside_twitch_settings', 'surfside_twitch_settings_nonce'); ?>
                <input type="hidden" name="surfside_twitch_settings_action" value="test">
                <p><button type="submit" class="surfside-front-secondary-button">Test Connection</button></p>
            </form>
        </div>
    </details>
    <?php
    return ob_get_clean();
}
