<?php

if (!defined('ABSPATH')) {
    exit;
}

function surfside_tools_calendar_suggestion_duplicate_assets() {
    if (!is_user_logged_in() || !current_user_can('upload_files') || !function_exists('surfside_tools_calendar_get_all_events')) {
        return;
    }

    $events = array();
    foreach (surfside_tools_calendar_get_all_events() as $event) {
        $events[] = array(
            'id' => (int) $event['id'],
            'title' => (string) $event['title'],
            'date' => (string) $event['date'],
            'start_time' => (string) $event['start_time'],
            'end_time' => (string) $event['end_time'],
            'location' => (string) ($event['location_name'] ?? ''),
            'recurrence_type' => (string) ($event['recurrence_type'] ?? 'none'),
            'recurrence_interval' => (int) ($event['recurrence_interval'] ?? 1),
            'recurrence_weekdays' => array_values(array_map('intval', (array) ($event['recurrence_weekdays'] ?? array()))),
            'recurrence_day_of_month' => (int) ($event['recurrence_day_of_month'] ?? 0),
            'recurrence_week_of_month' => (int) ($event['recurrence_week_of_month'] ?? 0),
            'recurrence_weekday' => (int) ($event['recurrence_weekday'] ?? 0),
            'recurrence_end_date' => (string) ($event['recurrence_end_date'] ?? ''),
        );
    }

    $calendar_url = home_url('/dashboard/calendar/');
    ?>
    <style>
        .surfside-calendar-match {
            margin: 0 0 .8rem;
            padding: .75rem .85rem;
            border-radius: 10px;
            font-size: .94rem;
        }
        .surfside-calendar-match strong { display: inline; }
        .surfside-calendar-match-new { background: #eef8f0; color: #245f2a; }
        .surfside-calendar-match-possible { background: #fff8e6; color: #7a5600; }
        .surfside-calendar-match-likely { background: #fff0dc; color: #814500; }
        .surfside-calendar-match-exact { background: #fdecec; color: #8b2323; }
        .surfside-calendar-match-actions { display: flex; flex-wrap: wrap; gap: .65rem; margin-top: .65rem; }
        .surfside-calendar-match-actions a { font-weight: 700; }
    </style>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const existingEvents = <?php echo wp_json_encode($events); ?>;
        const calendarUrl = <?php echo wp_json_encode($calendar_url); ?>;

        function normalize(text) {
            return String(text || '')
                .toLowerCase()
                .replace(/&/g, ' and ')
                .replace(/[^a-z0-9\s]/g, ' ')
                .replace(/\b(the|a|an|at|on|in|for|of|to|and|with|our|church)\b/g, ' ')
                .replace(/\s+/g, ' ')
                .trim();
        }

        function titleSimilarity(a, b) {
            const left = normalize(a).split(' ').filter(Boolean);
            const right = normalize(b).split(' ').filter(Boolean);
            if (!left.length || !right.length) return 0;
            const setA = new Set(left);
            const setB = new Set(right);
            const intersection = [...setA].filter(function (word) { return setB.has(word); }).length;
            const union = new Set(left.concat(right)).size;
            let score = union ? intersection / union : 0;
            if (normalize(a) === normalize(b)) score = 1;
            else if (normalize(a).includes(normalize(b)) || normalize(b).includes(normalize(a))) score = Math.max(score, .88);
            return score;
        }

        function weekdayNumber(dateString) {
            const day = new Date(dateString + 'T12:00:00').getDay();
            return day === 0 ? 7 : day;
        }

        function recurrenceOccurs(event, dateString) {
            if (!event.date || dateString < event.date) return false;
            if (event.recurrence_end_date && dateString > event.recurrence_end_date) return false;
            if (event.recurrence_type === 'none') return event.date === dateString;

            const start = new Date(event.date + 'T12:00:00');
            const target = new Date(dateString + 'T12:00:00');
            const days = Math.round((target - start) / 86400000);
            const interval = Math.max(1, parseInt(event.recurrence_interval || 1, 10));
            if (days < 0) return false;

            if (event.recurrence_type === 'daily') {
                const allowed = event.recurrence_weekdays || [];
                return days % interval === 0 && (!allowed.length || allowed.includes(weekdayNumber(dateString)));
            }
            if (event.recurrence_type === 'weekly') {
                const allowed = (event.recurrence_weekdays && event.recurrence_weekdays.length) ? event.recurrence_weekdays : [weekdayNumber(event.date)];
                return Math.floor(days / 7) % interval === 0 && allowed.includes(weekdayNumber(dateString));
            }
            if (event.recurrence_type === 'monthly_date') {
                const targetDay = parseInt(dateString.slice(8, 10), 10);
                const expectedDay = parseInt(event.recurrence_day_of_month || event.date.slice(8, 10), 10);
                const months = (target.getFullYear() - start.getFullYear()) * 12 + target.getMonth() - start.getMonth();
                return months >= 0 && months % interval === 0 && targetDay === expectedDay;
            }
            if (event.recurrence_type === 'monthly_weekday') {
                const months = (target.getFullYear() - start.getFullYear()) * 12 + target.getMonth() - start.getMonth();
                const week = Math.ceil(target.getDate() / 7);
                return months >= 0 && months % interval === 0 && week === parseInt(event.recurrence_week_of_month || 1, 10) && weekdayNumber(dateString) === parseInt(event.recurrence_weekday || 1, 10);
            }
            return false;
        }

        function minutes(time) {
            if (!time) return null;
            const parts = time.split(':').map(Number);
            return parts[0] * 60 + parts[1];
        }

        function scoreMatch(suggestion, event) {
            const titleScore = titleSimilarity(suggestion.title, event.title);
            let score = Math.round(titleScore * 65);
            const occurs = recurrenceOccurs(event, suggestion.date);
            if (occurs) score += 25;
            else if (event.recurrence_type !== 'none' && titleScore >= .65) score += 12;

            const suggestedStart = minutes(suggestion.start);
            const eventStart = minutes(event.start_time);
            if (suggestedStart !== null && eventStart !== null) {
                const difference = Math.abs(suggestedStart - eventStart);
                if (difference === 0) score += 10;
                else if (difference <= 30) score += 6;
            }
            return Math.min(100, score);
        }

        function parseCard(card) {
            const title = card.querySelector('strong') ? card.querySelector('strong').textContent.trim() : '';
            const meta = card.querySelector('.surfside-calendar-suggestion-meta');
            const text = meta ? meta.textContent.trim() : '';
            const parts = text.split('·').map(function (part) { return part.trim(); });
            const times = (parts[1] || '').split(/[–-]/).map(function (part) { return part.trim(); });
            return { title: title, date: parts[0] || '', start: times[0] && times[0].includes(':') ? times[0] : '' };
        }

        document.querySelectorAll('.surfside-calendar-suggestion').forEach(function (card) {
            const suggestion = parseCard(card);
            const matches = existingEvents.map(function (event) {
                return { event: event, score: scoreMatch(suggestion, event) };
            }).sort(function (a, b) { return b.score - a.score; });

            const best = matches[0];
            let level = 'new';
            let label = 'New event';
            let detail = 'No similar calendar entry was found.';

            if (best && best.score >= 95) {
                level = 'exact';
                label = 'Exact match';
                detail = best.event.title + (best.event.recurrence_type !== 'none' ? ' · recurring ' + best.event.recurrence_type : ' · ' + best.event.date);
            } else if (best && best.score >= 78) {
                level = 'likely';
                label = 'Likely existing event (' + best.score + '%)';
                detail = best.event.title + (best.event.recurrence_type !== 'none' ? ' · recurring ' + best.event.recurrence_type : ' · ' + best.event.date);
            } else if (best && best.score >= 55) {
                level = 'possible';
                label = 'Possible match (' + best.score + '%)';
                detail = best.event.title + (best.event.recurrence_type !== 'none' ? ' · recurring ' + best.event.recurrence_type : ' · ' + best.event.date);
            }

            const box = document.createElement('div');
            box.className = 'surfside-calendar-match surfside-calendar-match-' + level;
            box.innerHTML = '<strong></strong><br><span></span>';
            box.querySelector('strong').textContent = label;
            box.querySelector('span').textContent = detail;

            if (best && best.score >= 55) {
                const actions = document.createElement('div');
                actions.className = 'surfside-calendar-match-actions';
                const edit = document.createElement('a');
                edit.href = calendarUrl + '?edit_event=' + encodeURIComponent(best.event.id);
                edit.target = '_blank';
                edit.rel = 'noopener';
                edit.textContent = 'View/Edit Existing';
                actions.appendChild(edit);
                box.appendChild(actions);

                if (best.score >= 78) {
                    const reviewButton = card.querySelector('.surfside-calendar-suggestion-button');
                    if (reviewButton) reviewButton.textContent = 'Review or Create Anyway';
                }
            }

            const meta = card.querySelector('.surfside-calendar-suggestion-meta');
            if (meta) meta.insertAdjacentElement('afterend', box);
        });
    });
    </script>
    <?php
}
add_action('wp_footer', 'surfside_tools_calendar_suggestion_duplicate_assets', 35);
