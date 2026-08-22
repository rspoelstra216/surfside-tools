<?php
/**
 * Dashboard-managed Ministries section.
 *
 * @package SurfsideTools
 */

if (!defined('ABSPATH')) {
    exit;
}

function surfside_tools_ministries_render_cards($ministries, $title, $intro, $class = 'surfside-ministries') {
    if (empty($ministries)) return '';
    $heading_id = wp_unique_id('surfside-ministries-heading-');
    ob_start(); ?>
    <section class="surfside-adult-ministries <?php echo esc_attr($class); ?>" aria-labelledby="<?php echo esc_attr($heading_id); ?>">
      <div class="surfside-adult-ministries__inner">
        <div class="surfside-adult-ministries__intro">
          <h2 id="<?php echo esc_attr($heading_id); ?>"><?php echo esc_html($title); ?></h2>
          <?php if (trim((string)$intro) !== '') : ?><p><?php echo esc_html($intro); ?></p><?php endif; ?>
        </div>
        <div class="surfside-adult-ministries__grid surfside-staggered-cards">
          <?php foreach ($ministries as $ministry) : ?>
            <article class="surfside-adult-ministries__card">
              <h3><?php if (!empty($ministry['icon'])) : ?><span aria-hidden="true"><?php echo esc_html($ministry['icon']); ?></span> <?php endif; ?><?php echo esc_html($ministry['name'] ?? ''); ?></h3>
              <?php $labels = function_exists('surfside_tools_ministry_audience_labels') ? surfside_tools_ministry_audience_labels($ministry) : array(); ?>
              <?php if ($labels) : ?><p class="surfside-ministries__audiences" aria-label="Audience"><?php foreach ($labels as $label) : ?><span class="surfside-ministries__audience"><?php echo esc_html($label); ?></span><?php endforeach; ?></p><?php endif; ?>
              <?php if (!empty($ministry['schedule'])) : ?><p class="surfside-adult-ministries__schedule"><?php echo esc_html($ministry['schedule']); ?></p><?php endif; ?>
              <?php if (!empty($ministry['location'])) : ?><p class="surfside-adult-ministries__location"><?php echo esc_html($ministry['location']); ?></p><?php endif; ?>
              <?php if (!empty($ministry['description'])) : ?><p class="surfside-adult-ministries__description"><?php echo esc_html($ministry['description']); ?></p><?php endif; ?>
            </article>
          <?php endforeach; ?>
        </div>
      </div>
      <style>.surfside-ministries__audiences{display:flex;flex-wrap:wrap;gap:7px;margin:8px 0 12px}.surfside-ministries__audience{display:inline-flex;padding:5px 9px;border-radius:999px;background:#eef4f7;color:#31566d;font-size:.78rem;font-weight:800;line-height:1}</style>
    </section>
    <?php return ob_get_clean();
}

function surfside_tools_ministries_shortcode($attributes = array()) {
    $attributes = shortcode_atts(array(
        'title' => 'Ministries',
        'intro' => 'Find a place to connect, grow, serve, and build meaningful relationships throughout the week.',
    ), $attributes, 'surfside_ministries');
    $ministries = function_exists('surfside_tools_get_ministries') ? surfside_tools_get_ministries() : array();
    return surfside_tools_ministries_render_cards($ministries, $attributes['title'], $attributes['intro']);
}
add_shortcode('surfside_ministries', 'surfside_tools_ministries_shortcode');

function surfside_tools_adult_ministries_shortcode($attributes = array()) {
    return surfside_tools_ministries_shortcode($attributes);
}
add_shortcode('surfside_adult_ministries', 'surfside_tools_adult_ministries_shortcode');

/**
 * Featured ministries are selected in the Ministry Manager. Registration is
 * performed when this file loads; no global init hook or page lookup is needed.
 */
function surfside_tools_featured_ministries_shortcode($attributes = array()) {
    $attributes = shortcode_atts(array(
        'title' => 'Ministries',
        'intro' => 'Find a place to connect, grow, serve, and build meaningful relationships throughout the week.',
    ), $attributes, 'surfside_featured_ministries');
    $ministries = function_exists('surfside_tools_get_ministries') ? surfside_tools_get_ministries() : array();
    $featured = array_values(array_filter($ministries, function($ministry) {
        return !empty($ministry['featured']);
    }));
    return surfside_tools_ministries_render_cards($featured, $attributes['title'], $attributes['intro'], 'surfside-featured-ministries');
}
add_shortcode('surfside_featured_ministries', 'surfside_tools_featured_ministries_shortcode');
