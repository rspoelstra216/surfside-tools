<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Final Productivity milestone refinements:
 * - Publish summary
 * - Conservative event-title cleanup
 * - Confidence explanations
 * - Undo for newly created suggestion events
 * - Batch creation for selected new-event suggestions
 */

function surfside_tools_productivity_undo_event() {
    if (!is_user_logged_in() || !current_user_can('upload_files')) {
        wp_send_json_error(array('message' => 'You do not have permission to undo calendar events.'), 403);
    }

    check_ajax_referer('surfside_productivity_undo_event', 'nonce');

    $event_id = isset($_POST['event_id']) ? absint($_POST['event_id']) : 0;
    $post = $event_id ? get_post($event_id) : null;

    if (!$post || $post->post_type !== 'surfside_event' || $post->post_status === 'trash') {
        wp_send_json_error(array('message' => 'That calendar event is no longer available.'), 404);
    }

    if (!current_user_can('delete_post', $event_id)) {
        wp_send_json_error(array('message' => 'You do not have permission to remove that event.'), 403);
    }

    $result = wp_trash_post($event_id);
    if (!$result) {
        wp_send_json_error(array('message' => 'The event could not be moved to Trash.'), 500);
    }

    wp_send_json_success(array(
        'message' => 'The newly created event was removed.',
        'event_id' => $event_id,
    ));
}
add_action('wp_ajax_surfside_productivity_undo_event', 'surfside_tools_productivity_undo_event');

