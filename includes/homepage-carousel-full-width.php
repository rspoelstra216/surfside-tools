<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Break the public homepage carousel out of the theme's narrow content column.
 */
function surfside_tools_homepage_carousel_full_width_styles() {
    wp_register_style(
        'surfside-tools-homepage-carousel-full-width',
        false,
        array('surfside-tools-homepage-carousel'),
        SURFSIDE_TOOLS_VERSION
    );
    wp_enqueue_style('surfside-tools-homepage-carousel-full-width');
    wp_add_inline_style(
        'surfside-tools-homepage-carousel-full-width',
        '.surfside-scroll-carousel{width:calc(100vw - 32px)!important;max-width:none!important;margin-left:50%!important;transform:translateX(-50%);overflow:hidden}'
    );
}
add_action('wp_enqueue_scripts', 'surfside_tools_homepage_carousel_full_width_styles', 99);
