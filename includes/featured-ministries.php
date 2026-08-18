<?php
/** Featured Ministry classification and alternate website list. */
if (!defined('ABSPATH')) { exit; }

function surfside_tools_event_is_featured_ministry($event_id) {
    $event_id = absint($event_id);
    if (!$event_id || !get_post_meta($event_id, '_surfside_event_show_on_ministries', true)) return false;
    if (!metadata_exists('post', $event_id, '_surfside_event_featured_ministry')) return true;
    return (bool) get_post_meta($event_id, '_surfside_event_featured_ministry', true);
}

function surfside_tools_featured_ministry_save($post_id, $post, $update) {
    if (!$post || $post->post_type !== 'surfside_event' || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || wp_is_post_revision($post_id)) return;
    if (empty($_POST['surfside_calendar_action']) || sanitize_key(wp_unslash($_POST['surfside_calendar_action'])) !== 'save') return;
    if (empty($_POST['surfside_calendar_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['surfside_calendar_nonce'])), 'surfside_calendar_manager')) return;
    if (!current_user_can('edit_post', $post_id)) return;
    $is_ministry = !empty($_POST['event_show_on_ministries']);
    update_post_meta($post_id, '_surfside_event_featured_ministry', ($is_ministry && !empty($_POST['event_featured_ministry'])) ? 1 : 0);
}
add_action('save_post_surfside_event', 'surfside_tools_featured_ministry_save', 40, 3);

function surfside_tools_featured_ministry_manager_fields() {
    if (!is_user_logged_in() || !current_user_can('upload_files')) return;
    $edit_id = isset($_GET['edit_event']) ? absint($_GET['edit_event']) : 0;
    $checked = $edit_id ? surfside_tools_event_is_featured_ministry($edit_id) : false;
    ?>
    <template id="surfside-featured-ministry-template"><label class="surfside-calendar-checkbox surfside-calendar-featured-check surfside-featured-ministry-check" data-surfside-featured-ministry hidden><input type="checkbox" name="event_featured_ministry" value="1" <?php checked($checked); ?>><span>Featured Ministry</span></label></template>
    <style>.surfside-featured-ministry-check{margin-left:20px!important;margin-right:20px!important}.surfside-featured-ministry-check[hidden]{display:none!important}</style>
    <script>document.addEventListener('DOMContentLoaded',function(){var f=document.querySelector('.surfside-calendar-form'),t=document.getElementById('surfside-featured-ministry-template');if(!f||!t||f.querySelector('[name="event_featured_ministry"]'))return;var m=f.querySelector('input[name="event_show_on_ministries"]'),a=m?m.closest('label'):null,x=t.content.cloneNode(true).querySelector('[data-surfside-featured-ministry]');if(a&&a.parentNode)a.parentNode.insertBefore(x,a.nextSibling);else f.appendChild(x);function u(){x.hidden=!(m&&m.checked);var c=x.querySelector('input');if(c)c.disabled=x.hidden}if(m)m.addEventListener('change',u);u();});</script>
    <?php
}
add_action('wp_footer','surfside_tools_featured_ministry_manager_fields',100);
add_action('admin_footer','surfside_tools_featured_ministry_manager_fields',100);

function surfside_tools_all_ministries_shortcode($attributes=array()) {
    $attributes = shortcode_atts(array('title'=>'More Ministries','intro'=>'Find more ways to connect at Surfside.','include_featured'=>'no'),$attributes,'surfside_all_ministries');
    $today=current_time('Y-m-d'); $end=wp_date('Y-m-d',strtotime($today.' +2 years')); $items=array();
    foreach(surfside_tools_calendar_get_all_events() as $event){
        $id=absint($event['id']??0); if(!$id||empty($event['show_on_ministries'])) continue;
        $featured=surfside_tools_event_is_featured_ministry($id);
        if($featured && strtolower((string)$attributes['include_featured'])!=='yes') continue;
        $occ=surfside_tools_calendar_event_occurrences($event,$today,$end); if(empty($occ)) continue;
        $event['next_occurrence_date']=$occ[0]['date']??''; $event['audience']=function_exists('surfside_tools_event_audience')?surfside_tools_event_audience($id):''; $items[]=$event;
    }
    if(empty($items)) return '';
    usort($items,function($a,$b){return strcasecmp($a['title']??'',$b['title']??'');});
    $audience_labels=function_exists('surfside_tools_event_audience_choices')?surfside_tools_event_audience_choices():array();
    ob_start(); ?>
    <section class="surfside-all-ministries"><div class="surfside-all-ministries__inner"><div class="surfside-all-ministries__intro"><h2><?php echo esc_html($attributes['title']); ?></h2><?php if(trim((string)$attributes['intro'])!==''): ?><p><?php echo esc_html($attributes['intro']); ?></p><?php endif; ?></div><div class="surfside-all-ministries__list">
    <?php foreach($items as $event): $aud=$event['audience']??''; ?><article class="surfside-all-ministries__item"><div><h3><?php echo esc_html($event['title']??''); ?></h3><p class="surfside-all-ministries__meta"><?php if($aud&&isset($audience_labels[$aud])): ?><span><?php echo esc_html($audience_labels[$aud]); ?></span><?php endif; ?><?php if(surfside_tools_calendar_recurrence_label($event)): ?><span><?php echo esc_html(surfside_tools_calendar_recurrence_label($event)); ?></span><?php endif; ?><?php if(!empty($event['location'])): ?><span><?php echo esc_html($event['location']); ?></span><?php endif; ?></p><?php if(!empty($event['description'])): ?><p><?php echo esc_html(wp_trim_words(wp_strip_all_tags($event['description']),24)); ?></p><?php endif; ?></div><div class="surfside-all-ministries__next"><strong>Next</strong><span><?php echo esc_html(surfside_tools_calendar_format_date($event['next_occurrence_date'])); ?></span><span><?php echo esc_html(surfside_tools_calendar_format_time_range($event)); ?></span></div></article><?php endforeach; ?>
    </div></div></section>
    <style>.surfside-all-ministries{margin:32px 0}.surfside-all-ministries__inner{max-width:1100px;margin:0 auto}.surfside-all-ministries__intro{margin-bottom:18px}.surfside-all-ministries__intro h2{margin:0 0 6px}.surfside-all-ministries__intro p{margin:0;color:#60708a}.surfside-all-ministries__list{display:grid;gap:12px}.surfside-all-ministries__item{display:flex;justify-content:space-between;gap:24px;padding:18px 20px;border:1px solid rgba(7,27,58,.14);border-radius:14px;background:#fff}.surfside-all-ministries__item h3{margin:0 0 7px}.surfside-all-ministries__item p{margin:6px 0 0}.surfside-all-ministries__meta{display:flex;gap:8px;flex-wrap:wrap;color:#60708a;font-size:.92rem}.surfside-all-ministries__meta span:not(:last-child):after{content:' ·';}.surfside-all-ministries__next{min-width:150px;text-align:right;display:flex;flex-direction:column;gap:3px;color:#60708a}.surfside-all-ministries__next strong{color:#071b3a}@media(max-width:700px){.surfside-all-ministries__item{display:block}.surfside-all-ministries__next{margin-top:12px;text-align:left}}
    </style><?php return ob_get_clean();
}
add_shortcode('surfside_all_ministries','surfside_tools_all_ministries_shortcode');

function surfside_tools_featured_ministry_api($result,$server,$request){if(!$request||$request->get_route()!=='/surfside/v1/events')return $result;$response=rest_ensure_response($result);$data=$response->get_data();if(empty($data['events'])||!is_array($data['events']))return $response;foreach($data['events'] as &$event){$id=absint($event['id']??0);if($id)$event['is_featured_ministry']=surfside_tools_event_is_featured_ministry($id);}unset($event);$response->set_data($data);return $response;}
add_filter('rest_post_dispatch','surfside_tools_featured_ministry_api',20,3);
