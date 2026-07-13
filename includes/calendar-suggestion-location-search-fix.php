<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Improves the venue lookup shown on Weekly Update calendar suggestions.
 * Provides a consistent visible menu using saved locations and venues already
 * used by events, while the existing Google Places autocomplete remains active.
 */
function surfside_tools_calendar_suggestion_location_search_fix_assets() {
    if (!is_user_logged_in() || !current_user_can('upload_files')) {
        return;
    }

    $known = array();

    if (function_exists('surfside_tools_calendar_get_saved_locations')) {
        foreach (surfside_tools_calendar_get_saved_locations() as $location) {
            $name = trim((string) ($location['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $key = strtolower($name);
            $known[$key] = array(
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
            if ($name === '') {
                continue;
            }
            $key = strtolower($name);
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

            if (!isset($known[$key])) {
                $known[$key] = $candidate;
            } else {
                foreach (array('address','id','place_id','lat','lng','maps_url') as $field) {
                    if (empty($known[$key][$field]) && !empty($candidate[$field])) {
                        $known[$key][$field] = $candidate[$field];
                    }
                }
            }
        }
    }

    $known_locations = array_values($known);
    usort($known_locations, function ($a, $b) {
        return strcasecmp($a['name'], $b['name']);
    });
    ?>
    <style>
        .surfside-calendar-location-required {
            position: relative;
            background: #eef4fb !important;
            color: #244765 !important;
            border-color: #c8d9e8 !important;
        }
        .surfside-calendar-location-required .surfside-location-search-help {
            display: block;
            margin: .35rem 0 0;
            font-weight: 500;
            color: #49657d;
        }
        .surfside-known-location-menu {
            position: absolute;
            z-index: 10020;
            left: .7rem;
            right: .7rem;
            margin-top: .25rem;
            max-height: 230px;
            overflow-y: auto;
            border: 1px solid #b8c9d8;
            border-radius: 9px;
            background: #fff;
            box-shadow: 0 12px 28px rgba(31, 41, 55, .16);
        }
        .surfside-known-location-menu[hidden] { display: none; }
        .surfside-known-location-option {
            display: block;
            width: 100%;
            padding: .7rem .8rem;
            border: 0;
            border-bottom: 1px solid #edf1f5;
            background: #fff;
            color: #172b3a;
            text-align: left;
            cursor: pointer;
        }
        .surfside-known-location-option:last-child { border-bottom: 0; }
        .surfside-known-location-option:hover,
        .surfside-known-location-option:focus { background: #f0f6fb; }
        .surfside-known-location-option strong,
        .surfside-known-location-option span { display: block; }
        .surfside-known-location-option span {
            margin-top: .12rem;
            color: #5b6f7f;
            font-size: .85rem;
        }
        .surfside-known-location-empty {
            padding: .7rem .8rem;
            color: #5b6f7f;
            font-weight: 500;
        }
    </style>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const knownLocations = <?php echo wp_json_encode($known_locations); ?>;

        function clean(value) {
            return String(value || '').replace(/\s+/g, ' ').trim();
        }

        function normalize(value) {
            return clean(value).toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/[^a-z0-9 ]/g, '');
        }

        function mapsUrl(location) {
            if (location.maps_url) return location.maps_url;
            const query = clean(location.name + (location.address ? ', ' + location.address : ''));
            return query ? 'https://www.google.com/maps/search/?api=1&query=' + encodeURIComponent(query) : '';
        }

        function applyLocation(card, input, location) {
            input.value = location.name || '';
            input.removeAttribute('aria-invalid');
            card.dataset.surfsideVenue = location.name || '';
            card.dataset.surfsideVenueAddress = location.address || '';
            card.dataset.surfsideVenueId = location.id || '';
            card.dataset.surfsideVenuePlaceId = location.place_id || '';
            card.dataset.surfsideVenueLat = location.lat || '';
            card.dataset.surfsideVenueLng = location.lng || '';
            card.dataset.surfsideVenueMapsUrl = mapsUrl(location);

            const status = card.querySelector('.surfside-calendar-location-lookup-status');
            if (status) {
                status.textContent = (location.source || 'Location') + ' selected' + (location.address ? ': ' + location.address : '.');
            }
            input.dispatchEvent(new Event('change', { bubbles: true }));
        }

        document.querySelectorAll('.surfside-calendar-required-venue').forEach(function (input, index) {
            const box = input.closest('.surfside-calendar-location-required');
            const card = input.closest('.surfside-calendar-suggestion');
            if (!box || !card) return;

            input.placeholder = 'e.g., Surfside Community Fellowship or Cozy Corner Cafe';
            input.removeAttribute('list');

            const existingHelp = box.querySelector('.surfside-location-search-help');
            if (!existingHelp) {
                const help = document.createElement('span');
                help.className = 'surfside-location-search-help';
                help.textContent = 'Start typing to search locations already used on the calendar, or choose a Google suggestion.';
                input.insertAdjacentElement('afterend', help);
            }

            const menu = document.createElement('div');
            menu.className = 'surfside-known-location-menu';
            menu.id = 'surfside-known-location-menu-' + index;
            menu.hidden = true;
            input.setAttribute('aria-controls', menu.id);
            input.setAttribute('aria-expanded', 'false');
            box.appendChild(menu);

            function closeMenu() {
                menu.hidden = true;
                input.setAttribute('aria-expanded', 'false');
            }

            function renderMenu() {
                const term = normalize(input.value);
                const results = knownLocations.filter(function (location) {
                    if (!term) return true;
                    return normalize(location.name).includes(term) || normalize(location.address).includes(term);
                }).slice(0, 8);

                menu.innerHTML = '';
                if (!results.length) {
                    const empty = document.createElement('div');
                    empty.className = 'surfside-known-location-empty';
                    empty.textContent = 'No saved match yet. Keep typing to search Google Places.';
                    menu.appendChild(empty);
                } else {
                    results.forEach(function (location) {
                        const option = document.createElement('button');
                        option.type = 'button';
                        option.className = 'surfside-known-location-option';
                        option.innerHTML = '<strong></strong><span></span>';
                        option.querySelector('strong').textContent = location.name;
                        option.querySelector('span').textContent = [location.address, location.source].filter(Boolean).join(' · ');
                        option.addEventListener('mousedown', function (event) {
                            event.preventDefault();
                            applyLocation(card, input, location);
                            closeMenu();
                        });
                        menu.appendChild(option);
                    });
                }
                menu.hidden = false;
                input.setAttribute('aria-expanded', 'true');
            }

            input.addEventListener('focus', renderMenu);
            input.addEventListener('input', renderMenu);
            input.addEventListener('blur', function () {
                window.setTimeout(closeMenu, 180);
            });
        });
    });
    </script>
    <?php
}
add_action('wp_footer', 'surfside_tools_calendar_suggestion_location_search_fix_assets', 44);
