<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Build and serve individual calendar occurrences for personal calendars.
 */
function surfside_tools_calendar_integration_occurrence($event_id, $date) {
    $event = function_exists('surfside_tools_calendar_get_event')
        ? surfside_tools_calendar_get_event(absint($event_id))
        : null;

    $date = function_exists('surfside_tools_calendar_date_for_input')
        ? surfside_tools_calendar_date_for_input($date)
        : '';

    if (!$event || $date === '' || !function_exists('surfside_tools_calendar_event_occurrences')) {
        return null;
    }

    $occurrences = surfside_tools_calendar_event_occurrences($event, $date, $date);
    foreach ($occurrences as $occurrence) {
        if (($occurrence['date'] ?? '') === $date) {
            return $occurrence;
        }
    }

    return null;
}

function surfside_tools_calendar_integration_times($event) {
    $timezone = wp_timezone();
    $date = (string) ($event['date'] ?? '');
    $start_time = trim((string) ($event['start_time'] ?? ''));
    $end_time = trim((string) ($event['end_time'] ?? ''));
    $all_day = !empty($event['all_day']) || $start_time === '';

    try {
        if ($all_day) {
            $start = new DateTimeImmutable($date . ' 00:00:00', $timezone);
            return array(
                'all_day' => true,
                'start' => $start,
                'end' => $start->modify('+1 day'),
            );
        }

        $start = new DateTimeImmutable($date . ' ' . $start_time, $timezone);
        $end = $end_time !== ''
            ? new DateTimeImmutable($date . ' ' . $end_time, $timezone)
            : $start->modify('+1 hour');

        if ($end <= $start) {
            $end = $end->modify('+1 day');
        }

        return array('all_day' => false, 'start' => $start, 'end' => $end);
    } catch (Exception $e) {
        return null;
    }
}

function surfside_tools_calendar_integration_description($event) {
    $description = html_entity_decode(
        wp_strip_all_tags((string) ($event['description'] ?? '')),
        ENT_QUOTES | ENT_HTML5,
        get_bloginfo('charset') ?: 'UTF-8'
    );

    return trim(preg_replace('/\s*\r?\n\s*/', "\n", $description));
}

function surfside_tools_calendar_integration_location($event) {
    $name = trim((string) ($event['location_name'] ?? $event['location'] ?? ''));
    $address = trim((string) ($event['location_address'] ?? ''));

    if ($name !== '' && $address !== '' && strcasecmp($name, $address) !== 0) {
        return $name . ', ' . $address;
    }

    return $name !== '' ? $name : $address;
}

function surfside_tools_calendar_integration_ics_escape($value) {
    $value = str_replace('\\', '\\\\', (string) $value);
    $value = str_replace(array("\r\n", "\r", "\n"), '\\n', $value);
    return str_replace(array(';', ','), array('\\;', '\\,'), $value);
}

function surfside_tools_calendar_integration_fold_line($line) {
    $line = (string) $line;
    $lines = array();

    while (strlen($line) > 73) {
        $cut = 73;
        while ($cut > 0 && (ord($line[$cut]) & 0xC0) === 0x80) {
            $cut--;
        }
        $lines[] = substr($line, 0, $cut);
        $line = ' ' . substr($line, $cut);
    }

    $lines[] = $line;
    return implode("\r\n", $lines);
}

function surfside_tools_calendar_integration_ics($event) {
    $times = surfside_tools_calendar_integration_times($event);
    if (!$times) {
        return '';
    }

    $host = wp_parse_url(home_url('/'), PHP_URL_HOST) ?: 'surfsidefellowship.org';
    $uid = 'surfside-event-' . absint($event['id'] ?? 0) . '-' . str_replace('-', '', (string) $event['date']) . '@' . $host;
    $description = surfside_tools_calendar_integration_description($event);
    $location = surfside_tools_calendar_integration_location($event);
    $lines = array(
        'BEGIN:VCALENDAR',
        'VERSION:2.0',
        'PRODID:-//Surfside Community Fellowship//Surfside Tools//EN',
        'CALSCALE:GREGORIAN',
        'METHOD:PUBLISH',
        'BEGIN:VEVENT',
        'UID:' . $uid,
        'DTSTAMP:' . gmdate('Ymd\THis\Z'),
    );

    if ($times['all_day']) {
        $lines[] = 'DTSTART;VALUE=DATE:' . $times['start']->format('Ymd');
        $lines[] = 'DTEND;VALUE=DATE:' . $times['end']->format('Ymd');
    } else {
        $lines[] = 'DTSTART:' . $times['start']->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z');
        $lines[] = 'DTEND:' . $times['end']->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z');
    }

    $lines[] = 'SUMMARY:' . surfside_tools_calendar_integration_ics_escape($event['title'] ?? 'Surfside Event');
    if ($description !== '') {
        $lines[] = 'DESCRIPTION:' . surfside_tools_calendar_integration_ics_escape($description);
    }
    if ($location !== '') {
        $lines[] = 'LOCATION:' . surfside_tools_calendar_integration_ics_escape($location);
    }
    $lines[] = 'STATUS:CONFIRMED';
    $lines[] = 'END:VEVENT';
    $lines[] = 'END:VCALENDAR';

    return implode("\r\n", array_map('surfside_tools_calendar_integration_fold_line', $lines)) . "\r\n";
}

