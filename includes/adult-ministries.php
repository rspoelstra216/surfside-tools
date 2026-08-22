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
    <?php foreach ($ministries as $ministry) : ?><article class="surfside-adult-ministries__card"><h3><?php if (!empty($ministry['icon'])) : ?><span aria-hidden="true"><?php echo esc_html($ministry['icon']); ?></span> <?php endif; ?><?php echo esc_html($ministry['name'] ?? ''); ?></h3><?php if(!empty($ministry['schedule'])): ?><p class="surfside-adult-ministries__schedule"><?php echo esc_html($ministry['schedule']); ?></p><?php endif; ?><?php if(!empty($ministry['location'])): ?><p class="surfside-adult-ministries__location"><?php echo esc_html($ministry['location']); ?></p><?php endif; ?><?php if(!empty($ministry['description'])): ?><p class="surfside-adult-ministries__description"><?php echo esc_html($ministry['description']); ?></p><?php endif; ?></article><?php endforeach; ?>
    </div></div></section><?php return ob_get_clean();
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

/** Compact directory with audience filtering. */
function surfside_tools_all_ministries_shortcode($attributes=array()) {
    $attributes=shortcode_atts(array('title'=>'Ministry Directory','intro'=>'Explore more ways to connect, grow, and serve at Surfside.'),$attributes,'surfside_all_ministries');
    $items=function_exists('surfside_tools_get_ministries')?array_values((array)surfside_tools_get_ministries()):array();
    if(empty($items)) return '';
    $choices=function_exists('surfside_tools_ministry_audience_choices')?surfside_tools_ministry_audience_choices():array('kids'=>'Kids','youth'=>'Youth','adults'=>'Adults','all_ages'=>'All Ages');
    $directory_id=wp_unique_id('surfside-ministry-directory-');
    ob_start(); ?>
    <section class="surfside-all-ministries" id="<?php echo esc_attr($directory_id); ?>"><div class="surfside-all-ministries__inner"><div class="surfside-all-ministries__intro"><h2><?php echo esc_html($attributes['title']); ?></h2><?php if(trim((string)$attributes['intro'])!==''): ?><p><?php echo esc_html($attributes['intro']); ?></p><?php endif; ?></div>
      <div class="surfside-all-ministries__filters" aria-label="Filter ministries by audience"><span>Filter by audience:</span><?php foreach($choices as $key=>$label): ?><button type="button" data-ministry-filter="<?php echo esc_attr($key); ?>" aria-pressed="false"><?php echo esc_html($label); ?></button><?php endforeach; ?><button type="button" class="surfside-all-ministries__clear" data-ministry-filter-clear hidden>Clear filter</button></div>
      <div class="surfside-all-ministries__list" data-ministry-directory-list>
      <?php foreach($items as $m): $audiences=isset($m['audiences'])&&is_array($m['audiences'])?$m['audiences']:array('adults'); $featured=!empty($m['featured']); ?><article class="surfside-all-ministries__item" data-ministry-directory-item data-featured="<?php echo $featured?'1':'0'; ?>" data-audiences="<?php echo esc_attr(implode(' ',array_map('sanitize_key',$audiences))); ?>" <?php if($featured): ?>hidden<?php endif; ?>><h3><?php if(!empty($m['icon'])): ?><span aria-hidden="true"><?php echo esc_html($m['icon']); ?></span> <?php endif; ?><?php echo esc_html($m['name']??''); ?></h3><p class="surfside-all-ministries__meta"><?php if(!empty($m['schedule'])): ?><span><?php echo esc_html($m['schedule']); ?></span><?php endif; ?><?php if(!empty($m['location'])): ?><span><?php echo esc_html($m['location']); ?></span><?php endif; ?></p></article><?php endforeach; ?>
      </div>
    </div><style>.surfside-all-ministries{margin:28px 0}.surfside-all-ministries__inner{max-width:1100px;margin:0 auto}.surfside-all-ministries__intro{margin-bottom:12px}.surfside-all-ministries__intro h2{margin:0 0 5px}.surfside-all-ministries__intro p{margin:0;color:#60708a}.surfside-all-ministries__filters{display:flex;align-items:center;flex-wrap:wrap;gap:7px;margin:0 0 14px;color:#60708a;font-size:.85rem}.surfside-all-ministries__filters>span{font-weight:700;color:#31566d}.surfside-all-ministries__filters button{padding:6px 10px;border:1px solid var(--surfside-color-border,#d8e1e9);border-radius:999px;background:#fff;color:#31566d;font:inherit;font-weight:800;cursor:pointer}.surfside-all-ministries__filters button[aria-pressed="true"]{border-color:var(--surfside-color-blue-700,#075c9c);background:#eef4f7;color:var(--surfside-color-blue-700,#075c9c)}.surfside-all-ministries__clear{border-color:transparent!important;background:transparent!important;text-decoration:underline}.surfside-all-ministries__list{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px 18px}.surfside-all-ministries__item{padding:8px 0;border:0;border-bottom:1px solid var(--surfside-color-border,#d8e1e9);background:transparent}.surfside-all-ministries__item[hidden]{display:none!important}.surfside-all-ministries__item h3{margin:0;font-size:1rem;line-height:1.25}.surfside-all-ministries__meta{display:flex;gap:0;flex-wrap:wrap;margin:3px 0 0;color:#60708a;font-size:.8rem;line-height:1.3}.surfside-all-ministries__meta span+span:before{content:' · ';}@media(max-width:900px){.surfside-all-ministries__list{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:620px){.surfside-all-ministries__list{grid-template-columns:1fr}}</style>
    <script>(function(){var root=document.getElementById(<?php echo wp_json_encode($directory_id); ?>);if(!root)return;var items=root.querySelectorAll('[data-ministry-directory-item]'),buttons=root.querySelectorAll('[data-ministry-filter]'),clear=root.querySelector('[data-ministry-filter-clear]');function reset(){items.forEach(function(item){item.hidden=item.getAttribute('data-featured')==='1';});buttons.forEach(function(button){button.setAttribute('aria-pressed','false');});if(clear)clear.hidden=true;}buttons.forEach(function(button){button.addEventListener('click',function(){var filter=button.getAttribute('data-ministry-filter');buttons.forEach(function(other){other.setAttribute('aria-pressed',other===button?'true':'false');});items.forEach(function(item){var audiences=(item.getAttribute('data-audiences')||'').split(/\s+/);item.hidden=audiences.indexOf(filter)===-1;});if(clear)clear.hidden=false;});});if(clear)clear.addEventListener('click',reset);reset();}());</script></section>
    <?php return ob_get_clean();
}
add_shortcode('surfside_all_ministries','surfside_tools_all_ministries_shortcode');
