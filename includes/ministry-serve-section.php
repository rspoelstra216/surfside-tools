<?php
/** Dynamic Serve & Get Involved presentation for featured Ministry Manager records. */
if (!defined('ABSPATH')) { exit; }

function surfside_tools_serve_ministries_shortcode($attributes=array()) {
    $attributes=shortcode_atts(array(
        'title'=>'Serve & Get Involved',
        'subheading'=>'Use Your Gifts to Serve Others',
        'intro'=>'The church isn’t just a place to attend—it’s a place to belong, serve, and make a difference. Whether you enjoy welcoming guests, working behind the scenes, or helping people feel connected, there are opportunities to get involved.',
        'closing_title'=>'Interested in Serving?',
        'closing'=>'There are too many opportunities to list here! We’d love to help you find a place to use your gifts and talents.',
        'button'=>'Contact Us About Serving',
        'button_url'=>home_url('/contact/'),
    ),$attributes,'surfside_featured_ministries');

    $items=array_values(array_filter((array)surfside_tools_get_ministries(),function($m){return !empty($m['featured']);}));
    if(empty($items)) return '';

    $heading_id=wp_unique_id('surfside-serve-heading-');
    ob_start(); ?>
    <section class="surfside-serve-ministries" aria-labelledby="<?php echo esc_attr($heading_id); ?>">
      <div class="surfside-serve-ministries__inner">
        <div class="surfside-serve-ministries__intro">
          <h2 id="<?php echo esc_attr($heading_id); ?>"><?php echo esc_html($attributes['title']); ?></h2>
          <?php if(trim((string)$attributes['subheading'])!==''): ?><h3><?php echo esc_html($attributes['subheading']); ?></h3><?php endif; ?>
          <?php if(trim((string)$attributes['intro'])!==''): ?><p><?php echo esc_html($attributes['intro']); ?></p><?php endif; ?>
        </div>
        <div class="surfside-adult-ministries__grid surfside-staggered-cards surfside-serve-ministries__grid">
          <?php foreach($items as $ministry): ?>
            <article class="surfside-adult-ministries__card surfside-featured-ministries__card">
              <h3><?php if(!empty($ministry['icon'])): ?><span aria-hidden="true"><?php echo esc_html($ministry['icon']); ?></span> <?php endif; ?><?php echo esc_html($ministry['name']??''); ?></h3>
              <?php $labels=function_exists('surfside_tools_ministry_audience_labels')?surfside_tools_ministry_audience_labels($ministry):array(); if($labels): ?><p class="surfside-ministries__audiences"><?php foreach($labels as $label): ?><span class="surfside-ministries__audience"><?php echo esc_html($label); ?></span><?php endforeach; ?></p><?php endif; ?>
              <?php if(!empty($ministry['schedule'])): ?><p class="surfside-adult-ministries__schedule"><?php echo esc_html($ministry['schedule']); ?></p><?php endif; ?>
              <?php if(!empty($ministry['location'])): ?><p class="surfside-adult-ministries__location"><?php echo esc_html($ministry['location']); ?></p><?php endif; ?>
              <?php if(!empty($ministry['description'])): ?><p class="surfside-adult-ministries__description"><?php echo esc_html($ministry['description']); ?></p><?php endif; ?>
            </article>
          <?php endforeach; ?>
        </div>
        <div class="surfside-serve-ministries__closing">
          <?php if(trim((string)$attributes['closing_title'])!==''): ?><h3><?php echo esc_html($attributes['closing_title']); ?></h3><?php endif; ?>
          <?php if(trim((string)$attributes['closing'])!==''): ?><p><?php echo esc_html($attributes['closing']); ?></p><?php endif; ?>
          <?php if(trim((string)$attributes['button'])!=='' && trim((string)$attributes['button_url'])!==''): ?><a class="surfside-button" href="<?php echo esc_url($attributes['button_url']); ?>"><?php echo esc_html($attributes['button']); ?></a><?php endif; ?>
        </div>
      </div>
      <style>
        .surfside-serve-ministries{box-sizing:border-box;width:100%;margin:0;padding:52px 24px;background:var(--surfside-color-sand-100,#f5f1e9);color:var(--surfside-color-ink,#10243a)}
        .surfside-serve-ministries__inner{width:100%;max-width:1180px;margin:0 auto}
        .surfside-serve-ministries__intro{text-align:center;width:100%;margin:0 auto 28px}
        .surfside-serve-ministries__intro h2{margin:0 0 10px;font-size:clamp(2rem,4vw,3rem);line-height:1.08;color:var(--surfside-color-ocean-950,#061b33)}
        .surfside-serve-ministries__intro h3{margin:0 0 14px;font-size:1.08rem;font-weight:700;color:var(--surfside-color-blue-700,#075c9c)}
        .surfside-serve-ministries__intro p{max-width:1080px;margin:0 auto;text-align:left;color:var(--surfside-color-muted,#536579);line-height:1.6}
        .surfside-serve-ministries__grid{margin-top:30px}
        .surfside-featured-ministries__card{background:#fff!important}
        .surfside-ministries__audiences{display:flex;flex-wrap:wrap;gap:7px;margin:8px 0 12px}.surfside-ministries__audience{display:inline-flex;padding:5px 9px;border-radius:999px;background:#eef4f7;color:#31566d;font-size:.78rem;font-weight:800;line-height:1}
        .surfside-serve-ministries__closing{text-align:center;max-width:860px;margin:34px auto 0}
        .surfside-serve-ministries__closing h3{margin:0 0 6px;font-size:1.05rem;color:var(--surfside-color-ocean-950,#061b33)}
        .surfside-serve-ministries__closing p{margin:0 0 14px;color:var(--surfside-color-muted,#536579)}
        @media(max-width:700px){.surfside-serve-ministries{padding:38px 20px}.surfside-serve-ministries__intro p{text-align:center}}
      </style>
    </section>
    <?php return ob_get_clean();
}

/**
 * WordPress constrained Groups cap Shortcode blocks at the content width even when
 * the shortcode's own markup is wider. Mark the Featured Ministries shortcode block
 * as alignfull at render time so the theme's native full-width layout rules apply.
 */
function surfside_tools_featured_ministries_alignfull_block($block_content,$block) {
    if (($block['blockName'] ?? '') !== 'core/shortcode') return $block_content;
    $raw = isset($block['innerHTML']) ? (string)$block['innerHTML'] : '';
    if (strpos($raw,'[surfside_featured_ministries') === false) return $block_content;
    if (strpos($block_content,'wp-block-shortcode') === false || strpos($block_content,'alignfull') !== false) return $block_content;
    return preg_replace('/class=("|\')wp-block-shortcode\1/','class="wp-block-shortcode alignfull"',$block_content,1);
}
add_filter('render_block','surfside_tools_featured_ministries_alignfull_block',20,2);

add_action('init',function(){
    remove_shortcode('surfside_featured_ministries');
    add_shortcode('surfside_featured_ministries','surfside_tools_serve_ministries_shortcode');
},30);
