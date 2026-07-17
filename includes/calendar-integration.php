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
 * Add calendar actions to every public event-details modal. Modal IDs already
 * contain the event ID and selected occurrence date, so recurring events export
 * the occurrence the visitor actually opened rather than the whole series.
 */
function surfside_tools_calendar_integration_assets() {
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
        .surfside-event-calendar-action:hover,
        .surfside-event-calendar-action:focus-visible {
            border-color: #0b4f9c;
            background: #eef6ff;
        }
        .surfside-event-calendar-action:focus-visible {
            outline: 3px solid rgba(11, 79, 156, .24);
            outline-offset: 2px;
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

        function actionUrl(action, eventId, date, client) {
            var url = new URL(baseUrl, window.location.href);
            url.searchParams.set('surfside_calendar_action', action);
            url.searchParams.set('event_id', eventId);
            url.searchParams.set('occurrence', date);
            if (client) url.searchParams.set('client', client);
            return url.toString();
        }

        function addAction(container, label, href, newWindow) {
            var link = document.createElement('a');
            link.className = 'surfside-event-calendar-action';
            link.href = href;
            link.textContent = label;
            if (newWindow) {
                link.target = '_blank';
                link.rel = 'noopener noreferrer';
            }
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

                addAction(actions, 'Apple Calendar', actionUrl('ics', eventId, date, 'apple'), false);
                addAction(actions, 'Google Calendar', actionUrl('google', eventId, date, ''), true);
                addAction(actions, 'Download ICS', actionUrl('ics', eventId, date, 'download'), false);

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
