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

add_action('admin_init', function () {
    register_setting('surfside_tools_app_settings_group', 'surfside_tools_app_settings', array(
        'type' => 'array',
        'sanitize_callback' => function ($input) {
            $input = is_array($input) ? $input : array();
            $existing = surfside_tools_app_settings();
            return array(
                'home_hero_image_id' => absint($input['home_hero_image_id'] ?? 0),
                'giving_url' => esc_url_raw(trim((string)($input['giving_url'] ?? ''))),
                // Preserve any key saved by the initial YouVersion foundation until
                // Site Settings -> Integrations migrates it to the dedicated option.
                'youversion_app_key' => sanitize_text_field((string)($existing['youversion_app_key'] ?? '')),
            );
        },
        'default' => array('home_hero_image_id' => 0, 'giving_url' => '', 'youversion_app_key' => ''),
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
