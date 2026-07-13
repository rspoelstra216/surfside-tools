<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Small workflow refinements for the staff Calendar Manager.
 */
function surfside_tools_calendar_manager_refinement_assets() {
    if (!is_user_logged_in() || !current_user_can('upload_files') || !function_exists('surfside_tools_calendar_get_all_events')) {
        return;
    }

    $today = current_time('Y-m-d');
    $range_end = date('Y-m-d', strtotime($today . ' +5 years'));
    $search = isset($_GET['event_search']) ? strtolower(trim(sanitize_text_field(wp_unslash($_GET['event_search'])))) : '';
    $event_status = array();
    $active_count = 0;

    foreach (surfside_tools_calendar_get_all_events() as $event) {
        $occurrences = surfside_tools_calendar_event_occurrences($event, $today, $range_end);
        $has_future = !empty($occurrences);
        $range_label = '';

        if ($has_future) {
            $haystack = strtolower(trim(
                ($event['title'] ?? '') . ' ' .
                ($event['location_name'] ?? '') . ' ' .
                ($event['location_address'] ?? '')
            ));
            if ($search === '' || strpos($haystack, $search) !== false) {
                $active_count++;
            }
        }

        if (
            $has_future &&
            !empty($event['recurrence_end_date']) &&
            ($event['recurrence_type'] ?? 'none') !== 'none'
        ) {
            $start_ts = strtotime(($event['date'] ?? '') . ' 12:00:00');
            $end_ts = strtotime($event['recurrence_end_date'] . ' 12:00:00');

            if ($start_ts && $end_ts) {
                if (date('Y', $start_ts) !== date('Y', $end_ts)) {
                    $range_label = date_i18n('F j, Y', $start_ts) . '–' . date_i18n('F j, Y', $end_ts);
                } elseif (date('m', $start_ts) !== date('m', $end_ts)) {
                    $range_label = date_i18n('F j', $start_ts) . '–' . date_i18n('F j, Y', $end_ts);
                } else {
                    $range_label = date_i18n('F j', $start_ts) . '–' . date_i18n('j, Y', $end_ts);
                }
            }
        }

        $event_status[(string) (int) $event['id']] = array(
            'has_future' => $has_future,
            'range_label' => $range_label,
        );
    }
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const eventStatus = <?php echo wp_json_encode($event_status); ?>;
        const activeCount = <?php echo (int) $active_count; ?>;

        // Use a friendlier prompt when a room/building was found but the main venue was not.
        document.querySelectorAll('.surfside-calendar-location-required').forEach(function (box) {
            const label = box.querySelector('label');
            if (label) {
                Array.from(label.childNodes).forEach(function (node) {
                    if (node.nodeType === Node.TEXT_NODE && node.nodeValue.trim()) {
                        node.nodeValue = 'Where is this event being held? ';
                    }
                });
            }
            const help = box.querySelector('small');
            const card = box.closest('.surfside-calendar-suggestion');
            const meeting = card ? (card.dataset.surfsideMeetingLocation || '') : '';
            if (help) {
                help.textContent = meeting
                    ? 'We found ' + meeting + ', but still need the church, campus, or venue.'
                    : 'Enter the church, campus, or venue before saving.';
            }
        });

        const eventList = document.querySelector('.surfside-calendar-event-list');
        if (!eventList) {
            return;
        }

        eventList.querySelectorAll('.surfside-calendar-event').forEach(function (card) {
            const editLink = card.querySelector('.surfside-calendar-event-actions a[href*="edit_event="]');
            if (!editLink) return;

            const match = editLink.href.match(/[?&]edit_event=(\d+)/);
            const status = match ? eventStatus[match[1]] : null;
            if (!status) return;

            if (!status.has_future) {
                card.remove();
                return;
            }

            if (status.range_label) {
                const dateLine = card.querySelector(':scope > div:first-child > p');
                const dateStrong = dateLine ? dateLine.querySelector('strong') : null;
                if (dateStrong) {
                    dateStrong.textContent = status.range_label;
                }
            }
        });

        const countPill = document.querySelector('.surfside-calendar-status-pill');
        if (countPill) {
            countPill.textContent = activeCount + ' active event' + (activeCount === 1 ? '' : 's');
        }

        if (!eventList.querySelector('.surfside-calendar-event')) {
            const empty = document.createElement('p');
            empty.className = 'surfside-staff-muted';
            empty.textContent = 'No active events were found.';
            eventList.replaceWith(empty);
        }
    });
    </script>
    <?php
}
add_action('wp_footer', 'surfside_tools_calendar_manager_refinement_assets', 50);
