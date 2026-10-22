<?php
/** Church Prayer List submission and staff management. */
if (!defined('ABSPATH')) { exit; }

function surfside_tools_prayer_list_requests() {
    $items = get_option('surfside_tools_prayer_list_requests', array());
    return is_array($items) ? $items : array();
}
function surfside_tools_prayer_list_save_requests($items) { update_option('surfside_tools_prayer_list_requests', array_values($items), false); }

function surfside_tools_prayer_list_add_pending($data, $verified_owner_uid = '') {
    $duration = absint($data['prayer_duration'] ?? 0);
    if (!in_array($duration, array(7,14,30), true)) $duration = 14;
    $display = sanitize_key($data['prayer_name_display'] ?? 'named');
    if (!in_array($display, array('named','anonymous'), true)) $display = 'named';
    $items = surfside_tools_prayer_list_requests();
    $id = wp_generate_uuid4();
    $items[] = array(
        'owner_uid'=>(string)$verified_owner_uid,'publication_notified_at'=>0,
        'id'=>$id,'status'=>'pending','name'=>sanitize_text_field($data['name']??''),'email'=>sanitize_email($data['email']??''),'phone'=>sanitize_text_field($data['phone']??''),'message'=>sanitize_textarea_field($data['message']??''),'name_display'=>$display,'duration_days'=>$duration,'submitted_at'=>current_time('timestamp'),'approved_at'=>0,'expires_at'=>0,'answered_at'=>0,
    );
    surfside_tools_prayer_list_save_requests($items);
    return $id;
}

function surfside_tools_prayer_list_is_active($item) { return ($item['status']??'')==='published' && absint($item['expires_at']??0) >= current_time('timestamp'); }
function surfside_tools_prayer_list_pending_count() { return count(array_filter(surfside_tools_prayer_list_requests(), function($item){return ($item['status']??'')==='pending';})); }
function surfside_tools_prayer_list_active_count() { return count(array_filter(surfside_tools_prayer_list_requests(), 'surfside_tools_prayer_list_is_active')); }

function surfside_tools_prayer_list_api_response() {
    $items = array_values(array_filter(surfside_tools_prayer_list_requests(), 'surfside_tools_prayer_list_is_active'));
    usort($items, function($a, $b) { return absint($b['approved_at'] ?? 0) <=> absint($a['approved_at'] ?? 0); });

    $public = array_map(function($item) {
        $anonymous = ($item['name_display'] ?? '') === 'anonymous';
        return array(
            'id' => (string) ($item['id'] ?? ''),
            'display_name' => $anonymous ? 'Anonymous' : sanitize_text_field($item['name'] ?? ''),
            'anonymous' => $anonymous,
            'request' => sanitize_textarea_field($item['message'] ?? ''),
            'published_at' => absint($item['approved_at'] ?? 0),
            'expires_at' => absint($item['expires_at'] ?? 0),
        );
    }, $items);

    return rest_ensure_response(array(
        'api_version' => 1,
        'generated_at' => current_datetime()->format(DATE_ATOM),
        'count' => count($public),
        'requests' => $public,
    ));
}

function surfside_tools_prayer_list_register_api() {
    register_rest_route('surfside/v1', '/prayer-list', array(
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'surfside_tools_prayer_list_api_response',
        'permission_callback' => '__return_true',
    ));
}
add_action('rest_api_init', 'surfside_tools_prayer_list_register_api');

function surfside_tools_prayer_list_page_url($section='pending') {
    if (function_exists('surfside_tools_member_engagement_url')) return add_query_arg('section',sanitize_key($section),surfside_tools_member_engagement_url('prayer-requests'));
    return add_query_arg(array('surfside-prayer-review'=>'1','section'=>sanitize_key($section)),surfside_tools_staff_page_url());
}

