<?php
/**
 * OpenAI credentials in the staff-only Integrations workspace.
 *
 * Secrets are stored in a non-autoloaded WP option, never printed into HTML.
 * A server-defined constant remains an optional higher-priority override.
 */
if (!defined('ABSPATH')) { exit; }

const SURFSIDE_TOOLS_OPENAI_SECRET_OPTION = 'surfside_tools_openai_api_secret';

function surfside_tools_openai_stored_api_key() {
    $value = get_option(SURFSIDE_TOOLS_OPENAI_SECRET_OPTION, '');
    return is_string($value) ? trim($value) : '';
}

function surfside_tools_openai_key_from_config() {
    return defined('SURFSIDE_TOOLS_OPENAI_API_KEY') &&
        is_string(SURFSIDE_TOOLS_OPENAI_API_KEY) &&
        trim(SURFSIDE_TOOLS_OPENAI_API_KEY) !== '';
}

/**
 * A connection test confirms only the key and configured model are
 * accessible, not whether enough billing credit remains for generation.
 */
function surfside_tools_openai_integration_test_connection() {
    if (!function_exists('surfside_tools_propresenter_api_key')) {
        return new WP_Error('surfside_openai_not_available', 'ProPresenter integration is unavailable.');
    }
    $key = surfside_tools_propresenter_api_key();
    if ($key === '') {
        return new WP_Error('surfside_openai_missing', 'Save an API key before testing the connection.');
    }
    $model = defined('SURFSIDE_PROPRESENTER_MODEL') ? SURFSIDE_PROPRESENTER_MODEL : 'gpt-5.4-mini';
    $response = wp_remote_get(
        'https://api.openai.com/v1/models/' . rawurlencode($model),
        array(
            'timeout' => 15,
            'redirection' => 0,
            'headers' => array('Authorization' => 'Bearer ' . $key),
        )
    );
    if (is_wp_error($response)) {
        return new WP_Error('surfside_openai_network_error', 'Could not connect to OpenAI. Please try again.');
    }
    $code = (int) wp_remote_retrieve_response_code($response);
    if ($code === 200) {
        return true;
    }
    if ($code === 401) {
        return new WP_Error('surfside_openai_unauthorized', 'OpenAI rejected the API key. Verify it in the OpenAI dashboard.');
    }
    if ($code === 403 || $code === 404) {
        return new WP_Error('surfside_openai_model_access', 'The key does not have access to the configured OpenAI model.');
    }
    return new WP_Error('surfside_openai_test_failed',
        'OpenAI did not confirm the connection (HTTP ' . $code . '). Please try again.');
}

function surfside_tools_openai_integration_handle_post() {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' ||
        empty($_POST['surfside_openai_integration_action'])) {
        return '';
    }
    $action = sanitize_key(wp_unslash($_POST['surfside_openai_integration_action']));
    if (!in_array($action, array('save', 'remove', 'test'), true)) return '';
    // This is more restrictive than merely being allowed to view Integrations.
    if (!is_user_logged_in() || !current_user_can('manage_options')) {
        return surfside_tools_frontend_settings_notice(
            'Only administrators can change or test the OpenAI connection.', 'error');
    }
    $nonce = sanitize_text_field(wp_unslash($_POST['surfside_openai_integration_nonce'] ?? ''));
    if (!wp_verify_nonce($nonce, 'surfside_openai_integration')) {
        return surfside_tools_frontend_settings_notice(
            'Security check failed. Refresh and try again.', 'error');
    }

    if ($action === 'test') {
        $result = surfside_tools_openai_integration_test_connection();
        return is_wp_error($result)
            ? surfside_tools_frontend_settings_notice($result->get_error_message(), 'error')
            : surfside_tools_frontend_settings_notice(
                'OpenAI accepted the saved key and the model is accessible. Billing credits were not checked.');
    }
    if (surfside_tools_openai_key_from_config()) {
        return surfside_tools_frontend_settings_notice(
            'This key is managed through server configuration. Change it in wp-config.php.', 'error');
    }
    if ($action === 'remove') {
        delete_option(SURFSIDE_TOOLS_OPENAI_SECRET_OPTION);
        return surfside_tools_frontend_settings_notice('Saved OpenAI API key removed.');
    }

    $raw = $_POST['surfside_openai_api_key'] ?? '';
    if (!is_string($raw)) {
        return surfside_tools_frontend_settings_notice('Invalid API key input.', 'error');
    }
    $key = trim(wp_unslash($raw));
    if ($key === '') {
        return surfside_tools_frontend_settings_notice(
            surfside_tools_openai_stored_api_key() !== ''
                ? 'No change made. Leave the key blank to keep the saved key.'
                : 'Enter an API key before saving.',
            surfside_tools_openai_stored_api_key() !== '' ? 'success' : 'error');
    }
    // Reject unexpected content and whitespace rather than modifying the key.
    if (strlen($key) > 512 || strlen($key) < 12 || preg_match('/\s/', $key) ||
        strpos($key, '<') !== false || strpos($key, '>') !== false) {
        return surfside_tools_frontend_settings_notice(
            'The API key format is invalid. Copy the full secret from the OpenAI dashboard.', 'error');
    }
    update_option(SURFSIDE_TOOLS_OPENAI_SECRET_OPTION, $key, false);
    return surfside_tools_frontend_settings_notice(
        'OpenAI API key saved. Use Test Connection to verify access.');
}

