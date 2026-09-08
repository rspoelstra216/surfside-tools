<?php

if (!defined('ABSPATH')) {
    exit;
}

function surfside_tools_normalize_place_name($name) {
    $name = strtolower(trim(wp_strip_all_tags((string) $name)));
    return trim((string) preg_replace('/[^a-z0-9]+/', ' ', $name));
}

function surfside_tools_get_hidden_place_names() {
    $hidden = get_option('surfside_tools_hidden_place_names', array());
    return is_array($hidden) ? array_values(array_filter(array_map('sanitize_text_field', $hidden))) : array();
}

function surfside_tools_place_name_is_hidden($name) {
    $normalized = surfside_tools_normalize_place_name($name);
    return $normalized !== '' && in_array($normalized, surfside_tools_get_hidden_place_names(), true);
}

/**
 * Canonical Saved Places data used by the front-end Integrations page and WP-admin fallback.
 */
function surfside_tools_saved_places_data() {
    $saved = function_exists('surfside_tools_calendar_get_saved_locations')
        ? surfside_tools_calendar_get_saved_locations()
        : array();

    $saved_names = array();
    foreach ($saved as $place) {
        $key = surfside_tools_normalize_place_name($place['name'] ?? '');
        if ($key !== '') {
            $saved_names[$key] = true;
        }
    }

    $calendar_places = array();
    if (function_exists('surfside_tools_calendar_get_all_events')) {
        foreach (surfside_tools_calendar_get_all_events() as $event) {
            $name = trim((string) ($event['location_name'] ?? ''));
            $key = surfside_tools_normalize_place_name($name);
            if ($name === '' || $key === '' || isset($saved_names[$key])) {
                continue;
            }
            if (!isset($calendar_places[$key])) {
                $calendar_places[$key] = array(
                    'name' => $name,
                    'address' => trim((string) ($event['location_address'] ?? '')),
                );
            }
        }
    }

    return array($saved, $calendar_places);
}

/**
 * Build the known-location payload used by Weekly Update, excluding hidden names server-side.
 */
function surfside_tools_saved_place_suggestion_locations() {
    $known = array();
    $hidden = array_fill_keys(surfside_tools_get_hidden_place_names(), true);

    if (function_exists('surfside_tools_calendar_get_saved_locations')) {
        foreach (surfside_tools_calendar_get_saved_locations() as $location) {
            $name = trim((string) ($location['name'] ?? ''));
            $normalized = surfside_tools_normalize_place_name($name);
            if ($name === '' || $normalized === '' || isset($hidden[$normalized])) {
                continue;
            }
            $known[$normalized] = array(
                'name' => $name,
                'address' => trim((string) ($location['address'] ?? '')),
                'id' => absint($location['id'] ?? 0),
                'place_id' => '',
                'lat' => '',
                'lng' => '',
                'maps_url' => '',
                'source' => 'Saved location',
            );
        }
    }

    if (function_exists('surfside_tools_calendar_get_all_events')) {
        foreach (surfside_tools_calendar_get_all_events() as $event) {
            $name = trim((string) ($event['location_name'] ?? ($event['location'] ?? '')));
            $normalized = surfside_tools_normalize_place_name($name);
            if ($name === '' || $normalized === '' || isset($hidden[$normalized])) {
                continue;
            }

            $candidate = array(
                'name' => $name,
                'address' => trim((string) ($event['location_address'] ?? '')),
                'id' => absint($event['location_id'] ?? 0),
                'place_id' => trim((string) ($event['location_place_id'] ?? '')),
                'lat' => trim((string) ($event['location_lat'] ?? '')),
                'lng' => trim((string) ($event['location_lng'] ?? '')),
                'maps_url' => trim((string) ($event['location_maps_url'] ?? '')),
                'source' => 'Used on calendar',
            );

            if (!isset($known[$normalized])) {
                $known[$normalized] = $candidate;
                continue;
            }

            foreach (array('address', 'id', 'place_id', 'lat', 'lng', 'maps_url') as $field) {
                if (empty($known[$normalized][$field]) && !empty($candidate[$field])) {
                    $known[$normalized][$field] = $candidate[$field];
                }
            }
        }
    }

    $locations = array_values($known);
    usort($locations, function ($a, $b) {
        return strcasecmp($a['name'], $b['name']);
    });
    return $locations;
}

