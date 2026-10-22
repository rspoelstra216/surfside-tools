<?php
/**
 * Calendar workspace: Events, Calendar Defaults and Saved Places.
 *
 * Uses the established /dashboard/calendar/ staff route; no new WordPress
 * pages, options or location records are created by this interface.
 */
if (!defined('ABSPATH')) { exit; }

function surfside_tools_calendar_workspace_view() {
    $view = isset($_GET['view']) && is_string($_GET['view'])
        ? sanitize_key(wp_unslash($_GET['view'])) : 'events';
    return in_array($view, array('defaults', 'places'), true) ? $view : 'events';
}

function surfside_tools_calendar_workspace_url($view = 'events') {
    $base = surfside_tools_staff_page_url('calendar');
    return in_array($view, array('defaults', 'places'), true)
        ? add_query_arg('view', $view, $base) : $base;
}

function surfside_tools_calendar_workspace_tabs() {
    $active = surfside_tools_calendar_workspace_view();
    $tabs = array('events' => 'Manage Events');
    if (current_user_can('manage_options')) {
        $tabs['defaults'] = 'Calendar Defaults';
        $tabs['places'] = 'Saved Places';
    }
    ob_start(); ?>
    <nav class="surfside-calendar-workspace-tabs" aria-label="Calendar management">
      <?php foreach ($tabs as $view => $label) : ?>
        <a href="<?php echo esc_url(surfside_tools_calendar_workspace_url($view)); ?>"
           class="<?php echo $view === $active ? 'is-active' : ''; ?>"
           <?php if ($view === $active) : ?>aria-current="page"<?php endif; ?>><?php echo esc_html($label); ?></a>
      <?php endforeach; ?>
    </nav>
    <?php return ob_get_clean();
}

/**
 * Save only calendar preference keys, without destroying the Google Maps
 * API key or any additional settings stored in surfside_tools_settings.
 */
function surfside_tools_calendar_defaults_handle_post() {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' ||
        !isset($_POST['surfside_calendar_preferences_action'])) return '';
    if (!current_user_can('manage_options')) {
        return surfside_tools_frontend_settings_notice('You do not have permission to change calendar settings.', 'error');
    }
    $nonce = isset($_POST['surfside_calendar_preferences_nonce']) && is_string($_POST['surfside_calendar_preferences_nonce'])
        ? sanitize_text_field(wp_unslash($_POST['surfside_calendar_preferences_nonce'])) : '';
    if (!wp_verify_nonce($nonce, 'surfside_calendar_preferences')) {
        return surfside_tools_frontend_settings_notice('Security check failed. Refresh and try again.', 'error');
    }
    $action = isset($_POST['surfside_calendar_preferences_action']) && is_string($_POST['surfside_calendar_preferences_action'])
        ? sanitize_key(wp_unslash($_POST['surfside_calendar_preferences_action'])) : '';
    if ($action !== 'save') {
        return surfside_tools_frontend_settings_notice('Unknown calendar settings action.', 'error');
    }

    $stored = get_option('surfside_tools_settings', array());
    $settings = is_array($stored) ? $stored : array();
    $mode = isset($_POST['this_week_mode']) && is_string($_POST['this_week_mode'])
        ? sanitize_key(wp_unslash($_POST['this_week_mode'])) : 'next7';
    $settings['this_week_mode'] = in_array($mode, array('next7', 'sunday'), true) ? $mode : 'next7';
    $settings['default_event_duration'] = max(15, min(480,
        isset($_POST['default_event_duration']) ? absint($_POST['default_event_duration']) : 60));
    update_option('surfside_tools_settings', $settings, false);
    return surfside_tools_frontend_settings_notice('Calendar defaults saved.');
}

/** Keep existing Saved Places mutations and nonce/capability validation. */
function surfside_tools_calendar_places_handle_post() {
    $allowed = array('delete_saved_place', 'hide_calendar_place', 'restore_calendar_place');
    $action = isset($_POST['surfside_front_settings_action']) && is_string($_POST['surfside_front_settings_action'])
        ? sanitize_key(wp_unslash($_POST['surfside_front_settings_action'])) : '';
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !in_array($action, $allowed, true)) return '';
    return surfside_tools_frontend_settings_handle_post();
}

function surfside_tools_calendar_defaults_panel() {
    if (!current_user_can('manage_options')) return '<p>You do not have permission to manage calendar defaults.</p>';
    $notice = surfside_tools_calendar_defaults_handle_post();
    $stored = get_option('surfside_tools_settings', array());
    $settings = is_array($stored) ? $stored : array();
    $mode = (string) ($settings['this_week_mode'] ?? 'next7');
    $duration = (int) ($settings['default_event_duration'] ?? 60);
    ob_start(); ?>
    <section class="surfside-calendar-preferences">
      <?php echo $notice; // Existing notice helper escapes text. ?>
      <div class="surfside-calendar-preference-card">
        <h2>Calendar Defaults</h2>
        <p>Choose how the church calendar presents the week, and the suggested duration when you create an event.</p>
        <form method="post" class="surfside-calendar-preferences-form">
          <?php wp_nonce_field('surfside_calendar_preferences', 'surfside_calendar_preferences_nonce'); ?>
          <input type="hidden" name="surfside_calendar_preferences_action" value="save">
          <fieldset>
            <legend>This Week at Surfside</legend>
            <label><input type="radio" name="this_week_mode" value="next7" <?php checked($mode, 'next7'); ?>> Next 7 days starting today</label>
            <label><input type="radio" name="this_week_mode" value="sunday" <?php checked($mode, 'sunday'); ?>> Current Sunday–Saturday week</label>
          </fieldset>
          <label for="surfside-calendar-default-duration" class="surfside-calendar-preference-label">Default event duration</label>
          <div class="surfside-calendar-duration-row">
            <input id="surfside-calendar-default-duration" type="number" min="15" max="480" step="15"
              name="default_event_duration" value="<?php echo esc_attr($duration); ?>" required> <span>minutes</span>
          </div>
          <p>This suggests an end time after an event start time is entered; it won't change previously saved events.</p>
          <button type="submit" class="surfside-staff-button">Save Calendar Defaults</button>
        </form>
      </div>
    </section>
    <?php return ob_get_clean();
}

