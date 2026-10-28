<?php
/**
 * Offline announcement slideshow: one managed playlist shared by TV, web and mobile.
 * Legacy announcement_video_id remains the fallback for existing clients.
 */
if (!defined('ABSPATH')) { exit; }

const SURFSIDE_TOOLS_OFFLINE_SLIDESHOW_OPTION = 'surfside_tools_offline_slideshow';
const SURFSIDE_TOOLS_OFFLINE_SLIDESHOW_LIMIT = 50;

function surfside_tools_offline_slideshow_settings() {
    $stored = get_option(SURFSIDE_TOOLS_OFFLINE_SLIDESHOW_OPTION, array());
    $stored = is_array($stored) ? $stored : array();
    $duration = absint($stored['default_duration_seconds'] ?? 8);
    return array(
        'default_duration_seconds' => max(3, min(60, $duration ?: 8)),
        'slides' => isset($stored['slides']) && is_array($stored['slides']) ? $stored['slides'] : array(),
        'updated_at' => absint($stored['updated_at'] ?? 0),
    );
}

/** Accept only existing image attachments, with a bounded order and unique IDs. */
function surfside_tools_offline_slideshow_validate_slides($items) {
    $slides = array();
    $seen = array();
    foreach ($items as $item) {
        if (!is_array($item)) continue;
        $id = absint($item['id'] ?? 0);
        if (!$id || isset($seen[$id]) || get_post_type($id) !== 'attachment' || !wp_attachment_is_image($id)) continue;
        $seen[$id] = true;
        $duration = absint($item['duration_seconds'] ?? 0);
        $slides[] = array(
            'id' => $id,
            'enabled' => !empty($item['enabled']),
            'duration_seconds' => $duration ? max(3, min(60, $duration)) : 0,
        );
        if (count($slides) >= SURFSIDE_TOOLS_OFFLINE_SLIDESHOW_LIMIT) break;
    }
    return $slides;
}

/** Published playlist only; no staff-only draft slides or arbitrary supplied URLs. */
function surfside_tools_offline_slideshow_public_data() {
    $settings = surfside_tools_offline_slideshow_settings();
    $slides = array();
    foreach (array_slice($settings['slides'], 0, SURFSIDE_TOOLS_OFFLINE_SLIDESHOW_LIMIT) as $slide) {
        if (empty($slide['enabled'])) continue;
        $id = absint($slide['id'] ?? 0);
        if (!$id || get_post_type($id) !== 'attachment' || !wp_attachment_is_image($id)) continue;
        $url = wp_get_attachment_image_url($id, 'full');
        if (!$url) continue;
        $seconds = absint($slide['duration_seconds'] ?? 0);
        $slides[] = array(
            'id' => $id,
            'image_url' => esc_url_raw($url),
            'duration_seconds' => $seconds ? max(3, min(60, $seconds)) : $settings['default_duration_seconds'],
        );
    }
    return array(
        'updated_at' => $settings['updated_at'] ? gmdate(DATE_ATOM, $settings['updated_at']) : '',
        'default_duration_seconds' => $settings['default_duration_seconds'],
        'slides' => $slides,
    );
}

/** Additive API contract: old clients continue using livestream.announcement_video_url. */
function surfside_tools_offline_slideshow_mobile_api($payload) {
    if (isset($payload['livestream']) && is_array($payload['livestream'])) {
        $payload['livestream']['announcement_slideshow'] = surfside_tools_offline_slideshow_public_data();
    }
    return $payload;
}
add_filter('surfside_tools_mobile_api_app', 'surfside_tools_offline_slideshow_mobile_api');

function surfside_tools_offline_slideshow_handle_post() {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !isset($_POST['surfside_slideshow_action'])) return '';
    if (!current_user_can('manage_options')) return array('type' => 'error', 'message' => 'You do not have permission to publish announcement slides.');
    $nonce = isset($_POST['surfside_slideshow_nonce']) && is_string($_POST['surfside_slideshow_nonce'])
        ? sanitize_text_field(wp_unslash($_POST['surfside_slideshow_nonce'])) : '';
    if (!wp_verify_nonce($nonce, 'surfside_slideshow_save')) {
        return array('type' => 'error', 'message' => 'Security check failed. Refresh and try again.');
    }
    if (!isset($_POST['surfside_slideshow_slides']) || !is_string($_POST['surfside_slideshow_slides'])) {
        return array('type' => 'error', 'message' => 'Missing slide information. No changes were saved.');
    }
    $submitted = json_decode(wp_unslash($_POST['surfside_slideshow_slides']), true);
    if (!is_array($submitted) || count($submitted) > SURFSIDE_TOOLS_OFFLINE_SLIDESHOW_LIMIT) {
        return array('type' => 'error', 'message' => 'Invalid slideshow data or too many images (maximum 50). No changes were saved.');
    }
    $duration = isset($_POST['surfside_slideshow_default_duration']) ? absint($_POST['surfside_slideshow_default_duration']) : 8;
    update_option(SURFSIDE_TOOLS_OFFLINE_SLIDESHOW_OPTION, array(
        'default_duration_seconds' => max(3, min(60, $duration ?: 8)),
        'slides' => surfside_tools_offline_slideshow_validate_slides($submitted),
        'updated_at' => time(),
    ), false);
    if (function_exists('surfside_tools_purge_cache')) surfside_tools_purge_cache();
    return array('type' => 'success', 'message' => 'Announcement slideshow published. Existing video-only apps will continue to use the legacy MP4.');
}

