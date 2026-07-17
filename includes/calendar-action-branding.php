<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Polish the calendar action buttons added by calendar-integration.php.
 * This runs after the base integration so behavior remains unchanged while
 * labels, service logos, and hover colors become clearer and friendlier.
 */
function surfside_tools_calendar_action_branding_assets() {
    ?>
    <style id="surfside-calendar-action-branding-styles">
        .surfside-event-calendar-action {
            gap: 8px;
        }
        .surfside-event-calendar-action-icon {
            display: inline-flex;
            width: 18px;
            height: 18px;
            flex: 0 0 18px;
            align-items: center;
            justify-content: center;
        }
        .surfside-event-calendar-action-icon svg {
            display: block;
            width: 100%;
            height: 100%;
        }
        .surfside-event-calendar-action[data-calendar-brand="apple"]:hover,
        .surfside-event-calendar-action[data-calendar-brand="apple"]:focus-visible {
            border-color: #1d1d1f;
            background: #f2f2f2;
            color: #1d1d1f;
        }
        .surfside-event-calendar-action[data-calendar-brand="google"]:hover,
        .surfside-event-calendar-action[data-calendar-brand="google"]:focus-visible {
            border-color: #4285f4;
            background: #f1f6ff;
            color: #174ea6;
        }
        .surfside-event-calendar-action[data-calendar-brand="download"]:hover,
        .surfside-event-calendar-action[data-calendar-brand="download"]:focus-visible {
            border-color: #0b4f9c;
            background: #eef6ff;
            color: #0b4f9c;
        }
        @media (min-width: 601px) {
            .surfside-event-calendar-actions {
                flex-wrap: nowrap;
            }
            .surfside-event-calendar-action {
                flex: 0 1 auto;
                min-width: 0;
                padding: 8px 9px;
                gap: 7px;
                font-size: .82rem;
                white-space: nowrap;
            }
        }
    </style>
    <script id="surfside-calendar-action-branding-script">
    (function () {
        'use strict';

        var icons = {
            apple: '<svg viewBox="0 0 384 512" aria-hidden="true" focusable="false" role="img"><path fill="currentColor" d="M279.55 258.94c-.2-36.7 16.4-64.4 50-84.8-18.8-26.9-47.2-41.7-84.7-44.6-35.5-2.8-74.3 20.7-88.5 20.7-15 0-49.4-19.7-72.8-19.7-31 0-60.2 18-76.1 47.9-32.2 55.9-8.2 138.3 23.1 183.7 15.6 22.5 34.2 47.8 58.6 46.8 23.2-.9 32-15 60.1-15 28.1 0 36 15 60.5 14.5 25-.4 40.8-22.7 56.3-45.3 18-26.3 25.4-51.8 25.8-53.1-.6-.3-49.6-19-49.8-75.1zm-24.7-166.3c12.7-15.1 21.3-36.1 19-56.9-18.3.7-40.4 12.9-53.3 28-11.6 13.4-21.7 34.7-19 55.1 20.4 1.6 40.6-10.3 53.3-26.2z"/></svg>',
            google: '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false" role="img"><path fill="#4285F4" d="M18 3h-1V1h-2v2H9V1H7v2H6a3 3 0 0 0-3 3v12a3 3 0 0 0 3 3h12a3 3 0 0 0 3-3V6a3 3 0 0 0-3-3z"/><path fill="#34A853" d="M3 9h18v9a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3V9z"/><path fill="#FBBC04" d="M3 14h9v7H6a3 3 0 0 1-3-3v-4z"/><path fill="#EA4335" d="M6 3h12a3 3 0 0 1 3 3v3H3V6a3 3 0 0 1 3-3z"/><rect x="7" y="8" width="10" height="10" rx="1" fill="#fff"/><path fill="#4285F4" d="M9.2 11.1h2.7v1.2h-1.4v.8h1.2v1.1h-1.2v1.7H9.2v-4.8zm3.5 0h1.3v4.8h-1.3v-4.8zm2.2 0h1.3v4.8h-1.3v-4.8z"/></svg>',
            download: '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false" role="img"><path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m0 0 4-4m-4 4-4-4M5 19h14"/></svg>'
        };

        function decorate(link, brand, label) {
            if (!link || link.dataset.calendarBranded === '1') return;

            link.dataset.calendarBrand = brand;
            link.dataset.calendarBranded = '1';
            link.textContent = '';

            var icon = document.createElement('span');
            icon.className = 'surfside-event-calendar-action-icon';
            icon.setAttribute('aria-hidden', 'true');
            icon.innerHTML = icons[brand];

            var text = document.createElement('span');
            text.textContent = label;

            link.appendChild(icon);
            link.appendChild(text);
        }

        function initialize() {
            document.querySelectorAll('.surfside-event-calendar-actions').forEach(function (actions) {
                var links = actions.querySelectorAll('.surfside-event-calendar-action');

                links.forEach(function (link) {
                    var url;
                    try {
                        url = new URL(link.href, window.location.href);
                    } catch (error) {
                        return;
                    }

                    var client = url.searchParams.get('client');
                    var action = url.searchParams.get('surfside_calendar_action');

                    if (client === 'apple') {
                        decorate(link, 'apple', 'Add to Apple Calendar');
                    } else if (action === 'google') {
                        decorate(link, 'google', 'Add to Google Calendar');
                    } else if (client === 'download') {
                        decorate(link, 'download', 'Download Event');
                    }
                });
            });
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initialize);
        } else {
            initialize();
        }
    })();
    </script>
    <?php
}
add_action('wp_footer', 'surfside_tools_calendar_action_branding_assets', 95);
