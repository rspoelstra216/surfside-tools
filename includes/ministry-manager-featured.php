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
    <?php foreach($items as $ministry): ?><article class="surfside-adult-ministries__card surfside-featured-ministries__card"><h3><?php if(!empty($ministry['icon'])): ?><span aria-hidden="true"><?php echo esc_html($ministry['icon']); ?></span> <?php endif; ?><?php echo esc_html($ministry['name']??''); ?></h3><?php if(!empty($ministry['schedule'])): ?><p class="surfside-adult-ministries__schedule"><?php echo esc_html($ministry['schedule']); ?></p><?php endif; ?><?php if(!empty($ministry['location'])): ?><p class="surfside-adult-ministries__location"><?php echo esc_html($ministry['location']); ?></p><?php endif; ?><?php if(!empty($ministry['description'])): ?><p class="surfside-adult-ministries__description"><?php echo esc_html($ministry['description']); ?></p><?php endif; ?></article><?php endforeach; ?>
    </div></div><style>.surfside-featured-ministries__card{background:#fff!important}</style></section>
    <?php return ob_get_clean();
}

function surfside_tools_all_ministries_manager_shortcode($attributes=array()) {
    $attributes=shortcode_atts(array(
        'title'=>'Ministry Directory',
        'intro'=>'Explore more ways to connect, grow, and serve at Surfside.',
        'closing_title'=>'Interested in Serving?',
        'closing'=>'There are too many opportunities to list here! We’d love to help you find a place to use your gifts and talents.',
        'button'=>'Contact Us About Serving',
        'button_url'=>home_url('/contact/'),
    ),$attributes,'surfside_all_ministries');

    $items=array_values((array)surfside_tools_get_ministries());
    if(empty($items))return '';

    $filters=array();
    foreach($items as $m){
        $labels=function_exists('surfside_tools_ministry_audience_labels')?surfside_tools_ministry_audience_labels($m):array();
        foreach($labels as $label){
            $slug=sanitize_title($label);
            if($slug!==''&&!isset($filters[$slug]))$filters[$slug]=$label;
        }
    }
    $preferred=array('kids'=>1,'youth'=>2,'adults'=>3,'all-ages'=>4);
    uksort($filters,function($a,$b)use($preferred){
        $pa=$preferred[$a]??99; $pb=$preferred[$b]??99;
        return $pa===$pb?strcmp($a,$b):($pa<$pb?-1:1);
    });

    $directory_id=wp_unique_id('surfside-ministry-directory-');
    $dialog_id=wp_unique_id('surfside-ministry-dialog-');
    ob_start(); ?>
    <section class="surfside-all-ministries" id="<?php echo esc_attr($directory_id); ?>">
      <div class="surfside-all-ministries__inner">
        <div class="surfside-all-ministries__intro">
          <h2><?php echo esc_html($attributes['title']); ?></h2>
          <?php if(trim((string)$attributes['intro'])!==''): ?><p><?php echo esc_html($attributes['intro']); ?></p><?php endif; ?>
        </div>
        <?php if(!empty($filters)): ?>
          <div class="surfside-all-ministries__filters" aria-label="Filter ministries by audience">
            <span>Filter by audience:</span>
            <?php foreach($filters as $slug=>$label): ?><button type="button" data-ministry-filter="<?php echo esc_attr($slug); ?>" aria-pressed="false"><?php echo esc_html($label); ?></button><?php endforeach; ?>
            <button type="button" class="surfside-all-ministries__clear" data-ministry-filter-clear hidden>Clear filter</button>
          </div>
        <?php endif; ?>
        <div class="surfside-all-ministries__list" data-ministry-directory-list>
          <?php foreach($items as $m):
              $labels=function_exists('surfside_tools_ministry_audience_labels')?surfside_tools_ministry_audience_labels($m):array();
              $audience_slugs=array_values(array_filter(array_map('sanitize_title',$labels)));
              $is_featured=!empty($m['featured']);
          ?>
            <button type="button" class="surfside-all-ministries__item" data-ministry-directory-item data-featured="<?php echo $is_featured?'1':'0'; ?>" data-audiences="<?php echo esc_attr(implode(' ', $audience_slugs)); ?>" data-name="<?php echo esc_attr($m['name']??''); ?>" data-icon="<?php echo esc_attr($m['icon']??''); ?>" data-schedule="<?php echo esc_attr($m['schedule']??''); ?>" data-location="<?php echo esc_attr($m['location']??''); ?>" data-description="<?php echo esc_attr($m['description']??''); ?>" aria-haspopup="dialog" aria-controls="<?php echo esc_attr($dialog_id); ?>" <?php if($is_featured): ?>hidden<?php endif; ?>>
              <span class="surfside-all-ministries__name"><?php if(!empty($m['icon'])): ?><span aria-hidden="true"><?php echo esc_html($m['icon']); ?></span> <?php endif; ?><?php echo esc_html($m['name']??''); ?></span>
              <span class="surfside-all-ministries__view">View details</span>
            </button>
          <?php endforeach; ?>
        </div>

        <dialog class="surfside-ministry-dialog" id="<?php echo esc_attr($dialog_id); ?>" data-ministry-dialog aria-labelledby="<?php echo esc_attr($dialog_id); ?>-title">
          <div class="surfside-ministry-dialog__panel">
            <button type="button" class="surfside-ministry-dialog__close" data-ministry-dialog-close aria-label="Close ministry details">×</button>
            <h3 id="<?php echo esc_attr($dialog_id); ?>-title" data-ministry-dialog-title></h3>
            <p class="surfside-ministry-dialog__schedule" data-ministry-dialog-schedule hidden></p>
            <p class="surfside-ministry-dialog__location" data-ministry-dialog-location hidden></p>
            <p class="surfside-ministry-dialog__description" data-ministry-dialog-description hidden></p>
          </div>
        </dialog>

        <div class="surfside-all-ministries__closing">
          <?php if(trim((string)$attributes['closing_title'])!==''): ?><h3><?php echo esc_html($attributes['closing_title']); ?></h3><?php endif; ?>
          <?php if(trim((string)$attributes['closing'])!==''): ?><p><?php echo esc_html($attributes['closing']); ?></p><?php endif; ?>
          <?php if(trim((string)$attributes['button'])!=='' && trim((string)$attributes['button_url'])!==''): ?><a class="surfside-button" href="<?php echo esc_url($attributes['button_url']); ?>"><?php echo esc_html($attributes['button']); ?></a><?php endif; ?>
        </div>
      </div>
      <style>
      .surfside-all-ministries{position:relative;z-index:0;box-sizing:border-box;width:100%!important;max-width:none!important;margin:0!important;padding-block:clamp(2.5rem,4vw,3.25rem);color:var(--surfside-color-ink,#10243a)}
      .surfside-all-ministries::before{position:absolute;z-index:-1;inset-block:0;left:50%;width:100vw;width:100dvw;background:var(--surfside-color-white,#fff);content:"";transform:translateX(-50%)}
      .surfside-all-ministries__inner{box-sizing:border-box;width:min(100% - 2rem,72rem);margin-inline:auto}
      .surfside-all-ministries__intro{margin-bottom:14px}.surfside-all-ministries__intro h2{margin:0 0 4px}.surfside-all-ministries__intro p{margin:0;color:#60708a}
      .surfside-all-ministries__filters{display:flex;align-items:center;flex-wrap:wrap;gap:7px;margin:0 0 14px;color:#60708a;font-size:.85rem}.surfside-all-ministries__filters>span{font-weight:700;color:#31566d}.surfside-all-ministries__filters button{padding:6px 10px;border:1px solid var(--surfside-color-border,#d8e1e9);border-radius:999px;background:#fff;color:#31566d;font:inherit;font-weight:800;cursor:pointer}.surfside-all-ministries__filters button[aria-pressed="true"]{border-color:var(--surfside-color-blue-700,#075c9c);background:#eef4f7;color:var(--surfside-color-blue-700,#075c9c)}.surfside-all-ministries__filters button:focus-visible{outline:3px solid rgba(11,95,165,.2);outline-offset:2px}.surfside-all-ministries__clear{border-color:transparent!important;background:transparent!important;text-decoration:underline}
      .surfside-all-ministries__list{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px}
      .surfside-all-ministries__item{appearance:none;display:flex;min-height:74px;padding:10px 14px;border:1px solid var(--surfside-color-border,#d8e1e9);border-radius:10px;background:#fff;color:inherit;font:inherit;flex-direction:column;align-items:center;justify-content:center;gap:5px;text-align:center;cursor:pointer;transition:border-color .15s ease,box-shadow .15s ease,transform .15s ease}.surfside-all-ministries__item[hidden]{display:none!important}.surfside-all-ministries__item:hover{border-color:#a9bdca;box-shadow:0 4px 12px rgba(6,27,51,.07);transform:translateY(-1px)}.surfside-all-ministries__item:focus-visible{outline:3px solid rgba(11,95,165,.2);outline-offset:2px}
      .surfside-all-ministries__name{font-size:1rem;font-weight:800;line-height:1.2}.surfside-all-ministries__view{color:#60708a;font-size:.75rem;font-weight:700}
      .surfside-all-ministries__closing{text-align:center;max-width:54rem;margin:2rem auto 0}.surfside-all-ministries__closing h3{margin:0 0 .35rem;color:var(--surfside-color-ocean-950,#061b33);font-size:1.05rem}.surfside-all-ministries__closing p{margin:0 0 .9rem;color:var(--surfside-color-muted,#536579)}
      .surfside-ministry-dialog{width:min(calc(100% - 2rem),34rem);padding:0;border:0;border-radius:18px;background:#fff;color:var(--surfside-color-ink,#10243a);box-shadow:0 24px 70px rgba(6,27,51,.28)}.surfside-ministry-dialog::backdrop{background:rgba(6,27,51,.52)}.surfside-ministry-dialog__panel{position:relative;padding:28px}.surfside-ministry-dialog__close{position:absolute;top:12px;right:14px;width:38px;height:38px;padding:0;border:0;border-radius:50%;background:#eef4f7;color:#31566d;font-size:1.6rem;line-height:1;cursor:pointer}.surfside-ministry-dialog h3{margin:0 44px 16px 0;color:var(--surfside-color-ocean-950,#061b33);font-size:1.55rem;line-height:1.2}.surfside-ministry-dialog__schedule{margin:0;color:var(--surfside-color-blue-700,#075c9c);font-weight:800}.surfside-ministry-dialog__location{margin:.25rem 0 0;color:#60708a}.surfside-ministry-dialog__description{margin:1rem 0 0;line-height:1.6}.surfside-ministry-dialog p[hidden]{display:none!important}
      @media(max-width:900px){.surfside-all-ministries__list{grid-template-columns:repeat(2,minmax(0,1fr))}}
      @media(max-width:700px){.surfside-all-ministries__inner{width:min(100% - 1.25rem,72rem)}.surfside-all-ministries__list{grid-template-columns:1fr}.surfside-all-ministries__item{min-height:64px}}
      </style>
      <script>
      (function(){
        var root=document.getElementById(<?php echo wp_json_encode($directory_id); ?>); if(!root)return;
        var items=root.querySelectorAll('[data-ministry-directory-item]');
        var buttons=root.querySelectorAll('[data-ministry-filter]');
        var clear=root.querySelector('[data-ministry-filter-clear]');
        var dialog=root.querySelector('[data-ministry-dialog]');
        var dialogTitle=root.querySelector('[data-ministry-dialog-title]');
        var dialogSchedule=root.querySelector('[data-ministry-dialog-schedule]');
        var dialogLocation=root.querySelector('[data-ministry-dialog-location]');
        var dialogDescription=root.querySelector('[data-ministry-dialog-description]');
        var dialogClose=root.querySelector('[data-ministry-dialog-close]');

        function reset(){
          items.forEach(function(item){item.hidden=item.getAttribute('data-featured')==='1';});
          buttons.forEach(function(button){button.setAttribute('aria-pressed','false');});
          if(clear)clear.hidden=true;
        }
        function fillField(node,value){if(!node)return;node.textContent=value||'';node.hidden=!value;}
        function openDetails(item){
          if(!dialog)return;
          var name=item.getAttribute('data-name')||'';
          var icon=item.getAttribute('data-icon')||'';
          if(dialogTitle)dialogTitle.textContent=(icon?icon+' ':'')+name;
          fillField(dialogSchedule,item.getAttribute('data-schedule')||'');
          fillField(dialogLocation,item.getAttribute('data-location')||'');
          fillField(dialogDescription,item.getAttribute('data-description')||'');
          if(typeof dialog.showModal==='function')dialog.showModal();else dialog.setAttribute('open','open');
        }

        buttons.forEach(function(button){button.addEventListener('click',function(){
          var filter=button.getAttribute('data-ministry-filter');
          buttons.forEach(function(other){other.setAttribute('aria-pressed',other===button?'true':'false');});
          items.forEach(function(item){var audiences=(item.getAttribute('data-audiences')||'').split(/\s+/); item.hidden=audiences.indexOf(filter)===-1;});
          if(clear)clear.hidden=false;
        });});
        items.forEach(function(item){item.addEventListener('click',function(){openDetails(item);});});
        if(clear)clear.addEventListener('click',reset);
        if(dialogClose)dialogClose.addEventListener('click',function(){if(typeof dialog.close==='function')dialog.close();else dialog.removeAttribute('open');});
        if(dialog)dialog.addEventListener('click',function(event){if(event.target===dialog&&typeof dialog.close==='function')dialog.close();});
        reset();
      }());
      </script>
    </section>
    <?php return ob_get_clean();
}

add_action('init',function(){remove_shortcode('surfside_featured_ministries');remove_shortcode('surfside_all_ministries');add_shortcode('surfside_featured_ministries','surfside_tools_featured_ministries_manager_shortcode');add_shortcode('surfside_all_ministries','surfside_tools_all_ministries_manager_shortcode');},20);
