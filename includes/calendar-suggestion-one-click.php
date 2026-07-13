<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Save a clearly new announcement suggestion as a one-time calendar event.
 */
function surfside_tools_save_announcement_as_event() {
    if (!is_user_logged_in() || !current_user_can('upload_files')) {
        wp_send_json_error(array('message' => 'You do not have permission to add calendar events.'), 403);
    }

    check_ajax_referer('surfside_save_announcement_event', 'nonce');

    $title = isset($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : '';
    $date = isset($_POST['date']) && function_exists('surfside_tools_calendar_date_for_input')
        ? surfside_tools_calendar_date_for_input(wp_unslash($_POST['date']))
        : '';
    $start_time = isset($_POST['start_time']) && function_exists('surfside_tools_calendar_time_for_input')
        ? surfside_tools_calendar_time_for_input(wp_unslash($_POST['start_time']))
        : '';
    $end_time = isset($_POST['end_time']) && function_exists('surfside_tools_calendar_time_for_input')
        ? surfside_tools_calendar_time_for_input(wp_unslash($_POST['end_time']))
        : '';
    $description = isset($_POST['description']) ? wp_kses_post(wp_unslash($_POST['description'])) : '';

    if ($title === '' || $date === '') {
        wp_send_json_error(array('message' => 'The suggestion needs an event title and date before it can be saved.'), 400);
    }

    // Server-side safety check in case the calendar changed after the page loaded.
    if (function_exists('surfside_tools_calendar_get_occurrences')) {
        $normalized_title = strtolower(trim(preg_replace('/[^a-z0-9]+/i', ' ', $title)));
        foreach (surfside_tools_calendar_get_occurrences($date, $date) as $occurrence) {
            $existing_title = strtolower(trim(preg_replace('/[^a-z0-9]+/i', ' ', (string) ($occurrence['title'] ?? ''))));
            $same_time = $start_time === '' || empty($occurrence['start_time']) || $start_time === $occurrence['start_time'];
            if ($existing_title === $normalized_title && $same_time) {
                wp_send_json_error(array(
                    'message' => 'A matching event is already on the calendar.',
                    'event_id' => (int) $occurrence['id'],
                    'edit_url' => add_query_arg('edit_event', (int) $occurrence['id'], home_url('/dashboard/calendar/')),
                ), 409);
            }
        }
    }

    $event_id = wp_insert_post(array(
        'post_type' => 'surfside_event',
        'post_status' => 'publish',
        'post_title' => $title,
        'post_content' => $description,
    ), true);

    if (is_wp_error($event_id)) {
        wp_send_json_error(array('message' => 'Unable to save the event: ' . $event_id->get_error_message()), 500);
    }

    update_post_meta($event_id, '_surfside_event_date', $date);
    update_post_meta($event_id, '_surfside_event_start_time', $start_time);
    update_post_meta($event_id, '_surfside_event_end_time', $end_time);
    update_post_meta($event_id, '_surfside_event_location', '');
    update_post_meta($event_id, '_surfside_event_location_name', '');
    update_post_meta($event_id, '_surfside_event_location_address', '');
    update_post_meta($event_id, '_surfside_event_location_id', 0);
    update_post_meta($event_id, '_surfside_event_all_day', 0);
    update_post_meta($event_id, '_surfside_event_featured', 0);
    update_post_meta($event_id, '_surfside_event_recurrence_type', 'none');
    update_post_meta($event_id, '_surfside_event_recurrence_interval', 1);
    update_post_meta($event_id, '_surfside_event_recurrence_weekdays', array());
    update_post_meta($event_id, '_surfside_event_recurrence_end_date', '');

    wp_send_json_success(array(
        'message' => 'Event added to the calendar.',
        'event_id' => (int) $event_id,
        'edit_url' => add_query_arg('edit_event', (int) $event_id, home_url('/dashboard/calendar/')),
    ));
}
add_action('wp_ajax_surfside_save_announcement_event', 'surfside_tools_save_announcement_as_event');

function surfside_tools_calendar_suggestion_one_click_assets() {
    if (!is_user_logged_in() || !current_user_can('upload_files')) {
        return;
    }

    $ajax_url = admin_url('admin-ajax.php');
    $nonce = wp_create_nonce('surfside_save_announcement_event');
    ?>
    <style>
        .surfside-calendar-one-click-actions {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: .65rem;
            margin-top: .75rem;
        }
        .surfside-calendar-save-event {
            padding: .6rem .9rem;
            border: 0;
            border-radius: 999px;
            background: #2f7d32;
            color: #fff;
            font: inherit;
            font-weight: 700;
            cursor: pointer;
        }
        .surfside-calendar-save-event:disabled { opacity: .7; cursor: wait; }
        .surfside-calendar-review-link {
            font-weight: 700;
            color: #0f5ca8;
            background: transparent;
            border: 0;
            padding: .35rem 0;
            cursor: pointer;
            text-decoration: underline;
        }
        .surfside-calendar-one-click-error {
            margin-top: .65rem;
            color: #9b1c1c;
            font-weight: 600;
        }
    </style>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const ajaxUrl = <?php echo wp_json_encode($ajax_url); ?>;
        const nonce = <?php echo wp_json_encode($nonce); ?>;
        const announcementForm = document.querySelector('.surfside-weekly-update-publish-form, .surfside-docx-save-form');
        if (!announcementForm) return;

        const datedAnnouncements = Array.from(announcementForm.querySelectorAll('textarea[name="announcement_items[]"]')).filter(function (textarea) {
            return /\b(January|February|March|April|May|June|July|August|September|October|November|December|Jan\.?|Feb\.?|Mar\.?|Apr\.?|Jun\.?|Jul\.?|Aug\.?|Sep\.?|Sept\.?|Oct\.?|Nov\.?|Dec\.?)\s+\d{1,2}(?:st|nd|rd|th)?/i.test(textarea.value);
        });

        function parseCard(card, index) {
            const title = card.querySelector('strong') ? card.querySelector('strong').textContent.trim() : '';
            const meta = card.querySelector('.surfside-calendar-suggestion-meta');
            const parts = meta ? meta.textContent.split('·').map(function (part) { return part.trim(); }) : [];
            const range = (parts[1] || '').match(/^(\d{2}:\d{2})(?:[–-](\d{2}:\d{2}))?$/);
            return {
                title: title,
                date: parts[0] || '',
                start_time: range ? range[1] : '',
                end_time: range && range[2] ? range[2] : '',
                description: datedAnnouncements[index] ? datedAnnouncements[index].value.trim() : ''
            };
        }

        document.querySelectorAll('.surfside-calendar-suggestion').forEach(function (card, index) {
            if (!card.querySelector('.surfside-calendar-match-new')) return;

            const existingReviewButton = card.querySelector('.surfside-calendar-suggestion-button');
            if (!existingReviewButton) return;

            const actions = document.createElement('div');
            actions.className = 'surfside-calendar-one-click-actions';

            const saveButton = document.createElement('button');
            saveButton.type = 'button';
            saveButton.className = 'surfside-calendar-save-event';
            saveButton.textContent = 'Save Announcement as Event';

            existingReviewButton.classList.add('surfside-calendar-review-link');
            existingReviewButton.textContent = 'Review Details';
            existingReviewButton.parentNode.insertBefore(actions, existingReviewButton);
            actions.appendChild(saveButton);
            actions.appendChild(existingReviewButton);

            saveButton.addEventListener('click', function () {
                const suggestion = parseCard(card, index);
                const body = new URLSearchParams({
                    action: 'surfside_save_announcement_event',
                    nonce: nonce,
                    title: suggestion.title,
                    date: suggestion.date,
                    start_time: suggestion.start_time,
                    end_time: suggestion.end_time,
                    description: suggestion.description
                });

                saveButton.disabled = true;
                saveButton.textContent = 'Saving…';
                const oldError = card.querySelector('.surfside-calendar-one-click-error');
                if (oldError) oldError.remove();

                fetch(ajaxUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                    body: body.toString()
                }).then(function (response) {
                    return response.json().then(function (json) {
                        if (!response.ok || !json.success) throw json;
                        return json;
                    });
                }).then(function (json) {
                    card.classList.add('is-added');
                    saveButton.textContent = 'Added to Calendar';
                    saveButton.disabled = true;
                    existingReviewButton.textContent = 'View/Edit Saved Event';
                    existingReviewButton.onclick = function (event) {
                        event.preventDefault();
                        window.open(json.data.edit_url, '_blank', 'noopener');
                    };

                    if (!card.querySelector('.surfside-calendar-suggestion-complete')) {
                        const status = document.createElement('span');
                        status.className = 'surfside-calendar-suggestion-complete';
                        status.textContent = '✓ Event saved. Continue reviewing the Weekly Update.';
                        card.appendChild(status);
                    }
                }).catch(function (error) {
                    saveButton.disabled = false;
                    saveButton.textContent = 'Save Announcement as Event';
                    const message = error && error.data && error.data.message ? error.data.message : 'The event could not be saved. Please review the details instead.';
                    const notice = document.createElement('div');
                    notice.className = 'surfside-calendar-one-click-error';
                    notice.textContent = message;
                    card.appendChild(notice);
                });
            });
        });
    });
    </script>
    <?php
}
add_action('wp_footer', 'surfside_tools_calendar_suggestion_one_click_assets', 45);
