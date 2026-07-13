<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Provides a dedicated Google Places prediction menu for venue fields rendered
 * dynamically on the Weekly Update review page. This avoids relying on the
 * native pac-container used by the Calendar Manager modal.
 */
function surfside_tools_weekly_update_google_predictions_assets() {
    if (!is_user_logged_in() || !current_user_can('upload_files')) {
        return;
    }
    ?>
    <style>
        .surfside-google-prediction-menu {
            position: absolute;
            z-index: 2147483647;
            left: .7rem;
            right: .7rem;
            margin-top: .3rem;
            max-height: 260px;
            overflow-y: auto;
            background: #fff;
            border: 1px solid #b8c9d8;
            border-radius: 9px;
            box-shadow: 0 14px 32px rgba(31,41,55,.2);
        }
        .surfside-google-prediction-menu[hidden] { display: none; }
        .surfside-google-prediction-option {
            display: block;
            width: 100%;
            padding: .72rem .82rem;
            border: 0;
            border-bottom: 1px solid #edf1f5;
            background: #fff;
            color: #172b3a;
            text-align: left;
            cursor: pointer;
        }
        .surfside-google-prediction-option:last-child { border-bottom: 0; }
        .surfside-google-prediction-option:hover,
        .surfside-google-prediction-option:focus { background: #f0f6fb; }
        .surfside-google-prediction-option strong,
        .surfside-google-prediction-option span { display: block; }
        .surfside-google-prediction-option span { margin-top: .12rem; color: #5b6f7f; font-size: .84rem; }
    </style>
    <script>
    (function () {
        let autocompleteService = null;
        let placesService = null;
        let serviceNode = null;
        let sessionToken = null;

        function googleReady() {
            return !!(window.google && google.maps && google.maps.places && google.maps.places.AutocompleteService && google.maps.places.PlacesService);
        }

        function ensureServices() {
            if (!googleReady()) return false;
            if (!autocompleteService) autocompleteService = new google.maps.places.AutocompleteService();
            if (!serviceNode) {
                serviceNode = document.createElement('div');
                serviceNode.style.display = 'none';
                document.body.appendChild(serviceNode);
            }
            if (!placesService) placesService = new google.maps.places.PlacesService(serviceNode);
            return true;
        }

        function clean(value) {
            return String(value || '').replace(/\s+/g, ' ').trim();
        }

        function setCardLocation(card, input, place) {
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
            if (status) status.textContent = 'Google place selected' + (address ? ': ' + address : '.');
            input.dispatchEvent(new Event('change', { bubbles: true }));
        }

        function initializeInput(input) {
            if (!input || input.dataset.surfsideGooglePredictionReady === '1') return;
            const box = input.closest('.surfside-calendar-location-required');
            const card = input.closest('.surfside-calendar-suggestion');
            if (!box || !card) return;

            input.dataset.surfsideGooglePredictionReady = '1';
            input.setAttribute('autocomplete', 'off');

            const menu = document.createElement('div');
            menu.className = 'surfside-google-prediction-menu';
            menu.hidden = true;
            box.appendChild(menu);

            let requestNumber = 0;
            let timer = null;

            function closeMenu() {
                menu.hidden = true;
                menu.innerHTML = '';
            }

            function renderPredictions(predictions) {
                menu.innerHTML = '';
                if (!predictions || !predictions.length) {
                    closeMenu();
                    return;
                }
                predictions.slice(0, 7).forEach(function (prediction) {
                    const option = document.createElement('button');
                    option.type = 'button';
                    option.className = 'surfside-google-prediction-option';
                    option.innerHTML = '<strong></strong><span></span>';
                    option.querySelector('strong').textContent = prediction.structured_formatting && prediction.structured_formatting.main_text ? prediction.structured_formatting.main_text : prediction.description;
                    option.querySelector('span').textContent = prediction.structured_formatting && prediction.structured_formatting.secondary_text ? prediction.structured_formatting.secondary_text : '';
                    option.addEventListener('mousedown', function (event) {
                        event.preventDefault();
                        if (!ensureServices()) return;
                        placesService.getDetails({
                            placeId: prediction.place_id,
                            fields: ['name', 'formatted_address', 'place_id', 'geometry', 'url']
                        }, function (place, status) {
                            if (status === google.maps.places.PlacesServiceStatus.OK && place) {
                                setCardLocation(card, input, place);
                            }
                            closeMenu();
                            sessionToken = null;
                        });
                    });
                    menu.appendChild(option);
                });
                menu.hidden = false;
            }

            input.addEventListener('input', function () {
                window.clearTimeout(timer);
                const term = clean(input.value);
                if (term.length < 3) {
                    closeMenu();
                    return;
                }
                timer = window.setTimeout(function () {
                    if (!ensureServices()) return;
                    const currentRequest = ++requestNumber;
                    if (!sessionToken && google.maps.places.AutocompleteSessionToken) {
                        sessionToken = new google.maps.places.AutocompleteSessionToken();
                    }
                    autocompleteService.getPlacePredictions({
                        input: term,
                        sessionToken: sessionToken || undefined,
                        types: ['establishment']
                    }, function (predictions, status) {
                        if (currentRequest !== requestNumber) return;
                        if (status === google.maps.places.PlacesServiceStatus.OK) {
                            renderPredictions(predictions);
                        } else {
                            closeMenu();
                        }
                    });
                }, 220);
            });

            input.addEventListener('blur', function () {
                window.setTimeout(closeMenu, 180);
            });
        }

        function initializeAll() {
            document.querySelectorAll('.surfside-calendar-required-venue').forEach(initializeInput);
        }

        document.addEventListener('DOMContentLoaded', initializeAll);
        new MutationObserver(initializeAll).observe(document.documentElement, { childList: true, subtree: true });
        let attempts = 0;
        const wait = window.setInterval(function () {
            attempts++;
            initializeAll();
            if (googleReady() || attempts >= 120) window.clearInterval(wait);
        }, 250);
    })();
    </script>
    <?php
}
add_action('wp_footer', 'surfside_tools_weekly_update_google_predictions_assets', 120);
