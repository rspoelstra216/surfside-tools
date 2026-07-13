<?php

if (!defined('ABSPATH')) {
    exit;
}

function surfside_tools_save_suggested_event_location($post_id) {
    if (get_post_type($post_id) !== 'surfside_event' || !current_user_can('upload_files')) {
        return;
    }

    $fields = array(
        'suggested_location_name' => array('_surfside_event_location', '_surfside_event_location_name'),
        'suggested_location_address' => array('_surfside_event_location_address'),
        'suggested_location_id' => array('_surfside_event_location_id'),
        'suggested_location_place_id' => array('_surfside_event_location_place_id'),
        'suggested_location_lat' => array('_surfside_event_location_lat'),
        'suggested_location_lng' => array('_surfside_event_location_lng'),
        'suggested_location_maps_url' => array('_surfside_event_location_maps_url'),
        'suggested_meeting_location' => array('_surfside_event_location_building_room'),
    );

    foreach ($fields as $request_key => $meta_keys) {
        if (!isset($_POST[$request_key])) {
            continue;
        }
        $value = $request_key === 'suggested_location_maps_url'
            ? esc_url_raw(wp_unslash($_POST[$request_key]))
            : sanitize_text_field(wp_unslash($_POST[$request_key]));
        if ($value === '') {
            continue;
        }
        foreach ($meta_keys as $meta_key) {
            update_post_meta($post_id, $meta_key, $value);
        }
    }
}
add_action('save_post_surfside_event', 'surfside_tools_save_suggested_event_location', 20);

