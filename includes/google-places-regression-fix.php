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

/**
 * Attach Google Places to dynamically rendered Weekly Update venue fields.
 */
function surfside_tools_google_places_regression_fix_assets() {
    if (!is_user_logged_in() || !current_user_can('upload_files')) {
        return;
    }
    ?>
    <script>
    (function () {
        function setCardLocation(card, input, place) {
            if (!card || !place || !place.name) return;
            const address = place.formatted_address || '';
            const lat = place.geometry && place.geometry.location ? place.geometry.location.lat() : '';
            const lng = place.geometry && place.geometry.location ? place.geometry.location.lng() : '';
            input.value = place.name;
            input.removeAttribute('aria-invalid');
            card.dataset.surfsideVenue = place.name;
            card.dataset.surfsideVenueAddress = address;
            card.dataset.surfsideVenueId = '';
            card.dataset.surfsideVenuePlaceId = place.place_id || '';
            card.dataset.surfsideVenueLat = lat;
            card.dataset.surfsideVenueLng = lng;
            card.dataset.surfsideVenueMapsUrl = place.url || ('https://www.google.com/maps/search/?api=1&query=' + encodeURIComponent(place.name + (address ? ', ' + address : '')));

            const status = card.querySelector('.surfside-calendar-location-lookup-status');
            if (status) status.textContent = 'Google place selected' + (address ? ': ' + address : '.');
            input.dispatchEvent(new Event('change', { bubbles: true }));
        }

        function initializeField(input) {
            if (!input) return false;
            if (
                input.dataset.surfsideGooglePlacesReady === '1' ||
                input.dataset.surfsideNativeGoogleReady === '1'
            ) {
                return true;
            }
            if (!(window.google && google.maps && google.maps.places && google.maps.places.Autocomplete)) {
                return false;
            }

            const card = input.closest('.surfside-calendar-suggestion');
            if (!card) return false;

            input.dataset.surfsideGooglePlacesReady = '1';
            input.setAttribute('autocomplete', 'off');
            const autocomplete = new google.maps.places.Autocomplete(input, {
                fields: ['name', 'formatted_address', 'place_id', 'geometry', 'url'],
                types: ['establishment']
            });
            autocomplete.addListener('place_changed', function () {
                setCardLocation(card, input, autocomplete.getPlace());
            });
            return true;
        }

        function initializeAll() {
            document.querySelectorAll('.surfside-calendar-required-venue').forEach(initializeField);
        }

        let attempts = 0;
        const timer = window.setInterval(function () {
            attempts++;
            initializeAll();
            if (attempts >= 120 || document.querySelectorAll('.surfside-calendar-required-venue:not(.pac-target-input)').length === 0) {
                window.clearInterval(timer);
            }
        }, 250);

        document.addEventListener('DOMContentLoaded', initializeAll);
        document.addEventListener('focusin', function (event) {
            if (event.target && event.target.matches('.surfside-calendar-required-venue')) {
                initializeField(event.target);
            }
        });
        new MutationObserver(initializeAll).observe(document.documentElement, { childList: true, subtree: true });
    })();
    </script>
    <?php
}
add_action('wp_footer', 'surfside_tools_google_places_regression_fix_assets', 80);
