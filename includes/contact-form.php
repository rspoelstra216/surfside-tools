<?php
/** Native website contact form using shared Surfside contact routing. */
if (!defined('ABSPATH')) { exit; }

function surfside_tools_contact_send($data){
    $categories=surfside_tools_contact_categories();
    $name=sanitize_text_field($data['name']??''); $email=sanitize_email($data['email']??''); $phone=sanitize_text_field($data['phone']??'');
    $category=sanitize_key($data['category']??'general'); $message=sanitize_textarea_field($data['message']??''); $preferred=sanitize_key($data['preferred_contact']??''); $privacy=sanitize_key($data['prayer_privacy']??'');
    if($name===''||$message===''||!isset($categories[$category]))return new WP_Error('invalid','Please provide your name and message.');
    if(strlen($message)>2000)return new WP_Error('long','Message cannot exceed 2000 characters.');
    if($email===''&&$phone==='')return new WP_Error('contact','Please provide an email address or phone number.');
    if(!empty($data['email'])&&!is_email($data['email']))return new WP_Error('email','Please provide a valid email address.');
    if($category==='pastor'&&$preferred==='email'&&$email==='')return new WP_Error('email','Please provide an email address for email contact.');
    if($category==='pastor'&&$preferred==='phone'&&$phone==='')return new WP_Error('phone','Please provide a phone number for phone contact.');
    $to=surfside_tools_contact_recipient($category); if(!$to)return new WP_Error('unavailable','Contact submissions are temporarily unavailable.');
    $subject='Surfside Website: '.$categories[$category];
    $lines=array('Name: '.$name,'Email: '.($email?:'Not provided'),'Phone: '.($phone?:'Not provided'),'Category: '.$categories[$category]);
    if($category==='pastor'&&in_array($preferred,array('email','phone'),true))$lines[]='Preferred Contact: '.ucfirst($preferred);
    if($category==='prayer')$lines[]='Prayer Privacy: '.($privacy==='pastoral'?'Pastoral Staff Only':'Share with Prayer Team');
    $lines[]= ''; $lines[]='Message:'; $lines[]=$message; $headers=$email?array('Reply-To: '.$name.' <'.$email.'>'):array();
    return wp_mail($to,$subject,implode("\n",$lines),$headers)?true:new WP_Error('send','We could not send your message right now. Please try again later.');
}

function surfside_tools_verify_turnstile($token){
    $settings=surfside_tools_contact_settings(); $secret=$settings['turnstile_secret_key']??'';
    if(empty($settings['turnstile_site_key'])||empty($secret))return new WP_Error('turnstile_config','Contact form protection is not configured yet.');
    if(!$token)return new WP_Error('turnstile_required','Please complete the human verification.');
    $body=array('secret'=>$secret,'response'=>$token); if(!empty($_SERVER['REMOTE_ADDR']))$body['remoteip']=sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']));
    $response=wp_remote_post('https://challenges.cloudflare.com/turnstile/v0/siteverify',array('timeout'=>10,'body'=>$body));
    if(is_wp_error($response))return new WP_Error('turnstile_unavailable','Human verification is temporarily unavailable. Please try again.');
    $result=json_decode(wp_remote_retrieve_body($response),true);
    return !empty($result['success'])?true:new WP_Error('turnstile_failed','Human verification failed. Please try again.');
}

function surfside_tools_contact_form_handler(){
    if($_SERVER['REQUEST_METHOD']!=='POST'||empty($_POST['surfside_contact_form_nonce']))return;
    $return_url=wp_get_referer(); if(!$return_url)$return_url=home_url('/contact/'); $return_url=remove_query_arg(array('surfside_contact','surfside_error'),$return_url);
    if(!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['surfside_contact_form_nonce'])),'surfside_contact_form')){wp_safe_redirect(add_query_arg('surfside_error','nonce',$return_url).'#Contact');exit;}
    if(!empty($_POST['website'])){wp_safe_redirect(add_query_arg('surfside_contact','sent',$return_url).'#Contact');exit;}
    $ip=$_SERVER['REMOTE_ADDR']??''; $rate_key='surfside_web_contact_'.md5($ip?:'unknown'); $count=(int)get_transient($rate_key);
    if($count>=5){wp_safe_redirect(add_query_arg('surfside_error','rate',$return_url).'#Contact');exit;}
    $turnstile=surfside_tools_verify_turnstile(sanitize_text_field(wp_unslash($_POST['cf-turnstile-response']??'')));
    if(is_wp_error($turnstile)){wp_safe_redirect(add_query_arg('surfside_error','turnstile',$return_url).'#Contact');exit;}
    $data=array(); foreach(array('category','name','email','phone','message','preferred_contact','prayer_privacy') as $field)$data[$field]=sanitize_textarea_field(wp_unslash($_POST[$field]??''));
    $sent=surfside_tools_contact_send($data);
    if(is_wp_error($sent)){set_transient('surfside_contact_error_'.wp_get_session_token(),$sent->get_error_message(),MINUTE_IN_SECONDS);wp_safe_redirect(add_query_arg('surfside_error','send',$return_url).'#Contact');exit;}
    set_transient($rate_key,$count+1,HOUR_IN_SECONDS); wp_safe_redirect(add_query_arg('surfside_contact','sent',$return_url).'#Contact');exit;
}
add_action('template_redirect','surfside_tools_contact_form_handler');

