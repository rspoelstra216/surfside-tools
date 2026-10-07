<?php
/** Exercise actual website/app callbacks without sending email. */
define('ABSPATH', __DIR__);
define('HOUR_IN_SECONDS', 3600);
class WP_Error { public $code; function __construct($code, $message='', $data=array()) { $this->code=$code; } }
class WP_REST_Request { private $data; function __construct($data){$this->data=$data;} function get_json_params(){return $this->data;} }
function add_action(...$args){}
function add_shortcode(...$args){}
function sanitize_text_field($v){return trim((string)$v);}
function sanitize_textarea_field($v){return trim((string)$v);}
function sanitize_key($v){return (string)$v;}
function sanitize_email($v){return is_email($v)?$v:'';}
function is_email($v){return filter_var($v,FILTER_VALIDATE_EMAIL)!==false;}
function absint($v){return abs((int)$v);}
function get_option($key,$default=false){return $key==='surfside_tools_contact_settings'?$GLOBALS['settings']:($key==='admin_email'?'admin@example.org':$default);}
function surfside_tools_get_site_information(){return array('identity'=>array('email'=>'church@example.org'));}
function get_transient($key){return 0;}
function set_transient(...$args){}
function is_wp_error($v){return $v instanceof WP_Error;}
function rest_ensure_response($v){return $v;}
function wp_mail($to,...$args){$GLOBALS['mail'][]=$to;return true;}
function surfside_tools_prayer_list_add_pending($data){$GLOBALS['pending']++;}
require __DIR__.'/../includes/contact-management.php';
require __DIR__.'/../includes/contact-form.php';
require __DIR__.'/../includes/mobile-api.php';
function check($condition,$label){if(!$condition)throw new RuntimeException($label);}
foreach(array('website','app') as $source){
 foreach(array('pastoral','prayer-team','church-list','pastor','general') as $kind){
  $GLOBALS['settings']=array('recipients'=>array('pastor'=>'pastor@example.org','prayer'=>'secretary@example.org','general'=>'general@example.org'));
  $GLOBALS['mail']=array();$GLOBALS['pending']=0;
  $category=in_array($kind,array('pastor','general'),true)?$kind:'prayer';
  $data=array('name'=>'Test','email'=>'test@example.org','category'=>$category,'message'=>'Test only','prayer_privacy'=>$kind,'prayer_name_display'=>'anonymous','prayer_duration'=>14);
  $result=$source==='app'?surfside_tools_mobile_api_contact(new WP_REST_Request($data)):surfside_tools_contact_send($data);
  $expected=in_array($kind,array('pastoral','pastor'),true)?'pastor@example.org':($kind==='general'?'general@example.org':'secretary@example.org');
  check(!is_wp_error($result)&&$GLOBALS['mail']===array($expected),"$source $kind recipient");
  check($GLOBALS['pending']===($kind==='church-list'?1:0),"$source $kind public-list privacy");
 }
 foreach(array('', 'invalid-address') as $address){
  $GLOBALS['settings']['recipients']['pastor']=$address;$GLOBALS['mail']=array();$GLOBALS['pending']=0;
  $data['category']='prayer';$data['prayer_privacy']='pastoral';
  $result=$source==='app'?surfside_tools_mobile_api_contact(new WP_REST_Request($data)):surfside_tools_contact_send($data);
  check(is_wp_error($result)&&$GLOBALS['mail']===array()&&$GLOBALS['pending']===0,"$source refuses pastoral fallback");
 }
}
echo "Contact privacy routing fixtures passed.\n";