/** One independent form per action, consistent with the Integrations page. */
function surfside_tools_openai_integration_panel() {
    if (!is_user_logged_in() || !current_user_can('manage_options')) return '';
    $server_key = surfside_tools_openai_key_from_config();
    $saved = surfside_tools_openai_stored_api_key() !== '';
    $configured = $server_key || $saved;
    $status = $server_key ? 'Managed by server' : ($saved ? 'Key saved' : 'Not configured');

    ob_start();
    ?>
    <details id="surfside-openai" class="surfside-front-settings-card surfside-integration-card">
        <summary><span>OpenAI — ProPresenter Announcements</span>
            <span class="surfside-integration-status"><?php echo esc_html($status); ?></span>
        </summary>
        <div class="surfside-integration-body">
            <p class="surfside-staff-muted">Generates ProPresenter-ready announcement slides immediately after the weekly DOCX upload. The original announcements and Saturday release are unaffected.</p>
            <?php if ($server_key) : ?>
                <p class="surfside-front-description">The API key is defined on the server and takes priority over dashboard credentials. To change it, update the server configuration.</p>
            <?php else : ?>
                <form method="post">
                    <?php wp_nonce_field('surfside_openai_integration', 'surfside_openai_integration_nonce'); ?>
                    <input type="hidden" name="surfside_openai_integration_action" value="save">
                    <label for="surfside-openai-api-key"><strong>OpenAI Secret API Key</strong></label>
                    <input id="surfside-openai-api-key" type="password" name="surfside_openai_api_key"
                        value="" autocomplete="new-password"
                        placeholder="<?php echo esc_attr($saved ? 'Saved — leave blank to keep the existing key' : 'Paste new OpenAI API key'); ?>">
                    <p class="surfside-front-description">Stored on the server in the WordPress database; never displayed again here or sent to website/app visitors. Only administrators can change it. Database backups must be protected.</p>
                    <p><button type="submit" class="surfside-front-primary-button">Save OpenAI Key</button></p>
                </form>
            <?php endif; ?>
            <?php if ($configured) : ?>
                <form method="post">
                    <?php wp_nonce_field('surfside_openai_integration', 'surfside_openai_integration_nonce'); ?>
                    <input type="hidden" name="surfside_openai_integration_action" value="test">
                    <p><button type="submit" class="surfside-front-secondary-button">Test OpenAI Connection</button></p>
                </form>
            <?php endif; ?>
            <?php if ($saved && !$server_key) : ?>
                <form method="post" onsubmit="return confirm('Remove the saved OpenAI API key? AI slide generation will stop until a new key is added.');">
                    <?php wp_nonce_field('surfside_openai_integration', 'surfside_openai_integration_nonce'); ?>
                    <input type="hidden" name="surfside_openai_integration_action" value="remove">
                    <p><button type="submit" class="surfside-front-delete-button">Remove saved key</button></p>
                </form>
            <?php endif; ?>
            <p class="surfside-front-description">
                <a href="https://platform.openai.com/api-keys" target="_blank" rel="noopener noreferrer">Create or manage an OpenAI API key</a>.
                API usage is billed separately from ChatGPT.
            </p>
        </div>
    </details>
    <?php
    return ob_get_clean();
}