function surfside_tools_delete_saved_place_record($place_id) {
    $place_id = absint($place_id);
    $post = $place_id ? get_post($place_id) : null;
    if (!$post || $post->post_type !== 'surfside_location') {
        return new WP_Error('surfside_saved_place_missing', 'That saved place could not be found.');
    }

    wp_trash_post($place_id);
    return 'Saved place removed. Existing calendar events were not changed.';
}

function surfside_tools_hide_calendar_place_name($name) {
    $normalized = surfside_tools_normalize_place_name($name);
    if ($normalized === '') {
        return new WP_Error('surfside_saved_place_empty', 'That place name was empty.');
    }

    $hidden = surfside_tools_get_hidden_place_names();
    if (!in_array($normalized, $hidden, true)) {
        $hidden[] = $normalized;
        update_option('surfside_tools_hidden_place_names', array_values($hidden), false);
    }
    return 'Place removed from future suggestions. Existing events were not changed.';
}

function surfside_tools_restore_calendar_place_name($name) {
    $normalized = surfside_tools_normalize_place_name($name);
    if ($normalized === '') {
        return new WP_Error('surfside_saved_place_empty', 'That place name was empty.');
    }

    $hidden = array_values(array_diff(surfside_tools_get_hidden_place_names(), array($normalized)));
    update_option('surfside_tools_hidden_place_names', $hidden, false);
    return 'Place restored to location suggestions.';
}

function surfside_tools_saved_places_redirect($message) {
    wp_safe_redirect(add_query_arg(array(
        'page' => 'surfside-tools-settings',
        'surfside_places_notice' => rawurlencode($message),
    ), admin_url('admin.php')));
    exit;
}

function surfside_tools_delete_saved_place() {
    if (!current_user_can('manage_options')) {
        wp_die('You do not have permission to manage saved places.');
    }

    $place_id = isset($_POST['place_id']) ? absint($_POST['place_id']) : 0;
    check_admin_referer('surfside_delete_saved_place_' . $place_id);
    $result = surfside_tools_delete_saved_place_record($place_id);
    surfside_tools_saved_places_redirect(is_wp_error($result) ? $result->get_error_message() : $result);
}
add_action('admin_post_surfside_delete_saved_place', 'surfside_tools_delete_saved_place');

function surfside_tools_hide_calendar_place() {
    if (!current_user_can('manage_options')) {
        wp_die('You do not have permission to manage saved places.');
    }

    check_admin_referer('surfside_hide_calendar_place');
    $name = isset($_POST['place_name']) ? sanitize_text_field(wp_unslash($_POST['place_name'])) : '';
    $result = surfside_tools_hide_calendar_place_name($name);
    surfside_tools_saved_places_redirect(is_wp_error($result) ? $result->get_error_message() : $result);
}
add_action('admin_post_surfside_hide_calendar_place', 'surfside_tools_hide_calendar_place');

function surfside_tools_restore_calendar_place() {
    if (!current_user_can('manage_options')) {
        wp_die('You do not have permission to manage saved places.');
    }

    check_admin_referer('surfside_restore_calendar_place');
    $name = isset($_POST['place_name']) ? sanitize_text_field(wp_unslash($_POST['place_name'])) : '';
    $result = surfside_tools_restore_calendar_place_name($name);
    surfside_tools_saved_places_redirect(is_wp_error($result) ? $result->get_error_message() : $result);
}
add_action('admin_post_surfside_restore_calendar_place', 'surfside_tools_restore_calendar_place');

