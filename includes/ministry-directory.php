<?php
/** Lightweight public Ministry shortcodes restored without global migrations or footer queries. */
if (!defined('ABSPATH')) { exit; }

function surfside_tools_featured_ministries_shortcode($atts=array()) {
    $atts=shortcode_atts(array(
        'title'=>'Serve & Get Involved',
        'intro'=>'The church isn’t just a place to attend—it’s a place to belong, serve, and make a difference. Whether you enjoy welcoming guests, working behind the scenes, or helping people feel connected, there are opportunities to get involved.'
    ),$atts,'surfside_featured_ministries');
    $items=array_values(array_filter((array)surfside_tools_get_ministries(),function($m){return !empty($m['featured']);}));
    if(empty($items)) return '';
    ob_start(); ?>
    <section class="surfside-adult-ministries surfside-featured-ministries"><div class="surfside-adult-ministries__inner">
      <div class="surfside-adult-ministries__intro"><h2><?php echo esc_html($atts['title']); ?></h2><p><strong>Use Your Gifts to Serve Others</strong></p><p><?php echo esc_html($atts['intro']); ?></p></div>
      <div class="surfside-adult-ministries__grid surfside-staggered-cards">
      <?php foreach($items as $m): ?><article class="surfside-adult-ministries__card" style="background:#fff"><h3><?php if(!empty($m['icon'])) echo '<span aria-hidden="true">'.esc_html($m['icon']).'</span> '; ?><?php echo esc_html($m['name']??''); ?></h3><?php if(!empty($m['schedule'])):?><p class="surfside-adult-ministries__schedule"><?php echo esc_html($m['schedule']); ?></p><?php endif; ?><?php if(!empty($m['location'])):?><p class="surfside-adult-ministries__location"><?php echo esc_html($m['location']); ?></p><?php endif; ?><?php if(!empty($m['description'])):?><p class="surfside-adult-ministries__description"><?php echo esc_html($m['description']); ?></p><?php endif; ?></article><?php endforeach; ?>
      </div>
    </div></section>
    <?php return ob_get_clean();
}

