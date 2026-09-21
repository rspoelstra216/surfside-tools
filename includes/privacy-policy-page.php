<?php
/** Public privacy policy for the Surfside website and mobile app. */
if (!defined('ABSPATH')) { exit; }

const SURFSIDE_TOOLS_PRIVACY_POLICY_SCHEMA_VERSION = 2;
const SURFSIDE_TOOLS_PRIVACY_POLICY_SCHEMA_OPTION = 'surfside_tools_privacy_policy_schema';

function surfside_tools_privacy_policy_shortcode(){
    $support_email='support@surfsidefellowship.org';
    $deletion_url=home_url('/account-deletion/');
    ob_start(); ?>
    <section class="surfside-privacy-policy alignwide" aria-label="Surfside privacy policy">
      <p class="surfside-privacy-policy__eyebrow">Surfside Community Fellowship</p>
      <p class="surfside-privacy-policy__lead">This Privacy Policy explains how Surfside Community Fellowship collects, uses, shares, protects, retains, and deletes information through the Surfside mobile app and surfsidefellowship.org.</p>
      <p><strong>Effective date:</strong> September 21, 2026</p>

      <h2>Information we collect</h2>
      <h3>Account and profile information</h3>
      <p>If you create or use a Surfside account, we collect your name, email address, account identifier, authentication information, account role, and account creation or update timestamps. You may sign in with an email and password or with Google. Surfside does not receive your Google password.</p>

      <h3>Content you choose to save</h3>
      <p>Signed-in users may save Scripture, message notes, annotations, and a private history of prayer requests. This content is associated with the user's Surfside account so it can be synchronized across devices.</p>

      <h3>Contact and prayer submissions</h3>
      <p>When you contact the church through the app or website, we collect the information you provide, which may include your name, email address, phone number, preferred contact method, message, and prayer request. For a Church Prayer List submission, we also collect your selected name display, requested active period, and review or lifecycle status.</p>

      <h3>Notifications and device information</h3>
      <p>If you enable notifications, we collect an Expo push token, device platform, notification preferences, and registration timestamps. The app also stores preferences locally on your device, such as Bible-version and notification choices. Surfside does not collect precise location, contacts, photos, microphone recordings, advertising identifiers, or payment-card information through the app.</p>

      <h3>Website security and technical information</h3>
      <p>Our website and service providers may process limited technical information such as IP address, browser or device information, request timestamps, security events, and essential cookies. The website contact form uses Cloudflare Turnstile to prevent automated abuse.</p>

      <h2>How we use information</h2>
      <ul>
        <li>Provide authentication, account synchronization, saved content, prayer history, and staff-authorized features.</li>
        <li>Deliver church information, events, Bible content, livestream links, and notifications selected by the user.</li>
        <li>Route contact and prayer messages to the appropriate church staff or prayer team.</li>
        <li>Review and publish Church Prayer List submissions according to the submitter's choices.</li>
        <li>Protect the app, website, accounts, and forms from abuse; diagnose failures; and maintain reliable service.</li>
        <li>Respond to support, privacy, and deletion requests and comply with applicable legal obligations.</li>
      </ul>

      <h2>When information is shared</h2>
      <p>Surfside does not sell personal information and does not share it for targeted advertising. We disclose information only as needed for the purposes described in this policy:</p>
      <ul>
        <li><strong>Church recipients:</strong> Contact and prayer messages are shared with the staff, pastoral staff, or prayer team appropriate to the category and audience you select.</li>
        <li><strong>Church Prayer List:</strong> If you request publication, approved prayer text and either your name or an anonymous label may be shown to app users for the period you select.</li>
        <li><strong>Service providers:</strong> Google Firebase provides authentication and cloud data storage; Google supports optional Google Sign-In; Expo supports push-notification delivery; our WordPress, hosting, and email providers operate the website and route messages; and Cloudflare Turnstile protects website forms.</li>
        <li><strong>Legal and safety needs:</strong> We may disclose information when reasonably necessary to comply with law, protect people, investigate abuse, or protect Surfside's rights and services.</li>
      </ul>
      <p>The app and website may link to services such as YouTube, Twitch, Facebook, Google Maps, YouVersion, and third-party giving pages. Information you provide directly to those services is governed by their own privacy policies.</p>

      <h2>Data security</h2>
      <p>We use HTTPS for data in transit, authenticated access and role-based restrictions for protected functions, and access controls provided by our service providers. Access to personal information is limited to people and providers who need it for the purposes described above. No security method is perfect, but we take reasonable steps to protect information against unauthorized access, alteration, disclosure, or destruction.</p>

      <h2>Retention and deletion</h2>
      <ul>
        <li>Account profiles and synchronized account content are retained while the account exists and are deleted when the account is deleted.</li>
        <li>Push-notification registrations are retained while active and are removed when the associated account is deleted, the token is reported invalid, or deletion is otherwise requested.</li>
        <li>Private contact-form and prayer-request emails are retained for up to 12 months, then deleted.</li>
        <li>Church Prayer List submissions remain active for the selected 7-, 14-, or 30-day period. Completed records are deleted after 90 days.</li>
        <li>Verified privacy and deletion requests are fulfilled within 30 days.</li>
        <li>Local app preferences remain on the device until changed, cleared, or the app is uninstalled.</li>
      </ul>
      <p>Service providers may retain limited security, backup, and transaction logs according to their own retention schedules or legal obligations.</p>

      <h2>Your choices and requests</h2>
      <p>You may change notification categories in the app, choose whether a Church Prayer List submission displays your name, and choose the requested active period for that submission. You may delete your Surfside account from the app or request help without the app.</p>
      <p><a class="surfside-privacy-policy__button" href="<?php echo esc_url($deletion_url); ?>">View account and data deletion instructions</a></p>
      <p>For access, correction, deletion, or other privacy questions, email <a href="mailto:<?php echo esc_attr($support_email); ?>"><?php echo esc_html($support_email); ?></a>. We may need to verify your identity before acting on a request.</p>

      <h2>Children's privacy</h2>
      <p>The Surfside app and website are intended for a general church audience and are not designed to collect personal information directly from children. A parent or guardian should contact us if they believe a child submitted personal information so we can review and, where appropriate, delete it.</p>

      <h2>Changes to this policy</h2>
      <p>We may update this policy as the app, website, or legal requirements change. We will post the revised policy on this page and update the effective date.</p>

      <h2>Contact us</h2>
      <p>Surfside Community Fellowship<br>Email: <a href="mailto:<?php echo esc_attr($support_email); ?>"><?php echo esc_html($support_email); ?></a><br>Phone: <a href="tel:+13216072111">(321) 607-2111</a></p>
    </section>
    <style>
      .surfside-privacy-policy{width:100%;max-width:var(--wp--style--global--wide-size,80rem);margin-left:auto!important;margin-right:auto!important;box-sizing:border-box;color:#18212b}.surfside-privacy-policy__eyebrow{margin:0 0 8px;color:#176a9a;font-weight:800;letter-spacing:.08em;text-transform:uppercase}.surfside-privacy-policy__lead{font-size:1.12em;line-height:1.65}.surfside-privacy-policy h2{margin-top:38px}.surfside-privacy-policy h3{margin-top:24px}.surfside-privacy-policy li+li{margin-top:9px}.surfside-privacy-policy__button{display:inline-block;padding:12px 18px;border-radius:9px;background:#176a9a;color:#fff!important;font-weight:700;text-decoration:none}.surfside-privacy-policy__button:hover,.surfside-privacy-policy__button:focus{background:#0f577f;color:#fff!important}
    </style>
    <?php return ob_get_clean();
}
add_shortcode('surfside_privacy_policy','surfside_tools_privacy_policy_shortcode');

/** Create and register the public Privacy Policy once after this schema version is deployed. */
function surfside_tools_provision_privacy_policy_page(){
    if(!is_admin())return;
    $installed=(int)get_option(SURFSIDE_TOOLS_PRIVACY_POLICY_SCHEMA_OPTION,0);
    if($installed>=SURFSIDE_TOOLS_PRIVACY_POLICY_SCHEMA_VERSION)return;

    $page=get_page_by_path('privacy-policy',OBJECT,'page');
    $changed=false;
    $page_id=0;
    if($page instanceof WP_Post){
        $page_id=(int)$page->ID;
        $content=(string)$page->post_content;
        $wordpress_placeholder=strpos($content,'Suggested text:')!==false;
        $managed=trim($content)===''||has_shortcode($content,'surfside_privacy_policy')||$wordpress_placeholder;
        if($managed&&((string)$page->post_title!=='Privacy Policy'||trim((string)$page->post_content)!=='[surfside_privacy_policy]'||(string)$page->post_status!=='publish')){
            $result=wp_update_post(array('ID'=>$page_id,'post_title'=>'Privacy Policy','post_content'=>'[surfside_privacy_policy]','post_status'=>'publish'),true);
            if(is_wp_error($result))return;
            $changed=true;
        }
    }else{
        $result=wp_insert_post(array('post_title'=>'Privacy Policy','post_name'=>'privacy-policy','post_content'=>'[surfside_privacy_policy]','post_status'=>'publish','post_type'=>'page'),true);
        if(is_wp_error($result)||!$result)return;
        $page_id=(int)$result;
        $changed=true;
    }

    if($page_id)update_option('wp_page_for_privacy_policy',$page_id);
    update_option(SURFSIDE_TOOLS_PRIVACY_POLICY_SCHEMA_OPTION,SURFSIDE_TOOLS_PRIVACY_POLICY_SCHEMA_VERSION,false);
    if($changed)flush_rewrite_rules(false);
}
add_action('admin_init','surfside_tools_provision_privacy_policy_page',7);
