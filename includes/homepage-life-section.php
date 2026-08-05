<?php
/**
 * Plugin-rendered Life at Surfside homepage section.
 *
 * @package SurfsideTools
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Render the homepage photo story section.
 *
 * @param array $attributes Shortcode attributes.
 * @return string
 */
function surfside_tools_life_at_surfside_shortcode($attributes = array()) {
    $attributes = shortcode_atts(
        array(
            'title' => 'Life at Surfside',
            'intro' => 'From worship services and Bible studies to outreach projects, fellowship events, and children’s ministry, here’s a glimpse of life at Surfside.',
        ),
        $attributes,
        'surfside_life_at_surfside'
    );

    if (!function_exists('surfside_tools_photo_carousel_shortcode')) {
        return '';
    }

    $carousel = surfside_tools_photo_carousel_shortcode();
    if ($carousel === '') {
        return '';
    }

    $heading_id = wp_unique_id('surfside-life-heading-');

    ob_start();
    ?>
    <section class="surfside-life surfside-section" aria-labelledby="<?php echo esc_attr($heading_id); ?>">
        <div class="surfside-life__inner">
            <div class="surfside-life__intro">
                <h2 class="surfside-life__heading" id="<?php echo esc_attr($heading_id); ?>"><?php echo esc_html($attributes['title']); ?></h2>
                <?php if (trim((string) $attributes['intro']) !== '') : ?>
                    <p><?php echo esc_html($attributes['intro']); ?></p>
                <?php endif; ?>
            </div>
            <div class="surfside-life__carousel">
                <?php echo $carousel; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </div>
        </div>
    </section>
    <?php
    return ob_get_clean();
}
add_shortcode('surfside_life_at_surfside', 'surfside_tools_life_at_surfside_shortcode');
