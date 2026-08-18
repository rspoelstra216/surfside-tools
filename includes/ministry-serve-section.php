<?php
/** Dynamic Serve & Get Involved presentation for featured Ministry Manager records. */
if (!defined('ABSPATH')) { exit; }

function surfside_tools_serve_ministries_shortcode($attributes=array()) {
    $attributes=shortcode_atts(array(
        'title'=>'Serve & Get Involved',
        'subheading'=>'Use Your Gifts to Serve Others',
        'intro'=>'The church isn’t just a place to attend—it’s a place to belong, serve, and make a difference. Whether you enjoy welcoming guests, working behind the scenes, or helping people feel connected, there are opportunities to get involved.',
    ),$attributes,'surfside_featured_ministries');

    $items=array_values(array_filter((array)surfside_tools_get_ministries(),function($m){return !empty($m['featured']);}));
    if(empty($items)) return '';

    $heading_id=wp_unique_id('surfside-serve-heading-');
    ob_start(); ?>
    <section class="surfside-adult-ministries surfside-serve-ministries" aria-labelledby="<?php echo esc_attr($heading_id); ?>">
      <div class="surfside-adult-ministries__inner">
        <div class="surfside-adult-ministries__intro surfside-serve-ministries__intro">
          <h2 id="<?php echo esc_attr($heading_id); ?>"><?php echo esc_html($attributes['title']); ?></h2>
          <?php if(trim((string)$attributes['subheading'])!==''): ?><h3><?php echo esc_html($attributes['subheading']); ?></h3><?php endif; ?>
          <?php if(trim((string)$attributes['intro'])!==''): ?><p><?php echo esc_html($attributes['intro']); ?></p><?php endif; ?>
        </div>
        <div class="surfside-adult-ministries__grid surfside-staggered-cards">
          <?php foreach($items as $ministry): ?>
            <article class="surfside-adult-ministries__card">
              <h3><?php if(!empty($ministry['icon'])): ?><span aria-hidden="true"><?php echo esc_html($ministry['icon']); ?></span> <?php endif; ?><?php echo esc_html($ministry['name']??''); ?></h3>
              <?php if(!empty($ministry['schedule'])): ?><p class="surfside-adult-ministries__schedule"><?php echo esc_html($ministry['schedule']); ?></p><?php endif; ?>
              <?php if(!empty($ministry['location'])): ?><p class="surfside-adult-ministries__location"><?php echo esc_html($ministry['location']); ?></p><?php endif; ?>
              <?php if(!empty($ministry['description'])): ?><p class="surfside-adult-ministries__description"><?php echo esc_html($ministry['description']); ?></p><?php endif; ?>
            </article>
          <?php endforeach; ?>
        </div>
      </div>
      <style>
        /* Width, sand-band breakout, inner container, grid, and cards intentionally
           come from the proven surfside-adult-ministries implementation. */
        .surfside-serve-ministries__intro h3{margin:.4rem 0 .85rem;color:var(--surfside-color-blue-700,#075c9c);font-size:1.08rem;font-weight:700}
        .surfside-serve-ministries__intro>p{margin-top:0}
      </style>
    </section>
    <?php return ob_get_clean();
}

add_action('init',function(){
    remove_shortcode('surfside_featured_ministries');
    add_shortcode('surfside_featured_ministries','surfside_tools_serve_ministries_shortcode');
},30);
