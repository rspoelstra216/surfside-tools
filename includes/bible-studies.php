<?php
/** Bible Study event classification and mobile API support. */
if (!defined('ABSPATH')) { exit; }

function surfside_tools_bible_study_audience_choices() { return function_exists('surfside_tools_event_audience_choices') ? surfside_tools_event_audience_choices() : array('kids'=>'Kids','youth'=>'Youth','adults'=>'Adults','all-ages'=>'All Ages'); }
function surfside_tools_bible_study_audience($event_id) { return function_exists('surfside_tools_event_audience') ? surfside_tools_event_audience($event_id) : sanitize_key((string)get_post_meta(absint($event_id),'_surfside_event_bible_study_audience',true)); }
function surfside_tools_event_is_bible_study($event_id) { return (bool)get_post_meta(absint($event_id),'_surfside_event_is_bible_study',true); }

function surfside_tools_bible_study_save_event_meta($post_id,$post,$update) {
    if (!$post || $post->post_type !== 'surfside_event' || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || wp_is_post_revision($post_id)) return;
    if (empty($_POST['surfside_calendar_action']) || sanitize_key(wp_unslash($_POST['surfside_calendar_action'])) !== 'save') return;
    if (empty($_POST['surfside_calendar_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['surfside_calendar_nonce'])),'surfside_calendar_manager')) return;
    if (!current_user_can('edit_post',$post_id)) return;
    update_post_meta($post_id,'_surfside_event_is_bible_study',!empty($_POST['event_is_bible_study'])?1:0);
}
add_action('save_post_surfside_event','surfside_tools_bible_study_save_event_meta',20,3);

function surfside_tools_bible_study_manager_fields() {
    if (!is_user_logged_in() || !current_user_can('upload_files')) return;
    $edit_id=isset($_GET['edit_event'])?absint($_GET['edit_event']):0;
    $is_bible_study=$edit_id?surfside_tools_event_is_bible_study($edit_id):false;
    $study_ids=get_posts(array('post_type'=>'surfside_event','post_status'=>'publish','posts_per_page'=>-1,'fields'=>'ids','meta_key'=>'_surfside_event_is_bible_study','meta_value'=>'1','no_found_rows'=>true)); ?>
    <template id="surfside-bible-study-fields-template"><label class="surfside-calendar-checkbox surfside-calendar-featured-check surfside-bible-study-check"><input type="checkbox" name="event_is_bible_study" value="1" data-surfside-bible-study-toggle <?php checked($is_bible_study); ?>><span>List as Bible Study</span></label></template>
    <script>document.addEventListener('DOMContentLoaded',function(){var f=document.querySelector('.surfside-calendar-form'),t=document.getElementById('surfside-bible-study-fields-template');if(f&&t&&!f.querySelector('[name="event_is_bible_study"]')){var m=f.querySelector('input[name="event_show_on_ministries"]'),a=m?m.closest('label'):f.querySelector('.surfside-calendar-form-actions'),x=t.content.cloneNode(true);if(a&&a.parentNode)a.parentNode.insertBefore(x,a.nextSibling);else f.appendChild(x)}var ids=<?php echo wp_json_encode(array_values(array_map('absint',$study_ids))); ?>;document.querySelectorAll('.surfside-calendar-event').forEach(function(c){var l=c.querySelector('a[href*="edit_event="]'),h=c.querySelector('h3');if(!l||!h)return;try{var id=parseInt(new URL(l.href,window.location.href).searchParams.get('edit_event')||'0',10);if(ids.indexOf(id)===-1||h.querySelector('[data-bible-study-badge]'))return;var b=document.createElement('span');b.className='surfside-calendar-featured-badge';b.setAttribute('data-bible-study-badge','1');b.textContent='Bible Study';h.appendChild(document.createTextNode(' '));h.appendChild(b)}catch(e){}})});</script><?php
}
add_action('wp_footer','surfside_tools_bible_study_manager_fields',98); add_action('admin_footer','surfside_tools_bible_study_manager_fields',98);

function surfside_tools_bible_study_enrich_events_response($result,$server,$request) {
    if (!$request || $request->get_route()!=='/surfside/v1/events') return $result;
    $response=rest_ensure_response($result); $data=$response->get_data(); if(empty($data['events'])||!is_array($data['events'])) return $response;
    foreach($data['events'] as &$event){$id=absint($event['id']??0);if(!$id)continue;$event['is_ministry']=(bool)get_post_meta($id,'_surfside_event_show_on_ministries',true);$event['is_bible_study']=surfside_tools_event_is_bible_study($id);$event['audience']=surfside_tools_bible_study_audience($id);$event['bible_study_audience']=$event['audience'];} unset($event);$response->set_data($data);return $response;
}
add_filter('rest_post_dispatch','surfside_tools_bible_study_enrich_events_response',10,3);
function surfside_tools_bible_study_register_mobile_route(){register_rest_route('surfside/v1','/bible-studies',array('methods'=>WP_REST_Server::READABLE,'callback'=>'surfside_tools_mobile_api_bible_studies','permission_callback'=>'__return_true'));} add_action('rest_api_init','surfside_tools_bible_study_register_mobile_route');
function surfside_tools_mobile_api_bible_studies(){
    if(!function_exists('surfside_tools_calendar_get_all_events')||!function_exists('surfside_tools_calendar_event_occurrences'))return new WP_Error('surfside_bible_studies_unavailable','Bible Studies are temporarily unavailable.',array('status'=>503));
    $today=current_time('Y-m-d');$end=wp_date('Y-m-d',strtotime($today.' +2 years'));$studies=array();
    foreach(surfside_tools_calendar_get_all_events() as $event){$id=absint($event['id']??0);if(!$id||!surfside_tools_event_is_bible_study($id))continue;$occ=surfside_tools_calendar_event_occurrences($event,$today,$end);if(empty($occ))continue;$next=$occ[0];$item=function_exists('surfside_tools_mobile_api_event')?surfside_tools_mobile_api_event($next):array('id'=>$id,'title'=>(string)($event['title']??''),'description'=>wp_strip_all_tags((string)($event['description']??''),true),'date'=>(string)($next['date']??''),'start_time'=>(string)($event['start_time']??''),'end_time'=>(string)($event['end_time']??''));$item['is_ministry']=!empty($event['show_on_ministries']);$item['is_bible_study']=true;$item['audience']=surfside_tools_bible_study_audience($id);$item['bible_study_audience']=$item['audience'];$item['next_occurrence']=(string)($next['date']??'');$item['recurrence_label']=function_exists('surfside_tools_calendar_recurrence_label')?surfside_tools_calendar_recurrence_label($event):'';$item['upcoming_dates']=array_values(array_slice(array_map(function($o){return(string)($o['date']??'');},$occ),0,12));$studies[]=$item;}
    usort($studies,function($a,$b){return strcmp(($a['next_occurrence']??'').' '.(($a['start_time']??'')?:'00:00'),($b['next_occurrence']??'').' '.(($b['start_time']??'')?:'00:00'));});return rest_ensure_response(array('api_version'=>1,'generated_at'=>current_datetime()->format(DATE_ATOM),'count'=>count($studies),'studies'=>array_values($studies)));
}