function surfside_tools_productivity_finish_assets() {
    if (!is_user_logged_in() || !current_user_can('upload_files')) {
        return;
    }

    $events = array();
    if (function_exists('surfside_tools_calendar_get_all_events')) {
        foreach (surfside_tools_calendar_get_all_events() as $event) {
            $events[] = array(
                'id' => (int) ($event['id'] ?? 0),
                'title' => (string) ($event['title'] ?? ''),
                'date' => (string) ($event['date'] ?? ''),
                'start_time' => (string) ($event['start_time'] ?? ''),
                'recurrence_type' => (string) ($event['recurrence_type'] ?? 'none'),
                'recurrence_interval' => (int) ($event['recurrence_interval'] ?? 1),
                'recurrence_weekdays' => array_values(array_map('intval', (array) ($event['recurrence_weekdays'] ?? array()))),
                'recurrence_day_of_month' => (int) ($event['recurrence_day_of_month'] ?? 0),
                'recurrence_week_of_month' => (int) ($event['recurrence_week_of_month'] ?? 0),
                'recurrence_weekday' => (int) ($event['recurrence_weekday'] ?? 0),
                'recurrence_end_date' => (string) ($event['recurrence_end_date'] ?? ''),
            );
        }
    }

    $publish_summary = null;
    if (
        isset($_POST['surfside_tools_weekly_publish_nonce']) &&
        wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['surfside_tools_weekly_publish_nonce'])), 'surfside_tools_weekly_publish')
    ) {
        $posted_summary = isset($_POST['surfside_productivity_summary'])
            ? json_decode(wp_unslash($_POST['surfside_productivity_summary']), true)
            : array();

        if (!is_array($posted_summary)) {
            $posted_summary = array();
        }

        $announcement_count = 0;
        if (isset($_POST['publish_announcements']) && $_POST['publish_announcements'] === '1' && !empty($_POST['announcement_items']) && is_array($_POST['announcement_items'])) {
            foreach (wp_unslash($_POST['announcement_items']) as $item) {
                if (trim(sanitize_textarea_field($item)) !== '') {
                    $announcement_count++;
                }
            }
        }

        $publish_summary = array(
            'announcements' => $announcement_count,
            'sermon' => isset($_POST['publish_sermon']) && $_POST['publish_sermon'] === '1',
            'events' => array_values(array_filter((array) ($posted_summary['events'] ?? array()), function ($event) {
                return is_array($event) && !empty($event['event_id']);
            })),
        );
    }

    $ajax_url = admin_url('admin-ajax.php');
    $undo_nonce = wp_create_nonce('surfside_productivity_undo_event');
    $calendar_url = home_url('/dashboard/calendar/');
    ?>
    <style>
        .surfside-productivity-batch {
            display:flex;
            flex-wrap:wrap;
            align-items:center;
            gap:.65rem;
            margin:1rem 0;
            padding:.85rem 1rem;
            border:1px solid #cbd8e5;
            border-radius:12px;
            background:#f5f8fb;
        }
        .surfside-productivity-batch button {
            padding:.58rem .9rem;
            border:0;
            border-radius:999px;
            background:#245f8f;
            color:#fff;
            font:inherit;
            font-weight:700;
            cursor:pointer;
        }
        .surfside-productivity-batch button:disabled { opacity:.65; cursor:default; }
        .surfside-productivity-batch-status { color:#40576a; font-weight:600; }
        .surfside-productivity-select {
            display:flex;
            align-items:center;
            gap:.45rem;
            margin:0 0 .6rem;
            color:#324b5f;
            font-size:.92rem;
            font-weight:700;
        }
        .surfside-productivity-select input { width:auto; margin:0; }
        .surfside-confidence-why {
            margin-top:.55rem;
        }
        .surfside-confidence-why summary {
            cursor:pointer;
            font-weight:700;
        }
        .surfside-confidence-why ul {
            margin:.55rem 0 0 1.1rem;
        }
        .surfside-confidence-why li { margin:.22rem 0; }
        .surfside-productivity-undo {
            display:inline-block;
            margin:.65rem 0 0 .55rem;
            padding:.46rem .72rem;
            border:1px solid #8b2d2d;
            border-radius:999px;
            background:#fff;
            color:#8b2d2d;
            font:inherit;
            font-weight:700;
            cursor:pointer;
        }
        .surfside-productivity-undo:disabled { opacity:.6; cursor:default; }
        .surfside-productivity-undone {
            display:block;
            margin-top:.65rem;
            color:#6a3b00;
            font-weight:700;
        }
        .surfside-productivity-publish-summary {
            margin:1rem 0 1.25rem;
            padding:1.15rem 1.25rem;
            border:1px solid #8bc391;
            border-radius:14px;
            background:#f0f9f1;
            color:#214f27;
        }
        .surfside-productivity-publish-summary h3 { margin:0 0 .65rem; }
        .surfside-productivity-publish-summary ul { margin:.5rem 0 .8rem 1.25rem; }
        .surfside-productivity-publish-summary-actions {
            display:flex;
            flex-wrap:wrap;
            gap:.65rem;
        }
        .surfside-productivity-publish-summary-actions a {
            display:inline-block;
            padding:.52rem .78rem;
            border:1px solid #2f7137;
            border-radius:999px;
            background:#fff;
            color:#2f7137;
            font-weight:700;
            text-decoration:none;
        }
    </style>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const ajaxUrl = <?php echo wp_json_encode($ajax_url); ?>;
        const undoNonce = <?php echo wp_json_encode($undo_nonce); ?>;
        const calendarUrl = <?php echo wp_json_encode($calendar_url); ?>;
        const existingEvents = <?php echo wp_json_encode($events); ?>;
        const publishSummary = <?php echo wp_json_encode($publish_summary); ?>;
        const storageKey = 'surfsideProductivityEvents';
        const announcementForm = document.querySelector('.surfside-weekly-update-publish-form, .surfside-docx-save-form');

        function clean(value) {
            return String(value || '').replace(/\s+/g, ' ').trim();
        }

        function normalize(value) {
            return clean(value)
                .toLowerCase()
                .replace(/&/g, ' and ')
                .replace(/[^a-z0-9\s]/g, ' ')
                .replace(/\b(the|a|an|at|on|in|for|of|to|and|with|our|church)\b/g, ' ')
                .replace(/\s+/g, ' ')
                .trim();
        }

        function refinedTitle(title) {
            let output = clean(title)
                .replace(/^the\s+/i, '')
                .replace(/\s+(?:classes?|sessions?|meetings?|event)$/i, '')
                .replace(/\s+(?:will be offered|will take place)$/i, '')
                .trim();

            const words = output.split(/\s+/);
            if (words.length >= 4 && /\b(?:monday|tuesday|wednesday|thursday|friday|saturday|sunday|morning|afternoon|evening)\b/i.test(output)) {
                const candidate = output
                    .replace(/\b(?:monday|tuesday|wednesday|thursday|friday|saturday|sunday)\b/ig, '')
                    .replace(/\b(?:morning|afternoon|evening)\b/ig, '')
                    .replace(/\s+/g, ' ')
                    .trim();
                if (candidate.split(/\s+/).length >= 2) {
                    output = candidate;
                }
            }

            return output || clean(title);
        }

        function loadTrackedEvents() {
            try {
                const parsed = JSON.parse(sessionStorage.getItem(storageKey) || '[]');
                return Array.isArray(parsed) ? parsed : [];
            } catch (error) {
                return [];
            }
        }

        function saveTrackedEvents(events) {
            sessionStorage.setItem(storageKey, JSON.stringify(events));
        }

        function trackEvent(eventData) {
            const events = loadTrackedEvents().filter(function (item) {
                return parseInt(item.event_id, 10) !== parseInt(eventData.event_id, 10);
            });
            events.push(eventData);
            saveTrackedEvents(events);
        }

        function untrackEvent(eventId) {
            saveTrackedEvents(loadTrackedEvents().filter(function (item) {
                return parseInt(item.event_id, 10) !== parseInt(eventId, 10);
            }));
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
            if (!event.date || !dateString || dateString < event.date) return false;
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
                const allowed = event.recurrence_weekdays && event.recurrence_weekdays.length ? event.recurrence_weekdays : [weekdayNumber(event.date)];
                return Math.floor(days / 7) % interval === 0 && allowed.includes(weekdayNumber(dateString));
            }
            if (event.recurrence_type === 'monthly_date') {
                const months = (target.getFullYear() - start.getFullYear()) * 12 + target.getMonth() - start.getMonth();
                const expectedDay = parseInt(event.recurrence_day_of_month || event.date.slice(8, 10), 10);
                return months >= 0 && months % interval === 0 && target.getDate() === expectedDay;
            }
            if (event.recurrence_type === 'monthly_weekday') {
                const months = (target.getFullYear() - start.getFullYear()) * 12 + target.getMonth() - start.getMonth();
                return months >= 0 && months % interval === 0 && Math.ceil(target.getDate() / 7) === parseInt(event.recurrence_week_of_month || 1, 10) && weekdayNumber(dateString) === parseInt(event.recurrence_weekday || 1, 10);
            }
            return false;
        }

        function minutes(time) {
            if (!time || !/^\d{2}:\d{2}$/.test(time)) return null;
            const parts = time.split(':').map(Number);
            return parts[0] * 60 + parts[1];
        }

        function cardDetails(card) {
            const titleNode = card.querySelector(':scope > strong');
            const meta = card.querySelector('.surfside-calendar-suggestion-meta');
            const parts = meta ? meta.textContent.split('·').map(function (part) { return clean(part); }) : [];
            const time = (parts[1] || '').split(/[–-]/)[0].trim();
            return {
                title: titleNode ? clean(titleNode.textContent) : '',
                date: parts[0] || '',
                start: /^\d{2}:\d{2}$/.test(time) ? time : ''
            };
        }

        function matchExplanation(card) {
            const suggestion = cardDetails(card);
            const matches = existingEvents.map(function (event) {
                const titleScore = titleSimilarity(suggestion.title, event.title);
                const titlePoints = Math.round(titleScore * 65);
                const occurs = recurrenceOccurs(event, suggestion.date);
                const recurrencePoints = occurs ? 25 : (event.recurrence_type !== 'none' && titleScore >= .65 ? 12 : 0);
                const suggestedStart = minutes(suggestion.start);
                const eventStart = minutes(event.start_time);
                let timePoints = 0;
                if (suggestedStart !== null && eventStart !== null) {
                    const difference = Math.abs(suggestedStart - eventStart);
                    if (difference === 0) timePoints = 10;
                    else if (difference <= 30) timePoints = 6;
                }
                return {
                    event: event,
                    titleScore: titleScore,
                    titlePoints: titlePoints,
                    recurrencePoints: recurrencePoints,
                    occurs: occurs,
                    timePoints: timePoints,
                    total: Math.min(100, titlePoints + recurrencePoints + timePoints)
                };
            }).sort(function (a, b) { return b.total - a.total; });
            return matches[0] || null;
        }

        function addConfidenceExplanations() {
            document.querySelectorAll('.surfside-calendar-suggestion').forEach(function (card) {
                const box = card.querySelector('.surfside-calendar-match:not(.surfside-calendar-match-new)');
                if (!box || box.querySelector('.surfside-confidence-why')) return;
                const best = matchExplanation(card);
                if (!best) return;

                const details = document.createElement('details');
                details.className = 'surfside-confidence-why';
                details.innerHTML = '<summary>Why did this match?</summary><ul></ul>';
                const list = details.querySelector('ul');

                const titleItem = document.createElement('li');
                titleItem.textContent = 'Title similarity: ' + Math.round(best.titleScore * 100) + '% (' + best.titlePoints + ' points)';
                list.appendChild(titleItem);

                const dateItem = document.createElement('li');
                dateItem.textContent = best.occurs
                    ? 'Date matches an occurrence of the existing event (' + best.recurrencePoints + ' points)'
                    : (best.recurrencePoints ? 'Recurring event with a strong title match (' + best.recurrencePoints + ' points)' : 'Date or recurrence did not add confidence');
                list.appendChild(dateItem);

                const timeItem = document.createElement('li');
                timeItem.textContent = best.timePoints
                    ? 'Start time matches or is within 30 minutes (' + best.timePoints + ' points)'
                    : 'Start time did not add confidence';
                list.appendChild(timeItem);

                const totalItem = document.createElement('li');
                totalItem.textContent = 'Total confidence: ' + best.total + '% against “' + best.event.title + '”';
                list.appendChild(totalItem);

                box.appendChild(details);
            });
        }

        function refineCardTitles() {
            document.querySelectorAll('.surfside-calendar-suggestion').forEach(function (card) {
                const titleNode = card.querySelector(':scope > strong');
                if (!titleNode) return;
                const original = clean(titleNode.textContent);
                const refined = refinedTitle(original);
                if (refined && refined !== original) {
                    card.dataset.surfsideOriginalTitle = original;
                    card.dataset.surfsideRefinedTitle = refined;
                    titleNode.textContent = refined;
                }
            });
        }

        function restoreCardAfterUndo(card) {
            card.classList.remove('is-added');
            const saveButton = card.querySelector('.surfside-calendar-save-event');
            const reviewButton = card.querySelector('.surfside-calendar-review-link');
            const recurrence = card.querySelector('.surfside-calendar-recurrence-detected');
            if (saveButton) {
                saveButton.disabled = false;
                saveButton.textContent = recurrence ? 'Save Recurring Event' : 'Save Announcement as Event';
            }
            if (reviewButton) {
                reviewButton.textContent = 'Review Details';
            }
            card.querySelectorAll('.surfside-calendar-suggestion-complete, .surfside-productivity-undo').forEach(function (node) { node.remove(); });
            if (!card.querySelector('.surfside-productivity-undone')) {
                const notice = document.createElement('span');
                notice.className = 'surfside-productivity-undone';
                notice.textContent = 'Undo complete. The event was removed and can be saved again.';
                card.appendChild(notice);
            }
        }

        function addUndoButton(card, eventData) {
            if (!card || card.querySelector('.surfside-productivity-undo')) return;
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'surfside-productivity-undo';
            let remaining = 30;
            button.textContent = 'Undo (' + remaining + 's)';
            card.appendChild(button);

            const timer = window.setInterval(function () {
                remaining--;
                if (remaining <= 0) {
                    window.clearInterval(timer);
                    button.remove();
                } else {
                    button.textContent = 'Undo (' + remaining + 's)';
                }
            }, 1000);

            button.addEventListener('click', function () {
                window.clearInterval(timer);
                button.disabled = true;
                button.textContent = 'Undoing…';
                const body = new URLSearchParams({
                    action: 'surfside_productivity_undo_event',
                    nonce: undoNonce,
                    event_id: String(eventData.event_id)
                });
                fetch(ajaxUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                    body: body.toString()
                }).then(function (response) {
                    return response.json().then(function (json) {
                        if (!response.ok || !json.success) throw json;
                        return json;
                    });
                }).then(function () {
                    untrackEvent(eventData.event_id);
                    restoreCardAfterUndo(card);
                }).catch(function (error) {
                    button.disabled = false;
                    button.textContent = 'Undo';
                    window.alert(error && error.data && error.data.message ? error.data.message : 'The event could not be undone.');
                });
            });
        }

        function findSavingCard(title) {
            const normalizedTitle = normalize(title);
            return Array.from(document.querySelectorAll('.surfside-calendar-suggestion')).find(function (card) {
                const save = card.querySelector('.surfside-calendar-save-event');
                const titleNode = card.querySelector(':scope > strong');
                return save && save.disabled && titleNode && normalize(titleNode.textContent) === normalizedTitle;
            }) || null;
        }

        function installFetchTracking() {
            const originalFetch = window.fetch;
            window.fetch = function (input, init) {
                let requestBody = null;
                let isSuggestionSave = false;
                if (init && typeof init.body === 'string' && init.body.indexOf('action=surfside_save_announcement_event') !== -1) {
                    requestBody = new URLSearchParams(init.body);
                    isSuggestionSave = true;
                    const refined = refinedTitle(requestBody.get('title') || '');
                    if (refined) {
                        requestBody.set('title', refined);
                        init = Object.assign({}, init, { body: requestBody.toString() });
                    }
                }

                return originalFetch.call(this, input, init).then(function (response) {
                    if (isSuggestionSave) {
                        response.clone().json().then(function (json) {
                            if (!json || !json.success || !json.data || !json.data.event_id) return;
                            const title = refinedTitle(requestBody.get('title') || 'New Event');
                            const eventData = {
                                event_id: parseInt(json.data.event_id, 10),
                                title: title,
                                recurrence_type: json.data.recurrence_type || 'none',
                                edit_url: json.data.edit_url || ''
                            };
                            trackEvent(eventData);
                            window.setTimeout(function () {
                                addUndoButton(findSavingCard(title) || Array.from(document.querySelectorAll('.surfside-calendar-suggestion.is-added')).pop(), eventData);
                            }, 50);
                        }).catch(function () {});
                    }
                    return response;
                });
            };
        }

        function waitForSave(button) {
            return new Promise(function (resolve) {
                const started = Date.now();
                const timer = window.setInterval(function () {
                    const done = /Added|Recurring Event Added/i.test(button.textContent) || !button.disabled;
                    if (done || Date.now() - started > 20000) {
                        window.clearInterval(timer);
                        resolve(/Added/i.test(button.textContent));
                    }
                }, 150);
            });
        }

        function addBatchControls() {
            const section = document.querySelector('.surfside-calendar-suggestions');
            if (!section || section.querySelector('.surfside-productivity-batch')) return;

            const eligibleCards = Array.from(section.querySelectorAll('.surfside-calendar-suggestion')).filter(function (card) {
                return !!card.querySelector('.surfside-calendar-match-new') && !!card.querySelector('.surfside-calendar-save-event');
            });
            if (eligibleCards.length < 2) return;

            eligibleCards.forEach(function (card, index) {
                const selector = document.createElement('label');
                selector.className = 'surfside-productivity-select';
                selector.innerHTML = '<input type="checkbox" class="surfside-productivity-checkbox" value="' + index + '"> Include in batch save';
                card.insertBefore(selector, card.firstChild);
            });

            const controls = document.createElement('div');
            controls.className = 'surfside-productivity-batch';
            controls.innerHTML = '<button type="button">Save Selected Events</button><button type="button" class="surfside-productivity-select-all">Select All New</button><span class="surfside-productivity-batch-status" aria-live="polite">Select the new events you want to create.</span>';
            const list = section.querySelector('.surfside-calendar-suggestion-list');
            section.insertBefore(controls, list);

            const saveSelected = controls.querySelector('button:not(.surfside-productivity-select-all)');
            const selectAll = controls.querySelector('.surfside-productivity-select-all');
            const status = controls.querySelector('.surfside-productivity-batch-status');

            selectAll.addEventListener('click', function () {
                eligibleCards.forEach(function (card) {
                    const checkbox = card.querySelector('.surfside-productivity-checkbox');
                    if (checkbox && !card.classList.contains('is-added')) checkbox.checked = true;
                });
                status.textContent = 'All available new events selected.';
            });

            saveSelected.addEventListener('click', async function () {
                const selected = eligibleCards.filter(function (card) {
                    const checkbox = card.querySelector('.surfside-productivity-checkbox');
                    return checkbox && checkbox.checked && !card.classList.contains('is-added');
                });
                if (!selected.length) {
                    status.textContent = 'Select at least one new event first.';
                    return;
                }

                saveSelected.disabled = true;
                selectAll.disabled = true;
                let saved = 0;
                let skipped = 0;

                for (let index = 0; index < selected.length; index++) {
                    const card = selected[index];
                    const venueInput = card.querySelector('.surfside-calendar-required-venue');
                    if (venueInput && !clean(venueInput.value)) {
                        venueInput.setAttribute('aria-invalid', 'true');
                        skipped++;
                        continue;
                    }
                    const button = card.querySelector('.surfside-calendar-save-event');
                    if (!button || button.disabled) {
                        skipped++;
                        continue;
                    }
                    status.textContent = 'Saving event ' + (index + 1) + ' of ' + selected.length + '…';
                    button.click();
                    if (await waitForSave(button)) saved++;
                    else skipped++;
                }

                saveSelected.disabled = false;
                selectAll.disabled = false;
                status.textContent = saved + ' event' + (saved === 1 ? '' : 's') + ' saved' + (skipped ? '; ' + skipped + ' need review.' : '.');
            });
        }

        function preparePublishSummary() {
            if (!announcementForm || !announcementForm.matches('.surfside-weekly-update-publish-form')) return;
            announcementForm.addEventListener('submit', function () {
                let input = announcementForm.querySelector('input[name="surfside_productivity_summary"]');
                if (!input) {
                    input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'surfside_productivity_summary';
                    announcementForm.appendChild(input);
                }
                input.value = JSON.stringify({ events: loadTrackedEvents() });
            });
        }

        function showPublishSummary() {
            if (!publishSummary) return;
            const tool = document.querySelector('.surfside-weekly-update-tool');
            if (!tool) return;

            const events = Array.isArray(publishSummary.events) ? publishSummary.events : [];
            const recurring = events.filter(function (event) { return event.recurrence_type && event.recurrence_type !== 'none'; }).length;
            const oneTime = events.length - recurring;
            const box = document.createElement('section');
            box.className = 'surfside-productivity-publish-summary';
            box.innerHTML = '<h3>Weekly Update Published</h3><ul></ul><div class="surfside-productivity-publish-summary-actions"><a href="' + calendarUrl + '">View Calendar</a></div>';
            const list = box.querySelector('ul');

            if (publishSummary.announcements) {
                const item = document.createElement('li');
                item.textContent = publishSummary.announcements + ' announcement' + (publishSummary.announcements === 1 ? '' : 's') + ' published';
                list.appendChild(item);
            }
            if (publishSummary.sermon) {
                const item = document.createElement('li');
                item.textContent = 'Sermon notes published';
                list.appendChild(item);
            }
            if (oneTime) {
                const item = document.createElement('li');
                item.textContent = oneTime + ' new calendar event' + (oneTime === 1 ? '' : 's') + ' created';
                list.appendChild(item);
            }
            if (recurring) {
                const item = document.createElement('li');
                item.textContent = recurring + ' recurring calendar series created';
                list.appendChild(item);
            }
            if (!events.length) {
                const item = document.createElement('li');
                item.textContent = 'No new calendar events were created during this review';
                list.appendChild(item);
            }

            const firstHeading = tool.querySelector('h2');
            if (firstHeading) firstHeading.insertAdjacentElement('afterend', box);
            else tool.insertBefore(box, tool.firstChild);
            sessionStorage.removeItem(storageKey);
        }

        refineCardTitles();
        addConfidenceExplanations();
        installFetchTracking();
        addBatchControls();
        preparePublishSummary();
        showPublishSummary();
    });
    </script>
    <?php
}
add_action('wp_footer', 'surfside_tools_productivity_finish_assets', 70);
