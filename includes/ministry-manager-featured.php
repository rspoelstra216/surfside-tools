<?php
/** Featured Ministry control for canonical Ministry Manager records. */
if (!defined('ABSPATH')) { exit; }

// The calendar event remains classifiable as a Ministry, but website featured
// placement is owned by the canonical Ministry Manager to avoid two sources of truth.
remove_action('wp_footer','surfside_tools_featured_ministry_manager_fields',100);
remove_action('admin_footer','surfside_tools_featured_ministry_manager_fields',100);
remove_action('save_post_surfside_event','surfside_tools_featured_ministry_save',40);

function surfside_tools_ministry_manager_featured_field() {
    if (!is_user_logged_in() || !current_user_can('manage_options')) return;
    $featured = array();
    foreach ((array) surfside_tools_get_ministries() as $ministry) {
        $featured[(string)($ministry['key'] ?? '')] = !empty($ministry['featured']);
    }
    ?>
    <style>.surfside-ministry-featured{grid-column:1/-1;margin:2px 0 0}.surfside-ministry-featured label{display:inline-flex;align-items:center;gap:9px;font-weight:800;color:#26323d}.surfside-ministry-featured input{width:20px;height:20px}</style>
    <script>
    document.addEventListener('DOMContentLoaded',function(){
      var list=document.querySelector('[data-surfside-ministries]'); if(!list)return;
      var saved=<?php echo wp_json_encode($featured); ?>;
      function addField(card){
        if(card.querySelector('[data-ministry-featured]'))return;
        var key=(card.querySelector('input[name$="[key]"]')||{}).value||'';
        var checked=Object.prototype.hasOwnProperty.call(saved,key)?!!saved[key]:false;
        var description=card.querySelector('.surfside-information-ministry-description');
        var wrap=document.createElement('div'); wrap.className='surfside-ministry-featured'; wrap.setAttribute('data-ministry-featured','1');
        wrap.innerHTML='<input type="hidden" data-featured-hidden value="0"><label><input type="checkbox" data-featured-check value="1"'+(checked?' checked':'')+'> Featured Ministry</label>';
        if(description)card.insertBefore(wrap,description);else card.appendChild(wrap);
        syncNames(card);
      }
      function syncNames(card){
        var keyInput=card.querySelector('input[name$="[key]"]'); if(!keyInput)return;
        var match=(keyInput.name||'').match(/^ministries\[[^\]]+\]/); if(!match)return;
        var base=match[0]; var hidden=card.querySelector('[data-featured-hidden]'),check=card.querySelector('[data-featured-check]');
        if(hidden)hidden.name=base+'[featured]'; if(check)check.name=base+'[featured]';
      }
      function scan(){list.querySelectorAll('.surfside-information-ministry').forEach(function(card){addField(card);syncNames(card);});}
      scan(); new MutationObserver(scan).observe(list,{childList:true,subtree:true});
      list.addEventListener('click',function(){setTimeout(scan,0);});
    });
    </script>
    <?php
}
add_action('wp_footer','surfside_tools_ministry_manager_featured_field',101);

