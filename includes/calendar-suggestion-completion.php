<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Connects the calendar suggestion modal back to the Weekly Update page.
 * After an event is saved in the modal, the modal closes and the suggestion
 * card is marked complete without losing the unsaved Weekly Update review.
 */
function surfside_tools_calendar_suggestion_completion_assets() {
    if (!is_user_logged_in() || !current_user_can('upload_files')) {
        return;
    }
    ?>
    <style>
        .surfside-calendar-suggestion.is-added {
            border-color: #86c98b;
            background: #f0f9f1;
        }
        .surfside-calendar-suggestion.is-added .surfside-calendar-suggestion-button {
            background: #2f7d32;
            cursor: default;
        }
        .surfside-calendar-suggestion-complete {
            display: block;
            margin-top: 0.65rem;
            color: #256c2b;
            font-weight: 700;
        }
    </style>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        let activeSuggestionButton = null;

        document.addEventListener('click', function (event) {
            const button = event.target.closest('.surfside-calendar-suggestion-button');
            if (!button || button.disabled) {
                return;
            }
            activeSuggestionButton = button;
        }, true);

        window.addEventListener('message', function (event) {
            if (event.origin !== window.location.origin || !event.data || event.data.type !== 'surfside-calendar-suggestion-saved') {
                return;
            }

            if (activeSuggestionButton) {
                const card = activeSuggestionButton.closest('.surfside-calendar-suggestion');
                if (card) {
                    card.classList.add('is-added');
                    activeSuggestionButton.textContent = 'Added to Calendar';
                    activeSuggestionButton.disabled = true;

                    if (!card.querySelector('.surfside-calendar-suggestion-complete')) {
                        const status = document.createElement('span');
                        status.className = 'surfside-calendar-suggestion-complete';
                        status.textContent = '✓ Event saved. Continue reviewing the Weekly Update below.';
                        card.appendChild(status);
                    }
                }
            }

            const closeButton = document.querySelector('.surfside-calendar-suggestion-close');
            if (closeButton) {
                closeButton.click();
            }
            activeSuggestionButton = null;
        });

        const query = new URLSearchParams(window.location.search);
        if (query.get('suggestion') !== '1') {
            return;
        }

        const calendarForm = document.querySelector('.surfside-calendar-form');
        if (calendarForm) {
            const submitButton = calendarForm.querySelector('button[type="submit"]');
            if (submitButton) {
                submitButton.textContent = 'Save Event & Return to Weekly Update';
            }
        }

        const successNotice = Array.from(document.querySelectorAll('.surfside-calendar-notice.surfside-calendar-success')).find(function (notice) {
            return notice.textContent.trim().toLowerCase().indexOf('event saved') !== -1;
        });

        if (successNotice && window.parent !== window) {
            window.parent.postMessage({
                type: 'surfside-calendar-suggestion-saved'
            }, window.location.origin);
        }
    });
    </script>
    <?php
}
add_action('wp_footer', 'surfside_tools_calendar_suggestion_completion_assets', 40);
