<?php
/**
 * Dashboard-managed Adult Ministries section.
 *
 * @package SurfsideTools
 */

if (!defined('ABSPATH')) {
    exit;
}

function surfside_tools_adult_ministries_shortcode($attributes = array()) {
    $attributes = shortcode_atts(array(
        'title' => 'Adult Ministries',
        'intro' => 'Find a place to connect, grow, and build meaningful relationships throughout the week.',
    ), $attributes, 'surfside_adult_ministries');

    $information = surfside_tools_get_site_information();
    $ministries = (array) ($information['adult_ministries'] ?? array());
    if (empty($ministries)) {
        return '';
    }

    $heading_id = wp_unique_id('surfside-adult-ministries-heading-');
    ob_start();
    ?>
    <section class="surfside-adult-ministries" aria-labelledby="<?php echo esc_attr($heading_id); ?>">
        <div class="surfside-adult-ministries__inner">
            <div class="surfside-adult-ministries__intro">
                <h2 id="<?php echo esc_attr($heading_id); ?>"><?php echo esc_html($attributes['title']); ?></h2>
                <?php if (trim((string) $attributes['intro']) !== '') : ?><p><?php echo esc_html($attributes['intro']); ?></p><?php endif; ?>
            </div>
            <div class="surfside-adult-ministries__grid surfside-staggered-cards">
                <?php foreach ($ministries as $ministry) : ?>
                    <article class="surfside-adult-ministries__card">
                        <h3><?php if (!empty($ministry['icon'])) : ?><span aria-hidden="true"><?php echo esc_html($ministry['icon']); ?></span> <?php endif; ?><?php echo esc_html($ministry['name'] ?? ''); ?></h3>
                        <?php if (!empty($ministry['schedule'])) : ?><p class="surfside-adult-ministries__schedule"><?php echo esc_html($ministry['schedule']); ?></p><?php endif; ?>
                        <?php if (!empty($ministry['location'])) : ?><p class="surfside-adult-ministries__location"><?php echo esc_html($ministry['location']); ?></p><?php endif; ?>
                        <?php if (!empty($ministry['description'])) : ?><p class="surfside-adult-ministries__description"><?php echo esc_html($ministry['description']); ?></p><?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php
    return ob_get_clean();
}
add_shortcode('surfside_adult_ministries', 'surfside_tools_adult_ministries_shortcode');