function surfside_tools_prayer_list_add_email_review_link($args) {
    $message=(string)($args['message']??'');
    if (strpos($message,'Prayer Audience: Church Prayer List')===false) return $args;
    $args['message']=$message."\n\nACTION REQUIRED\nThis request is awaiting review before it can appear in the Surfside app.\nReview Prayer Request: ".surfside_tools_prayer_list_page_url('pending');
    return $args;
}
add_filter('wp_mail','surfside_tools_prayer_list_add_email_review_link');

/** Claim the first publication once. Legacy approved records also stay silent. */
function surfside_tools_prayer_list_claim_publication(&$item) {
    if (!empty($item['publication_notified_at']) || !empty($item['approved_at']) || ($item['status'] ?? '') === 'published') return false;
    $item['publication_notified_at'] = current_time('timestamp');
    return true;
}

/** Send the member-facing push only after staff approves a request for the public prayer list. */
function surfside_tools_prayer_list_send_published_notification() {
    if (!function_exists('surfside_tools_push_send')) return;
    $result = surfside_tools_push_send(
        'New Prayer or Praise',
        'A new prayer or praise has been added to Church Prayers and Praises.',
        'prayer-list',
        array('prayer_requests')
    );
    if (is_wp_error($result)) {
        error_log('Surfside prayer request push notification failed: '.$result->get_error_message());
    }
}

function surfside_tools_prayer_list_handle_review() {
    if (!is_user_logged_in()||!current_user_can('upload_files')||empty($_POST['surfside_prayer_review_nonce'])) return;
    if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['surfside_prayer_review_nonce'])),'surfside_prayer_review')) return;
    $id=sanitize_text_field(wp_unslash($_POST['request_id']??''));
    $action=sanitize_key(wp_unslash($_POST['prayer_action']??''));
    if (!$id||!in_array($action,array('approve','private','archive','answered','extend-7','extend-14','extend-30'),true)) return;
    $items=surfside_tools_prayer_list_requests(); $redirect='pending'; $send_published_notification=false;
    foreach($items as &$item){
        if(($item['id']??'')!==$id)continue;
        if($action==='approve'){
            $send_published_notification=surfside_tools_prayer_list_claim_publication($item);
            $days=absint($item['duration_days']??14);$item['status']='published';$item['approved_at']=current_time('timestamp');$item['expires_at']=$item['approved_at']+($days*DAY_IN_SECONDS);$redirect='active';
        }
        elseif($action==='private'){$item['status']='private';$redirect='history';}
        elseif($action==='archive'){$item['status']='archived';$redirect='history';}
        elseif($action==='answered'){$item['status']='answered';$item['answered_at']=current_time('timestamp');$redirect='history';}
        elseif(strpos($action,'extend-')===0){$days=absint(substr($action,7));$base=max(current_time('timestamp'),absint($item['expires_at']??0));$item['status']='published';$item['expires_at']=$base+($days*DAY_IN_SECONDS);$redirect='active';}
        break;
    }
    unset($item);
    surfside_tools_prayer_list_save_requests($items);
    // Notify only for the first publish transition. Extensions, edits, and later management actions stay silent.
    if($send_published_notification)surfside_tools_prayer_list_send_published_notification();
    wp_safe_redirect(surfside_tools_prayer_list_page_url($redirect)); exit;
}
add_action('template_redirect','surfside_tools_prayer_list_handle_review');

function surfside_tools_prayer_list_status_label($item) {
    $status=$item['status']??'';
    if($status==='published'&&!surfside_tools_prayer_list_is_active($item))return 'Expired';
    $labels=array('pending'=>'Pending','published'=>'Active','private'=>'Kept Private','archived'=>'Archived','answered'=>'Answered');
    return $labels[$status]??ucfirst($status?:'Unknown');
}

/**
 * Member care interface: display-only status and action clarity.
 * Existing POST fields, permissions, notification, and publication behavior
 * are intentionally unchanged.
 */
