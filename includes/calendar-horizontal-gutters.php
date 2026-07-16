<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Legacy compatibility shim.
 *
 * Calendar width and centering are now owned by
 * calendar-layout-refinement.php so competing viewport rules are not emitted.
 */
function surfside_tools_calendar_horizontal_gutters() {
    return;
}
add_action('wp_enqueue_scripts', 'surfside_tools_calendar_horizontal_gutters', 120);
