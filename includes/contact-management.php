<?php
/** Shared contact routing and staff management for website and mobile app submissions. */
if (!defined('ABSPATH')) { exit; }

function surfside_tools_contact_categories(){return array('general'=>'General Questions','prayer'=>'Prayer Request','ministry'=>'Ministry Information','small-group'=>'Small Group Information','pastor'=>'Speak to a Pastor');}
function surfside_tools_contact_settings(){
    $saved=get_option('surfside_tools_contact_settings',array()); $saved=is_array($saved)?$saved:array();
    $information=function_exists('surfside_tools_get_site_information')?surfside_tools_get_site_information():array(); $identity=(array)($information['identity']??array());
    $fallback=sanitize_email($identity['email']??''); if(!$fallback)$fallback=sanitize_email(get_option('admin_email'));
    $recipients=array(); foreach(surfside_tools_contact_categories() as $key=>$label){$recipients[$key]=sanitize_email($saved['recipients'][$key]??'')?:$fallback;}
    return array('recipients'=>$recipients,'turnstile_site_key'=>sanitize_text_field($saved['turnstile_site_key']??''),'turnstile_secret_key'=>sanitize_text_field($saved['turnstile_secret_key']??''));
}
function surfside_tools_contact_recipient($category){$settings=surfside_tools_contact_settings();return sanitize_email($settings['recipients'][$category]??'');}

function surfside_tools_staff_contact_management_shortcode(){
    surfside_tools_prevent_cache(); surfside_tools_staff_enqueue_styles();
    if(!is_user_logged_in())return surfside_tools_staff_login_box('Please log in to manage contact routing.');
    if(!current_user_can('manage_options'))return '<div class="surfside-staff-shell"><p>You do not have permission to manage contact routing.</p></div>';
    $saved=false;
    if($_SERVER['REQUEST_METHOD']==='POST'&&isset($_POST['surfside_contact_nonce'])&&wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['surfside_contact_nonce'])),'surfside_contact_save')){
        $existing=get_option('surfside_tools_contact_settings',array()); $existing=is_array($existing)?$existing:array();
        $recipients=array(); foreach(surfside_tools_contact_categories() as $key=>$label){$recipients[$key]=sanitize_email(wp_unslash($_POST['recipient_'.$key]??''));}
        $site_key=sanitize_text_field(wp_unslash($_POST['turnstile_site_key']??''));
        $secret_input=sanitize_text_field(wp_unslash($_POST['turnstile_secret_key']??''));
        $secret_key=$secret_input!==''?$secret_input:sanitize_text_field($existing['turnstile_secret_key']??'');
        update_option('surfside_tools_contact_settings',array('recipients'=>$recipients,'turnstile_site_key'=>$site_key,'turnstile_secret_key'=>$secret_key)); $saved=true;
    }
    $settings=surfside_tools_contact_settings(); ob_start(); ?>
    <div class="surfside-staff-shell">
      <div class="surfside-staff-back"><a href="<?php echo esc_url(surfside_tools_staff_page_url('site-management')); ?>">← Back to Site Management</a></div>
      <section class="surfside-staff-hero"><p class="surfside-staff-eyebrow">Connect</p><h1>Manage Contact Routing</h1><p class="surfside-staff-muted">Choose where each website and mobile-app Connect message should be delivered. These addresses stay on the server and are never exposed in the app.</p></section>
      <?php if($saved):?><div style="padding:14px 18px;margin-bottom:20px;border-radius:10px;background:#eaf7ef;color:#126b36;font-weight:700">Contact settings saved.</div><?php endif; ?>
      <form method="post"><?php wp_nonce_field('surfside_contact_save','surfside_contact_nonce'); ?>
        <section class="surfside-staff-panel"><h2>Message Routing</h2><p class="surfside-staff-muted">Each category can go to a different person or mailbox. Leaving an address blank falls back to the church email configured in Surfside Information.</p>
        <div style="display:grid;gap:18px;max-width:720px;margin-top:20px">
        <?php foreach(surfside_tools_contact_categories() as $key=>$label):?><label><strong style="display:block;margin-bottom:6px"><?php echo esc_html($label); ?></strong><input type="email" name="recipient_<?php echo esc_attr($key); ?>" value="<?php echo esc_attr($settings['recipients'][$key]??''); ?>" class="regular-text" style="width:100%;max-width:560px" placeholder="church@example.com"></label><?php endforeach; ?>
        </div></section>
        <section class="surfside-staff-panel"><h2>Cloudflare Turnstile</h2><p class="surfside-staff-muted">Protect the public website contact form from automated submissions. Enter the site key and secret from your Cloudflare Turnstile widget. The secret is stored on the server and is never rendered on the public page.</p><div style="display:grid;gap:18px;max-width:720px;margin-top:20px"><label><strong style="display:block;margin-bottom:6px">Site key</strong><input type="text" name="turnstile_site_key" value="<?php echo esc_attr($settings['turnstile_site_key']??''); ?>" class="regular-text" style="width:100%;max-width:560px" autocomplete="off"></label><label><strong style="display:block;margin-bottom:6px">Secret key</strong><input type="password" name="turnstile_secret_key" value="" class="regular-text" style="width:100%;max-width:560px" autocomplete="new-password" placeholder="<?php echo !empty($settings['turnstile_secret_key'])?'Saved — leave blank to keep current secret':'Enter Turnstile secret key'; ?>"></label></div></section>
        <button type="submit" class="surfside-staff-button" style="width:auto;min-width:190px;border:0;cursor:pointer">Save Contact Settings</button>
      </form>
    </div><?php return ob_get_clean();
}
add_shortcode('surfside_staff_contact_management','surfside_tools_staff_contact_management_shortcode');
function surfside_tools_ensure_contact_management_page(){if(!function_exists('surfside_tools_ensure_staff_page'))return;$dashboard=get_page_by_path('dashboard');if(!$dashboard)return;surfside_tools_ensure_staff_page('Manage Contact Routing','contact-routing','[surfside_staff_contact_management]',(int)$dashboard->ID);} add_action('init','surfside_tools_ensure_contact_management_page',81);