function surfside_tools_calendar_suggestion_location_assets() {
    if (!is_user_logged_in() || !current_user_can('upload_files')) {
        return;
    }

    if (function_exists('surfside_tools_calendar_enqueue_google_places')) {
        surfside_tools_calendar_enqueue_google_places();
    }

    $calendar_url = home_url('/dashboard/calendar/');
    $saved_locations = function_exists('surfside_tools_calendar_get_saved_locations')
        ? surfside_tools_calendar_get_saved_locations()
        : array();
    ?>
    <style>
        .surfside-calendar-location-detected,
        .surfside-calendar-location-required,
        .surfside-calendar-location-selected {
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
            background: #eef4fb;
            color: #244765;
            border: 1px solid #c8d9e8;
        }
        .surfside-calendar-location-selected {
            background: #edf7ed;
            color: #245f2a;
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
            border: 1px solid #7896ad;
            border-radius: 7px;
            background: #fff;
            color: #1f2937;
            font: inherit;
            font-weight: 500;
        }
        .surfside-calendar-location-required input[aria-invalid="true"] {
            outline: 2px solid #b42318;
            outline-offset: 1px;
        }
        .surfside-calendar-location-required small {
            display: block;
            margin-top: .35rem;
            font-weight: 500;
        }
        .surfside-calendar-location-lookup-status {
            display: block;
            margin-top: .35rem;
            font-weight: 600;
            color: #285f52;
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

        function normalize(value) {
            return clean(value).toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/[^a-z0-9 ]/g, '');
        }

        function mapsUrl(location) {
            if (location.maps_url) return location.maps_url;
            const query = clean((location.venue || '') + (location.address ? ', ' + location.address : ''));
            return query ? 'https://www.google.com/maps/search/?api=1&query=' + encodeURIComponent(query) : '';
        }

        function savedLocationMatch(value) {
            const target = normalize(value);
            if (!target) return null;
            return savedLocations.find(function (saved) {
                const savedName = normalize(saved.name);
                return savedName === target || savedName.includes(target) || target.includes(savedName);
            }) || null;
        }

        function detectLocation(text) {
            const source = clean(text);
            const lower = normalize(source);

            for (const saved of savedLocations) {
                const name = clean(saved.name);
                if (name && lower.includes(normalize(name))) {
                    return { venue: name, address: clean(saved.address), location_id: saved.id || 0, meeting: '', label: name };
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
                    return { venue: '', address: '', location_id: 0, meeting: clean(match[1]), label: clean(match[1]) };
                }
            }

            const atVenue = source.match(/\b(?:at|located at|meeting at)\s+(?:the\s+)?([A-Z][A-Za-z0-9'&.-]+(?:\s+[A-Z][A-Za-z0-9'&.-]+){1,4})(?=\s+(?:on|from|for|at|in)\b|[.,;]|$)/);
            if (atVenue) {
                const candidate = clean(atVenue[1]);
                const blocked = /^(Information Table|Legal Rights|Christian Leaders|Public Areas)$/i;
                if (!blocked.test(candidate)) {
                    const saved = savedLocationMatch(candidate);
                    return saved
                        ? { venue: clean(saved.name), address: clean(saved.address), location_id: saved.id || 0, meeting: '', label: clean(saved.name) }
                        : { venue: candidate, address: '', location_id: 0, meeting: '', label: candidate };
                }
            }

            return { venue: '', address: '', location_id: 0, meeting: '', label: '' };
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
            const location = {
                venue: venueInput ? clean(venueInput.value) : (card.dataset.surfsideVenue || ''),
                address: card.dataset.surfsideVenueAddress || '',
                location_id: card.dataset.surfsideVenueId || '',
                place_id: card.dataset.surfsideVenuePlaceId || '',
                lat: card.dataset.surfsideVenueLat || '',
                lng: card.dataset.surfsideVenueLng || '',
                maps_url: card.dataset.surfsideVenueMapsUrl || '',
                meeting: card.dataset.surfsideMeetingLocation || ''
            };
            location.maps_url = mapsUrl(location);
            return location;
        }

        function setCardLocation(card, location, message) {
            card.dataset.surfsideVenue = location.venue || '';
            card.dataset.surfsideVenueAddress = location.address || '';
            card.dataset.surfsideVenueId = location.location_id || '';
            card.dataset.surfsideVenuePlaceId = location.place_id || '';
            card.dataset.surfsideVenueLat = location.lat || '';
            card.dataset.surfsideVenueLng = location.lng || '';
            card.dataset.surfsideVenueMapsUrl = mapsUrl(location);

            const input = card.querySelector('.surfside-calendar-required-venue');
            if (input && location.venue) input.value = location.venue;
            const status = card.querySelector('.surfside-calendar-location-lookup-status');
            if (status) status.textContent = message || '';
        }

        function initializeGoogleAutocomplete(input, card) {
            let attempts = 0;
            const timer = window.setInterval(function () {
                attempts++;
                if (window.google && google.maps && google.maps.places && google.maps.places.Autocomplete) {
                    window.clearInterval(timer);
                    const autocomplete = new google.maps.places.Autocomplete(input, {
                        fields: ['name', 'formatted_address', 'place_id', 'geometry', 'url']
                    });
                    autocomplete.addListener('place_changed', function () {
                        const place = autocomplete.getPlace();
                        if (!place || !place.name) return;
                        setCardLocation(card, {
                            venue: place.name,
                            address: place.formatted_address || '',
                            place_id: place.place_id || '',
                            lat: place.geometry && place.geometry.location ? place.geometry.location.lat() : '',
                            lng: place.geometry && place.geometry.location ? place.geometry.location.lng() : '',
                            maps_url: place.url || ''
                        }, 'Google place selected' + (place.formatted_address ? ': ' + place.formatted_address : '.'));
                    });
                } else if (attempts >= 20) {
                    window.clearInterval(timer);
                }
            }, 250);
        }

        if (announcementForm) {
            const datedAnnouncements = Array.from(announcementForm.querySelectorAll('textarea[name="announcement_items[]"]')).filter(function (textarea) {
                return /\b(January|February|March|April|May|June|July|August|September|October|November|December|Jan\.?|Feb\.?|Mar\.?|Apr\.?|Jun\.?|Jul\.?|Aug\.?|Sep\.?|Sept\.?|Oct\.?|Nov\.?|Dec\.?)\s+\d{1,2}(?:st|nd|rd|th)?/i.test(textarea.value);
            });

            document.querySelectorAll('.surfside-calendar-suggestion').forEach(function (card, index) {
                const suggestion = cardSuggestion(card, index, datedAnnouncements);
                const location = detectLocation(suggestion.description);
                card.dataset.surfsideVenue = location.venue;
                card.dataset.surfsideVenueAddress = location.address || '';
                card.dataset.surfsideVenueId = location.location_id || '';
                card.dataset.surfsideMeetingLocation = location.meeting;

                const insertionTarget = card.querySelector('.surfside-calendar-recurrence-detected') || card.querySelector('.surfside-calendar-match') || card.querySelector('.surfside-calendar-suggestion-meta');

                if (location.label) {
                    const notice = document.createElement('div');
                    notice.className = 'surfside-calendar-location-detected';
                    notice.textContent = location.meeting
                        ? '📍 Meeting location detected: ' + location.label
                        : '📍 Venue detected: ' + location.label + (location.address ? ' · ' + location.address : '');
                    if (insertionTarget) insertionTarget.insertAdjacentElement('afterend', notice);

                    if (location.meeting && !location.venue) {
                        card.dataset.surfsideVenueRequired = '1';
                        const required = document.createElement('div');
                        required.className = 'surfside-calendar-location-required';
                        const listId = 'surfside-saved-locations-' + index;
                        required.innerHTML = '<label>Where is this event being held?<input type="search" class="surfside-calendar-required-venue" placeholder="Search saved locations or Google Places" autocomplete="off" list="' + listId + '"></label><datalist id="' + listId + '"></datalist><small>We found “' + location.meeting.replace(/</g, '&lt;').replace(/>/g, '&gt;') + ',” but still need the church, campus, or venue.</small><span class="surfside-calendar-location-lookup-status" aria-live="polite"></span>';
                        notice.insertAdjacentElement('afterend', required);
                        const datalist = required.querySelector('datalist');
                        savedLocations.forEach(function (saved) {
                            const option = document.createElement('option');
                            option.value = clean(saved.name);
                            option.label = clean(saved.address);
                            datalist.appendChild(option);
                        });
                        initializeGoogleAutocomplete(required.querySelector('input'), card);
                    }
                }
            });

            document.addEventListener('change', function (event) {
                if (!event.target.matches('.surfside-calendar-required-venue')) return;
                const card = event.target.closest('.surfside-calendar-suggestion');
                const saved = savedLocationMatch(event.target.value);
                if (card && saved) {
                    setCardLocation(card, {
                        venue: clean(saved.name),
                        address: clean(saved.address),
                        location_id: saved.id || 0
                    }, 'Saved location selected' + (saved.address ? ': ' + saved.address : '.'));
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
                    event_location_address: location.address,
                    event_location_id: location.location_id,
                    event_location_place_id: location.place_id,
                    event_location_lat: location.lat,
                    event_location_lng: location.lng,
                    event_location_maps_url: location.maps_url,
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
                if (card) {
                    card.dataset.surfsideVenue = clean(event.target.value);
                    card.dataset.surfsideVenueAddress = '';
                    card.dataset.surfsideVenueId = '';
                    card.dataset.surfsideVenuePlaceId = '';
                    card.dataset.surfsideVenueLat = '';
                    card.dataset.surfsideVenueLng = '';
                    card.dataset.surfsideVenueMapsUrl = '';
                }
            });
        }

        const originalFetch = window.fetch;
        window.fetch = function (input, init) {
            if (activeLocation && init && typeof init.body === 'string' && init.body.indexOf('action=surfside_save_announcement_event') !== -1) {
                const body = new URLSearchParams(init.body);
                body.set('suggested_location_name', activeLocation.venue || '');
                body.set('suggested_location_address', activeLocation.address || '');
                body.set('suggested_location_id', activeLocation.location_id || '');
                body.set('suggested_location_place_id', activeLocation.place_id || '');
                body.set('suggested_location_lat', activeLocation.lat || '');
                body.set('suggested_location_lng', activeLocation.lng || '');
                body.set('suggested_location_maps_url', activeLocation.maps_url || '');
                body.set('suggested_meeting_location', activeLocation.meeting || '');
                init = Object.assign({}, init, { body: body.toString() });
                activeLocation = null;
            }
            return originalFetch.call(this, input, init);
        };

        const query = new URLSearchParams(window.location.search);
        const calendarForm = document.querySelector('.surfside-calendar-form');
        if (calendarForm && query.get('suggestion') === '1') {
            const mappings = {
                event_location_name: '.surfside-location-name',
                event_location_address: '.surfside-location-address',
                event_location_id: '.surfside-location-id',
                event_location_place_id: '.surfside-location-place-id',
                event_location_lat: '.surfside-location-lat',
                event_location_lng: '.surfside-location-lng',
                event_location_maps_url: '.surfside-location-maps-url',
                event_location_building_room: '[name="event_location_building_room"]'
            };
            Object.keys(mappings).forEach(function (key) {
                const field = calendarForm.querySelector(mappings[key]);
                const value = query.get(key) || '';
                if (field && value) field.value = value;
            });
            const searchField = calendarForm.querySelector('.surfside-location-search');
            if (searchField && query.get('event_location_name')) searchField.value = query.get('event_location_name');
        }
    });
    </script>
    <?php
}
add_action('wp_footer', 'surfside_tools_calendar_suggestion_location_assets', 42);