function surfside_tools_all_ministries_shortcode($atts=array()) {
    $atts=shortcode_atts(array(
      'title'=>'Ministry Directory',
      'intro'=>'Explore more ways to connect, grow, and serve at Surfside.',
      'closing_title'=>'Interested in Serving?',
      'closing'=>'There are too many opportunities to list here! We’d love to help you find a place to use your gifts and talents.',
      'button'=>'Contact Us About Serving',
      'button_url'=>home_url('/contact/')
    ),$atts,'surfside_all_ministries');
    $items=array_values((array)surfside_tools_get_ministries());
    if(empty($items)) return '';
    $filters=surfside_tools_ministry_audience_choices();
    $id=wp_unique_id('surfside-ministry-directory-');
    ob_start(); ?>
    <section class="surfside-ministry-directory" id="<?php echo esc_attr($id); ?>"><div class="surfside-ministry-directory__inner">
      <header class="surfside-ministry-directory__intro"><h2><?php echo esc_html($atts['title']); ?></h2><p><?php echo esc_html($atts['intro']); ?></p></header>
      <div class="surfside-ministry-directory__filters" aria-label="Filter ministries by audience"><span>Filter by audience:</span><?php foreach($filters as $key=>$label): ?><button type="button" data-filter="<?php echo esc_attr($key); ?>" aria-pressed="false"><?php echo esc_html($label); ?></button><?php endforeach; ?><button type="button" data-clear hidden>Clear</button></div>
      <div class="surfside-ministry-directory__grid">
      <?php foreach($items as $m): $aud=implode(' ',array_map('sanitize_key',(array)($m['audiences']??array()))); ?>
        <button type="button" class="surfside-ministry-directory__card" data-card data-audiences="<?php echo esc_attr($aud); ?>" data-featured="<?php echo !empty($m['featured'])?'1':'0'; ?>" data-name="<?php echo esc_attr($m['name']??''); ?>" data-icon="<?php echo esc_attr($m['icon']??''); ?>" data-schedule="<?php echo esc_attr($m['schedule']??''); ?>" data-location="<?php echo esc_attr($m['location']??''); ?>" data-description="<?php echo esc_attr($m['description']??''); ?>" <?php if(!empty($m['featured'])) echo 'hidden'; ?>><strong><?php if(!empty($m['icon'])) echo esc_html($m['icon']).' '; ?><?php echo esc_html($m['name']??''); ?></strong><small>View details</small></button>
      <?php endforeach; ?>
      </div>
      <div class="surfside-ministry-directory__closing"><h3><?php echo esc_html($atts['closing_title']); ?></h3><p><?php echo esc_html($atts['closing']); ?></p><a class="surfside-button" href="<?php echo esc_url($atts['button_url']); ?>"><?php echo esc_html($atts['button']); ?></a></div>
    </div>
    <dialog class="surfside-ministry-directory__dialog"><button type="button" class="surfside-ministry-directory__close" aria-label="Close">×</button><h3 data-title></h3><p data-schedule></p><p data-location></p><p data-description></p></dialog>
    <style>
    .surfside-ministry-directory{position:relative;z-index:0;width:100%!important;max-width:none!important;margin:0!important;padding:clamp(2.5rem,4vw,3.25rem) 0}.surfside-ministry-directory:before{content:"";position:absolute;z-index:-1;inset:0 50%;width:100vw;margin-left:-50vw;background:#fff}.surfside-ministry-directory__inner{width:min(100% - 2rem,72rem);margin:auto}.surfside-ministry-directory__intro{text-align:center}.surfside-ministry-directory__intro h2{margin:0 0 .4rem}.surfside-ministry-directory__intro p{margin:0;color:#60708a}.surfside-ministry-directory__filters{display:flex;justify-content:center;align-items:center;flex-wrap:wrap;gap:8px;margin:16px 0}.surfside-ministry-directory__filters span{font-weight:700;color:#31566d}.surfside-ministry-directory__filters button{padding:7px 11px;border:1px solid #d8e1e9;border-radius:999px;background:#fff;color:#31566d;font-weight:800;cursor:pointer}.surfside-ministry-directory__filters button[aria-pressed="true"]{background:#176a9a;color:#fff;border-color:#176a9a}.surfside-ministry-directory__grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px}.surfside-ministry-directory__card{min-height:74px;padding:12px;border:1px solid #d8e1e9;border-radius:10px;background:#fff;color:#10243a;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:5px;text-align:center;cursor:pointer}.surfside-ministry-directory__card[hidden]{display:none}.surfside-ministry-directory__card small{color:#60708a;font-weight:700}.surfside-ministry-directory__closing{text-align:center;max-width:54rem;margin:2rem auto 0}.surfside-ministry-directory__closing h3{margin:0 0 .35rem}.surfside-ministry-directory__closing p{color:#536579}.surfside-ministry-directory__dialog{width:min(calc(100% - 2rem),34rem);border:0;border-radius:18px;padding:28px;box-shadow:0 24px 70px rgba(6,27,51,.28)}.surfside-ministry-directory__dialog::backdrop{background:rgba(6,27,51,.52)}.surfside-ministry-directory__close{position:absolute;right:14px;top:12px;width:38px;height:38px;border:0;border-radius:50%;font-size:1.5rem;cursor:pointer}@media(max-width:900px){.surfside-ministry-directory__grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:700px){.surfside-ministry-directory__grid{grid-template-columns:1fr}}
    </style>
    <script>(function(){var root=document.getElementById(<?php echo wp_json_encode($id); ?>);if(!root)return;var cards=[].slice.call(root.querySelectorAll('[data-card]'));var buttons=[].slice.call(root.querySelectorAll('[data-filter]'));var clear=root.querySelector('[data-clear]');var dialog=root.parentNode.querySelector('.surfside-ministry-directory__dialog');function apply(filter){cards.forEach(function(c){var show=filter?c.dataset.audiences.split(' ').indexOf(filter)!==-1:c.dataset.featured!=='1';c.hidden=!show;});buttons.forEach(function(b){b.setAttribute('aria-pressed',b.dataset.filter===filter?'true':'false');});if(clear)clear.hidden=!filter;}buttons.forEach(function(b){b.addEventListener('click',function(){apply(b.dataset.filter);});});if(clear)clear.addEventListener('click',function(){apply('');});cards.forEach(function(c){c.addEventListener('click',function(){if(!dialog)return;dialog.querySelector('[data-title]').textContent=(c.dataset.icon?c.dataset.icon+' ':'')+c.dataset.name;dialog.querySelector('[data-schedule]').textContent=c.dataset.schedule||'';dialog.querySelector('[data-location]').textContent=c.dataset.location||'';dialog.querySelector('[data-description]').textContent=c.dataset.description||'';dialog.showModal();});});if(dialog){dialog.querySelector('.surfside-ministry-directory__close').addEventListener('click',function(){dialog.close();});dialog.addEventListener('click',function(e){if(e.target===dialog)dialog.close();});}})();</script>
    </section>
    <?php return ob_get_clean();
}

remove_shortcode('surfside_featured_ministries');
remove_shortcode('surfside_all_ministries');
add_shortcode('surfside_featured_ministries','surfside_tools_featured_ministries_shortcode');
add_shortcode('surfside_all_ministries','surfside_tools_all_ministries_shortcode');
