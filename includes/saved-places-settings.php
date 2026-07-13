<?php

if (!defined('ABSPATH')) {
    exit;
}

function surfside_tools_normalize_place_name($name) {
    $name = strtolower(trim(wp_strip_all_tags((string) $name)));
    return preg_replace('/[^a-z0-9]+/', ' ', $name);
}

function surfside_tools_get_hidden_place_names() {
    $hidden = get_option('surfside_tools_hidden_place_names', array());
    return is_array($hidden) ? array_values(array_filter(array_map('sanitize_text_field', $hidden))) : array();
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

    $post = $place_id ? get_post($place_id) : null;
    if (!$post || $post->post_type !== 'surfside_location') {
        surfside_tools_saved_places_redirect('That saved place could not be found.');
    }

    wp_trash_post($place_id);
    surfside_tools_saved_places_redirect('Saved place removed. Existing calendar events were not changed.');
}
add_action('admin_post_surfside_delete_saved_place', 'surfside_tools_delete_saved_place');

function surfside_tools_hide_calendar_place() {
    if (!current_user_can('manage_options')) {
        wp_die('You do not have permission to manage saved places.');
    }

    check_admin_referer('surfside_hide_calendar_place');
    $name = isset($_POST['place_name']) ? sanitize_text_field(wp_unslash($_POST['place_name'])) : '';
    $normalized = surfside_tools_normalize_place_name($name);
    if ($normalized === '') {
        surfside_tools_saved_places_redirect('That place name was empty.');
    }

    $hidden = surfside_tools_get_hidden_place_names();
    if (!in_array($normalized, $hidden, true)) {
        $hidden[] = $normalized;
        update_option('surfside_tools_hidden_place_names', array_values($hidden), false);
    }
    surfside_tools_saved_places_redirect('Place removed from location suggestions. Existing calendar events were not changed.');
}
add_action('admin_post_surfside_hide_calendar_place', 'surfside_tools_hide_calendar_place');

function surfside_tools_restore_calendar_place() {
    if (!current_user_can('manage_options')) {
        wp_die('You do not have permission to manage saved places.');
    }

    check_admin_referer('surfside_restore_calendar_place');
    $name = isset($_POST['place_name']) ? sanitize_text_field(wp_unslash($_POST['place_name'])) : '';
    $hidden = array_values(array_diff(surfside_tools_get_hidden_place_names(), array($name)));
    update_option('surfside_tools_hidden_place_names', $hidden, false);
    surfside_tools_saved_places_redirect('Place restored to location suggestions.');
}
add_action('admin_post_surfside_restore_calendar_place', 'surfside_tools_restore_calendar_place');

function surfside_tools_saved_places_settings_card() {
    if (!current_user_can('manage_options')) {
        return;
    }

    $saved = function_exists('surfside_tools_calendar_get_saved_locations')
        ? surfside_tools_calendar_get_saved_locations()
        : array();
    $saved_names = array();
    foreach ($saved as $place) {
        $saved_names[surfside_tools_normalize_place_name($place['name'] ?? '')] = true;
    }

    $calendar_places = array();
    if (function_exists('surfside_tools_calendar_get_all_events')) {
        foreach (surfside_tools_calendar_get_all_events() as $event) {
            $name = trim((string) ($event['location_name'] ?? ''));
            $key = surfside_tools_normalize_place_name($name);
            if ($name === '' || isset($saved_names[$key])) {
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

function surfside_tools_hide_removed_place_suggestions() {
    if (is_admin()) return;
    $hidden = surfside_tools_get_hidden_place_names();
    if (!$hidden) return;
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const hidden = <?php echo wp_json_encode($hidden); ?>;
        const normalize = value => String(value || '').toLowerCase().replace(/[^a-z0-9]+/g, ' ').trim();
        const cleanMenus = function () {
            document.querySelectorAll('.surfside-known-location-option').forEach(function (option) {
                const name = option.querySelector('strong');
                if (name && hidden.includes(normalize(name.textContent))) option.remove();
            });
        };
        cleanMenus();
        new MutationObserver(cleanMenus).observe(document.body, { childList: true, subtree: true });
    });
    </script>
    <?php
}
add_action('wp_footer', 'surfside_tools_hide_removed_place_suggestions', 60);