function surfside_tools_featured_ministries_manager_shortcode($attributes=array()) {
    $attributes=shortcode_atts(array('title'=>'Ministries','intro'=>'Find a place to connect, grow, serve, and build meaningful relationships throughout the week.'),$attributes,'surfside_featured_ministries');
    $items=array_values(array_filter((array)surfside_tools_get_ministries(),function($m){return !empty($m['featured']);}));
    if(empty($items)) return '';
    $heading_id=wp_unique_id('surfside-featured-ministries-heading-'); ob_start(); ?>
    <section class="surfside-adult-ministries surfside-featured-ministries" aria-labelledby="<?php echo esc_attr($heading_id); ?>"><div class="surfside-adult-ministries__inner"><div class="surfside-adult-ministries__intro"><h2 id="<?php echo esc_attr($heading_id); ?>"><?php echo esc_html($attributes['title']); ?></h2><?php if(trim((string)$attributes['intro'])!==''): ?><p><?php echo esc_html($attributes['intro']); ?></p><?php endif; ?></div><div class="surfside-adult-ministries__grid surfside-staggered-cards">
    <?php foreach($items as $ministry): ?><article class="surfside-adult-ministries__card surfside-featured-ministries__card"><h3><?php if(!empty($ministry['icon'])): ?><span aria-hidden="true"><?php echo esc_html($ministry['icon']); ?></span> <?php endif; ?><?php echo esc_html($ministry['name']??''); ?></h3><?php $labels=surfside_tools_ministry_audience_labels($ministry); if($labels): ?><p class="surfside-ministries__audiences"><?php foreach($labels as $label): ?><span class="surfside-ministries__audience"><?php echo esc_html($label); ?></span><?php endforeach; ?></p><?php endif; ?><?php if(!empty($ministry['schedule'])): ?><p class="surfside-adult-ministries__schedule"><?php echo esc_html($ministry['schedule']); ?></p><?php endif; ?><?php if(!empty($ministry['location'])): ?><p class="surfside-adult-ministries__location"><?php echo esc_html($ministry['location']); ?></p><?php endif; ?><?php if(!empty($ministry['description'])): ?><p class="surfside-adult-ministries__description"><?php echo esc_html($ministry['description']); ?></p><?php endif; ?></article><?php endforeach; ?>
    </div></div><style>.surfside-featured-ministries__card{background:#fff!important}.surfside-ministries__audiences{display:flex;flex-wrap:wrap;gap:7px;margin:8px 0 12px}.surfside-ministries__audience{display:inline-flex;padding:5px 9px;border-radius:999px;background:#eef4f7;color:#31566d;font-size:.78rem;font-weight:800;line-height:1}</style></section>
    <?php return ob_get_clean();
}

function surfside_tools_all_ministries_manager_shortcode($attributes=array()) {
    $attributes=shortcode_atts(array('title'=>'Ministry Directory','intro'=>'Explore more ways to connect, grow, and serve at Surfside.'),$attributes,'surfside_all_ministries');
    $items=array_values(array_filter((array)surfside_tools_get_ministries(),function($m){return empty($m['featured']);})); if(empty($items))return '';
    ob_start(); ?><section class="surfside-all-ministries"><div class="surfside-all-ministries__inner"><div class="surfside-all-ministries__intro"><h2><?php echo esc_html($attributes['title']); ?></h2><?php if(trim((string)$attributes['intro'])!==''): ?><p><?php echo esc_html($attributes['intro']); ?></p><?php endif; ?></div><div class="surfside-all-ministries__list">
    <?php foreach($items as $m): ?><article class="surfside-all-ministries__item"><div class="surfside-all-ministries__top"><h3><?php if(!empty($m['icon'])): ?><span aria-hidden="true"><?php echo esc_html($m['icon']); ?></span> <?php endif; ?><?php echo esc_html($m['name']??''); ?></h3><?php $labels=surfside_tools_ministry_audience_labels($m); if($labels): ?><span class="surfside-all-ministries__audience"><?php echo esc_html(implode(' · ',$labels)); ?></span><?php endif; ?></div><p class="surfside-all-ministries__meta"><?php if(!empty($m['schedule'])): ?><span><?php echo esc_html($m['schedule']); ?></span><?php endif; ?><?php if(!empty($m['location'])): ?><span><?php echo esc_html($m['location']); ?></span><?php endif; ?></p><?php if(!empty($m['description'])): ?><p class="surfside-all-ministries__description"><?php echo esc_html(wp_trim_words($m['description'],18)); ?></p><?php endif; ?></article><?php endforeach; ?>
    </div></div><style>
    .surfside-all-ministries{margin:28px 0}.surfside-all-ministries__inner{max-width:1100px;margin:0 auto}.surfside-all-ministries__intro{margin-bottom:16px}.surfside-all-ministries__intro h2{margin:0 0 6px}.surfside-all-ministries__intro p{margin:0;color:#60708a}.surfside-all-ministries__list{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.surfside-all-ministries__item{padding:12px 14px;border:1px solid var(--surfside-color-border,#d8e1e9);border-radius:12px;background:#fff}.surfside-all-ministries__top{display:flex;align-items:baseline;justify-content:space-between;gap:12px}.surfside-all-ministries__item h3{margin:0;font-size:1.05rem;line-height:1.25}.surfside-all-ministries__audience{flex:0 0 auto;color:#31566d;font-size:.76rem;font-weight:800}.surfside-all-ministries__meta{display:flex;gap:0;flex-wrap:wrap;margin:5px 0 0;color:#60708a;font-size:.84rem;line-height:1.35}.surfside-all-ministries__meta span+span:before{content:' · ';}.surfside-all-ministries__description{margin:5px 0 0;color:#536579;font-size:.88rem;line-height:1.4}@media(max-width:700px){.surfside-all-ministries__list{grid-template-columns:1fr}.surfside-all-ministries__top{display:block}.surfside-all-ministries__audience{display:inline-block;margin-top:3px}}
    </style></section><?php return ob_get_clean();
}

add_action('init',function(){remove_shortcode('surfside_featured_ministries');remove_shortcode('surfside_all_ministries');add_shortcode('surfside_featured_ministries','surfside_tools_featured_ministries_manager_shortcode');add_shortcode('surfside_all_ministries','surfside_tools_all_ministries_manager_shortcode');},20);