function surfside_tools_calendar_integration_google_url($event) {
    $times = surfside_tools_calendar_integration_times($event);
    if (!$times) {
        return '';
    }

    if ($times['all_day']) {
        $dates = $times['start']->format('Ymd') . '/' . $times['end']->format('Ymd');
    } else {
        $utc = new DateTimeZone('UTC');
        $dates = $times['start']->setTimezone($utc)->format('Ymd\THis\Z')
            . '/' . $times['end']->setTimezone($utc)->format('Ymd\THis\Z');
    }

    return add_query_arg(array_filter(array(
        'action' => 'TEMPLATE',
        'text' => (string) ($event['title'] ?? 'Surfside Event'),
        'dates' => $dates,
        'details' => surfside_tools_calendar_integration_description($event),
        'location' => surfside_tools_calendar_integration_location($event),
    ), function ($value) {
        return $value !== '';
    }), 'https://calendar.google.com/calendar/render');
}

function surfside_tools_calendar_integration_endpoint_url($event_id, $date, $action = 'ics', $client = '') {
    $args = array(
        'surfside_calendar_action' => sanitize_key($action),
        'event_id' => absint($event_id),
        'occurrence' => sanitize_text_field((string) $date),
    );

    if ($client !== '') {
        $args['client'] = sanitize_key($client);
    }

    return add_query_arg($args, home_url('/'));
}

