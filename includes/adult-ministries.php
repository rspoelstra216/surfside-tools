<?php
/**
 * Dashboard-managed Ministries section.
 *
 * @package SurfsideTools
 */

if (!defined('ABSPATH')) { exit; }

function surfside_tools_ministries_render_cards($ministries, $title, $intro, $class = 'surfside-ministries') {
    if (empty($ministries)) return '';
    $heading_id = wp_unique_id('surfside-ministries-heading-'); ob_start(); ?>
    <section class="surfside-adult-ministries <?php echo esc_attr($class); ?>" aria-labelledby="<?php echo esc_attr($heading_id); ?>"><div class="surfside-adult-ministries__inner"><div class="surfside-adult-ministries__intro"><h2 id="<?php echo esc_attr($heading_id); ?>"><?php echo esc_html($title); ?></h2><?php if (trim((string)$intro) !== '') : ?><p><?php echo esc_html($intro); ?></p><?php endif; ?></div><div class="surfside-adult-ministries__grid surfside-staggered-cards">
    <?php foreach ($ministries as $ministry) : ?><article class="surfside-adult-ministries__card"><h3><?php if (!empty($ministry['icon'])) : ?><span aria-hidden="true"><?php echo esc_html($ministry['icon']); ?></span> <?php endif; ?><?php echo esc_html($ministry['name'] ?? ''); ?></h3><?php $labels=function_exists('surfside_tools_ministry_audience_labels')?surfside_tools_ministry_audience_labels($ministry):array(); if($labels): ?><p class="surfside-ministries__audiences" aria-label="Audience"><?php foreach($labels as $label): ?><span class="surfside-ministries__audience"><?php echo esc_html($label); ?></span><?php endforeach; ?></p><?php endif; ?><?php if(!empty($ministry['schedule'])): ?><p class="surfside-adult-ministries__schedule"><?php echo esc_html($ministry['schedule']); ?></p><?php endif; ?><?php if(!empty($ministry['location'])): ?><p class="surfside-adult-ministries__location"><?php echo esc_html($ministry['location']); ?></p><?php endif; ?><?php if(!empty($ministry['description'])): ?><p class="surfside-adult-ministries__description"><?php echo esc_html($ministry['description']); ?></p><?php endif; ?></article><?php endforeach; ?>
    </div></div><style>.surfside-ministries__audiences{display:flex;flex-wrap:wrap;gap:7px;margin:8px 0 12px}.surfside-ministries__audience{display:inline-flex;padding:5px 9px;border-radius:999px;background:#eef4f7;color:#31566d;font-size:.78rem;font-weight:800;line-height:1}</style></section><?php return ob_get_clean();
}

function surfside_tools_ministries_shortcode($attributes=array()) {
    $attributes=shortcode_atts(array('title'=>'Ministries','intro'=>'Find a place to connect, grow, serve, and build meaningful relationships throughout the week.'),$attributes,'surfside_ministries');
    $ministries=function_exists('surfside_tools_get_ministries')?surfside_tools_get_ministries():array();
    return surfside_tools_ministries_render_cards($ministries,$attributes['title'],$attributes['intro']);
}
add_shortcode('surfside_ministries','surfside_tools_ministries_shortcode');
function surfside_tools_adult_ministries_shortcode($attributes=array()){return surfside_tools_ministries_shortcode($attributes);} add_shortcode('surfside_adult_ministries','surfside_tools_adult_ministries_shortcode');

function surfside_tools_featured_ministries_shortcode($attributes=array()) {
    $attributes=shortcode_atts(array('title'=>'Ministries','intro'=>'Find a place to connect, grow, serve, and build meaningful relationships throughout the week.'),$attributes,'surfside_featured_ministries');
    $ministries=function_exists('surfside_tools_get_ministries')?surfside_tools_get_ministries():array();
    $featured=array_values(array_filter($ministries,function($m){return !empty($m['featured']);}));
    return surfside_tools_ministries_render_cards($featured,$attributes['title'],$attributes['intro'],'surfside-featured-ministries');
}
add_shortcode('surfside_featured_ministries','surfside_tools_featured_ministries_shortcode');

/** Compact directory of non-featured Ministry Manager records. */
function surfside_tools_all_ministries_shortcode($attributes=array()) {
    $attributes=shortcode_atts(array('title'=>'Ministry Directory','intro'=>'Explore more ways to connect, grow, and serve at Surfside.'),$attributes,'surfside_all_ministries');
    $ministries=function_exists('surfside_tools_get_ministries')?surfside_tools_get_ministries():array();
    $items=array_values(array_filter($ministries,function($m){return empty($m['featured']);}));
    if(empty($items)) return '';
    ob_start(); ?>
    <section class="surfside-all-ministries"><div class="surfside-all-ministries__inner"><div class="surfside-all-ministries__intro"><h2><?php echo esc_html($attributes['title']); ?></h2><?php if(trim((string)$attributes['intro'])!==''): ?><p><?php echo esc_html($attributes['intro']); ?></p><?php endif; ?></div><div class="surfside-all-ministries__list">
    <?php foreach($items as $m): ?><article class="surfside-all-ministries__item"><h3><?php if(!empty($m['icon'])): ?><span aria-hidden="true"><?php echo esc_html($m['icon']); ?></span> <?php endif; ?><?php echo esc_html($m['name']??''); ?></h3><?php $labels=function_exists('surfside_tools_ministry_audience_labels')?surfside_tools_ministry_audience_labels($m):array(); ?><p class="surfside-all-ministries__meta"><?php if($labels): ?><span><?php echo esc_html(implode(' · ',$labels)); ?></span><?php endif; ?><?php if(!empty($m['schedule'])): ?><span><?php echo esc_html($m['schedule']); ?></span><?php endif; ?><?php if(!empty($m['location'])): ?><span><?php echo esc_html($m['location']); ?></span><?php endif; ?></p></article><?php endforeach; ?>
    </div></div><style>.surfside-all-ministries{margin:28px 0}.surfside-all-ministries__inner{max-width:1100px;margin:0 auto}.surfside-all-ministries__intro{margin-bottom:14px}.surfside-all-ministries__intro h2{margin:0 0 5px}.surfside-all-ministries__intro p{margin:0;color:#60708a}.surfside-all-ministries__list{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px 18px}.surfside-all-ministries__item{padding:8px 0;border:0;border-bottom:1px solid var(--surfside-color-border,#d8e1e9);background:transparent}.surfside-all-ministries__item h3{margin:0;font-size:1rem;line-height:1.25}.surfside-all-ministries__meta{display:flex;gap:0;flex-wrap:wrap;margin:3px 0 0;color:#60708a;font-size:.8rem;line-height:1.3}.surfside-all-ministries__meta span+span:before{content:' · ';}@media(max-width:900px){.surfside-all-ministries__list{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:620px){.surfside-all-ministries__list{grid-template-columns:1fr}}</style></section>
    <?php return ob_get_clean();
}
add_shortcode('surfside_all_ministries','surfside_tools_all_ministries_shortcode');