/** Separate form is rendered after Watch Live's existing settings form, never nested. */
function surfside_tools_offline_slideshow_manager() {
    if (!is_user_logged_in() || !current_user_can('manage_options')) return '';
    $notice = surfside_tools_offline_slideshow_handle_post();
    $settings = surfside_tools_offline_slideshow_settings();
    $editor_slides = array();
    foreach (array_slice($settings['slides'], 0, SURFSIDE_TOOLS_OFFLINE_SLIDESHOW_LIMIT) as $slide) {
        $id = absint($slide['id'] ?? 0);
        if (!$id || get_post_type($id) !== 'attachment' || !wp_attachment_is_image($id)) continue;
        $editor_slides[] = array(
            'id' => $id,
            'title' => get_the_title($id) ?: ('Image #' . $id),
            'url' => wp_get_attachment_image_url($id, 'full'),
            'thumbnail_url' => wp_get_attachment_image_url($id, 'medium'),
            'enabled' => !empty($slide['enabled']),
            'duration_seconds' => absint($slide['duration_seconds'] ?? 0),
        );
    }
    $slides_json = wp_json_encode($editor_slides);
    $stored_json = wp_json_encode($settings['slides']);
    ob_start(); ?>
    <section class="surfside-information-card surfside-offline-slideshow" data-surfside-offline-slideshow
             data-slides="<?php echo esc_attr($slides_json); ?>">
      <h2>Offline announcement slideshow</h2>
      <p>Upload or select images, change their order, and publish one playlist for all slideshow-capable platforms. Recommended image size: 1920 × 1080 (16:9).</p>
      <?php if ($notice) : ?>
        <p class="surfside-slideshow-notice surfside-slideshow-notice--<?php echo esc_attr($notice['type']); ?>" role="status"><?php echo esc_html($notice['message']); ?></p>
      <?php endif; ?>
      <form method="post" class="surfside-slideshow-form">
        <?php wp_nonce_field('surfside_slideshow_save', 'surfside_slideshow_nonce'); ?>
        <input type="hidden" name="surfside_slideshow_action" value="save">
        <input type="hidden" name="surfside_slideshow_slides" value="<?php echo esc_attr($stored_json); ?>" data-slideshow-serialized>
        <div class="surfside-slideshow-toolbar">
          <button type="button" class="surfside-information-add" data-slideshow-add>+ Add images</button>
          <label>Default duration (seconds)
            <input type="number" name="surfside_slideshow_default_duration" min="3" max="60" step="1"
                   value="<?php echo esc_attr($settings['default_duration_seconds']); ?>" data-slideshow-default>
          </label>
          <span>Up to 50 images. Disabled images stay in the editor but are not published.</span>
        </div>
        <div class="surfside-slideshow-list" data-slideshow-list aria-label="Announcement image order"></div>
        <p class="surfside-slideshow-empty" data-slideshow-empty>No images selected. Use Add images to choose existing images or upload new ones.</p>
        <div class="surfside-slideshow-preview" data-slideshow-preview hidden>
          <img src="" alt="" data-slideshow-preview-image>
          <p data-slideshow-preview-label></p>
        </div>
        <div class="surfside-slideshow-actions">
          <button type="button" class="surfside-information-add" data-slideshow-preview-button>Preview slideshow</button>
          <button type="submit" class="surfside-information-save">Publish slideshow</button>
        </div>
      </form>
      <p class="surfside-information-help">Publishing only updates the image playlist and does not change live-stream behavior or delete media. The existing MP4 remains available as a fallback until each website/TV/mobile client is updated.</p>
    </section>
    <?php return ob_get_clean();
}

/** Load editor assets only for the authorized Watch Live staff page. */
function surfside_tools_offline_slideshow_enqueue_assets() {
    if (!is_user_logged_in() || !current_user_can('manage_options') || !is_page()) return;
    if (!function_exists('surfside_tools_streaming_workspace_tab') || surfside_tools_streaming_workspace_tab() !== 'watch') return;
    $post = get_queried_object();
    if (!($post instanceof WP_Post) || trim((string) get_page_uri($post->ID), '/') !== 'dashboard/site-settings/streaming') return;
    wp_enqueue_media();
    wp_enqueue_style('surfside-offline-slideshow', SURFSIDE_TOOLS_URL . 'assets/css/offline-slideshow.css', array(), SURFSIDE_TOOLS_VERSION);
    wp_enqueue_script('surfside-offline-slideshow', SURFSIDE_TOOLS_URL . 'assets/js/offline-slideshow.js', array(), SURFSIDE_TOOLS_VERSION, true);
}
add_action('wp_enqueue_scripts', 'surfside_tools_offline_slideshow_enqueue_assets', 20);