function surfside_tools_calendar_places_panel() {
    if (!current_user_can('manage_options')) return '<p>You do not have permission to manage saved places.</p>';
    $notice = surfside_tools_calendar_places_handle_post();
    list($saved, $calendar_places) = surfside_tools_frontend_saved_places_data();
    $hidden = function_exists('surfside_tools_get_hidden_place_names')
        ? surfside_tools_get_hidden_place_names() : array();
    ob_start(); ?>
    <section class="surfside-calendar-places">
      <?php echo $notice; // Existing notice helper escapes text. ?>
      <div class="surfside-calendar-preference-card">
        <h2>Saved Places</h2>
        <p>Manage saved locations and places previously used in events. Removing a place does not modify existing events.</p>
        <?php if (!$saved && !$calendar_places) : ?>
          <p>No saved or previously used places were found.</p>
        <?php else : ?>
          <div class="surfside-calendar-place-table">
            <div class="surfside-calendar-place-head"><span>Place</span><span>Address</span><span>Source</span><span>Action</span></div>
            <?php foreach ($saved as $place) : ?>
              <div class="surfside-calendar-place-row">
                <strong><?php echo esc_html($place['name'] ?? ''); ?></strong>
                <span><?php echo esc_html($place['address'] ?? ''); ?></span>
                <span>Saved place</span>
                <form method="post" onsubmit="return confirm('Remove this saved place? Existing events will keep their current location.');">
                  <?php wp_nonce_field('surfside_front_settings', 'surfside_front_settings_nonce'); ?>
                  <input type="hidden" name="surfside_front_settings_action" value="delete_saved_place">
                  <input type="hidden" name="place_id" value="<?php echo absint($place['id'] ?? 0); ?>">
                  <button type="submit" class="surfside-calendar-place-remove">Delete</button>
                </form>
              </div>
            <?php endforeach; ?>
            <?php foreach ($calendar_places as $key => $place) : if (in_array($key, $hidden, true)) continue; ?>
              <div class="surfside-calendar-place-row">
                <strong><?php echo esc_html($place['name']); ?></strong>
                <span><?php echo esc_html($place['address']); ?></span>
                <span>Previously used</span>
                <form method="post">
                  <?php wp_nonce_field('surfside_front_settings', 'surfside_front_settings_nonce'); ?>
                  <input type="hidden" name="surfside_front_settings_action" value="hide_calendar_place">
                  <input type="hidden" name="place_name" value="<?php echo esc_attr($place['name']); ?>">
                  <button type="submit" class="surfside-calendar-place-remove">Remove</button>
                </form>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
        <?php if ($hidden) : ?>
          <details class="surfside-calendar-removed-places">
            <summary>Removed suggestions (<?php echo count($hidden); ?>)</summary>
            <div class="surfside-calendar-restore-list">
              <?php foreach ($hidden as $name) : ?>
                <form method="post">
                  <?php wp_nonce_field('surfside_front_settings', 'surfside_front_settings_nonce'); ?>
                  <input type="hidden" name="surfside_front_settings_action" value="restore_calendar_place">
                  <input type="hidden" name="place_name" value="<?php echo esc_attr($name); ?>">
                  <button type="submit" class="surfside-staff-button-secondary">Restore <?php echo esc_html(ucwords($name)); ?></button>
                </form>
              <?php endforeach; ?>
            </div>
          </details>
        <?php endif; ?>
      </div>
    </section>
    <?php return ob_get_clean();
}

function surfside_tools_calendar_workspace_enqueue_styles() {
    if (!is_user_logged_in() || !current_user_can('upload_files') || !is_page()) return;
    $post = get_queried_object();
    if (!($post instanceof WP_Post) || trim((string) get_page_uri($post->ID), '/') !== 'dashboard/calendar') return;
    $file = 'assets/css/calendar-workspace.css';
    $version = function_exists('surfside_tools_workspace_asset_version')
        ? surfside_tools_workspace_asset_version($file) : SURFSIDE_TOOLS_VERSION;
    wp_enqueue_style('surfside-calendar-workspace', SURFSIDE_TOOLS_URL . $file, array(), $version);
}
add_action('wp_enqueue_scripts', 'surfside_tools_calendar_workspace_enqueue_styles', 20);
