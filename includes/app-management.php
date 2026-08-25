<?php
/**
 * Mobile app presentation and integration settings.
 */
if (!defined('ABSPATH')) { exit; }

function surfside_tools_app_settings() {
    $settings = get_option('surfside_tools_app_settings', array());
    return is_array($settings) ? $settings : array();
}

function surfside_tools_app_hero_image_url() {
    $settings = surfside_tools_app_settings();
    $id = absint($settings['home_hero_image_id'] ?? 0);
    $url = $id ? wp_get_attachment_image_url($id, 'full') : '';
    return $url ? esc_url_raw($url) : '';
}

function surfside_tools_app_giving_url() {
    $settings = surfside_tools_app_settings();
    return esc_url_raw($settings['giving_url'] ?? '');
}

function surfside_tools_app_featured_announcement() {
    $settings = surfside_tools_app_settings();
    $enabled = !empty($settings['featured_enabled']);
    $headline = sanitize_text_field((string)($settings['featured_headline'] ?? ''));
    $message = sanitize_textarea_field((string)($settings['featured_message'] ?? ''));
    $button_label = sanitize_text_field((string)($settings['featured_button_label'] ?? ''));
    $button_url = esc_url_raw((string)($settings['featured_button_url'] ?? ''));
    $starts_at = sanitize_text_field((string)($settings['featured_starts_at'] ?? ''));
    $ends_at = sanitize_text_field((string)($settings['featured_ends_at'] ?? ''));
    $timezone = wp_timezone();
    $now = current_datetime();
    $start = $starts_at ? DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $starts_at, $timezone) : null;
    $end = $ends_at ? DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $ends_at, $timezone) : null;
    $active = $enabled && $headline !== '' && $ends_at !== '' && $end && (!$start || $now >= $start) && $now <= $end;
    return array(
        'enabled' => $enabled,
        'active' => (bool)$active,
        'headline' => $headline,
        'message' => $message,
        'button_label' => $button_label,
        'button_url' => $button_url,
        'starts_at' => $start ? $start->format(DATE_ATOM) : '',
        'ends_at' => $end ? $end->format(DATE_ATOM) : '',
    );
}

add_action('admin_init', function () {
    register_setting('surfside_tools_app_settings_group', 'surfside_tools_app_settings', array(
        'type' => 'array',
        'sanitize_callback' => function ($input) {
            $input = is_array($input) ? $input : array();
            $existing = surfside_tools_app_settings();
            $starts_at = sanitize_text_field((string)($input['featured_starts_at'] ?? ''));
            $ends_at = sanitize_text_field((string)($input['featured_ends_at'] ?? ''));
            if ($starts_at && !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $starts_at)) { $starts_at = ''; }
            if ($ends_at && !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $ends_at)) { $ends_at = ''; }
            if (!empty($input['featured_enabled']) && $ends_at === '') {
                add_settings_error('surfside_tools_app_settings', 'featured_run_until_required', 'Featured Home Announcement requires a Run Until date and time before it can be enabled.', 'error');
            }
            return array(
                'home_hero_image_id' => absint($input['home_hero_image_id'] ?? 0),
                'giving_url' => esc_url_raw(trim((string)($input['giving_url'] ?? ''))),
                'featured_enabled' => !empty($input['featured_enabled']) && $ends_at !== '' ? 1 : 0,
                'featured_headline' => sanitize_text_field((string)($input['featured_headline'] ?? '')),
                'featured_message' => sanitize_textarea_field((string)($input['featured_message'] ?? '')),
                'featured_button_label' => sanitize_text_field((string)($input['featured_button_label'] ?? '')),
                'featured_button_url' => esc_url_raw(trim((string)($input['featured_button_url'] ?? ''))),
                'featured_starts_at' => $starts_at,
                'featured_ends_at' => $ends_at,
                // Preserve any key saved by the initial YouVersion foundation until
                // Site Settings -> Integrations migrates it to the dedicated option.
                'youversion_app_key' => sanitize_text_field((string)($existing['youversion_app_key'] ?? '')),
            );
        },
        'default' => array('home_hero_image_id' => 0, 'giving_url' => '', 'featured_enabled' => 0, 'featured_headline' => '', 'featured_message' => '', 'featured_button_label' => '', 'featured_button_url' => '', 'featured_starts_at' => '', 'featured_ends_at' => '', 'youversion_app_key' => ''),
    ));
});

add_action('admin_enqueue_scripts', function ($hook) {
    if (strpos($hook, 'surfside-tools-app') === false) { return; }
    wp_enqueue_media();
    wp_register_script('surfside-tools-app-admin', '', array('jquery'), SURFSIDE_TOOLS_VERSION, true);
    wp_enqueue_script('surfside-tools-app-admin');
    wp_add_inline_script('surfside-tools-app-admin', "jQuery(function($){var frame;$('#surfside-app-select-hero').on('click',function(e){e.preventDefault();if(frame){frame.open();return;}frame=wp.media({title:'Choose App Home Hero',button:{text:'Use this image'},multiple:false,library:{type:'image'}});frame.on('select',function(){var a=frame.state().get('selection').first().toJSON();$('#surfside-app-hero-id').val(a.id);$('#surfside-app-hero-preview').attr('src',a.url).show();$('#surfside-app-remove-hero').show();});frame.open();});$('#surfside-app-remove-hero').on('click',function(e){e.preventDefault();$('#surfside-app-hero-id').val('0');$('#surfside-app-hero-preview').hide().attr('src','');$(this).hide();});});");
});

