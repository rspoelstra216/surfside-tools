<?php
/**
 * YouTube Data API connection and importer status for Church Settings → Integrations.
 * All actions require a WordPress admin capability and explicit nonce.
 */
if (!defined('ABSPATH')) { exit; }

function surfside_tools_youtube_settings_notice($message, $type = 'success') {
    set_transient('surfside_youtube_notice_' . get_current_user_id(), array(
        'message' => sanitize_text_field($message),
        'type' => $type === 'error' ? 'error' : 'success',
    ), 90);
}

function surfside_tools_youtube_settings_action() {
    if (!current_user_can('manage_options')) wp_die('You do not have permission to manage YouTube integration.', '', array('response' => 403));
    check_admin_referer('surfside_youtube_settings', 'surfside_youtube_nonce');

    $action = isset($_POST['youtube_action']) && is_string($_POST['youtube_action'])
        ? sanitize_key(wp_unslash($_POST['youtube_action']))
        : '';
    $destination = surfside_tools_staff_page_url('settings') . '#surfside-youtube';

    if ($action === 'save') {
        $config = surfside_tools_youtube_config();
        $channel_input = isset($_POST['youtube_channel']) && is_string($_POST['youtube_channel'])
            ? sanitize_text_field(wp_unslash($_POST['youtube_channel'])) : '';
        $channel = surfside_tools_youtube_channel_identifier($channel_input);
        $master = isset($_POST['youtube_master']) && is_string($_POST['youtube_master'])
            ? trim(sanitize_text_field(wp_unslash($_POST['youtube_master']))) : '';
        $key = isset($_POST['youtube_api_key']) && is_string($_POST['youtube_api_key'])
            ? trim(sanitize_text_field(wp_unslash($_POST['youtube_api_key']))) : '';
        $clear = !empty($_POST['youtube_clear_key']);

        if ($channel === '' || ($master !== '' && !surfside_tools_youtube_valid_playlist_id($master))) {
            surfside_tools_youtube_settings_notice('Enter a valid channel @handle or ID and, if supplied, a valid master playlist ID.', 'error');
        } elseif ($key !== '' && !preg_match('/^[A-Za-z0-9_-]{20,200}$/', $key)) {
            surfside_tools_youtube_settings_notice('The YouTube API key format looks invalid.', 'error');
        } else {
            $next = array('channel_ref' => $channel, 'master_playlist_id' => $master);
            update_option(SURFSIDE_YT_CONFIG_OPTION, $next, false);
            if (!defined('SURFSIDE_YOUTUBE_API_KEY')) {
                if ($clear) delete_option('surfside_tools_youtube_api_key');
                elseif ($key !== '') update_option('surfside_tools_youtube_api_key', $key, false);
            }
            if ($config !== $next || $clear || $key !== '') {
                wp_clear_scheduled_hook(SURFSIDE_YT_TICK);
                $state = surfside_tools_youtube_state();
                $state['status'] = 'idle';
                $state['message'] = 'Settings changed. Run Sync Now to refresh the archive.';
                update_option(SURFSIDE_YT_STATE_OPTION, $state, false);
            }
            surfside_tools_youtube_settings_notice('YouTube settings saved. Test the connection, then start synchronization.');
        }
    } elseif ($action === 'test') {
        $config = surfside_tools_youtube_config();
        $result = surfside_tools_youtube_resolve_channel($config['channel_ref']);
        surfside_tools_youtube_settings_notice(
            is_wp_error($result) ? $result->get_error_message() : 'Connected to YouTube channel: ' . $result['title'] . '.',
            is_wp_error($result) ? 'error' : 'success'
        );
    } elseif ($action === 'sync') {
        $result = surfside_tools_youtube_start_sync();
        surfside_tools_youtube_settings_notice(
            is_wp_error($result) ? $result->get_error_message() : 'YouTube metadata sync queued. Refresh this page later to see progress.',
            is_wp_error($result) ? 'error' : 'success'
        );
    } else {
        surfside_tools_youtube_settings_notice('Unknown YouTube integration action.', 'error');
    }

    wp_safe_redirect($destination);
    exit;
}
add_action('admin_post_surfside_youtube_settings', 'surfside_tools_youtube_settings_action');

