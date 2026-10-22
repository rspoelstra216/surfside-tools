<?php
/** Shared contact routing and staff management for website and mobile app submissions. */
if (!defined('ABSPATH')) { exit; }

function surfside_tools_contact_categories(){return array('general'=>'General Questions','prayer'=>'Prayers and Praises','ministry'=>'Ministry Information','small-group'=>'Small Group Information','pastor'=>'Speak to a Pastor');}
function surfside_tools_contact_settings(){
    $saved=get_option('surfside_tools_contact_settings',array()); $saved=is_array($saved)?$saved:array();
    $information=function_exists('surfside_tools_get_site_information')?surfside_tools_get_site_information():array(); $identity=(array)($information['identity']??array());
    $fallback=sanitize_email($identity['email']??''); if(!$fallback)$fallback=sanitize_email(get_option('admin_email'));
    $recipients=array(); foreach(surfside_tools_contact_categories() as $key=>$label){$recipients[$key]=sanitize_email($saved['recipients'][$key]??'')?:$fallback;}
    return array('recipients'=>$recipients,'turnstile_site_key'=>sanitize_text_field($saved['turnstile_site_key']??''),'turnstile_secret_key'=>sanitize_text_field($saved['turnstile_secret_key']??''));
}
/** Resolve prayer privacy before applying ordinary category fallbacks. */
function surfside_tools_contact_submission_recipient($category, $privacy = '') {
    if ($category === 'prayer' && $privacy === 'pastoral') {
        $saved = get_option('surfside_tools_contact_settings', array());
        $saved = is_array($saved) ? $saved : array();
        $to = sanitize_email($saved['recipients']['pastor'] ?? '');
        if (!$to || !is_email($to)) {
            return new WP_Error('surfside_contact_pastoral_unavailable', 'Pastoral-only submissions are temporarily unavailable. Please contact the pastor directly.', array('status' => 503));
        }
        return $to;
    }
    return surfside_tools_contact_recipient($category);
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
        $existing['recipients']=$recipients;
        update_option('surfside_tools_contact_settings',$existing); $saved=true;
    }
    $settings=surfside_tools_contact_settings();
    $stored=get_option('surfside_tools_contact_settings',array());
    $stored=is_array($stored)?$stored:array();
    $explicit_recipients=isset($stored['recipients'])&&is_array($stored['recipients'])?$stored['recipients']:array();
    $pastoral_address=sanitize_email($explicit_recipients['pastor']??'');
    $pastoral_configured=$pastoral_address&&is_email($pastoral_address);
    ob_start();
    ?>
    <div class="surfside-staff-shell surfside-contact-routing-workspace">
      <div class="surfside-staff-back"><a href="<?php echo esc_url(surfside_tools_staff_page_url('site-settings')); ?>">← Back to Church Settings</a></div>
      <section class="surfside-staff-hero">
        <p class="surfside-staff-eyebrow">Community &amp; Communications</p>
        <h1>Contact Routing</h1>
        <p class="surfside-staff-muted">Control which church mailbox receives Connect messages submitted through the website and mobile app. Recipient addresses are kept server-side.</p>
      </section>
      <?php if($saved):?><div class="surfside-care-save-notice" role="status">Contact settings saved.</div><?php endif; ?>
      <div class="surfside-contact-routing-summary" aria-label="Contact routing overview">
        <div>
          <strong>Message categories</strong>
          <span><?php echo count(surfside_tools_contact_categories()); ?> routing destinations</span>
        </div>
        <div>
          <strong>Pastoral privacy</strong>
          <span class="<?php echo $pastoral_configured ? 'is-configured' : 'needs-attention'; ?>">
            <?php echo $pastoral_configured ? 'Dedicated address configured' : 'Dedicated address required'; ?>
          </span>
        </div>
      </div>
      <form method="post" class="surfside-contact-routing-form">
        <?php wp_nonce_field('surfside_contact_save','surfside_contact_nonce'); ?>
        <section class="surfside-staff-panel">
          <div class="surfside-care-section-heading">
            <div>
              <h2>Message Routing</h2>
              <p>Assign a recipient for each category. Ordinary categories use Surfside's church email when their saved address is blank.</p>
            </div>
          </div>
          <div class="surfside-contact-routing-grid">
            <?php foreach(surfside_tools_contact_categories() as $key=>$label):
                $is_pastor=$key==='pastor';
                $field_value=$is_pastor?$pastoral_address:($settings['recipients'][$key]??'');
                $help=$is_pastor
                    ? 'Pastoral Staff Only prayer requests require a specifically saved recipient. They never fall back to the general church email.'
                    : ($key==='prayer'
                        ? 'Prayer Team and Church Prayer List messages are routed here.'
                        : 'This category falls back to the church email when no recipient is saved.');
            ?>
              <div class="surfside-contact-routing-field<?php echo $is_pastor?' is-pastoral':''; ?>">
                <label for="surfside-recipient-<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></label>
                <?php if($is_pastor):?><span class="surfside-contact-routing-privacy">Privacy-sensitive</span><?php endif; ?>
                <input type="email" id="surfside-recipient-<?php echo esc_attr($key); ?>" name="recipient_<?php echo esc_attr($key); ?>" value="<?php echo esc_attr($field_value); ?>" class="regular-text" placeholder="church@example.com" autocomplete="off" aria-describedby="surfside-recipient-help-<?php echo esc_attr($key); ?>">
                <p id="surfside-recipient-help-<?php echo esc_attr($key); ?>"><?php echo esc_html($help); ?></p>
              </div>
            <?php endforeach; ?>
          </div>
        </section>
        <div class="surfside-contact-routing-save">
          <p>Changes apply to future Connect submissions after you save. This does not change existing messages.</p>
          <button type="submit" class="surfside-staff-button">Save Contact Settings</button>
        </div>
      </form>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('surfside_staff_contact_management','surfside_tools_staff_contact_management_shortcode');
function surfside_tools_ensure_contact_management_page(){if(!function_exists('surfside_tools_ensure_staff_page'))return;$dashboard=get_page_by_path('dashboard');if(!$dashboard)return;surfside_tools_ensure_staff_page('Manage Contact Routing','contact-routing','[surfside_staff_contact_management]',(int)$dashboard->ID);} add_action('init','surfside_tools_ensure_contact_management_page',81);
