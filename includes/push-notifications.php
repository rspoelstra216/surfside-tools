<?php
/** Push notification registration and staff sender for the Surfside mobile app. */
if (!defined('ABSPATH')) { exit; }

function surfside_tools_push_default_preferences(){
    return array('church_updates'=>true,'events_ministries'=>true,'kids_ministry'=>false,'livestream'=>false);
}
function surfside_tools_push_devices(){
    $devices=get_option('surfside_tools_push_devices',array());
    if(!is_array($devices))$devices=array();
    // Complete the original token-only migration once, then stop resurrecting legacy tokens.
    if(!get_option('surfside_tools_push_legacy_migrated',false)){
        foreach(surfside_tools_push_tokens_legacy() as $token){
            $key=hash('sha256',$token);
            if(!isset($devices[$key]))$devices[$key]=array('token'=>$token,'preferences'=>surfside_tools_push_default_preferences(),'updated_at'=>time());
        }
        update_option('surfside_tools_push_legacy_migrated',1,false);
        delete_option('surfside_tools_push_tokens');
        update_option('surfside_tools_push_devices',$devices,false);
    }
    return $devices;
}
function surfside_tools_push_tokens_legacy(){
    $tokens=get_option('surfside_tools_push_tokens',array());
    return is_array($tokens)?array_values(array_unique(array_filter(array_map('sanitize_text_field',$tokens)))):array();
}
function surfside_tools_push_tokens(){
    return array_values(array_filter(array_map(function($device){return sanitize_text_field($device['token']??'');},surfside_tools_push_devices())));
}
function surfside_tools_push_valid_token($token){
    return (bool)preg_match('/^(ExponentPushToken|ExpoPushToken)\\[[A-Za-z0-9_-]+\\]$/',(string)$token);
}
function surfside_tools_push_sanitize_preferences($input){
    $defaults=surfside_tools_push_default_preferences();$input=is_array($input)?$input:array();$out=array();
    foreach($defaults as $key=>$default)$out[$key]=array_key_exists($key,$input)?(bool)$input[$key]:$default;
    return $out;
}
function surfside_tools_push_audience_counts($devices){
    $counts=array_fill_keys(array_keys(surfside_tools_push_default_preferences()),0);
    foreach((array)$devices as $device){
        $prefs=surfside_tools_push_sanitize_preferences($device['preferences']??array());
        foreach($counts as $key=>$count){if(!empty($prefs[$key]))$counts[$key]++;}
    }
    return $counts;
}
function surfside_tools_push_remove_tokens($tokens){
    $tokens=array_values(array_unique(array_filter(array_map('sanitize_text_field',(array)$tokens))));if(!$tokens)return 0;
    $devices=surfside_tools_push_devices();$removed=0;
    foreach($devices as $key=>$device){if(in_array(sanitize_text_field($device['token']??''),$tokens,true)){unset($devices[$key]);$removed++;}}
    if($removed)update_option('surfside_tools_push_devices',$devices,false);
    return $removed;
}
function surfside_tools_push_legacy_count($devices){
    $count=0;foreach((array)$devices as $device){if(sanitize_key($device['platform']??'')==='')$count++;}return $count;
}
function surfside_tools_push_remove_legacy_devices(){
    $devices=surfside_tools_push_devices();$removed=0;
    foreach($devices as $key=>$device){if(sanitize_key($device['platform']??'')===''){unset($devices[$key]);$removed++;}}
    if($removed)update_option('surfside_tools_push_devices',$devices,false);
    return $removed;
}
function surfside_tools_push_store_receipts($tickets,$messages){
    $pending=get_option('surfside_tools_push_pending_receipts',array());if(!is_array($pending))$pending=array();
    foreach((array)$tickets as $index=>$ticket){
        $id=sanitize_text_field($ticket['id']??'');$token=sanitize_text_field($messages[$index]['to']??'');
        if(($ticket['status']??'')==='ok'&&$id!==''&&$token!=='')$pending[$id]=array('token'=>$token,'created_at'=>time());
    }
    if(count($pending)>5000)$pending=array_slice($pending,-5000,null,true);
    update_option('surfside_tools_push_pending_receipts',$pending,false);
}
function surfside_tools_push_process_receipts(){
    $pending=get_option('surfside_tools_push_pending_receipts',array());if(!is_array($pending)||!$pending)return 0;
    $now=time();$removed_tokens=array();$changed=false;
    foreach(array_chunk(array_keys($pending),1000) as $ids){
        $response=wp_remote_post('https://exp.host/--/api/v2/push/getReceipts',array('timeout'=>20,'headers'=>array('Accept'=>'application/json','Content-Type'=>'application/json'),'body'=>wp_json_encode(array('ids'=>$ids))));
        if(is_wp_error($response))continue;$code=wp_remote_retrieve_response_code($response);if($code<200||$code>=300)continue;
        $payload=json_decode(wp_remote_retrieve_body($response),true);$receipts=is_array($payload['data']??null)?$payload['data']:array();
        foreach($ids as $id){
            $created=(int)($pending[$id]['created_at']??0);
            if(isset($receipts[$id])){
                $receipt=$receipts[$id];
                if(($receipt['status']??'')==='error'&&($receipt['details']['error']??'')==='DeviceNotRegistered')$removed_tokens[]=$pending[$id]['token']??'';
                unset($pending[$id]);$changed=true;
            }elseif($created&&($now-$created)>82800){
                // Expo clears receipts after 24 hours; stop retaining unresolved IDs shortly before then.
                unset($pending[$id]);$changed=true;
            }
        }
    }
    if($changed)update_option('surfside_tools_push_pending_receipts',$pending,false);
    return $removed_tokens?surfside_tools_push_remove_tokens($removed_tokens):0;
}
function surfside_tools_push_register_token(WP_REST_Request $request){
    $params=(array)$request->get_json_params();
    $token=sanitize_text_field($params['token']??'');
    if(!surfside_tools_push_valid_token($token)) return new WP_Error('surfside_push_invalid_token','A valid Expo push token is required.',array('status'=>400));
    $devices=surfside_tools_push_devices();$key=hash('sha256',$token);$existing=$devices[$key]??array();
    $preferences=array_key_exists('preferences',$params)?surfside_tools_push_sanitize_preferences($params['preferences']):surfside_tools_push_sanitize_preferences($existing['preferences']??array());
    $devices[$key]=array('token'=>$token,'preferences'=>$preferences,'platform'=>sanitize_key($params['platform']??($existing['platform']??'')),'updated_at'=>time());
    if(count($devices)>5000){uasort($devices,function($a,$b){return (int)($a['updated_at']??0)<=>(int)($b['updated_at']??0);});$devices=array_slice($devices,-5000,null,true);}
    update_option('surfside_tools_push_devices',$devices,false);
    return rest_ensure_response(array('success'=>true,'preferences'=>$preferences));
}
add_action('rest_api_init',function(){
    register_rest_route('surfside/v1','/push/register',array('methods'=>WP_REST_Server::CREATABLE,'callback'=>'surfside_tools_push_register_token','permission_callback'=>'__return_true'));
});

