<?php
/** Featured Home announcement scheduling for the Surfside mobile app. */
if (!defined('ABSPATH')) { exit; }

function surfside_tools_app_featured_announcement() {
    $settings = function_exists('surfside_tools_app_settings') ? surfside_tools_app_settings() : array();
    $enabled = !empty($settings['featured_enabled']);
    $headline = sanitize_text_field((string)($settings['featured_headline'] ?? ''));
    $message = sanitize_textarea_field((string)($settings['featured_message'] ?? ''));
    $button_label = sanitize_text_field((string)($settings['featured_button_label'] ?? ''));
    $button_url = esc_url_raw((string)($settings['featured_button_url'] ?? ''));
    $starts_at = sanitize_text_field((string)($settings['featured_starts_at'] ?? ''));
    $ends_at = sanitize_text_field((string)($settings['featured_ends_at'] ?? ''));
    $timezone = wp_timezone();
    $now = current_datetime();
    $start = $starts_at ? DateTimeImmutable::createFromFormat('Y-m-d\\TH:i', $starts_at, $timezone) : null;
    $end = $ends_at ? DateTimeImmutable::createFromFormat('Y-m-d\\TH:i', $ends_at, $timezone) : null;
    $active = $enabled && $headline !== '' && $end && (!$start || $now >= $start) && $now <= $end;
    return array('enabled'=>$enabled,'active'=>(bool)$active,'headline'=>$headline,'message'=>$message,'button_label'=>$button_label,'button_url'=>$button_url,'starts_at'=>$start?$start->format(DATE_ATOM):'','ends_at'=>$end?$end->format(DATE_ATOM):'');
}

function surfside_tools_featured_announcement_valid_local_datetime($value) {
    if ($value === '') return '';
    return preg_match('/^\\d{4}-\\d{2}-\\d{2}T\\d{2}:\\d{2}$/', $value) ? $value : '';
}

add_filter('surfside_tools_mobile_api_app', function ($payload) {
    if (!is_array($payload)) return $payload;
    $featured = surfside_tools_app_featured_announcement();
    if (!isset($payload['app']) || !is_array($payload['app'])) $payload['app'] = array();
    $payload['app']['featured_announcement'] = $featured['active'] ? array('headline'=>$featured['headline'],'message'=>$featured['message'],'button_label'=>$featured['button_label'],'button_url'=>$featured['button_url'],'starts_at'=>$featured['starts_at'],'ends_at'=>$featured['ends_at']) : null;
    return $payload;
}, 20);

