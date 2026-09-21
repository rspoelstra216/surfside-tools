<?php
/** Public account and data deletion instructions for the Surfside mobile app. */
if (!defined('ABSPATH')) { exit; }

const SURFSIDE_TOOLS_ACCOUNT_DELETION_PAGE_SCHEMA_VERSION = 1;
const SURFSIDE_TOOLS_ACCOUNT_DELETION_PAGE_SCHEMA_OPTION = 'surfside_tools_account_deletion_page_schema';

function surfside_tools_account_deletion_shortcode(){
    $support_email='support@surfsidefellowship.org';
    $support_link='mailto:'.$support_email.'?subject='.rawurlencode('Surfside account deletion request');
    ob_start(); ?>
    <section class="surfside-account-deletion" aria-labelledby="surfside-account-deletion-title">
      <p class="surfside-account-deletion__eyebrow">Surfside Mobile App</p>
      <h1 id="surfside-account-deletion-title">Delete your Surfside account and data</h1>
      <p>This page applies to the Surfside mobile app provided by Surfside Community Fellowship. You can permanently delete your account directly in the app or ask us for help.</p>

      <h2>Delete your account in the app</h2>
      <ol>
        <li>Sign in to the Surfside app.</li>
        <li>Open <strong>More</strong>, select <strong>My Surfside</strong>, and open the account settings.</li>
        <li>Select <strong>Delete Account</strong>, then <strong>Continue to Delete Account</strong>.</li>
        <li>Verify your identity and confirm the final deletion prompt.</li>
      </ol>
      <p>Deletion is permanent and cannot be undone.</p>

      <div class="surfside-account-deletion__request">
        <h2>Request deletion without the app</h2>
        <p>Email us from the address associated with your Surfside account. Include the account email address and state that you are requesting account deletion. We will verify your identity and fulfill a verified request within 30 days.</p>
        <p><a class="surfside-account-deletion__button" href="<?php echo esc_url($support_link); ?>">Email <?php echo esc_html($support_email); ?></a></p>
      </div>

      <h2>Data deleted with your account</h2>
      <ul>
        <li>Your Surfside sign-in account and account profile.</li>
        <li>Saved Scripture, message notes, and annotations.</li>
        <li>Private prayer history stored with your account.</li>
        <li>The app installation's registered push-notification token.</li>
      </ul>

      <h2>Contact and prayer messages</h2>
      <p>Contact-form and prayer-request messages emailed to the church are stored separately from your app account and are not automatically removed by the in-app deletion process. You may request their deletion by emailing <a href="mailto:<?php echo esc_attr($support_email); ?>"><?php echo esc_html($support_email); ?></a>.</p>
      <ul>
        <li>Private contact-form and prayer-request emails are retained for up to 12 months, then deleted.</li>
        <li>Completed Church Prayer List records are deleted after 90 days.</li>
        <li>Verified deletion requests are fulfilled within 30 days.</li>
      </ul>
    </section>
    <style>
      .surfside-account-deletion{max-width:820px;margin:0 auto;color:#18212b}.surfside-account-deletion__eyebrow{margin:0 0 8px;color:#176a9a;font-weight:800;letter-spacing:.08em;text-transform:uppercase}.surfside-account-deletion h1{margin-top:0}.surfside-account-deletion h2{margin-top:34px}.surfside-account-deletion li+li{margin-top:8px}.surfside-account-deletion__request{margin:32px 0;padding:24px;border:1px solid #d7e3ea;border-radius:14px;background:#f7fbfd}.surfside-account-deletion__request h2{margin-top:0}.surfside-account-deletion__button{display:inline-block;padding:12px 18px;border-radius:9px;background:#176a9a;color:#fff!important;font-weight:700;text-decoration:none}.surfside-account-deletion__button:hover,.surfside-account-deletion__button:focus{background:#0f577f;color:#fff!important}
    </style>
    <?php return ob_get_clean();
}
add_shortcode('surfside_account_deletion','surfside_tools_account_deletion_shortcode');

/** Create the public page once after this schema version is deployed. */
function surfside_tools_provision_account_deletion_page(){
    if(!is_admin())return;
    $installed=(int)get_option(SURFSIDE_TOOLS_ACCOUNT_DELETION_PAGE_SCHEMA_OPTION,0);
    if($installed>=SURFSIDE_TOOLS_ACCOUNT_DELETION_PAGE_SCHEMA_VERSION)return;

    $page=get_page_by_path('account-deletion',OBJECT,'page');
    $changed=false;
    if($page instanceof WP_Post){
        $managed=trim((string)$page->post_content)===''||has_shortcode($page->post_content,'surfside_account_deletion');
        if($managed&&((string)$page->post_title!=='Account and Data Deletion'||trim((string)$page->post_content)!=='[surfside_account_deletion]'||(string)$page->post_status!=='publish')){
            $result=wp_update_post(array('ID'=>$page->ID,'post_title'=>'Account and Data Deletion','post_content'=>'[surfside_account_deletion]','post_status'=>'publish'),true);
            if(is_wp_error($result))return;
            $changed=true;
        }
    }else{
        $result=wp_insert_post(array('post_title'=>'Account and Data Deletion','post_name'=>'account-deletion','post_content'=>'[surfside_account_deletion]','post_status'=>'publish','post_type'=>'page'),true);
        if(is_wp_error($result)||!$result)return;
        $changed=true;
    }

    update_option(SURFSIDE_TOOLS_ACCOUNT_DELETION_PAGE_SCHEMA_OPTION,SURFSIDE_TOOLS_ACCOUNT_DELETION_PAGE_SCHEMA_VERSION,false);
    if($changed)flush_rewrite_rules(false);
}
add_action('admin_init','surfside_tools_provision_account_deletion_page',6);