function surfside_tools_prayer_list_render_request($item, $section) {
    $anonymous = ($item['name_display'] ?? '') === 'anonymous';
    $display = $anonymous ? 'Anonymous in prayer list' : ($item['name'] ?? '');
    ob_start();
    ?>
    <article class="surfside-prayer-manager-card">
        <header class="surfside-prayer-manager-head">
            <div>
                <strong class="surfside-prayer-requester"><?php echo esc_html($display); ?></strong>
                <?php if ($anonymous) : ?>
                    <span class="surfside-prayer-privacy-note">Name hidden from the public prayer list</span>
                <?php endif; ?>
            </div>
            <span class="surfside-prayer-status"><?php echo esc_html(surfside_tools_prayer_list_status_label($item)); ?></span>
        </header>
        <div class="surfside-prayer-request-text"><?php echo nl2br(esc_html($item['message'] ?? '')); ?></div>
        <p class="surfside-prayer-meta">
            <?php if ($section === 'pending') : ?>
                Requested active period: <strong><?php echo absint($item['duration_days'] ?? 14); ?> days</strong>
                <span aria-hidden="true"> · </span>
                Submitted <?php echo esc_html(wp_date('F j, Y g:i A', absint($item['submitted_at'] ?? 0))); ?>
            <?php elseif ($section === 'active') : ?>
                Published <?php echo esc_html(wp_date('F j, Y', absint($item['approved_at'] ?? 0))); ?>
                <span aria-hidden="true"> · </span>
                Expires <?php echo esc_html(wp_date('F j, Y', absint($item['expires_at'] ?? 0))); ?>
            <?php else : ?>
                Submitted <?php echo esc_html(wp_date('F j, Y', absint($item['submitted_at'] ?? 0))); ?>
                <?php if (!empty($item['expires_at'])) : ?>
                    <span aria-hidden="true"> · </span>
                    Expiration <?php echo esc_html(wp_date('F j, Y', absint($item['expires_at']))); ?>
                <?php endif; ?>
            <?php endif; ?>
        </p>
        <?php if ($section === 'pending') : ?>
            <div class="surfside-prayer-action-group">
                <p class="surfside-prayer-action-hint">Choose whether this request should appear on the public Church Prayer List.</p>
                <form method="post" class="surfside-prayer-actions">
                    <?php wp_nonce_field('surfside_prayer_review', 'surfside_prayer_review_nonce'); ?>
                    <input type="hidden" name="request_id" value="<?php echo esc_attr($item['id'] ?? ''); ?>">
                    <button type="submit" class="surfside-staff-button" name="prayer_action" value="approve">Approve &amp; Publish</button>
                    <button type="submit" class="surfside-staff-button-secondary" name="prayer_action" value="private">Keep Private</button>
                    <button type="submit" class="surfside-staff-button-secondary" name="prayer_action" value="archive">Archive</button>
                </form>
            </div>
        <?php elseif ($section === 'active') : ?>
            <div class="surfside-prayer-action-group">
                <div class="surfside-prayer-actions">
                    <form method="post" class="surfside-prayer-inline-actions">
                        <?php wp_nonce_field('surfside_prayer_review', 'surfside_prayer_review_nonce'); ?>
                        <input type="hidden" name="request_id" value="<?php echo esc_attr($item['id'] ?? ''); ?>">
                        <button type="submit" class="surfside-staff-button" name="prayer_action" value="answered">Mark Answered</button>
                        <button type="submit" class="surfside-staff-button-secondary" name="prayer_action" value="archive">Remove</button>
                    </form>
                    <form method="post" class="surfside-prayer-extend">
                        <?php wp_nonce_field('surfside_prayer_review', 'surfside_prayer_review_nonce'); ?>
                        <input type="hidden" name="request_id" value="<?php echo esc_attr($item['id'] ?? ''); ?>">
                        <label>Extend
                            <select name="prayer_action" aria-label="Extend active prayer request" onchange="if(this.value)this.form.submit()">
                                <option value="">Choose…</option>
                                <option value="extend-7">+7 days</option>
                                <option value="extend-14">+14 days</option>
                                <option value="extend-30">+30 days</option>
                            </select>
                        </label>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </article>
    <?php
    return ob_get_clean();
}