function surfside_tools_youtube_settings_panel() {
    if (!is_user_logged_in() || !current_user_can('manage_options')) return '';
    $config = surfside_tools_youtube_config();
    $configured = surfside_tools_youtube_api_key() !== '' && $config['channel_ref'] !== '';
    $state = surfside_tools_youtube_state();
    $notice_key = 'surfside_youtube_notice_' . get_current_user_id();
    $notice = get_transient($notice_key);
    if (is_array($notice)) delete_transient($notice_key);

    $count = null;
    $undated = 0;
    $series = 0;
    if ((int) get_option(SURFSIDE_YT_SCHEMA_OPTION, 0) >= SURFSIDE_YT_SCHEMA_VERSION) {
        global $wpdb;
        $tables = surfside_tools_youtube_tables();
        $master = (string) ($state['master_id'] ?? '');
        if ($master !== '') {
            $count = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$tables['members']} WHERE playlist_id = %s", $master
            ));
            $undated = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$tables['members']} m INNER JOIN {$tables['videos']} v ON m.video_id = v.video_id WHERE m.playlist_id = %s AND v.sermon_date IS NULL", $master
            ));
        }
        $series = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$tables['playlists']} WHERE category = 'series'");
    }
    $form_action = admin_url('admin-post.php');

    ob_start();
    ?>
    <details id="surfside-youtube" class="surfside-front-settings-card surfside-integration-card">
        <summary><span>YouTube Sermon Archive</span></summary>
        <div class="surfside-integration-body">
            <p class="surfside-front-description">Import public video titles, series playlists, thumbnails and sermon dates into Surfside Tools. Videos stay on YouTube. API credentials are never sent to the app or visitors.</p>

            <?php if (is_array($notice)) : ?>
                <div class="surfside-front-settings-notice surfside-front-settings-<?php echo esc_attr($notice['type']); ?>"><?php echo esc_html($notice['message']); ?></div>
            <?php endif; ?>

            <form method="post" action="<?php echo esc_url($form_action); ?>">
                <input type="hidden" name="action" value="surfside_youtube_settings">
                <input type="hidden" name="youtube_action" value="save">
                <?php wp_nonce_field('surfside_youtube_settings', 'surfside_youtube_nonce'); ?>

                <label for="surfside-youtube-api-key"><strong>YouTube Data API key</strong></label>
                <input id="surfside-youtube-api-key" type="password" name="youtube_api_key" value="" autocomplete="new-password" placeholder="<?php echo $configured ? 'Saved — enter a new key to replace' : 'Paste your YouTube Data API v3 key'; ?>">
                <p class="surfside-front-description">Create a server-side API key in Google Cloud with YouTube Data API v3 enabled. Restrict the key to that API. Leave blank to keep the saved key.</p>

                <label for="surfside-youtube-channel"><strong>YouTube channel</strong></label>
                <input id="surfside-youtube-channel" type="text" name="youtube_channel" required value="<?php echo esc_attr($config['channel_ref']); ?>" placeholder="@YourChurch or UC…">
                <p class="surfside-front-description">Accepts @handle, channel ID, or a youtube.com/@handle/playlists URL.</p>

                <label for="surfside-youtube-master"><strong>Master Messages playlist ID</strong> (optional)</label>
                <input id="surfside-youtube-master" type="text" name="youtube_master" value="<?php echo esc_attr($config['master_playlist_id']); ?>" placeholder="Auto-detect playlist named Messages">
                <p class="surfside-front-description">If blank, find one public playlist named exactly “Messages.” Enter its playlist ID if its name differs or multiple matches exist.</p>

                <?php if (!defined('SURFSIDE_YOUTUBE_API_KEY') && surfside_tools_youtube_api_key() !== '') : ?>
                    <p><label><input type="checkbox" name="youtube_clear_key" value="1"> Remove stored API key on Save</label></p>
                <?php endif; ?>
                <p><button type="submit" class="surfside-front-primary-button">Save YouTube Settings</button></p>
            </form>

            <div class="surfside-youtube-control-row">
                <form method="post" action="<?php echo esc_url($form_action); ?>">
                    <input type="hidden" name="action" value="surfside_youtube_settings">
                    <input type="hidden" name="youtube_action" value="test">
                    <?php wp_nonce_field('surfside_youtube_settings', 'surfside_youtube_nonce'); ?>
                    <button type="submit" class="surfside-front-secondary-button" <?php disabled(!$configured); ?>>Test Connection</button>
                </form>
                <form method="post" action="<?php echo esc_url($form_action); ?>">
                    <input type="hidden" name="action" value="surfside_youtube_settings">
                    <input type="hidden" name="youtube_action" value="sync">
                    <?php wp_nonce_field('surfside_youtube_settings', 'surfside_youtube_nonce'); ?>
                    <button type="submit" class="surfside-front-secondary-button" <?php disabled(!$configured); ?>>Sync Now</button>
                </form>
            </div>

            <div class="surfside-youtube-status">
                <h3>Import status</h3>
                <p><strong>State:</strong> <?php echo esc_html(ucfirst((string) ($state['status'] ?? 'not started'))); ?></p>
                <?php if (!empty($state['message'])) : ?><p><?php echo esc_html($state['message']); ?></p><?php endif; ?>
                <?php if (($state['status'] ?? '') === 'running') : ?>
                    <p>API pages processed: <?php echo esc_html(number_format_i18n((int) ($state['requests'] ?? 0))); ?>. Import runs in small background batches and may take time for thousands of videos.</p>
                <?php endif; ?>
                <?php if (!empty($state['last_success'])) : ?>
                    <p><strong>Last complete sync:</strong> <?php echo esc_html(wp_date('F j, Y g:i A', (int) $state['last_success'])); ?></p>
                <?php endif; ?>
                <?php if ($count !== null) : ?>
                    <p><strong>Master playlist videos:</strong> <?php echo esc_html(number_format_i18n($count)); ?>
                        · <strong>Series playlists:</strong> <?php echo esc_html(number_format_i18n($series)); ?>
                        · <strong>Dates needing review:</strong> <?php echo esc_html(number_format_i18n($undated)); ?></p>
                <?php endif; ?>
                <p class="surfside-front-description">Public website and app browsing will be added separately. Background runs depend on WordPress cron activity.</p>
            </div>
        </div>
    </details>
    <style>
    #surfside-youtube{scroll-margin-top:24px}
    #surfside-youtube .surfside-integration-body form{margin:10px 0 18px}
    #surfside-youtube .surfside-youtube-control-row{display:flex;flex-wrap:wrap;gap:12px;margin:12px 0 18px}
    #surfside-youtube .surfside-youtube-control-row form{margin:0}
    #surfside-youtube .surfside-youtube-status{border-top:1px solid #e2e8f0;padding-top:16px;margin-top:16px}
    #surfside-youtube .surfside-youtube-status h3{margin:0 0 8px}
    #surfside-youtube .surfside-youtube-status p{margin:8px 0}
    #surfside-youtube button:disabled{opacity:.5;cursor:not-allowed}
    </style>
    <?php
    return ob_get_clean();
}