function surfside_tools_push_send($title,$body,$destination='',$audiences=array()){
    $devices=surfside_tools_push_devices();$audiences=array_values(array_intersect(array_keys(surfside_tools_push_default_preferences()),(array)$audiences));$tokens=array();
    foreach($devices as $device){$token=sanitize_text_field($device['token']??'');if(!$token)continue;if(!$audiences){$tokens[]=$token;continue;}$prefs=surfside_tools_push_sanitize_preferences($device['preferences']??array());foreach($audiences as $audience){if(!empty($prefs[$audience])){$tokens[]=$token;break;}}}
    $tokens=array_values(array_unique($tokens));
    if(!$tokens) return new WP_Error('surfside_push_no_devices','No registered devices match this notification audience.');
    $messages=array();foreach($tokens as $token){$message=array('to'=>$token,'title'=>$title,'body'=>$body,'sound'=>'default');if($destination!=='')$message['data']=array('destination'=>$destination);$messages[]=$message;}
    $sent=0;$errors=array();$stale_tokens=array();
    foreach(array_chunk($messages,100) as $chunk){
        $response=wp_remote_post('https://exp.host/--/api/v2/push/send',array('timeout'=>20,'headers'=>array('Accept'=>'application/json','Content-Type'=>'application/json'),'body'=>wp_json_encode($chunk)));
        if(is_wp_error($response)){$errors[]=$response->get_error_message();continue;}
        $code=wp_remote_retrieve_response_code($response);if($code<200||$code>=300){$errors[]='Expo Push Service returned HTTP '.$code;continue;}
        $payload=json_decode(wp_remote_retrieve_body($response),true);$tickets=is_array($payload['data']??null)?$payload['data']:array();
        foreach($tickets as $index=>$ticket){
            if(($ticket['status']??'')==='error'&&($ticket['details']['error']??'')==='DeviceNotRegistered'&&!empty($chunk[$index]['to']))$stale_tokens[]=$chunk[$index]['to'];
        }
        surfside_tools_push_store_receipts($tickets,$chunk);
        $sent+=count($chunk);
    }
    if($stale_tokens)surfside_tools_push_remove_tokens($stale_tokens);
    if(!$sent)return new WP_Error('surfside_push_send_failed',implode(' ',$errors)?:'The notification could not be sent.');
    return array('sent'=>$sent,'errors'=>$errors,'removed_stale'=>count(array_unique($stale_tokens)));
}