function surfside_tools_calendar_integration_handle_request() {
    $action = isset($_GET['surfside_calendar_action'])
        ? sanitize_key(wp_unslash($_GET['surfside_calendar_action']))
        : '';

    if (!in_array($action, array('ics', 'google'), true)) {
        return;
    }

    $event_id = isset($_GET['event_id']) ? absint($_GET['event_id']) : 0;
    $date = isset($_GET['occurrence']) ? sanitize_text_field(wp_unslash($_GET['occurrence'])) : '';
    $event = surfside_tools_calendar_integration_occurrence($event_id, $date);

    if (!$event) {
        status_header(404);
        nocache_headers();
        wp_die('That calendar event occurrence could not be found.', 'Event not found', array('response' => 404));
    }

    if ($action === 'google') {
        $url = surfside_tools_calendar_integration_google_url($event);
        if ($url === '') {
            wp_die('This event could not be prepared for Google Calendar.', 'Calendar error', array('response' => 500));
        }
        wp_redirect($url, 302, 'Surfside Tools');
        exit;
    }

    $ics = surfside_tools_calendar_integration_ics($event);
    if ($ics === '') {
        wp_die('This event could not be prepared as a calendar file.', 'Calendar error', array('response' => 500));
    }

    $filename = sanitize_file_name(($event['title'] ?? 'surfside-event') . '-' . $date . '.ics');
    nocache_headers();
    header('Content-Type: text/calendar; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($ics));
    echo $ics;
    exit;
}
add_action('template_redirect', 'surfside_tools_calendar_integration_handle_request', 1);

/**
 * Calendar actions only exist on the two public calendar shortcodes. Avoid
 * emitting their CSS/JavaScript on every other public page.
 */
function surfside_tools_calendar_integration_should_load_assets() {
    if (is_admin() || !is_singular()) {
        return false;
    }

    $post = get_queried_object();
    if (!($post instanceof WP_Post)) {
        return false;
    }

    $content = (string) $post->post_content;
    return has_shortcode($content, 'surfside_month_calendar')
        || has_shortcode($content, 'surfside_public_calendar');
}

/**
 * Add branded personal-calendar actions to public event-details modals. Modal
 * IDs contain the event ID and selected occurrence date, so recurring events
 * export the occurrence the visitor actually opened rather than the series.
 */
function surfside_tools_calendar_integration_assets() {
    if (!surfside_tools_calendar_integration_should_load_assets()) {
        return;
    }

    $base_url = home_url('/');
    ?>
    <style id="surfside-calendar-integration-styles">
        .surfside-event-calendar-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 9px;
            margin-top: 22px;
            padding-top: 18px;
            border-top: 1px solid rgba(7, 27, 58, .12);
        }
        .surfside-event-calendar-action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            min-height: 40px;
            border: 1px solid rgba(11, 79, 156, .28);
            border-radius: 8px;
            padding: 8px 12px;
            background: #fff;
            color: #0b4f9c;
            font-size: .9rem;
            font-weight: 800;
            line-height: 1.2;
            text-decoration: none;
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
        .surfside-event-calendar-action:hover,
        .surfside-event-calendar-action:focus-visible {
            border-color: #0b4f9c;
            background: #eef6ff;
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
        .surfside-event-calendar-action:focus-visible {
            outline: 3px solid rgba(11, 79, 156, .24);
            outline-offset: 2px;
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
        @media (max-width: 600px) {
            .surfside-event-calendar-actions { display: grid; grid-template-columns: 1fr; }
            .surfside-event-calendar-action { width: 100%; min-height: 46px; }
        }
    </style>
    <script id="surfside-calendar-integration-script">
    (function () {
        'use strict';
        var baseUrl = <?php echo wp_json_encode($base_url); ?>;
        var icons = {
            apple: '<svg viewBox="0 0 384 512" aria-hidden="true" focusable="false" role="img"><path fill="currentColor" d="M279.55 258.94c-.2-36.7 16.4-64.4 50-84.8-18.8-26.9-47.2-41.7-84.7-44.6-35.5-2.8-74.3 20.7-88.5 20.7-15 0-49.4-19.7-72.8-19.7-31 0-60.2 18-76.1 47.9-32.2 55.9-8.2 138.3 23.1 183.7 15.6 22.5 34.2 47.8 58.6 46.8 23.2-.9 32-15 60.1-15 28.1 0 36 15 60.5 14.5 25-.4 40.8-22.7 56.3-45.3 18-26.3 25.4-51.8 25.8-53.1-.6-.3-49.6-19-49.8-75.1zm-24.7-166.3c12.7-15.1 21.3-36.1 19-56.9-18.3.7-40.4 12.9-53.3 28-11.6 13.4-21.7 34.7-19 55.1 20.4 1.6 40.6-10.3 53.3-26.2z"/></svg>',
            google: '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false" role="img"><path fill="#4285F4" d="M18 3h-1V1h-2v2H9V1H7v2H6a3 3 0 0 0-3 3v12a3 3 0 0 0 3 3h12a3 3 0 0 0 3-3V6a3 3 0 0 0-3-3z"/><path fill="#34A853" d="M3 9h18v9a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3V9z"/><path fill="#FBBC04" d="M3 14h9v7H6a3 3 0 0 1-3-3v-4z"/><path fill="#EA4335" d="M6 3h12a3 3 0 0 1 3 3v3H3V6a3 3 0 0 1 3-3z"/><rect x="7" y="8" width="10" height="10" rx="1" fill="#fff"/><path fill="#4285F4" d="M9.2 11.1h2.7v1.2h-1.4v.8h1.2v1.1h-1.2v1.7H9.2v-4.8zm3.5 0h1.3v4.8h-1.3v-4.8zm2.2 0h1.3v4.8h-1.3v-4.8z"/></svg>',
            download: '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false" role="img"><path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m0 0 4-4m-4 4-4-4M5 19h14"/></svg>'
        };

        function actionUrl(action, eventId, date, client) {
            var url = new URL(baseUrl, window.location.href);
            url.searchParams.set('surfside_calendar_action', action);
            url.searchParams.set('event_id', eventId);
            url.searchParams.set('occurrence', date);
            if (client) url.searchParams.set('client', client);
            return url.toString();
        }

        function addAction(container, brand, label, href, newWindow) {
            var link = document.createElement('a');
            link.className = 'surfside-event-calendar-action';
            link.dataset.calendarBrand = brand;
            link.href = href;
            if (newWindow) {
                link.target = '_blank';
                link.rel = 'noopener noreferrer';
            }

            var icon = document.createElement('span');
            icon.className = 'surfside-event-calendar-action-icon';
            icon.setAttribute('aria-hidden', 'true');
            icon.innerHTML = icons[brand];

            var text = document.createElement('span');
            text.textContent = label;

            link.appendChild(icon);
            link.appendChild(text);
            container.appendChild(link);
        }

        function initialize() {
            document.querySelectorAll('.surfside-event-modal[id^="surfside-event-detail-"]').forEach(function (modal) {
                if (modal.dataset.surfsideCalendarActions === '1') return;

                var match = modal.id.match(/^surfside-event-detail-(\d+)-(\d{8})$/);
                var card = modal.querySelector('.surfside-event-modal-card');
                if (!match || !card) return;

                var eventId = match[1];
                var rawDate = match[2];
                var date = rawDate.slice(0, 4) + '-' + rawDate.slice(4, 6) + '-' + rawDate.slice(6, 8);
                var actions = document.createElement('div');
                actions.className = 'surfside-event-calendar-actions';
                actions.setAttribute('aria-label', 'Add this event to a personal calendar');

                addAction(actions, 'apple', 'Add to Apple Calendar', actionUrl('ics', eventId, date, 'apple'), false);
                addAction(actions, 'google', 'Add to Google Calendar', actionUrl('google', eventId, date, ''), true);
                addAction(actions, 'download', 'Download Event', actionUrl('ics', eventId, date, 'download'), false);

                card.appendChild(actions);
                modal.dataset.surfsideCalendarActions = '1';
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
add_action('wp_footer', 'surfside_tools_calendar_integration_assets', 90);
