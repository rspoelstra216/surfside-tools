<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Keep monthly-calendar overflow controls visible and recover a missing
 * control from the already-rendered Day Details modal when necessary.
 */
function surfside_tools_calendar_day_details_overflow_fix() {
    if (!wp_script_is('surfside-tools-calendar-day-details', 'enqueued')) {
        return;
    }

    wp_add_inline_style('surfside-tools-calendar-manager', '
        .surfside-month-calendar-day-events .surfside-month-calendar-more-wrap {
            display:block !important;
            visibility:visible !important;
            opacity:1 !important;
            width:100% !important;
            margin:0 !important;
            padding:0 !important;
        }
        .surfside-month-calendar-day-events .surfside-month-calendar-more {
            display:flex !important;
            visibility:visible !important;
            opacity:1 !important;
            width:100% !important;
            position:relative !important;
            z-index:2 !important;
        }
    ');

    wp_add_inline_script('surfside-tools-calendar-day-details', <<<'JS'
(function () {
    'use strict';

    function repairOverflowButtons() {
        document.querySelectorAll('.surfside-day-modal[id^="surfside-day-detail-"]').forEach(function (modal) {
            var dayCell = modal.previousElementSibling;
            if (!dayCell || !dayCell.classList.contains('surfside-month-calendar-day')) return;

            var eventsContainer = dayCell.querySelector('.surfside-month-calendar-day-events');
            if (!eventsContainer) return;

            var overflowCount = modal.querySelectorAll('[data-surfside-day-event]').length - 2;
            if (overflowCount < 1) return;

            var button = eventsContainer.querySelector('[data-surfside-day-open]');
            if (!button) {
                var wrapper = document.createElement('div');
                wrapper.className = 'surfside-month-calendar-more-wrap';

                button = document.createElement('button');
                button.type = 'button';
                button.className = 'surfside-month-calendar-more';
                button.setAttribute('data-surfside-day-open', '');
                button.setAttribute('aria-haspopup', 'dialog');
                button.setAttribute('aria-controls', modal.id);
                wrapper.appendChild(button);
                eventsContainer.appendChild(wrapper);
            } else if (!button.parentElement.classList.contains('surfside-month-calendar-more-wrap')) {
                var existingWrapper = document.createElement('div');
                existingWrapper.className = 'surfside-month-calendar-more-wrap';
                button.parentNode.insertBefore(existingWrapper, button);
                existingWrapper.appendChild(button);
            }

            button.textContent = '+' + overflowCount + ' more ' + (overflowCount === 1 ? 'event' : 'events');
            button.style.setProperty('display', 'flex', 'important');
            button.style.setProperty('visibility', 'visible', 'important');
            button.style.setProperty('opacity', '1', 'important');
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', repairOverflowButtons);
    } else {
        repairOverflowButtons();
    }
})();
JS
    );
}
add_action('wp_enqueue_scripts', 'surfside_tools_calendar_day_details_overflow_fix', 100);
