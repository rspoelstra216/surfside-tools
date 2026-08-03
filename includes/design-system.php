<?php
/**
 * Shared Surfside Tools design foundation.
 *
 * @package SurfsideTools
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Load the shared design tokens and opt-in component primitives.
 */
function surfside_tools_enqueue_design_system() {
    wp_enqueue_style(
        'surfside-tools-design-system',
        SURFSIDE_TOOLS_URL . 'assets/css/design-system.css',
        array(),
        SURFSIDE_TOOLS_VERSION
    );
}
add_action('wp_enqueue_scripts', 'surfside_tools_enqueue_design_system', 5);
