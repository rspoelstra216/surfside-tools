<?php

if (!defined('ABSPATH')) {
    exit;
}

function surfside_tools_save_suggested_event_location($post_id) {
    if (get_post_type($post_id) !== 'surfside_event' || !current_user_can('upload_files')) {
        return;
    }

    if (isset($_POST['suggested_location_name'])) {
        $location_name = sanitize_text_field(wp_unslash($_POST['suggested_location_name']));
        if ($location_name !== '') {
            update_post_meta($post_id, '_surfside_event_location', $location_name);
            update_post_meta($post_id, '_surfside_event_location_name', $location_name);
        }
    }

    if (isset($_POST['suggested_meeting_location'])) {
        $meeting_location = sanitize_text_field(wp_unslash($_POST['suggested_meeting_location']));
        if ($meeting_location !== '') {
            update_post_meta($post_id, '_surfside_event_location_building_room', $meeting_location);
        }
    }
}
add_action('save_post_surfside_event', 'surfside_tools_save_suggested_event_location', 20);

function surfside_tools_calendar_suggestion_location_assets() {
    if (!is_user_logged_in() || !current_user_can('upload_files')) {
        return;
    }

    $calendar_url = home_url('/dashboard/calendar/');
    $saved_locations = function_exists('surfside_tools_calendar_get_saved_locations')
        ? surfside_tools_calendar_get_saved_locations()
        : array();
    ?>
    <style>
        .surfside-calendar-location-detected,
        .surfside-calendar-location-required {
            margin-top: .65rem;
            padding: .55rem .7rem;
            border-radius: 8px;
            font-size: .92rem;
            font-weight: 700;
        }
        .surfside-calendar-location-detected {
            background: #f1f7f5;
            color: #285f52;
        }
        .surfside-calendar-location-required {
            background: #fff7df;
            color: #744f00;
        }
        .surfside-calendar-location-required label {
            display: block;
            margin-bottom: .4rem;
        }
        .surfside-calendar-location-required input {
            display: block;
            width: 100%;
            margin-top: .4rem;
            padding: .55rem .65rem;
            border: 1px solid #c49a3a;
            border-radius: 7px;
            background: #fff;
            color: #1f2937;
            font: inherit;
            font-weight: 500;
        }
        .surfside-calendar-location-required small {
            display: block;
            margin-top: .35rem;
            font-weight: 500;
        }
    </style>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const calendarUrl = <?php echo wp_json_encode($calendar_url); ?>;
        const savedLocations = <?php echo wp_json_encode($saved_locations); ?>;
        const announcementForm = document.querySelector('.surfside-weekly-update-publish-form, .surfside-docx-save-form');
        let activeLocation = null;

        function clean(value) {
            return String(value || '').replace(/\s+/g, ' ').trim();
        }

        function detectLocation(text) {
            const source = clean(text);
            const lower = source.toLowerCase();

            for (const saved of savedLocations) {
                const name = clean(saved.name);
                if (name && lower.includes(name.toLowerCase())) {
                    return { venue: name, meeting: '', label: name };
                }
            }

            const internalPatterns = [
                /\b(Fellowship Hall)\b/i,
                /\b(Building\s+[A-Za-z0-9-]+)\b/i,
                /\b(Room\s+[A-Za-z0-9-]+)\b/i,
                /\b(Nursery)\b/i,
                /\b(Sanctuary)\b/i,
                /\b(Youth Room)\b/i,
                /\b(Children(?:'s)? Room)\b/i
            ];

            for (const pattern of internalPatterns) {
                const match = source.match(pattern);
                if (match) {
                    return { venue: '', meeting: clean(match[1]), label: clean(match[1]) };
                }
            }

            const atVenue = source.match(/\b(?:at|located at|meeting at)\s+(?:the\s+)?([A-Z][A-Za-z0-9'&.-]+(?:\s+[A-Z][A-Za-z0-9'&.-]+){1,4})(?=\s+(?:on|from|for|at|in)\b|[.,;]|$)/);
            if (atVenue) {
                const candidate = clean(atVenue[1]);
                const blocked = /^(Information Table|Legal Rights|Christian Leaders|Public Areas)$/i;
                if (!blocked.test(candidate)) {
                    return { venue: candidate, meeting: '', label: candidate };
                }
            }

            return { venue: '', meeting: '', label: '' };
        }

        function cardSuggestion(card, index, datedAnnouncements) {
            const titleNode = card.querySelector(':scope > strong');
            const meta = card.querySelector('.surfside-calendar-suggestion-meta');
            const parts = meta ? meta.textContent.split('·').map(function (part) { return part.trim(); }) : [];
            const range = (parts[1] || '').match(/^(\d{2}:\d{2})(?:[–-](\d{2}:\d{2}))?$/);
            return {
                title: titleNode ? titleNode.textContent.trim() : '',
                date: parts[0] || '',
                start: range ? range[1] : '',
                end: range && range[2] ? range[2] : '',
                description: datedAnnouncements[index] ? datedAnnouncements[index].value.trim() : ''
            };
        }

        function locationFromCard(card) {
            const venueInput = card.querySelector('.surfside-calendar-required-venue');
            return {
                venue: venueInput ? clean(venueInput.value) : (card.dataset.surfsideVenue || ''),
                meeting: card.dataset.surfsideMeetingLocation || ''
            };
        }

        if (announcementForm) {
            const datedAnnouncements = Array.from(announcementForm.querySelectorAll('textarea[name="announcement_items[]"]')).filter(function (textarea) {
                return /\b(January|February|March|April|May|June|July|August|September|October|November|December|Jan\.?|Feb\.?|Mar\.?|Apr\.?|Jun\.?|Jul\.?|Aug\.?|Sep\.?|Sept\.?|Oct\.?|Nov\.?|Dec\.?)\s+\d{1,2}(?:st|nd|rd|th)?/i.test(textarea.value);
            });

            document.querySelectorAll('.surfside-calendar-suggestion').forEach(function (card, index) {
                const suggestion = cardSuggestion(card, index, datedAnnouncements);
                const location = detectLocation(suggestion.description);
                card.dataset.surfsideVenue = location.venue;
                card.dataset.surfsideMeetingLocation = location.meeting;

                const insertionTarget = card.querySelector('.surfside-calendar-recurrence-detected') || card.querySelector('.surfside-calendar-match') || card.querySelector('.surfside-calendar-suggestion-meta');

                if (location.label) {
                    const notice = document.createElement('div');
                    notice.className = 'surfside-calendar-location-detected';
                    notice.textContent = location.meeting
                        ? '📍 Meeting location detected: ' + location.label
                        : '📍 Venue detected: ' + location.label;
                    if (insertionTarget) insertionTarget.insertAdjacentElement('afterend', notice);

                    if (location.meeting && !location.venue) {
                        card.dataset.surfsideVenueRequired = '1';
                        const required = document.createElement('div');
                        required.className = 'surfside-calendar-location-required';
                        required.innerHTML = '<label>Venue required<input type="text" class="surfside-calendar-required-venue" placeholder="e.g., Surfside Community Fellowship" autocomplete="organization"></label><small>“' + location.meeting.replace(/</g, '&lt;').replace(/>/g, '&gt;') + '” identifies the room or building, but the announcement does not identify the main venue.</small>';
                        notice.insertAdjacentElement('afterend', required);
                    }
                }
            });

            document.addEventListener('click', function (event) {
                const save = event.target.closest('.surfside-calendar-save-event');
                if (save) {
                    const card = save.closest('.surfside-calendar-suggestion');
                    const location = card ? locationFromCard(card) : { venue: '', meeting: '' };
                    if (card && card.dataset.surfsideVenueRequired === '1' && !location.venue) {
                        event.preventDefault();
                        event.stopImmediatePropagation();
                        const input = card.querySelector('.surfside-calendar-required-venue');
                        if (input) {
                            input.focus();
                            input.setAttribute('aria-invalid', 'true');
                        }
                        return;
                    }
                    activeLocation = location;
                }

                const review = event.target.closest('.surfside-calendar-review-link');
                if (!review || review.textContent.indexOf('Saved Event') !== -1) return;

                const card = review.closest('.surfside-calendar-suggestion');
                if (!card) return;
                const location = locationFromCard(card);
                if (!location.venue && !location.meeting) return;

                const cards = Array.from(document.querySelectorAll('.surfside-calendar-suggestion'));
                const suggestion = cardSuggestion(card, cards.indexOf(card), datedAnnouncements);
                event.preventDefault();
                event.stopImmediatePropagation();

                const params = new URLSearchParams({
                    suggestion: '1',
                    event_title: suggestion.title,
                    event_date: suggestion.date,
                    event_start_time: suggestion.start,
                    event_end_time: suggestion.end,
                    event_description: suggestion.description,
                    event_location_name: location.venue,
                    event_location_building_room: location.meeting
                });
                const url = calendarUrl + '?' + params.toString();
                const modal = document.querySelector('.surfside-calendar-suggestion-modal');
                const frame = modal ? modal.querySelector('iframe') : null;
                const newTab = modal ? modal.querySelector('.surfside-calendar-suggestion-modal-actions a') : null;
                if (modal && frame) {
                    frame.src = url;
                    if (newTab) newTab.href = url;
                    modal.hidden = false;
                    document.body.classList.add('surfside-calendar-modal-open');
                } else {
                    window.open(url, '_blank', 'noopener');
                }
            }, true);

            document.addEventListener('input', function (event) {
                if (!event.target.matches('.surfside-calendar-required-venue')) return;
                event.target.removeAttribute('aria-invalid');
                const card = event.target.closest('.surfside-calendar-suggestion');
                if (card) card.dataset.surfsideVenue = clean(event.target.value);
            });
        }

        const originalFetch = window.fetch;
        window.fetch = function (input, init) {
            if (activeLocation && init && typeof init.body === 'string' && init.body.indexOf('action=surfside_save_announcement_event') !== -1) {
                const body = new URLSearchParams(init.body);
                body.set('suggested_location_name', activeLocation.venue || '');
                body.set('suggested_meeting_location', activeLocation.meeting || '');
                init = Object.assign({}, init, { body: body.toString() });
                activeLocation = null;
            }
            return originalFetch.call(this, input, init);
        };

        const query = new URLSearchParams(window.location.search);
        const calendarForm = document.querySelector('.surfside-calendar-form');
        if (calendarForm && query.get('suggestion') === '1') {
            const venue = query.get('event_location_name') || '';
            const meeting = query.get('event_location_building_room') || '';
            const venueField = calendarForm.querySelector('[name="event_location_name"]');
            const meetingField = calendarForm.querySelector('[name="event_location_building_room"]');
            if (venueField && venue) venueField.value = venue;
            if (meetingField && meeting) meetingField.value = meeting;
        }
    });
    </script>
    <?php
}
add_action('wp_footer', 'surfside_tools_calendar_suggestion_location_assets', 42);
