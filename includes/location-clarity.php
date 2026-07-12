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
 * Save the optional meeting location value with the event.
 */
function surfside_tools_save_event_meeting_location($post_id) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (!isset($_POST['event_location_building_room'])) {
        return;
    }

    if (!current_user_can('upload_files')) {
        return;
    }

    $meeting_location = sanitize_text_field(wp_unslash($_POST['event_location_building_room']));
    update_post_meta($post_id, '_surfside_event_location_building_room', $meeting_location);
}
add_action('save_post_surfside_event', 'surfside_tools_save_event_meeting_location');

/**
 * Update the Calendar Manager location section and add Meeting Location.
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

    $output = str_replace('>Loading Google Places…</p>', '>🟢 Google Places Connected</p>', $output);
    $output = str_replace('<span>Location Name</span>', '<span>Venue</span>', $output);
    $output = str_replace('<span>Full Address</span>', '<span>Street Address</span>', $output);

    $event_id = isset($_GET['edit_event']) ? absint($_GET['edit_event']) : 0;
    $meeting_location = $event_id ? get_post_meta($event_id, '_surfside_event_location_building_room', true) : '';

    $meeting_location_field = sprintf(
        '<label class="surfside-location-building-room"><span>Meeting Location <small>(optional)</small></span><input type="text" name="event_location_building_room" class="surfside-location-building-room-input" value="%s" placeholder="e.g., Fellowship Hall, Building 4, Room 102"></label>',
        esc_attr($meeting_location)
    );

    $selected_marker = '<div class="surfside-location-selected"';
    if (strpos($output, 'name="event_location_building_room"') === false && strpos($output, $selected_marker) !== false) {
        $output = str_replace(
            $selected_marker,
            $meeting_location_field . "\n                        " . $selected_marker,
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
                if (
                    status.textContent.indexOf('Google Places is ready') !== -1 ||
                    status.textContent.indexOf('Connected to Google Places') !== -1
                ) {
                    status.textContent = '🟢 Google Places Connected';
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

/**
 * Enhance public calendar displays with meeting locations and equal week rows.
 */
function surfside_tools_calendar_public_meeting_locations() {
    if (is_admin()) {
        return;
    }

    $query = new WP_Query(array(
        'post_type' => 'surfside_event',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'fields' => 'ids',
        'no_found_rows' => true,
    ));

    $meeting_locations = array();
    foreach ($query->posts as $event_id) {
        $meeting_location = trim((string) get_post_meta($event_id, '_surfside_event_location_building_room', true));
        if ($meeting_location !== '') {
            $meeting_locations[(string) $event_id] = $meeting_location;
        }
    }
    wp_reset_postdata();
    ?>
    <style>
        @media (min-width: 901px) {
            .surfside-month-calendar-days {
                grid-auto-rows: 150px;
                align-items: stretch;
            }
            .surfside-month-calendar-day {
                min-height: 0;
                height: 150px;
                overflow: hidden;
                padding: 8px;
            }
            .surfside-month-calendar-date-number {
                margin-bottom: 5px;
            }
            .surfside-month-calendar-date-number strong {
                width: 26px;
                height: 26px;
                font-size: 14px;
            }
            .surfside-month-calendar-day-events {
                gap: 4px;
            }
            .surfside-month-calendar-item {
                min-height: 42px;
                padding: 4px 6px;
                border-radius: 7px;
            }
            .surfside-month-calendar-event-title {
                display: block;
                margin-bottom: 1px;
                overflow: hidden;
                white-space: nowrap;
                text-overflow: ellipsis;
                font-size: 13px;
                line-height: 1.15;
            }
            .surfside-month-calendar-event-button span:not(.surfside-month-calendar-event-title) {
                font-size: 11px;
                line-height: 1.2;
            }
            .surfside-month-calendar-location {
                display: none !important;
            }
        }
        .surfside-calendar-meeting-location-inline {
            font-weight: 700;
        }
        .surfside-month-calendar-more {
            margin: 2px 0 0 6px;
            color: #0b4f9c;
            font-size: 11px;
            font-weight: 900;
            line-height: 1.15;
        }
    </style>
    <script>
    (function () {
        'use strict';

        var meetingLocations = <?php echo wp_json_encode($meeting_locations); ?>;

        function eventIdFromControl(controlId) {
            var match = String(controlId || '').match(/surfside-event-detail(?:-card)?-(\d+)-/);
            return match ? match[1] : '';
        }

        function enhanceCalendarLocations() {
            document.querySelectorAll('.surfside-event-detail-button[aria-controls]').forEach(function (button) {
                var controlId = button.getAttribute('aria-controls');
                var eventId = eventIdFromControl(controlId);
                var meetingLocation = meetingLocations[eventId];

                if (meetingLocation) {
                    var listingLocation = button.querySelector('.surfside-public-calendar-location, .surfside-month-calendar-location');
                    if (listingLocation && listingLocation.textContent.indexOf(meetingLocation) === -1) {
                        var separator = document.createTextNode(' · ');
                        var detail = document.createElement('span');
                        detail.className = 'surfside-calendar-meeting-location-inline';
                        detail.textContent = meetingLocation;
                        listingLocation.appendChild(separator);
                        listingLocation.appendChild(detail);
                    }

                    var modal = controlId ? document.getElementById(controlId) : null;
                    var meta = modal ? modal.querySelector('.surfside-event-modal-meta') : null;
                    if (meta && !meta.querySelector('.surfside-event-meeting-location')) {
                        var row = document.createElement('p');
                        row.className = 'surfside-event-meeting-location';

                        var label = document.createElement('strong');
                        label.textContent = 'Meeting Location';

                        var value = document.createElement('span');
                        value.textContent = meetingLocation;

                        row.appendChild(label);
                        row.appendChild(value);
                        meta.appendChild(row);
                    }
                }
            });
        }

        function limitMonthCalendarEvents() {
            document.querySelectorAll('.surfside-month-calendar-day-events').forEach(function (container) {
                var events = Array.prototype.slice.call(container.querySelectorAll(':scope > .surfside-month-calendar-item'));
                var existingMore = container.querySelector(':scope > .surfside-month-calendar-more');

                if (existingMore) {
                    existingMore.remove();
                }

                events.forEach(function (event, index) {
                    event.hidden = index >= 2;
                });

                if (events.length > 2) {
                    var more = document.createElement('div');
                    more.className = 'surfside-month-calendar-more';
                    more.textContent = '+' + (events.length - 2) + ' more';
                    container.appendChild(more);
                }
            });
        }

        function initializeCalendarPolish() {
            enhanceCalendarLocations();
            limitMonthCalendarEvents();
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initializeCalendarPolish);
        } else {
            initializeCalendarPolish();
        }
    })();
    </script>
    <?php
}
add_action('wp_footer', 'surfside_tools_calendar_public_meeting_locations', 98);
