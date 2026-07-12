<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Calendar location clarity enhancements.
 *
 * Keeps the location-specific UI and persistence changes isolated from the
 * larger calendar manager module.
 */

/**
 * Save the optional building or room value with the event.
 */
function surfside_tools_save_event_building_room($post_id) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (!isset($_POST['event_location_building_room'])) {
        return;
    }

    if (!current_user_can('upload_files')) {
        return;
    }

    $building_room = sanitize_text_field(wp_unslash($_POST['event_location_building_room']));
    update_post_meta($post_id, '_surfside_event_location_building_room', $building_room);
}
add_action('save_post_surfside_event', 'surfside_tools_save_event_building_room');

/**
 * Update the Calendar Manager location section and add Building / Room.
 */
function surfside_tools_filter_calendar_location_clarity($output, $tag) {
    if ($tag !== 'surfside_tools_calendar_manager' || !is_string($output)) {
        return $output;
    }

    $output = str_replace(
        'placeholder="Start typing a place, church, restaurant, or address..."',
        'placeholder="Search for a church, business, or address..."',
        $output
    );

    $output = str_replace(
        'Choose a Google suggestion to fill the address automatically. For internal locations such as Building 4, enter the details manually below.',
        'Search Google for the event location. If needed, specify the building, room, or meeting location below.',
        $output
    );

    $output = str_replace('>Loading Google Places…</p>', '>Connected to Google Places</p>', $output);
    $output = str_replace('<span>Location Name</span>', '<span>Venue</span>', $output);
    $output = str_replace('<span>Full Address</span>', '<span>Street Address</span>', $output);

    $event_id = isset($_GET['edit_event']) ? absint($_GET['edit_event']) : 0;
    $building_room = $event_id ? get_post_meta($event_id, '_surfside_event_location_building_room', true) : '';

    $building_room_field = sprintf(
        '<label class="surfside-location-building-room"><span>Building / Room <small>(optional)</small></span><input type="text" name="event_location_building_room" class="surfside-location-building-room-input" value="%s" placeholder="e.g., Fellowship Hall, Building 4, Room 102"></label>',
        esc_attr($building_room)
    );

    $location_fields_end = '</div>\n                        <div class="surfside-location-selected"';
    if (strpos($output, $location_fields_end) !== false) {
        $output = str_replace(
            $location_fields_end,
            '</div>' . $building_room_field . '\n                        <div class="surfside-location-selected"',
            $output
        );
    }

    return $output;
}
add_filter('do_shortcode_tag', 'surfside_tools_filter_calendar_location_clarity', 10, 2);

/**
 * Keep the ready-state message concise after Google Places initializes.
 */
function surfside_tools_calendar_location_status_script() {
    if (!is_user_logged_in()) {
        return;
    }
    ?>
    <script>
    (function () {
        'use strict';

        function normalizeGooglePlacesStatus() {
            document.querySelectorAll('[data-surfside-google-status]').forEach(function (status) {
                if (status.textContent.indexOf('Google Places is ready') !== -1) {
                    status.textContent = 'Connected to Google Places';
                }
            });
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', normalizeGooglePlacesStatus);
        } else {
            normalizeGooglePlacesStatus();
        }

        var observer = new MutationObserver(normalizeGooglePlacesStatus);
        observer.observe(document.documentElement, {
            childList: true,
            subtree: true,
            characterData: true
        });
    })();
    </script>
    <?php
}
add_action('wp_footer', 'surfside_tools_calendar_location_status_script', 99);
add_action('admin_footer', 'surfside_tools_calendar_location_status_script', 99);