function surfside_tools_contact_form_shortcode(){
    $status=isset($_GET['surfside_contact'])&&sanitize_key(wp_unslash($_GET['surfside_contact']))==='sent'?'Thank you. Your message has been sent.':''; $error='';
    if(isset($_GET['surfside_error'])){$code=sanitize_key(wp_unslash($_GET['surfside_error']));$errors=array('nonce'=>'Please refresh the page and try again.','rate'=>'Too many messages have been submitted. Please try again later.','turnstile'=>'Please complete the human verification and try again.','send'=>'We could not send your message right now. Please try again later.');$error=$errors[$code]??$errors['send'];if($code==='send'){$stored=get_transient('surfside_contact_error_'.wp_get_session_token());if($stored){$error=$stored;delete_transient('surfside_contact_error_'.wp_get_session_token());}}}
    $values=array('category'=>'general','name'=>'','email'=>'','phone'=>'','message'=>'','preferred_contact'=>'email','prayer_privacy'=>'prayer-team'); $categories=surfside_tools_contact_categories(); $settings=surfside_tools_contact_settings(); $site_key=$settings['turnstile_site_key']??''; ob_start(); ?>
    <section class="surfside-contact-form" aria-label="Contact Surfside" id="Contact">
      <?php if($status):?><div class="surfside-contact-form__success"><?php echo esc_html($status);?></div><?php endif;?>
      <?php if($error):?><div class="surfside-contact-form__error"><?php echo esc_html($error);?></div><?php endif;?>
      <form method="post" action="<?php echo esc_url(get_permalink()); ?>" class="surfside-contact-form__form"><?php wp_nonce_field('surfside_contact_form','surfside_contact_form_nonce');?><input type="text" name="website" tabindex="-1" autocomplete="off" class="surfside-contact-form__trap" aria-hidden="true">
        <label>What can we help with?<select name="category" id="surfside-contact-category"><?php foreach($categories as $key=>$label):?><option value="<?php echo esc_attr($key);?>"><?php echo esc_html($label);?></option><?php endforeach;?></select></label>
        <label>Name *<input required name="name" autocomplete="name"></label>
        <div class="surfside-contact-form__row"><label>Email<input type="email" name="email" autocomplete="email"></label><label>Phone<input type="tel" name="phone" autocomplete="tel"></label></div>
        <fieldset id="surfside-contact-preferred"><legend>Preferred contact</legend><label><input type="radio" name="preferred_contact" value="email" checked> Email</label><label><input type="radio" name="preferred_contact" value="phone"> Phone</label></fieldset>
        <fieldset id="surfside-contact-prayer"><legend>May we share this request with the prayer team?</legend><label><input type="radio" name="prayer_privacy" value="prayer-team" checked> Yes, share with the prayer team</label><label><input type="radio" name="prayer_privacy" value="pastoral"> No, pastoral staff only</label></fieldset>
        <label>Message * <span class="surfside-contact-form__count"><span id="surfside-contact-count">0</span> / 2000</span><textarea required maxlength="2000" name="message" id="surfside-contact-message" rows="7"></textarea></label>
        <?php if($site_key):?><div class="cf-turnstile" data-sitekey="<?php echo esc_attr($site_key); ?>"></div><script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script><?php else:?><div class="surfside-contact-form__error">Human verification has not been configured yet.</div><?php endif;?>
        <button type="submit" class="surfside-button" <?php disabled(!$site_key); ?>>Send Message</button>
      </form>
    </section>
    <style>.surfside-contact-form{max-width:760px;margin:0 auto}.surfside-contact-form__form{display:grid;gap:20px}.surfside-contact-form label,.surfside-contact-form legend{font-weight:700;color:#10233e}.surfside-contact-form input,.surfside-contact-form select,.surfside-contact-form textarea{display:block;width:100%;box-sizing:border-box;margin-top:8px;padding:14px 16px;border:1px solid #cbd2d8;border-radius:12px;background:#fff;font:inherit;color:#18212b}.surfside-contact-form textarea{resize:vertical}.surfside-contact-form__row{display:grid;grid-template-columns:1fr 1fr;gap:18px}.surfside-contact-form fieldset{border:0;padding:0;margin:0;display:flex;gap:18px;flex-wrap:wrap}.surfside-contact-form fieldset label{font-weight:500}.surfside-contact-form fieldset input{display:inline;width:auto;margin:0 6px 0 0}.surfside-contact-form__count{float:right;font-weight:400;color:#6f7880}.surfside-contact-form__success,.surfside-contact-form__error{padding:14px 18px;border-radius:10px;margin-bottom:20px;font-weight:700}.surfside-contact-form__success{background:#eaf7ef;color:#126b36}.surfside-contact-form__error{background:#fff0f0;color:#9b1c1c}.surfside-contact-form__trap{position:absolute!important;left:-9999px!important}.surfside-contact-form .surfside-button{border:0;cursor:pointer;justify-self:start}.surfside-contact-form .surfside-button:disabled{opacity:.55;cursor:not-allowed}@media(max-width:600px){.surfside-contact-form__row{grid-template-columns:1fr}.surfside-contact-form .surfside-button{width:100%}}</style>
    <script>(function(){var c=document.getElementById('surfside-contact-category'),p=document.getElementById('surfside-contact-preferred'),r=document.getElementById('surfside-contact-prayer'),m=document.getElementById('surfside-contact-message'),n=document.getElementById('surfside-contact-count');function sync(){if(!c)return;p.style.display=c.value==='pastor'?'flex':'none';r.style.display=c.value==='prayer'?'flex':'none'}function count(){if(n&&m)n.textContent=m.value.length}if(c){c.addEventListener('change',sync);sync()}if(m){m.addEventListener('input',count);count()}})();</script>
    <?php return ob_get_clean();
}
add_shortcode('surfside_contact_form','surfside_tools_contact_form_shortcode');