function surfside_tools_prayer_list_manager_panel() {
    $section = isset($_GET['section']) ? sanitize_key(wp_unslash($_GET['section'])) : 'pending';
    if (!in_array($section, array('pending', 'active', 'history'), true)) $section = 'pending';
    $items = surfside_tools_prayer_list_requests();
    $pending = array_values(array_filter($items, function ($item) { return ($item['status'] ?? '') === 'pending'; }));
    $active = array_values(array_filter($items, 'surfside_tools_prayer_list_is_active'));
    $history = array_values(array_filter($items, function ($item) {
        return ($item['status'] ?? '') !== 'pending' && !surfside_tools_prayer_list_is_active($item);
    }));
    $lists = array('pending' => $pending, 'active' => $active, 'history' => $history);
    $current = $lists[$section];
    usort($current, function ($a, $b) {
        return absint($b['submitted_at'] ?? 0) <=> absint($a['submitted_at'] ?? 0);
    });
    $explanation = array(
        'pending' => 'Review new requests before adding them to the public Church Prayer List.',
        'active' => 'These requests are visible to church members until they expire or are marked answered.',
        'history' => 'Previously private, archived, answered, and expired requests stay here for staff reference.',
    );
    ob_start();
    ?>
    <div class="surfside-staff-back">
        <a href="<?php echo esc_url(function_exists('surfside_tools_member_engagement_url')
            ? surfside_tools_member_engagement_url()
            : surfside_tools_staff_page_url()); ?>">← Back to Member Engagement</a>
    </div>
    <section class="surfside-staff-hero">
        <p class="surfside-staff-eyebrow">Member Engagement</p>
        <h1>Prayers and Praises</h1>
        <p class="surfside-staff-muted">Review requests for the Church Prayer List, manage what's currently public, and keep completed requests organized.</p>
    </section>
    <div class="surfside-care-summary" aria-label="Prayer list overview">
        <div><strong><?php echo count($pending); ?></strong><span>Awaiting review</span></div>
        <div><strong><?php echo count($active); ?></strong><span>On prayer list</span></div>
        <div><strong><?php echo count($history); ?></strong><span>In history</span></div>
    </div>
    <nav class="surfside-prayer-tabs" aria-label="Prayer request sections">
        <?php foreach (array('pending' => 'Pending', 'active' => 'Active', 'history' => 'History') as $key => $label) : ?>
            <a class="<?php echo $section === $key ? 'is-active' : ''; ?>"
                <?php if ($section === $key) : ?>aria-current="page"<?php endif; ?>
                href="<?php echo esc_url(surfside_tools_prayer_list_page_url($key)); ?>">
                <?php echo esc_html($label); ?> <span><?php echo count($lists[$key]); ?></span>
            </a>
        <?php endforeach; ?>
    </nav>
    <section class="surfside-staff-panel surfside-prayer-manager" aria-labelledby="surfside-prayer-section-title">
        <div class="surfside-care-section-heading">
            <div>
                <h2 id="surfside-prayer-section-title"><?php echo esc_html(ucfirst($section)); ?> Prayer Requests</h2>
                <p><?php echo esc_html($explanation[$section]); ?></p>
            </div>
            <span class="surfside-care-section-count"><?php echo count($current); ?> request<?php echo count($current) === 1 ? '' : 's'; ?></span>
        </div>
        <?php if (!$current) : ?>
            <div class="surfside-care-empty">
                <strong><?php echo $section === 'pending' ? 'All caught up' : 'Nothing here yet'; ?></strong>
                <p><?php echo $section === 'pending' ? 'There are no new prayer requests waiting for review.' : 'No ' . esc_html($section) . ' prayer requests to show.'; ?></p>
            </div>
        <?php endif; ?>
        <?php foreach ($current as $item) echo surfside_tools_prayer_list_render_request($item, $section); ?>
    </section>
    <?php
    return ob_get_clean();
}

function surfside_tools_prayer_list_review_panel(){return surfside_tools_prayer_list_manager_panel();}

