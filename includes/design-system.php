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
    $style_path = SURFSIDE_TOOLS_PATH . 'assets/css/design-system.css';
    $style_version = file_exists($style_path)
        ? (string) filemtime($style_path)
        : SURFSIDE_TOOLS_VERSION;

    wp_enqueue_style(
        'surfside-tools-design-system',
        SURFSIDE_TOOLS_URL . 'assets/css/design-system.css',
        array(),
        $style_version
    );
}
add_action('wp_enqueue_scripts', 'surfside_tools_enqueue_design_system', 5);