function surfside_tools_saved_places_settings_card() {
    if (!current_user_can('manage_options')) {
        return;
    }

    list($saved, $calendar_places) = surfside_tools_saved_places_data();
    $hidden = surfside_tools_get_hidden_place_names();
    $notice = isset($_GET['surfside_places_notice']) ? sanitize_text_field(wp_unslash($_GET['surfside_places_notice'])) : '';
    ob_start();
    ?>
    <div id="surfside-saved-places-card" class="surfside-admin-card" style="margin-bottom:18px;">
        <h2>Saved Places</h2>
        <p class="surfside-admin-muted">Manage locations offered in Calendar Manager and Weekly Update suggestions. Removing a place does not change existing events.</p>
        <?php if ($notice) : ?><div class="notice notice-success inline"><p><?php echo esc_html($notice); ?></p></div><?php endif; ?>

        <?php if (!$saved && !$calendar_places) : ?>
            <p>No saved or previously used places were found.</p>
        <?php else : ?>
            <table class="widefat striped" style="margin-top:14px;">
                <thead><tr><th>Place</th><th>Address</th><th>Source</th><th style="width:130px;">Action</th></tr></thead>
                <tbody>
                <?php foreach ($saved as $place) : ?>
                    <tr>
                        <td><strong><?php echo esc_html($place['name'] ?? ''); ?></strong></td>
                        <td><?php echo esc_html($place['address'] ?? ''); ?></td>
                        <td>Saved place</td>
                        <td>
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('Remove this saved place? Existing events will keep their current location.');">
                                <input type="hidden" name="action" value="surfside_delete_saved_place">
                                <input type="hidden" name="place_id" value="<?php echo (int) ($place['id'] ?? 0); ?>">
                                <?php wp_nonce_field('surfside_delete_saved_place_' . (int) ($place['id'] ?? 0)); ?>
                                <button class="button button-link-delete" type="submit">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php foreach ($calendar_places as $key => $place) : if (in_array($key, $hidden, true)) continue; ?>
                    <tr>
                        <td><strong><?php echo esc_html($place['name']); ?></strong></td>
                        <td><?php echo esc_html($place['address']); ?></td>
                        <td>Previously used on calendar</td>
                        <td>
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                <input type="hidden" name="action" value="surfside_hide_calendar_place">
                                <input type="hidden" name="place_name" value="<?php echo esc_attr($place['name']); ?>">
                                <?php wp_nonce_field('surfside_hide_calendar_place'); ?>
                                <button class="button button-link-delete" type="submit">Remove</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <?php if ($hidden) : ?>
            <details style="margin-top:16px;"><summary><strong>Removed suggestions (<?php echo count($hidden); ?>)</strong></summary>
                <div style="margin-top:10px;display:flex;flex-wrap:wrap;gap:8px;">
                    <?php foreach ($hidden as $name) : ?>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                            <input type="hidden" name="action" value="surfside_restore_calendar_place">
                            <input type="hidden" name="place_name" value="<?php echo esc_attr($name); ?>">
                            <?php wp_nonce_field('surfside_restore_calendar_place'); ?>
                            <button class="button" type="submit">Restore <?php echo esc_html(ucwords($name)); ?></button>
                        </form>
                    <?php endforeach; ?>
                </div>
            </details>
        <?php endif; ?>
    </div>
    <?php
    $html = ob_get_clean();
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const settingsForm = document.querySelector('form[action="options.php"]');
        if (!settingsForm) return;
        const holder = document.createElement('div');
        holder.innerHTML = <?php echo wp_json_encode($html); ?>;
        const card = holder.firstElementChild;
        const submit = settingsForm.querySelector('.submit');
        if (card && submit) submit.insertAdjacentElement('beforebegin', card);
    });
    </script>
    <?php
}
add_action('admin_footer', function () {
    if (isset($_GET['page']) && $_GET['page'] === 'surfside-tools-settings') {
        surfside_tools_saved_places_settings_card();
    }
}, 30);