function surfside_tools_staff_featured_announcement_shortcode(){
    surfside_tools_prevent_cache();surfside_tools_staff_enqueue_styles();
    if(!is_user_logged_in())return surfside_tools_staff_login_box('Please log in to manage the mobile app.');
    if(!current_user_can('upload_files'))return '<div class="surfside-staff-shell"><p>You do not have permission to manage the mobile app.</p></div>';
    $saved=false;$validation_error='';
    if(($_SERVER['REQUEST_METHOD']??'')==='POST'&&isset($_POST['surfside_featured_home_nonce'])&&wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['surfside_featured_home_nonce'])),'surfside_featured_home_save')){
        $settings=function_exists('surfside_tools_app_settings')?surfside_tools_app_settings():array();
        $starts_at=surfside_tools_featured_announcement_valid_local_datetime(sanitize_text_field(wp_unslash($_POST['featured_starts_at']??'')));
        $ends_at=surfside_tools_featured_announcement_valid_local_datetime(sanitize_text_field(wp_unslash($_POST['featured_ends_at']??'')));
        $enabled_requested=!empty($_POST['featured_enabled']);
        if($enabled_requested&&$ends_at===''){$validation_error='Run Until is required when the featured announcement is enabled.';}else{
            $settings['featured_enabled']=$enabled_requested?1:0;
            $settings['featured_headline']=sanitize_text_field(wp_unslash($_POST['featured_headline']??''));
            $settings['featured_message']=sanitize_textarea_field(wp_unslash($_POST['featured_message']??''));
            $settings['featured_button_label']=sanitize_text_field(wp_unslash($_POST['featured_button_label']??''));
            $settings['featured_button_url']=esc_url_raw(trim((string)wp_unslash($_POST['featured_button_url']??'')));
            $settings['featured_starts_at']=$starts_at;$settings['featured_ends_at']=$ends_at;update_option('surfside_tools_app_settings',$settings);$saved=true;
        }
    }
    $settings=function_exists('surfside_tools_app_settings')?surfside_tools_app_settings():array();
    $featured=surfside_tools_app_featured_announcement();
    $enabled=!empty($settings['featured_enabled']);
    $headline=trim((string)($settings['featured_headline']??''));
    $starts_at=(string)($settings['featured_starts_at']??'');
    $ends_at=(string)($settings['featured_ends_at']??'');
    $now_local=current_datetime()->format('Y-m-d\\TH:i');
    $status='Not enabled';
    $status_detail='The normal Coming Up card is shown when no feature is active.';
    $status_key='off';
    if($enabled){
        if($headline===''){
            $status='Needs a headline';$status_detail='Add a headline and save before this can appear.';$status_key='attention';
        }elseif($ends_at===''){
            $status='Needs an end time';$status_detail='Set Run Until to enable a time-limited feature.';$status_key='attention';
        }elseif($starts_at!=='' && $starts_at>=$ends_at){
            $status='Check schedule';$status_detail='Run Until must be later than Start Showing.';$status_key='attention';
        }elseif($featured['active']){
            $status='Currently showing';$status_detail='This feature is visible in the app Home screen.';$status_key='live';
        }elseif($starts_at!=='' && $starts_at>$now_local){
            $status='Scheduled to start';$status_detail='The Coming Up card remains until the start time.';$status_key='scheduled';
        }elseif($ends_at<$now_local){
            $status='Expired';$status_detail='The normal Coming Up card has returned.';$status_key='expired';
        }else{
            $status='Not currently showing';$status_detail='Review the saved dates and headline.';$status_key='attention';
        }
    }
    ob_start();
    ?>
    <div class="surfside-staff-shell surfside-mobile-app-management surfside-featured-publishing surfside-content-media">
        <div class="surfside-staff-back"><a href="<?php echo esc_url(surfside_tools_staff_page_url('mobile-app')); ?>">← Back to Manage Mobile App</a></div>
        <section class="surfside-staff-hero">
            <p class="surfside-staff-eyebrow">Mobile App · Home</p>
            <h1>Featured Announcement</h1>
            <p class="surfside-staff-muted">Schedule a temporary announcement for the app Home screen. When the feature is active, it replaces the regular Coming Up card.</p>
        </section>

        <?php if($saved):?><div class="surfside-mobile-notice" role="status">Featured Announcement saved.</div><?php endif; ?>
        <?php if($validation_error):?><div class="surfside-mobile-error" role="alert"><?php echo esc_html($validation_error); ?></div><?php endif; ?>
        <div class="surfside-content-context surfside-featured-status-card">
            <div>
                <span class="surfside-content-eyebrow">Saved publication status</span>
                <strong><?php echo esc_html($status); ?></strong>
                <p><?php echo esc_html($status_detail); ?></p>
                <?php if($enabled): ?>
                    <p class="surfside-featured-schedule-detail">
                        Starts: <?php echo esc_html($starts_at!==''?str_replace('T',' at ',$starts_at):'Immediately'); ?>
                        <span aria-hidden="true"> · </span>
                        Ends: <?php echo esc_html($ends_at!==''?str_replace('T',' at ',$ends_at):'Not set'); ?>
                        (site timezone)
                    </p>
                <?php endif; ?>
            </div>
            <span class="surfside-featured-home-status surfside-featured-status-<?php echo esc_attr($status_key); ?>"><?php echo esc_html($status); ?></span>
        </div>

        <div class="surfside-featured-workspace-grid">
            <form method="post" class="surfside-featured-home-form">
                <?php wp_nonce_field('surfside_featured_home_save', 'surfside_featured_home_nonce'); ?>
                <section class="surfside-staff-panel">
                    <div class="surfside-featured-home-heading">
                        <div>
                            <h2>Home Feature</h2>
                            <p class="surfside-staff-muted">Use for baptisms, special services, ministry events, or other timely announcements.</p>
                        </div>
                    </div>
                    <label class="surfside-featured-home-toggle">
                        <input type="checkbox" name="featured_enabled" value="1" <?php checked($enabled); ?>>
                        <strong>Enable featured announcement</strong>
                    </label>
                    <div class="surfside-featured-home-grid">
                        <label class="surfside-featured-home-field surfside-featured-home-wide">
                            <span>Headline</span>
                            <input type="text" name="featured_headline" value="<?php echo esc_attr($settings['featured_headline']??''); ?>" maxlength="90" placeholder="Beach Baptism">
                        </label>
                        <label class="surfside-featured-home-field surfside-featured-home-wide">
                            <span>Short message</span>
                            <textarea name="featured_message" rows="3" maxlength="240" placeholder="Join us Sunday evening at Cherie Down Park."><?php echo esc_textarea($settings['featured_message']??''); ?></textarea>
                        </label>
                        <label class="surfside-featured-home-field">
                            <span>Start showing</span>
                            <input type="datetime-local" name="featured_starts_at" value="<?php echo esc_attr($starts_at); ?>">
                            <small>Optional. Leave blank to start immediately when enabled.</small>
                        </label>
                        <label class="surfside-featured-home-field">
                            <span>Run until</span>
                            <input type="datetime-local" name="featured_ends_at" value="<?php echo esc_attr($ends_at); ?>" data-featured-run-until>
                            <small>Required when enabled. The feature hides automatically after this time.</small>
                        </label>
                        <label class="surfside-featured-home-field">
                            <span>Button label</span>
                            <input type="text" name="featured_button_label" value="<?php echo esc_attr($settings['featured_button_label']??''); ?>" maxlength="30" placeholder="Learn More">
                            <small>Optional.</small>
                        </label>
                        <label class="surfside-featured-home-field">
                            <span>Button link</span>
                            <input type="url" name="featured_button_url" value="<?php echo esc_attr($settings['featured_button_url']??''); ?>" placeholder="https://...">
                            <small>Optional.</small>
                        </label>
                    </div>
                </section>
                <div class="surfside-content-save-actions">
                    <p>Saving an enabled feature with no future start time may display it immediately in the app.</p>
                    <button type="submit" class="surfside-staff-button surfside-mobile-save">Save Featured Announcement</button>
                </div>
            </form>
            <aside class="surfside-featured-preview" aria-labelledby="surfside-featured-preview-title">
                <span class="surfside-content-eyebrow">Saved content preview · Not a live app view</span>
                <h2 id="surfside-featured-preview-title">App Home card</h2>
                <?php if($headline!==''): ?>
                    <div class="surfside-featured-preview-card">
                        <span class="surfside-featured-preview-kicker">Featured announcement</span>
                        <h3><?php echo esc_html($headline); ?></h3>
                        <?php if(!empty($settings['featured_message'])): ?>
                            <p><?php echo nl2br(esc_html($settings['featured_message'])); ?></p>
                        <?php endif; ?>
                        <?php if(!empty($settings['featured_button_label'])): ?>
                            <span class="surfside-featured-preview-button"><?php echo esc_html($settings['featured_button_label']); ?></span>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="surfside-content-empty">
                        <strong>No saved headline yet</strong>
                        <p>Add a headline and save to see the announcement preview here.</p>
                    </div>
                <?php endif; ?>
                <p class="surfside-featured-preview-note">This previews saved text, not unsaved edits. The live app only shows it while enabled and within the configured time window.</p>
            </aside>
        </div>
    </div>
    <script>document.addEventListener('DOMContentLoaded',function(){const toggle=document.querySelector('input[name="featured_enabled"]');const until=document.querySelector('[data-featured-run-until]');if(!toggle||!until)return;const sync=()=>{until.required=toggle.checked;};toggle.addEventListener('change',sync);sync();});</script>
    <?php return ob_get_clean();
}
add_shortcode('surfside_staff_featured_announcement','surfside_tools_staff_featured_announcement_shortcode');
function surfside_tools_ensure_featured_announcement_page(){if(!function_exists('surfside_tools_ensure_staff_page'))return;$parent=get_page_by_path('dashboard/mobile-app');if(!$parent)$parent=get_page_by_path('mobile-app');if(!$parent)return;surfside_tools_ensure_staff_page('Featured Announcement','featured-announcement','[surfside_staff_featured_announcement]',(int)$parent->ID);}
