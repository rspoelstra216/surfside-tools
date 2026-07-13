<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Adds client-side calendar suggestions to the weekly announcement review screen
 * and prefills the calendar editor from a selected suggestion.
 */
function surfside_tools_calendar_suggestions_assets() {
    if (!is_user_logged_in() || !current_user_can('upload_files')) {
        return;
    }

    $calendar_url = home_url('/dashboard/calendar/');
    ?>
    <style>
        .surfside-calendar-suggestions {
            margin: 1.5rem 0;
            padding: 1.25rem;
            border: 1px solid rgba(15, 45, 82, 0.14);
            border-radius: 14px;
            background: #f7f9fc;
        }
        .surfside-calendar-suggestions h3 { margin-top: 0; }
        .surfside-calendar-suggestion-list {
            display: grid;
            gap: 0.9rem;
        }
        .surfside-calendar-suggestion {
            padding: 1rem;
            border: 1px solid rgba(15, 45, 82, 0.12);
            border-radius: 12px;
            background: #fff;
        }
        .surfside-calendar-suggestion strong { display: block; margin-bottom: 0.35rem; }
        .surfside-calendar-suggestion-meta { margin: 0 0 0.75rem; color: #4b5563; }
        .surfside-calendar-suggestion-button {
            display: inline-block;
            padding: 0.6rem 0.9rem;
            border: 0;
            border-radius: 999px;
            background: #0f5ca8;
            color: #fff;
            font: inherit;
            font-weight: 600;
            cursor: pointer;
        }
        .surfside-calendar-suggestion-note { margin-bottom: 0; font-size: 0.92rem; color: #4b5563; }
        .surfside-calendar-suggestion-modal[hidden] { display: none; }
        .surfside-calendar-suggestion-modal {
            position: fixed;
            inset: 0;
            z-index: 100000;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            background: rgba(15, 23, 42, 0.72);
        }
        .surfside-calendar-suggestion-dialog {
            width: min(1180px, 96vw);
            height: min(860px, 92vh);
            display: flex;
            flex-direction: column;
            overflow: hidden;
            border-radius: 16px;
            background: #fff;
            box-shadow: 0 24px 80px rgba(0, 0, 0, 0.3);
        }
        .surfside-calendar-suggestion-modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 0.85rem 1rem;
            border-bottom: 1px solid #e5e7eb;
        }
        .surfside-calendar-suggestion-modal-header strong { font-size: 1.05rem; }
        .surfside-calendar-suggestion-modal-actions {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .surfside-calendar-suggestion-modal-actions a {
            color: #0f5ca8;
            font-weight: 600;
        }
        .surfside-calendar-suggestion-close {
            width: 2.25rem;
            height: 2.25rem;
            border: 0;
            border-radius: 999px;
            background: #eef2f7;
            font-size: 1.35rem;
            line-height: 1;
            cursor: pointer;
        }
        .surfside-calendar-suggestion-frame {
            width: 100%;
            flex: 1;
            border: 0;
            background: #fff;
        }
        body.surfside-calendar-modal-open { overflow: hidden; }
        @media (max-width: 700px) {
            .surfside-calendar-suggestion-modal { padding: 0; }
            .surfside-calendar-suggestion-dialog {
                width: 100vw;
                height: 100vh;
                border-radius: 0;
            }
            .surfside-calendar-suggestion-modal-header { align-items: flex-start; }
            .surfside-calendar-suggestion-modal-actions { gap: 0.5rem; }
        }
    </style>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const calendarUrl = <?php echo wp_json_encode($calendar_url); ?>;

        function pad(value) {
            return String(value).padStart(2, '0');
        }

        function normalizeTime(hour, minute, meridiem) {
            let h = parseInt(hour, 10);
            const m = parseInt(minute || '0', 10);
            const suffix = (meridiem || '').toLowerCase();
            if (suffix === 'pm' && h < 12) h += 12;
            if (suffix === 'am' && h === 12) h = 0;
            return pad(h) + ':' + pad(m);
        }

        function parseDate(text, fallbackYear) {
            const months = {
                january:0, february:1, march:2, april:3, may:4, june:5,
                july:6, august:7, september:8, october:9, november:10, december:11,
                jan:0, feb:1, mar:2, apr:3, jun:5, jul:6, aug:7, sep:8, sept:8, oct:9, nov:10, dec:11
            };
            const match = text.match(/\b(January|February|March|April|May|June|July|August|September|October|November|December|Jan\.?|Feb\.?|Mar\.?|Apr\.?|Jun\.?|Jul\.?|Aug\.?|Sep\.?|Sept\.?|Oct\.?|Nov\.?|Dec\.?)\s+(\d{1,2})(?:st|nd|rd|th)?(?:,?\s+(20\d{2}))?/i);
            if (!match) return '';
            const key = match[1].replace('.', '').toLowerCase();
            const explicitYear = match[3] ? parseInt(match[3], 10) : 0;
            let year = explicitYear || parseInt(fallbackYear, 10);
            let date = new Date(year, months[key], parseInt(match[2], 10));

            if (!explicitYear) {
                const today = new Date();
                const ninetyDaysAgo = new Date(today.getFullYear(), today.getMonth(), today.getDate() - 90);
                if (date < ninetyDaysAgo) {
                    date = new Date(year + 1, months[key], parseInt(match[2], 10));
                }
            }

            return date.getFullYear() + '-' + pad(date.getMonth() + 1) + '-' + pad(date.getDate());
        }

        function parseTimes(text) {
            const range = text.match(/\b(\d{1,2})(?::(\d{2}))?\s*(am|pm)?\s*[–—-]\s*(\d{1,2})(?::(\d{2}))?\s*(am|pm)\b/i);
            if (!range) return { start: '', end: '' };
            const startMeridiem = range[3] || range[6];
            return {
                start: normalizeTime(range[1], range[2], startMeridiem),
                end: normalizeTime(range[4], range[5], range[6])
            };
        }

        function titleFromAnnouncement(text) {
            const monthPattern = '(?:January|February|March|April|May|June|July|August|September|October|November|December|Jan\\.?|Feb\\.?|Mar\\.?|Apr\\.?|Jun\\.?|Jul\\.?|Aug\\.?|Sep\\.?|Sept\\.?|Oct\\.?|Nov\\.?|Dec\\.?)';
            let title = text
                .replace(/^\s*\d+[.)]\s*/, '')
                .replace(new RegExp('^Beginning\\s+(?:Monday|Tuesday|Wednesday|Thursday|Friday|Saturday|Sunday),?\\s+' + monthPattern + '\\s+\\d{1,2}(?:st|nd|rd|th)?,?\\s*', 'i'), '')
                .split(new RegExp('\\s+(?:will be|is on|will take place|meets? on|on\\s+(?:Monday|Tuesday|Wednesday|Thursday|Friday|Saturday|Sunday|' + monthPattern + '))\\b', 'i'))[0]
                .split(/[.!?]/)[0]
                .trim();

            if (/^the\s+adult\s+mission\s+trip\s+to\b/i.test(title)) {
                title = 'Adult Mission Trip';
            } else {
                title = title.replace(/^the\s+/i, '');
            }

            if (title.length > 90) title = title.slice(0, 87).trim() + '…';
            return title || 'New Event';
        }

        function buildSuggestion(text, fallbackYear) {
            const date = parseDate(text, fallbackYear);
            if (!date) return null;
            const times = parseTimes(text);
            return {
                title: titleFromAnnouncement(text),
                date: date,
                start: times.start,
                end: times.end,
                description: text.trim()
            };
        }

        function createModal() {
            const modal = document.createElement('div');
            modal.className = 'surfside-calendar-suggestion-modal';
            modal.hidden = true;
            modal.innerHTML = '<div class="surfside-calendar-suggestion-dialog" role="dialog" aria-modal="true" aria-labelledby="surfside-calendar-modal-title"><div class="surfside-calendar-suggestion-modal-header"><strong id="surfside-calendar-modal-title">Review Calendar Suggestion</strong><div class="surfside-calendar-suggestion-modal-actions"><a target="_blank" rel="noopener">Open in new tab</a><button type="button" class="surfside-calendar-suggestion-close" aria-label="Close calendar review">×</button></div></div><iframe class="surfside-calendar-suggestion-frame" title="Calendar Manager"></iframe></div>';
            document.body.appendChild(modal);

            const frame = modal.querySelector('iframe');
            const newTab = modal.querySelector('a');
            const closeButton = modal.querySelector('.surfside-calendar-suggestion-close');

            function closeModal() {
                modal.hidden = true;
                frame.src = 'about:blank';
                document.body.classList.remove('surfside-calendar-modal-open');
            }

            closeButton.addEventListener('click', closeModal);
            modal.addEventListener('click', function (event) {
                if (event.target === modal) closeModal();
            });
            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && !modal.hidden) closeModal();
            });

            return {
                open: function (url) {
                    frame.src = url;
                    newTab.href = url;
                    modal.hidden = false;
                    document.body.classList.add('surfside-calendar-modal-open');
                    closeButton.focus();
                }
            };
        }

        const modalController = createModal();
        const announcementForm = document.querySelector('.surfside-weekly-update-publish-form, .surfside-docx-save-form');
        if (announcementForm) {
            const textareas = Array.from(announcementForm.querySelectorAll('textarea[name="announcement_items[]"]'));
            const announcementDate = announcementForm.querySelector('[name="announcement_date"]');
            const yearMatch = announcementDate && announcementDate.value.match(/20\d{2}/);
            const detectedYear = yearMatch ? parseInt(yearMatch[0], 10) : 0;
            const currentYear = new Date().getFullYear();
            const fallbackYear = detectedYear >= currentYear ? detectedYear : currentYear;
            const suggestions = textareas.map(function (textarea) {
                return buildSuggestion(textarea.value, fallbackYear);
            }).filter(Boolean);

            if (suggestions.length) {
                const section = document.createElement('section');
                section.className = 'surfside-calendar-suggestions';
                section.innerHTML = '<h3>Calendar Suggestions</h3><p>These announcements appear to include an event date. Review each suggestion in the Calendar Manager before saving it.</p><div class="surfside-calendar-suggestion-list"></div>';
                const list = section.querySelector('.surfside-calendar-suggestion-list');

                suggestions.forEach(function (suggestion) {
                    const params = new URLSearchParams({
                        suggestion: '1',
                        event_title: suggestion.title,
                        event_date: suggestion.date,
                        event_start_time: suggestion.start,
                        event_end_time: suggestion.end,
                        event_description: suggestion.description
                    });
                    const reviewUrl = calendarUrl + '?' + params.toString();
                    const card = document.createElement('article');
                    card.className = 'surfside-calendar-suggestion';
                    const timeLabel = suggestion.start ? suggestion.start + (suggestion.end ? '–' + suggestion.end : '') : 'Time not detected';
                    card.innerHTML = '<strong></strong><p class="surfside-calendar-suggestion-meta"></p><button type="button" class="surfside-calendar-suggestion-button">Review in Calendar</button>';
                    card.querySelector('strong').textContent = suggestion.title;
                    card.querySelector('.surfside-calendar-suggestion-meta').textContent = suggestion.date + ' · ' + timeLabel;
                    card.querySelector('button').addEventListener('click', function () {
                        modalController.open(reviewUrl);
                    });
                    list.appendChild(card);
                });

                const announcementReview = announcementForm.querySelector('.surfside-weekly-review-section');
                const preview = announcementForm.querySelector('.surfside-docx-preview, .surfside-announcements');
                if (preview) {
                    preview.insertAdjacentElement('afterend', section);
                } else if (announcementReview) {
                    announcementReview.appendChild(section);
                } else {
                    announcementForm.appendChild(section);
                }
            }
        }

        const calendarForm = document.querySelector('.surfside-calendar-form');
        const query = new URLSearchParams(window.location.search);
        if (calendarForm && query.get('suggestion') === '1') {
            const mappings = {
                event_title: 'event_title',
                event_date: 'event_date',
                event_start_time: 'event_start_time',
                event_end_time: 'event_end_time',
                event_description: 'event_description'
            };
            Object.keys(mappings).forEach(function (key) {
                const value = query.get(key);
                const field = calendarForm.querySelector('[name="' + mappings[key] + '"]');
                if (field && value) field.value = value;
            });

            const notice = document.createElement('div');
            notice.className = 'surfside-calendar-note surfside-calendar-status';
            notice.innerHTML = '<div><strong>Calendar suggestion loaded</strong><br>Review the title, date, time, location, recurrence, and description before adding the event.</div>';
            calendarForm.parentNode.insertBefore(notice, calendarForm);
        }
    });
    </script>
    <?php
}
add_action('wp_footer', 'surfside_tools_calendar_suggestions_assets', 30);