function surfside_tools_staff_push_notifications_shortcode(){
    surfside_tools_prevent_cache();surfside_tools_staff_enqueue_styles();if(!is_user_logged_in())return surfside_tools_staff_login_box('Please log in to send push notifications.');if(!current_user_can('upload_files'))return '<div class="surfside-staff-shell"><p>You do not have permission to send push notifications.</p></div>';
    $labels=array('church_updates'=>'Church Updates','events_ministries'=>'Events & Ministries','kids_ministry'=>'Kids Ministry','livestream'=>'Livestream Reminders');$notice='';$notice_type='success';
    $receipt_removed=surfside_tools_push_process_receipts();if($receipt_removed)$notice='Removed '.number_format_i18n($receipt_removed).' stale registration'.($receipt_removed===1?'':'s').' reported by Expo.';
    $title='';$body='';$destination='home';$audiences=array();
    if($_SERVER['REQUEST_METHOD']==='POST'&&isset($_POST['surfside_push_nonce'])&&wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['surfside_push_nonce'])),'surfside_push_send')){
        $action=sanitize_key(wp_unslash($_POST['push_action']??'send'));
        if($action==='remove_legacy'){
            $removed=surfside_tools_push_remove_legacy_devices();$notice=$removed?'Removed '.number_format_i18n($removed).' legacy registration'.($removed===1?'.':'s.'):'No legacy registrations were found.';
        }else{
            $title=sanitize_text_field(wp_unslash($_POST['push_title']??''));$body=sanitize_textarea_field(wp_unslash($_POST['push_body']??''));$destination=sanitize_key(wp_unslash($_POST['push_destination']??'home'));$audiences=array_map('sanitize_key',(array)($_POST['push_audience']??array()));$allowed=array('home'=>'home','worship'=>'worship','events'=>'events','give'=>'give','connect'=>'connect');if(!isset($allowed[$destination]))$destination='home';
            if($title===''||$body===''){$notice='Title and message are required.';$notice_type='error';}
            elseif(!$audiences){$notice='Choose at least one audience.';$notice_type='error';}
            else{$result=surfside_tools_push_send($title,$body,$destination,$audiences);if(is_wp_error($result)){$notice=$result->get_error_message();$notice_type='error';}else{$notice='Notification accepted for '.number_format_i18n($result['sent']).' matching device'.($result['sent']===1?'':'s').'.';if(!empty($result['removed_stale']))$notice.=' Removed '.number_format_i18n($result['removed_stale']).' stale registration'.($result['removed_stale']===1?'.':'s.');$title='';$body='';$destination='home';$audiences=array();}}
        }
    }
    $devices=surfside_tools_push_devices();$count=count($devices);$legacy_count=surfside_tools_push_legacy_count($devices);$audience_counts=surfside_tools_push_audience_counts($devices);ob_start();?>
    <div class="surfside-staff-shell surfside-push-manager"><div class="surfside-staff-back"><a href="<?php echo esc_url(surfside_tools_staff_page_url('mobile-app'));?>">← Back to Manage Mobile App</a></div><section class="surfside-staff-hero"><p class="surfside-staff-eyebrow">Mobile App</p><h1>Push Notifications</h1><p class="surfside-staff-muted">Send a timely message to people who have allowed notifications from the Surfside app.</p></section>
    <?php if($notice):?><div class="surfside-push-notice <?php echo $notice_type==='error'?'is-error':'';?>"><?php echo esc_html($notice);?></div><?php endif;?>
    <section class="surfside-staff-panel"><div class="surfside-push-count"><strong><?php echo esc_html(number_format_i18n($count));?></strong><span>registered device<?php echo $count===1?'':'s';?></span></div><?php if($legacy_count):?><div class="surfside-push-legacy"><div><strong><?php echo esc_html(number_format_i18n($legacy_count));?> legacy registration<?php echo $legacy_count===1?'':'s';?></strong><p>These records predate platform-aware app registration and may include old development installs.</p></div><form method="post"><?php wp_nonce_field('surfside_push_send','surfside_push_nonce');?><input type="hidden" name="push_action" value="remove_legacy"><button type="submit" class="surfside-staff-button-secondary">Remove Legacy Registrations</button></form></div><?php endif;?>
    <form method="post" class="surfside-push-form"><?php wp_nonce_field('surfside_push_send','surfside_push_nonce');?><input type="hidden" name="push_action" value="send"><label><strong>Title</strong><input type="text" name="push_title" maxlength="80" required value="<?php echo esc_attr($title);?>" placeholder="Surfside Community Fellowship"></label><label><strong>Message</strong><textarea name="push_body" rows="5" maxlength="240" required placeholder="Type the notification message..."><?php echo esc_textarea($body);?></textarea></label><fieldset><legend><strong>Audience</strong></legend><?php foreach($labels as $key=>$label):?><label class="surfside-push-check"><span><input type="checkbox" name="push_audience[]" value="<?php echo esc_attr($key);?>" <?php checked(in_array($key,$audiences,true));?>> <?php echo esc_html($label);?></span><small><?php echo esc_html(number_format_i18n($audience_counts[$key]??0));?> subscribed</small></label><?php endforeach;?></fieldset><label><strong>Open in app</strong><select name="push_destination"><option value="home" <?php selected($destination,'home');?>>Home</option><option value="worship" <?php selected($destination,'worship');?>>Worship</option><option value="events" <?php selected($destination,'events');?>>Events</option><option value="give" <?php selected($destination,'give');?>>Give</option><option value="connect" <?php selected($destination,'connect');?>>Connect</option></select></label><p class="surfside-staff-muted">Sending is immediate. A device receives the message when it has enabled at least one selected audience. Selecting multiple audiences does not send duplicate notifications to the same device. Expo delivery receipts are checked on later visits and stale tokens are removed automatically when reported.</p><button type="submit" class="surfside-staff-button surfside-push-send" <?php disabled($count===0);?>>Send Notification</button></form></section></div>
    <style>.surfside-push-form{display:grid;gap:20px;max-width:720px}.surfside-push-manager label strong{display:block;margin-bottom:7px}.surfside-push-manager input[type=text],.surfside-push-manager textarea,.surfside-push-manager select{width:100%;padding:12px 14px;box-sizing:border-box}.surfside-push-manager fieldset{border:1px solid #d8dee8;border-radius:10px;padding:14px 16px}.surfside-push-check{display:flex;align-items:center;justify-content:space-between;gap:16px;margin:8px 0}.surfside-push-check small{color:#6b7788;white-space:nowrap}.surfside-push-send{width:auto;min-width:210px;border:0;cursor:pointer}.surfside-push-count{display:flex;align-items:baseline;gap:8px;margin-bottom:18px}.surfside-push-count strong{font-size:32px;color:#0b4f9c}.surfside-push-count span{color:#4b5872}.surfside-push-notice{padding:14px 18px;margin-bottom:20px;border-radius:10px;background:#eaf7ef;color:#126b36;font-weight:700}.surfside-push-notice.is-error{background:#fbe9e7;color:#a33a32}.surfside-push-legacy{display:flex;align-items:center;justify-content:space-between;gap:20px;padding:14px 16px;margin:0 0 22px;border:1px solid #d8dee8;border-radius:10px;background:#f7f9fb}.surfside-push-legacy p{margin:4px 0 0;color:#6b7788;font-size:14px}.surfside-push-legacy form{margin:0;flex:0 0 auto}@media(max-width:620px){.surfside-push-legacy{align-items:flex-start;flex-direction:column}.surfside-push-check{align-items:flex-start;flex-direction:column;gap:2px}.surfside-push-check small{padding-left:24px}}</style><?php return ob_get_clean();
}
add_shortcode('surfside_staff_push_notifications','surfside_tools_staff_push_notifications_shortcode');
function surfside_tools_ensure_push_notifications_page(){if(!function_exists('surfside_tools_ensure_staff_page'))return;$parent=get_page_by_path('dashboard/mobile-app');if(!$parent)return;surfside_tools_ensure_staff_page('Push Notifications','push-notifications','[surfside_staff_push_notifications]',(int)$parent->ID);}
