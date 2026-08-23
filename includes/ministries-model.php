<?php
/** Canonical ministry data model. */
if (!defined('ABSPATH')) { exit; }
const SURFSIDE_TOOLS_MINISTRIES_OPTION = 'surfside_tools_ministries';
const SURFSIDE_TOOLS_MINISTRY_DEFAULT_EMAIL_OPTION = 'surfside_tools_ministry_default_email';

function surfside_tools_ministry_audience_choices(){return array('kids'=>'Kids','youth'=>'Youth','adults'=>'Adults','all_ages'=>'All Ages');}
function surfside_tools_sanitize_ministries($ministries){
 $ministries=is_array($ministries)?$ministries:array(); $allowed=array_keys(surfside_tools_ministry_audience_choices()); $clean=array();
 foreach($ministries as $index=>$m){if(!is_array($m))continue;$name=sanitize_text_field($m['name']??'');if($name==='')continue;$key=sanitize_key($m['key']??'');if($key==='')$key='ministry-'.substr(md5(wp_json_encode(array($name,$index))),0,12);$aud=isset($m['audiences'])&&is_array($m['audiences'])?array_map('sanitize_key',$m['audiences']):array('adults');$aud=array_values(array_unique(array_intersect($aud,$allowed)));if(!$aud)$aud=array('adults');$featured=array_key_exists('featured',$m)?!empty($m['featured']):true;$published=array_key_exists('published',$m)?!empty($m['published']):true;
  $clean[]=array('key'=>$key,'icon'=>sanitize_text_field($m['icon']??''),'name'=>$name,'schedule'=>sanitize_text_field($m['schedule']??''),'location'=>sanitize_text_field($m['location']??''),'description'=>sanitize_textarea_field($m['description']??''),'audiences'=>$aud,'featured'=>$featured,'published'=>$published,'contact_name'=>sanitize_text_field($m['contact_name']??''),'contact_email'=>sanitize_email($m['contact_email']??''),'contact_phone'=>sanitize_text_field($m['contact_phone']??''));
 }
 return $clean;
}
function surfside_tools_get_ministries(){
 $stored=get_option(SURFSIDE_TOOLS_MINISTRIES_OPTION,null);if(is_array($stored)&&!empty($stored))return surfside_tools_sanitize_ministries($stored);
 $info=function_exists('surfside_tools_get_site_information')?surfside_tools_get_site_information():array();$legacy=isset($info['adult_ministries'])&&is_array($info['adult_ministries'])?$info['adult_ministries']:array();foreach($legacy as &$m){if(is_array($m)&&empty($m['audiences']))$m['audiences']=array('adults');if(is_array($m)&&!array_key_exists('featured',$m))$m['featured']=true;if(is_array($m)&&!array_key_exists('published',$m))$m['published']=true;}unset($m);return surfside_tools_sanitize_ministries($legacy);
}
function surfside_tools_get_published_ministries(){return array_values(array_filter((array)surfside_tools_get_ministries(),function($m){return !array_key_exists('published',$m)||!empty($m['published']);}));}
function surfside_tools_update_ministries($ministries){$clean=surfside_tools_sanitize_ministries($ministries);$updated=update_option(SURFSIDE_TOOLS_MINISTRIES_OPTION,$clean,false);if(function_exists('surfside_tools_purge_cache'))surfside_tools_purge_cache();return $updated;}
function surfside_tools_get_ministry_default_email(){return sanitize_email((string)get_option(SURFSIDE_TOOLS_MINISTRY_DEFAULT_EMAIL_OPTION,''));}
function surfside_tools_update_ministry_default_email($email){return update_option(SURFSIDE_TOOLS_MINISTRY_DEFAULT_EMAIL_OPTION,sanitize_email($email),false);}
function surfside_tools_resolve_ministry_contact($m){$email=sanitize_email($m['contact_email']??'');$source='ministry';if($email===''){$email=surfside_tools_get_ministry_default_email();$source='default';}return array('name'=>sanitize_text_field($m['contact_name']??''),'email'=>$email,'phone'=>sanitize_text_field($m['contact_phone']??''),'source'=>$source);}
function surfside_tools_ministry_audience_labels($m){$choices=surfside_tools_ministry_audience_choices();$aud=isset($m['audiences'])&&is_array($m['audiences'])?$m['audiences']:array('adults');$labels=array();foreach($aud as $a)if(isset($choices[$a]))$labels[]=$choices[$a];return $labels;}
function surfside_tools_register_ministries_mobile_route(){register_rest_route('surfside/v1','/ministries',array('methods'=>WP_REST_Server::READABLE,'callback'=>'surfside_tools_mobile_api_ministries','permission_callback'=>'__return_true'));}add_action('rest_api_init','surfside_tools_register_ministries_mobile_route');
function surfside_tools_mobile_api_ministries(){
 $ministries=array();foreach((array)surfside_tools_get_published_ministries() as $m){$ministries[]=array('key'=>(string)($m['key']??''),'icon'=>(string)($m['icon']??''),'name'=>(string)($m['name']??''),'schedule'=>(string)($m['schedule']??''),'location'=>(string)($m['location']??''),'description'=>(string)($m['description']??''),'audiences'=>array_values((array)($m['audiences']??array())),'audience_labels'=>surfside_tools_ministry_audience_labels($m),'featured'=>!empty($m['featured']),'contact'=>surfside_tools_resolve_ministry_contact($m));}
 return rest_ensure_response(array('api_version'=>1,'generated_at'=>current_datetime()->format(DATE_ATOM),'count'=>count($ministries),'ministries'=>$ministries));
}
