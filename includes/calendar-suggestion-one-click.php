<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Save a clearly new announcement suggestion as a calendar event, including
 * recurrence when the announcement uses a supported, unambiguous phrase.
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

    $recurrence_type = isset($_POST['recurrence_type']) ? sanitize_key(wp_unslash($_POST['recurrence_type'])) : 'none';
    if (!in_array($recurrence_type, array('none', 'daily', 'weekly', 'monthly_date', 'monthly_weekday'), true)) {
        $recurrence_type = 'none';
    }
    $recurrence_interval = max(1, isset($_POST['recurrence_interval']) ? absint($_POST['recurrence_interval']) : 1);
    $recurrence_weekdays_raw = isset($_POST['recurrence_weekdays']) ? sanitize_text_field(wp_unslash($_POST['recurrence_weekdays'])) : '';
    $recurrence_weekdays = array_values(array_intersect(array_map('absint', array_filter(explode(',', $recurrence_weekdays_raw))), range(1, 7)));
    $recurrence_day_of_month = isset($_POST['recurrence_day_of_month']) ? min(31, max(0, absint($_POST['recurrence_day_of_month']))) : 0;
    $recurrence_week_of_month = isset($_POST['recurrence_week_of_month']) ? min(5, max(1, absint($_POST['recurrence_week_of_month']))) : 1;
    $recurrence_weekday = isset($_POST['recurrence_weekday']) ? min(7, max(1, absint($_POST['recurrence_weekday']))) : 1;
    $recurrence_end_date = isset($_POST['recurrence_end_date']) && function_exists('surfside_tools_calendar_date_for_input')
        ? surfside_tools_calendar_date_for_input(wp_unslash($_POST['recurrence_end_date']))
        : '';

    if ($title === '' || $date === '') {
        wp_send_json_error(array('message' => 'The suggestion needs an event title and date before it can be saved.'), 400);
    }

    if ($recurrence_end_date !== '' && $recurrence_end_date < $date) {
        wp_send_json_error(array('message' => 'The detected recurrence end date occurs before the event start date.'), 400);
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
    update_post_meta($event_id, '_surfside_event_recurrence_type', $recurrence_type);
    update_post_meta($event_id, '_surfside_event_recurrence_interval', $recurrence_interval);
    update_post_meta($event_id, '_surfside_event_recurrence_weekdays', $recurrence_weekdays);
    update_post_meta($event_id, '_surfside_event_recurrence_day_of_month', $recurrence_day_of_month);
    update_post_meta($event_id, '_surfside_event_recurrence_week_of_month', $recurrence_week_of_month);
    update_post_meta($event_id, '_surfside_event_recurrence_weekday', $recurrence_weekday);
    update_post_meta($event_id, '_surfside_event_recurrence_end_date', $recurrence_end_date);

    wp_send_json_success(array(
        'message' => $recurrence_type === 'none' ? 'Event added to the calendar.' : 'Recurring event added to the calendar.',
        'event_id' => (int) $event_id,
        'edit_url' => add_query_arg('edit_event', (int) $event_id, home_url('/dashboard/calendar/')),
        'recurrence_type' => $recurrence_type,
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
        .surfside-calendar-save-event:disabled { opacity: .78; cursor: default; }
        .surfside-calendar-review-link {
            font-weight: 700;
            color: #0f5ca8;
            background: transparent;
            border: 0;
            padding: .35rem 0;
            cursor: pointer;
            text-decoration: underline;
        }
        .surfside-calendar-suggestion.is-added .surfside-calendar-review-link {
            display: inline-block;
            padding: .55rem .8rem;
            border: 1px solid #155f8d;
            border-radius: 999px;
            background: #fff;
            color: #155f8d !important;
            text-decoration: none;
        }
        .surfside-calendar-recurrence-detected {
            margin-top: .65rem;
            padding: .55rem .7rem;
            border-radius: 8px;
            background: #eef4ff;
            color: #173f72;
            font-size: .92rem;
            font-weight: 700;
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

        const weekdayMap = { monday:1, tuesday:2, wednesday:3, thursday:4, friday:5, saturday:6, sunday:7 };
        const weekdayNames = { 1:'Monday', 2:'Tuesday', 3:'Wednesday', 4:'Thursday', 5:'Friday', 6:'Saturday', 7:'Sunday' };
        const monthMap = { january:1, february:2, march:3, april:4, may:5, june:6, july:7, august:8, september:9, october:10, november:11, december:12, jan:1, feb:2, mar:3, apr:4, jun:6, jul:7, aug:8, sep:9, sept:9, oct:10, nov:11, dec:12 };

        function pad(value) { return String(value).padStart(2, '0'); }

        function isoDate(year, month, day) {
            const date = new Date(year, month - 1, day, 12, 0, 0);
            if (date.getMonth() !== month - 1 || date.getDate() !== day) return '';
            return date.getFullYear() + '-' + pad(date.getMonth() + 1) + '-' + pad(date.getDate());
        }

        function jsDateToWeekday(dateString) {
            const day = new Date(dateString + 'T12:00:00').getDay();
            return day === 0 ? 7 : day;
        }

        function detectRangeEnd(text, startDate) {
            const sameMonth = text.match(/\b(January|February|March|April|May|June|July|August|September|October|November|December|Jan\.?|Feb\.?|Mar\.?|Apr\.?|Jun\.?|Jul\.?|Aug\.?|Sep\.?|Sept\.?|Oct\.?|Nov\.?|Dec\.?)\s+(\d{1,2})(?:st|nd|rd|th)?\s*[–—-]\s*(?:\1\s*)?(\d{1,2})(?:st|nd|rd|th)?(?:,?\s*(20\d{2}))?/i);
            if (sameMonth) {
                const key = sameMonth[1].replace('.', '').toLowerCase();
                const startYear = parseInt(startDate.slice(0, 4), 10);
                return isoDate(parseInt(sameMonth[4] || startYear, 10), monthMap[key], parseInt(sameMonth[3], 10));
            }

            const twoMonths = text.match(/\b(January|February|March|April|May|June|July|August|September|October|November|December|Jan\.?|Feb\.?|Mar\.?|Apr\.?|Jun\.?|Jul\.?|Aug\.?|Sep\.?|Sept\.?|Oct\.?|Nov\.?|Dec\.?)\s+(\d{1,2})(?:st|nd|rd|th)?\s*[–—-]\s*(January|February|March|April|May|June|July|August|September|October|November|December|Jan\.?|Feb\.?|Mar\.?|Apr\.?|Jun\.?|Jul\.?|Aug\.?|Sep\.?|Sept\.?|Oct\.?|Nov\.?|Dec\.?)\s+(\d{1,2})(?:st|nd|rd|th)?(?:,?\s*(20\d{2}))?/i);
            if (twoMonths) {
                const endKey = twoMonths[3].replace('.', '').toLowerCase();
                let year = parseInt(twoMonths[5] || startDate.slice(0, 4), 10);
                const endMonth = monthMap[endKey];
                if (!twoMonths[5] && endMonth < parseInt(startDate.slice(5, 7), 10)) year += 1;
                return isoDate(year, endMonth, parseInt(twoMonths[4], 10));
            }
            return '';
        }

        function detectRecurrence(text, startDate) {
            const lower = text.toLowerCase().replace(/[–—]/g, '-');
            const result = {
                type: 'none', interval: 1, weekdays: [], day_of_month: 0,
                week_of_month: 1, weekday: 1, end_date: '', label: 'One-time event'
            };

            const rangeEnd = detectRangeEnd(text, startDate);
            if (rangeEnd && rangeEnd > startDate) {
                result.type = 'daily';
                result.end_date = rangeEnd;
                result.label = 'Repeats daily through ' + rangeEnd;
                return result;
            }

            if (/\b(every\s+weekday|weekdays|monday\s*(?:through|thru|to|-)\s*friday)\b/i.test(lower)) {
                result.type = 'daily';
                result.weekdays = [1,2,3,4,5];
                result.label = 'Repeats weekdays';
                return result;
            }

            const ordinalMonthly = lower.match(/\b(first|second|third|fourth|fifth)\s+(monday|tuesday|wednesday|thursday|friday|saturday|sunday)(?:\s+of\s+(?:each|every)\s+month|\s+monthly)\b/i);
            if (ordinalMonthly) {
                const ordinalMap = { first:1, second:2, third:3, fourth:4, fifth:5 };
                result.type = 'monthly_weekday';
                result.week_of_month = ordinalMap[ordinalMonthly[1]];
                result.weekday = weekdayMap[ordinalMonthly[2]];
                result.label = 'Repeats monthly on the ' + ordinalMonthly[1] + ' ' + weekdayNames[result.weekday];
                return result;
            }

            const namedWeekly = lower.match(/\b(?:every|each)\s+(monday|tuesday|wednesday|thursday|friday|saturday|sunday)s?\b/i);
            if (namedWeekly) {
                result.type = 'weekly';
                result.weekday = weekdayMap[namedWeekly[1]];
                result.weekdays = [result.weekday];
                result.label = 'Repeats weekly on ' + weekdayNames[result.weekday];
                return result;
            }

            if (/\b(?:every\s+day|daily)\b/i.test(lower)) {
                result.type = 'daily';
                result.label = 'Repeats daily';
                return result;
            }

            if (/\b(?:every\s+week|weekly)\b/i.test(lower)) {
                result.type = 'weekly';
                result.weekday = jsDateToWeekday(startDate);
                result.weekdays = [result.weekday];
                result.label = 'Repeats weekly on ' + weekdayNames[result.weekday];
                return result;
            }

            if (/\b(?:every\s+month|monthly)\b/i.test(lower)) {
                result.type = 'monthly_date';
                result.day_of_month = parseInt(startDate.slice(8, 10), 10);
                result.label = 'Repeats monthly on day ' + result.day_of_month;
                return result;
            }

            return result;
        }

        function parseCard(card, index) {
            const title = card.querySelector('strong') ? card.querySelector('strong').textContent.trim() : '';
            const meta = card.querySelector('.surfside-calendar-suggestion-meta');
            const parts = meta ? meta.textContent.split('·').map(function (part) { return part.trim(); }) : [];
            const range = (parts[1] || '').match(/^(\d{2}:\d{2})(?:[–-](\d{2}:\d{2}))?$/);
            const description = datedAnnouncements[index] ? datedAnnouncements[index].value.trim() : '';
            const date = parts[0] || '';
            return {
                title: title,
                date: date,
                start_time: range ? range[1] : '',
                end_time: range && range[2] ? range[2] : '',
                description: description,
                recurrence: detectRecurrence(description, date)
            };
        }

        document.querySelectorAll('.surfside-calendar-suggestion').forEach(function (card, index) {
            if (!card.querySelector('.surfside-calendar-match-new')) return;

            const existingReviewButton = card.querySelector('.surfside-calendar-suggestion-button');
            if (!existingReviewButton) return;

            const suggestion = parseCard(card, index);
            if (suggestion.recurrence.type !== 'none') {
                const recurrenceNotice = document.createElement('div');
                recurrenceNotice.className = 'surfside-calendar-recurrence-detected';
                recurrenceNotice.textContent = '↻ Recurrence detected: ' + suggestion.recurrence.label;
                const matchBox = card.querySelector('.surfside-calendar-match');
                if (matchBox) matchBox.insertAdjacentElement('afterend', recurrenceNotice);
            }

            const actions = document.createElement('div');
            actions.className = 'surfside-calendar-one-click-actions';

            const saveButton = document.createElement('button');
            saveButton.type = 'button';
            saveButton.className = 'surfside-calendar-save-event';
            saveButton.textContent = suggestion.recurrence.type === 'none' ? 'Save Announcement as Event' : 'Save Recurring Event';

            existingReviewButton.classList.add('surfside-calendar-review-link');
            existingReviewButton.textContent = 'Review Details';
            existingReviewButton.parentNode.insertBefore(actions, existingReviewButton);
            actions.appendChild(saveButton);
            actions.appendChild(existingReviewButton);

            saveButton.addEventListener('click', function () {
                const recurrence = suggestion.recurrence;
                const body = new URLSearchParams({
                    action: 'surfside_save_announcement_event',
                    nonce: nonce,
                    title: suggestion.title,
                    date: suggestion.date,
                    start_time: suggestion.start_time,
                    end_time: suggestion.end_time,
                    description: suggestion.description,
                    recurrence_type: recurrence.type,
                    recurrence_interval: String(recurrence.interval),
                    recurrence_weekdays: recurrence.weekdays.join(','),
                    recurrence_day_of_month: String(recurrence.day_of_month),
                    recurrence_week_of_month: String(recurrence.week_of_month),
                    recurrence_weekday: String(recurrence.weekday),
                    recurrence_end_date: recurrence.end_date
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
                    saveButton.textContent = json.data.recurrence_type === 'none' ? 'Added to Calendar' : 'Recurring Event Added';
                    saveButton.disabled = true;
                    existingReviewButton.textContent = 'View/Edit Saved Event';
                    existingReviewButton.onclick = function (event) {
                        event.preventDefault();
                        window.open(json.data.edit_url, '_blank', 'noopener');
                    };

                    if (!card.querySelector('.surfside-calendar-suggestion-complete')) {
                        const status = document.createElement('span');
                        status.className = 'surfside-calendar-suggestion-complete';
                        status.textContent = json.data.recurrence_type === 'none'
                            ? '✓ Event saved. Continue reviewing the Weekly Update.'
                            : '✓ Recurring series saved. Continue reviewing the Weekly Update.';
                        card.appendChild(status);
                    }
                }).catch(function (error) {
                    saveButton.disabled = false;
                    saveButton.textContent = suggestion.recurrence.type === 'none' ? 'Save Announcement as Event' : 'Save Recurring Event';
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
