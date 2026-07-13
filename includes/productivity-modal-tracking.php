<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Include events created through the Calendar Manager review modal in the
 * final Weekly Update publish summary. One-click events are tracked by the
 * main Productivity module because their AJAX response includes the event ID.
 */
function surfside_tools_productivity_modal_tracking_assets() {
    if (!is_user_logged_in() || !current_user_can('upload_files')) {
        return;
    }
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const storageKey = 'surfsideProductivityEvents';
        let activeCard = null;

        function clean(value) {
            return String(value || '').replace(/\s+/g, ' ').trim();
        }

        function loadEvents() {
            try {
                const parsed = JSON.parse(sessionStorage.getItem(storageKey) || '[]');
                return Array.isArray(parsed) ? parsed : [];
            } catch (error) {
                return [];
            }
        }

        document.addEventListener('click', function (event) {
            const review = event.target.closest('.surfside-calendar-suggestion-button, .surfside-calendar-review-link');
            if (!review || /Saved Event|View\/Edit Existing/i.test(review.textContent)) return;
            activeCard = review.closest('.surfside-calendar-suggestion');
        }, true);

        window.addEventListener('message', function (event) {
            if (event.origin !== window.location.origin || !event.data || event.data.type !== 'surfside-calendar-suggestion-saved' || !activeCard) {
                return;
            }

            const titleNode = activeCard.querySelector(':scope > strong');
            const recurring = !!activeCard.querySelector('.surfside-calendar-recurrence-detected');
            const item = {
                event_id: -Date.now(),
                title: titleNode ? clean(titleNode.textContent) : 'Calendar event',
                recurrence_type: recurring ? 'recurring' : 'none',
                edit_url: '',
                source: 'review-modal'
            };

            const events = loadEvents();
            events.push(item);
            sessionStorage.setItem(storageKey, JSON.stringify(events));
            activeCard = null;
        });
    });
    </script>
    <?php
}
add_action('wp_footer', 'surfside_tools_productivity_modal_tracking_assets', 72);
