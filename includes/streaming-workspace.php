<?php
/**
 * Shared streaming workspace.
 *
 * Consolidates the staff UI without migrating any existing options, copying
 * credential forms, or changing the legacy /dashboard/site-streaming/ page.
 */
if (!defined('ABSPATH')) { exit; }

/** Only a small allowlist can select which existing editor to render. */
function surfside_tools_streaming_workspace_tab() {
    $raw = isset($_GET['stream_tab']) && is_string($_GET['stream_tab'])
        ? sanitize_key(wp_unslash($_GET['stream_tab'])) : 'cloudflare';
    return in_array($raw, array('cloudflare', 'watch', 'twitch'), true)
        ? $raw : 'cloudflare';
}

function surfside_tools_streaming_workspace_url($tab = 'cloudflare') {
    $base = surfside_tools_staff_page_url('site-settings/streaming');
    return $tab === 'cloudflare' ? $base : add_query_arg('stream_tab', $tab, $base);
}

/** Print the same nav for every view; use links rather than nested forms. */
function surfside_tools_streaming_workspace_tabs() {
    $selected = surfside_tools_streaming_workspace_tab();
    $choices = array(
        'cloudflare' => 'Cloudflare & Recordings',
        'watch' => 'Watch Live',
        'twitch' => 'Twitch Connection',
    );
    ob_start(); ?>
    <nav class="surfside-streaming-tabs" aria-label="Streaming settings">
      <?php foreach ($choices as $tab => $title) : ?>
        <a class="<?php echo $tab === $selected ? 'is-active' : ''; ?>"
           href="<?php echo esc_url(surfside_tools_streaming_workspace_url($tab)); ?>"
           <?php if ($tab === $selected) : ?>aria-current="page"<?php endif; ?>><?php echo esc_html($title); ?></a>
      <?php endforeach; ?>
    </nav>
    <?php return ob_get_clean();
}

/** Existing site-information and Twitch editors handle their own authorized POSTs. */
function surfside_tools_streaming_workspace_secondary() {
    if (!is_user_logged_in() || !current_user_can('manage_options')) {
        return '<p>You do not have permission to manage streaming.</p>';
    }
    if (function_exists('surfside_tools_prevent_cache')) surfside_tools_prevent_cache();
    if (function_exists('surfside_tools_staff_enqueue_styles')) surfside_tools_staff_enqueue_styles();
    $tab = surfside_tools_streaming_workspace_tab();

    if ($tab === 'watch') {
        if (!function_exists('surfside_tools_staff_site_information_shortcode')) {
            return '<p>Watch Live settings are temporarily unavailable.</p>';
        }
        // The original site-information shortcode processes the same
        // streaming-only POST and preserves all other site information.
        // Embed its form without the legacy page header or back link.
        ob_start(); ?>
        <div class="surfside-staff-shell surfside-streaming-watch">
          <section class="surfside-staff-hero">
            <p class="surfside-staff-eyebrow">Messages &amp; Media</p>
            <h1>Streaming</h1>
            <p>Manage live service delivery, recordings, Watch Live and Twitch from one place.</p>
          </section>
          <?php echo surfside_tools_streaming_workspace_tabs(); ?>
          <?php echo surfside_tools_staff_site_information_shortcode(array('section' => 'streaming', 'embedded' => true)); ?>
          <?php if (function_exists('surfside_tools_offline_slideshow_manager')) echo surfside_tools_offline_slideshow_manager(); ?>
        </div>
        <?php return ob_get_clean();
    }

    if ($tab === 'twitch') {
        if (!function_exists('surfside_tools_twitch_settings_panel') ||
            !function_exists('surfside_tools_twitch_settings_handle_post')) {
            return '<p>Twitch connection settings are temporarily unavailable.</p>';
        }
        $notice = surfside_tools_twitch_settings_handle_post();
        ob_start(); ?>
        <div class="surfside-staff-shell surfside-streaming-twitch">
          <section class="surfside-staff-hero">
            <p class="surfside-staff-eyebrow">Messages &amp; Media</p>
            <h1>Streaming</h1>
            <p>Configure Twitch as an optional fallback for Watch Live. Cloudflare remains the go-live notification source.</p>
          </section>
          <?php echo surfside_tools_streaming_workspace_tabs(); ?>
          <?php echo $notice; // Escaped by the existing notice function. ?>
          <?php echo surfside_tools_twitch_settings_panel(true); // Existing form and permission checks. ?>
        </div>
        <?php return ob_get_clean();
    }
    return '';
}

/** WordPress media picker must be enqueued before page output on Watch Live. */
function surfside_tools_streaming_workspace_watch_media_assets() {
    if (!is_user_logged_in() || !current_user_can('manage_options')) return;
    if (!is_page() || surfside_tools_streaming_workspace_tab() !== 'watch') return;
    $post = get_queried_object();
    if (!($post instanceof WP_Post) || trim((string) get_page_uri($post->ID), '/') !== 'dashboard/site-settings/streaming') return;
    wp_enqueue_media();
}
add_action('wp_enqueue_scripts', 'surfside_tools_streaming_workspace_watch_media_assets', 20);

function surfside_tools_streaming_workspace_styles() {
    if (!is_user_logged_in() || !current_user_can('manage_options') || !is_page()) return;
    $post = get_queried_object();
    if (!($post instanceof WP_Post) || trim((string) get_page_uri($post->ID), '/') !== 'dashboard/site-settings/streaming') return;
    wp_enqueue_style('surfside-streaming-workspace', SURFSIDE_TOOLS_URL . 'assets/css/streaming-workspace.css', array(), SURFSIDE_TOOLS_VERSION);
}
add_action('wp_enqueue_scripts', 'surfside_tools_streaming_workspace_styles', 25);
