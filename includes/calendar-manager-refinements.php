<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Small copy refinement for calendar suggestions that still need a venue.
 * Event-list state is rendered authoritatively by Calendar Manager itself.
 */
function surfside_tools_calendar_manager_refinement_assets() {
    if (!is_user_logged_in() || !current_user_can('upload_files')) {
        return;
    }

    global $post;
    if (!$post instanceof WP_Post || !has_shortcode((string) $post->post_content, 'surfside_tools_calendar_manager')) {
        return;
    }
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.surfside-calendar-location-required').forEach(function (box) {
            const label = box.querySelector('label');
            if (label) {
                Array.from(label.childNodes).forEach(function (node) {
                    if (node.nodeType === Node.TEXT_NODE && node.nodeValue.trim()) {
                        node.nodeValue = 'Where is this event being held? ';
                    }
                });
            }
            const help = box.querySelector('small');
            const card = box.closest('.surfside-calendar-suggestion');
            const meeting = card ? (card.dataset.surfsideMeetingLocation || '') : '';
            if (help) {
                help.textContent = meeting
                    ? 'We found ' + meeting + ', but still need the church, campus, or venue.'
                    : 'Enter the church, campus, or venue before saving.';
            }
        });
    });
    </script>
    <?php
}
add_action('wp_footer', 'surfside_tools_calendar_manager_refinement_assets', 50);
