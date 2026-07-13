<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Attach the same native Google Places Autocomplete used by Calendar Manager
 * to dynamically-rendered Weekly Update venue fields.
 */
function surfside_tools_weekly_update_native_google_places_assets() {
    if (!is_user_logged_in() || !current_user_can('upload_files')) {
        return;
    }
    ?>
    <style>
        .pac-container {
            z-index: 2147483647 !important;
        }
    </style>
    <script>
    (function () {
        function googleReady() {
            return !!(window.google && google.maps && google.maps.places && google.maps.places.Autocomplete);
        }

        function clean(value) {
            return String(value || '').replace(/\s+/g, ' ').trim();
        }

        function applyPlace(card, input, place) {
            if (!card || !place) return;

            const name = clean(place.name || input.value);
            const address = clean(place.formatted_address || '');
            const lat = place.geometry && place.geometry.location ? place.geometry.location.lat() : '';
            const lng = place.geometry && place.geometry.location ? place.geometry.location.lng() : '';

            input.value = name;
            input.removeAttribute('aria-invalid');
            card.dataset.surfsideVenue = name;
            card.dataset.surfsideVenueAddress = address;
            card.dataset.surfsideVenueId = '';
            card.dataset.surfsideVenuePlaceId = place.place_id || '';
            card.dataset.surfsideVenueLat = lat;
            card.dataset.surfsideVenueLng = lng;
            card.dataset.surfsideVenueMapsUrl = place.url || ('https://www.google.com/maps/search/?api=1&query=' + encodeURIComponent(name + (address ? ', ' + address : '')));

            const status = card.querySelector('.surfside-calendar-location-lookup-status');
            if (status) {
                status.textContent = 'Google place selected' + (address ? ': ' + address : '.');
            }

            input.dispatchEvent(new Event('change', { bubbles: true }));
        }

        function initializeInput(input) {
            if (!input || input.dataset.surfsideNativeGoogleReady === '1') return true;
            if (!googleReady()) return false;

            const card = input.closest('.surfside-calendar-suggestion');
            if (!card) return false;

            input.dataset.surfsideNativeGoogleReady = '1';
            input.setAttribute('autocomplete', 'off');

            const autocomplete = new google.maps.places.Autocomplete(input, {
                fields: ['name', 'formatted_address', 'place_id', 'geometry', 'url'],
                types: ['establishment']
            });

            autocomplete.addListener('place_changed', function () {
                applyPlace(card, input, autocomplete.getPlace());
            });

            return true;
        }

        function initializeAll() {
            document.querySelectorAll('.surfside-calendar-required-venue').forEach(initializeInput);
        }

        function initializeTarget(target) {
            if (target && target.matches && target.matches('.surfside-calendar-required-venue')) {
                if (!initializeInput(target)) {
                    let attempts = 0;
                    const retry = window.setInterval(function () {
                        attempts++;
                        if (initializeInput(target) || attempts >= 80) {
                            window.clearInterval(retry);
                        }
                    }, 250);
                }
            }
        }

        // Delegated events guarantee initialization when a dynamic field is first used.
        document.addEventListener('focusin', function (event) {
            initializeTarget(event.target);
        });
        document.addEventListener('pointerdown', function (event) {
            initializeTarget(event.target);
        });
        document.addEventListener('input', function (event) {
            initializeTarget(event.target);
        }, true);

        document.addEventListener('DOMContentLoaded', initializeAll);
        new MutationObserver(initializeAll).observe(document.documentElement, { childList: true, subtree: true });
        initializeAll();
    })();
    </script>
    <?php
}
add_action('wp_footer', 'surfside_tools_weekly_update_native_google_places_assets', 130);
