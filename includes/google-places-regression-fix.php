<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Enqueue Google Places early enough for WordPress to print the script.
 *
 * The previous implementation called wp_enqueue_script() from wp_footer at
 * priority 80. WordPress prints footer scripts much earlier, so the library was
 * never included on the Weekly Update page unless Calendar Manager had already
 * enqueued it independently.
 */
function surfside_tools_google_places_regression_fix_enqueue_api() {
    if (!is_user_logged_in() || !current_user_can('upload_files')) {
        return;
    }

    $api_key = function_exists('surfside_tools_get_setting')
        ? trim((string) surfside_tools_get_setting('google_maps_api_key', ''))
        : '';

    if ($api_key === '') {
        return;
    }

    if (
        !wp_script_is('surfside-google-places-fix-api', 'registered') &&
        !wp_script_is('surfside-google-places-fix-api', 'enqueued') &&
        !wp_script_is('surfside-google-places-fix-api', 'done')
    ) {
        wp_enqueue_script(
            'surfside-google-places-fix-api',
            add_query_arg(array(
                'key' => $api_key,
                'libraries' => 'places',
                'loading' => 'async',
            ), 'https://maps.googleapis.com/maps/api/js'),
            array(),
            null,
            true
        );
    }
}
add_action('wp_enqueue_scripts', 'surfside_tools_google_places_regression_fix_enqueue_api', 20);