function surfside_tools_admin_app_page() {
    if (!current_user_can('upload_files')) { wp_die('You do not have permission to manage the Surfside app.'); }
    $settings = surfside_tools_app_settings();
    $image_id = absint($settings['home_hero_image_id'] ?? 0);
    $image_url = $image_id ? wp_get_attachment_image_url($image_id, 'large') : '';
    $giving_url = esc_url($settings['giving_url'] ?? '');
    $featured = surfside_tools_app_featured_announcement();
    ?>
    <div class="wrap surfside-admin-wrap">
        <div class="surfside-admin-hero">
            <h1>Mobile App</h1>
            <p class="surfside-admin-muted">Manage settings that are unique to the Surfside mobile app. Shared church information, events, announcements, and sermon notes remain managed in their existing tools.</p>
        </div>
        <?php settings_errors(); ?>
        <form method="post" action="options.php">
            <?php settings_fields('surfside_tools_app_settings_group'); ?>
            <div class="surfside-admin-card" style="max-width:760px;">
                <h2>Home Experience</h2>
                <p class="surfside-admin-muted">Choose the congregation or worship photo used as the app Home hero. The app will crop the image responsively; a wide landscape image works best.</p>
                <input type="hidden" id="surfside-app-hero-id" name="surfside_tools_app_settings[home_hero_image_id]" value="<?php echo esc_attr($image_id); ?>">
                <img id="surfside-app-hero-preview" src="<?php echo esc_url($image_url); ?>" alt="" style="<?php echo $image_url ? '' : 'display:none;'; ?>width:100%;max-width:640px;max-height:300px;object-fit:cover;border-radius:12px;margin:8px 0 14px;">
                <p><button type="button" class="button button-primary" id="surfside-app-select-hero"><?php echo $image_id ? 'Replace Hero Image' : 'Select Hero Image'; ?></button> <button type="button" class="button" id="surfside-app-remove-hero" style="<?php echo $image_id ? '' : 'display:none;'; ?>">Remove</button></p>
                <p class="description">If no image is selected, the mobile app can fall back to its standard branded hero.</p>
            </div>
            <div class="surfside-admin-card" style="max-width:760px;">
                <h2>Featured Home Announcement</h2>
                <p class="surfside-admin-muted">Temporarily feature VBS, baptisms, special services, ministry opportunities, or other timely information on the app Home screen. When the announcement is inactive or expires, Home returns to the normal upcoming-event card.</p>
                <p><label><input type="checkbox" name="surfside_tools_app_settings[featured_enabled]" value="1" <?php checked(!empty($settings['featured_enabled'])); ?>> <strong>Enable featured announcement</strong></label></p>
                <p><label for="surfside-featured-headline"><strong>Headline</strong></label><br><input type="text" class="regular-text" style="width:100%;max-width:640px;" id="surfside-featured-headline" name="surfside_tools_app_settings[featured_headline]" value="<?php echo esc_attr($settings['featured_headline'] ?? ''); ?>"></p>
                <p><label for="surfside-featured-message"><strong>Short Message</strong></label><br><textarea class="large-text" rows="3" style="max-width:640px;" id="surfside-featured-message" name="surfside_tools_app_settings[featured_message]"><?php echo esc_textarea($settings['featured_message'] ?? ''); ?></textarea></p>
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;max-width:640px;">
                    <p><label for="surfside-featured-start"><strong>Start Showing</strong></label><br><input type="datetime-local" id="surfside-featured-start" name="surfside_tools_app_settings[featured_starts_at]" value="<?php echo esc_attr($settings['featured_starts_at'] ?? ''); ?>"><br><span class="description">Optional. Leave blank to start immediately.</span></p>
                    <p><label for="surfside-featured-end"><strong>Run Until</strong></label><br><input type="datetime-local" id="surfside-featured-end" name="surfside_tools_app_settings[featured_ends_at]" value="<?php echo esc_attr($settings['featured_ends_at'] ?? ''); ?>" required><br><span class="description">Required when enabled.</span></p>
                </div>
                <div style="display:grid;grid-template-columns:minmax(160px,220px) minmax(240px,1fr);gap:16px;max-width:640px;">
                    <p><label for="surfside-featured-button"><strong>Button Label</strong></label><br><input type="text" class="regular-text" style="width:100%;" id="surfside-featured-button" name="surfside_tools_app_settings[featured_button_label]" value="<?php echo esc_attr($settings['featured_button_label'] ?? ''); ?>" placeholder="Learn More"></p>
                    <p><label for="surfside-featured-url"><strong>Button Link</strong></label><br><input type="url" class="regular-text" style="width:100%;" id="surfside-featured-url" name="surfside_tools_app_settings[featured_button_url]" value="<?php echo esc_attr($settings['featured_button_url'] ?? ''); ?>" placeholder="https://..."></p>
                </div>
                <?php if (!empty($settings['featured_enabled'])) : ?><p class="description"><strong>Status:</strong> <?php echo $featured['active'] ? 'Currently showing in the app API.' : 'Scheduled or expired; not currently active.'; ?></p><?php endif; ?>
            </div>
            <div class="surfside-admin-card" style="max-width:760px;">
                <h2>Giving</h2>
                <p class="surfside-admin-muted">Set the secure giving form that opens inside the Surfside mobile app.</p>
                <p><label for="surfside-app-giving-url"><strong>Giving Form URL</strong></label></p>
                <input type="url" class="regular-text" style="width:100%;max-width:640px;" id="surfside-app-giving-url" name="surfside_tools_app_settings[giving_url]" value="<?php echo esc_attr($giving_url); ?>" placeholder="https://give.tithe.ly/?formId=...">
                <p class="description">Use the direct Tithely Giving Form URL, not the kiosk URL or embed code. Changes here can update the app without publishing a new app release.</p>
            </div>
            <?php submit_button('Save App Settings'); ?>
        </form>
    </div>
    <?php
}
