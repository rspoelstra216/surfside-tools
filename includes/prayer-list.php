<?php
/** Church Prayer List submission and staff management. */
if (!defined('ABSPATH')) { exit; }

function surfside_tools_prayer_list_requests() {
    $items = get_option('surfside_tools_prayer_list_requests', array());
    return is_array($items) ? $items : array();
}

function surfside_tools_prayer_list_save_requests($items) {
    update_option('surfside_tools_prayer_list_requests', array_values($items), false);
}

function surfside_tools_prayer_list_add_pending($data) {
    $duration = absint($data['prayer_duration'] ?? 0);
    if (!in_array($duration, array(7, 14, 30), true)) $duration = 14;
    $display = sanitize_key($data['prayer_name_display'] ?? 'named');
    if (!in_array($display, array('named', 'anonymous'), true)) $display = 'named';

    $items = surfside_tools_prayer_list_requests();
    $items[] = array(
        'id' => wp_generate_uuid4(),
        'status' => 'pending',
        'name' => sanitize_text_field($data['name'] ?? ''),
        'email' => sanitize_email($data['email'] ?? ''),
        'phone' => sanitize_text_field($data['phone'] ?? ''),
        'message' => sanitize_textarea_field($data['message'] ?? ''),
        'name_display' => $display,
        'duration_days' => $duration,
        'submitted_at' => current_time('timestamp'),
        'approved_at' => 0,
        'expires_at' => 0,
        'answered_at' => 0,
    );
    surfside_tools_prayer_list_save_requests($items);
}

function surfside_tools_prayer_list_is_active($item) {
    return ($item['status'] ?? '') === 'published' && absint($item['expires_at'] ?? 0) >= current_time('timestamp');
}

function surfside_tools_prayer_list_pending_count() {
    return count(array_filter(surfside_tools_prayer_list_requests(), function($item){ return ($item['status'] ?? '') === 'pending'; }));
}

function surfside_tools_prayer_list_active_count() {
    return count(array_filter(surfside_tools_prayer_list_requests(), 'surfside_tools_prayer_list_is_active'));
}

function surfside_tools_prayer_list_page_url($section = 'pending') {
    if (function_exists('surfside_tools_member_engagement_url')) {
        return add_query_arg('section', sanitize_key($section), surfside_tools_member_engagement_url('prayer-requests'));
    }
    return add_query_arg(array('surfside-prayer-review'=>'1','section'=>sanitize_key($section)), surfside_tools_staff_page_url());
}

function surfside_tools_prayer_list_add_email_review_link($args) {
    $message = isset($args['message']) ? (string) $args['message'] : '';
    if (strpos($message, 'Prayer Audience: Church Prayer List') === false) return $args;
    $args['message'] = $message . "\n\nACTION REQUIRED\nThis request is awaiting review before it can appear in the Surfside app.\nReview Prayer Request: " . surfside_tools_prayer_list_page_url('pending');
    return $args;
}
add_filter('wp_mail', 'surfside_tools_prayer_list_add_email_review_link');

function surfside_tools_prayer_list_handle_review() {
    if (!is_user_logged_in() || !current_user_can('upload_files') || empty($_POST['surfside_prayer_review_nonce'])) return;
    if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['surfside_prayer_review_nonce'])), 'surfside_prayer_review')) return;
    $id = sanitize_text_field(wp_unslash($_POST['request_id'] ?? ''));
    $action = sanitize_key(wp_unslash($_POST['prayer_action'] ?? ''));
    $allowed = array('approve','private','archive','answered','extend-7','extend-14','extend-30');
    if (!$id || !in_array($action, $allowed, true)) return;

    $items = surfside_tools_prayer_list_requests();
    $redirect_section = 'pending';
    foreach ($items as &$item) {
        if (($item['id'] ?? '') !== $id) continue;
        if ($action === 'approve') {
            $days = absint($item['duration_days'] ?? 14);
            $item['status'] = 'published';
            $item['approved_at'] = current_time('timestamp');
            $item['expires_at'] = $item['approved_at'] + ($days * DAY_IN_SECONDS);
            $redirect_section = 'active';
        } elseif ($action === 'private') {
            $item['status'] = 'private';
            $redirect_section = 'history';
        } elseif ($action === 'archive') {
            $item['status'] = 'archived';
            $redirect_section = 'history';
        } elseif ($action === 'answered') {
            $item['status'] = 'answered';
            $item['answered_at'] = current_time('timestamp');
            $redirect_section = 'history';
        } elseif (strpos($action, 'extend-') === 0) {
            $days = absint(substr($action, 7));
            $base = max(current_time('timestamp'), absint($item['expires_at'] ?? 0));
            $item['status'] = 'published';
            $item['expires_at'] = $base + ($days * DAY_IN_SECONDS);
            $redirect_section = 'active';
        }
        break;
    }
    unset($item);
    surfside_tools_prayer_list_save_requests($items);
    wp_safe_redirect(surfside_tools_prayer_list_page_url($redirect_section)); exit;
}
add_action('template_redirect', 'surfside_tools_prayer_list_handle_review');

function surfside_tools_prayer_list_status_label($item) {
    $status = $item['status'] ?? '';
    if ($status === 'published' && !surfside_tools_prayer_list_is_active($item)) return 'Expired';
    $labels = array('pending'=>'Pending','published'=>'Active','private'=>'Kept Private','archived'=>'Archived','answered'=>'Answered');
    return $labels[$status] ?? ucfirst($status ?: 'Unknown');
}

function surfside_tools_prayer_list_render_request($item, $section) {
    $anonymous = ($item['name_display'] ?? '') === 'anonymous';
    $display_name = $anonymous ? 'Anonymous in prayer list' : ($item['name'] ?? '');
    ob_start(); ?>
    <article class="surfside-prayer-manager-card">
      <div class="surfside-prayer-manager-head">
        <strong><?php echo esc_html($display_name); ?></strong>
        <span class="surfside-prayer-status"><?php echo esc_html(surfside_tools_prayer_list_status_label($item)); ?></span>
      </div>
      <p><?php echo nl2br(esc_html($item['message'] ?? '')); ?></p>
      <div class="surfside-prayer-meta">
        <?php if ($section === 'pending'): ?>Requested active period: <?php echo absint($item['duration_days'] ?? 14); ?> days · Submitted <?php echo esc_html(wp_date('F j, Y g:i A', absint($item['submitted_at'] ?? 0))); ?>
        <?php elseif ($section === 'active'): ?>Published <?php echo esc_html(wp_date('F j, Y', absint($item['approved_at'] ?? 0))); ?> · Expires <?php echo esc_html(wp_date('F j, Y', absint($item['expires_at'] ?? 0))); ?>
        <?php else: ?>Submitted <?php echo esc_html(wp_date('F j, Y', absint($item['submitted_at'] ?? 0))); ?><?php if (!empty($item['expires_at'])): ?> · Expiration <?php echo esc_html(wp_date('F j, Y', absint($item['expires_at']))); ?><?php endif; ?><?php endif; ?>
      </div>
      <form method="post" class="surfside-prayer-actions">
        <?php wp_nonce_field('surfside_prayer_review','surfside_prayer_review_nonce'); ?>
        <input type="hidden" name="request_id" value="<?php echo esc_attr($item['id'] ?? ''); ?>">
        <?php if ($section === 'pending'): ?>
          <button class="surfside-staff-button" name="prayer_action" value="approve">Approve &amp; Publish</button>
          <button class="surfside-staff-button-secondary" name="prayer_action" value="private">Keep Private</button>
          <button class="surfside-staff-button-secondary" name="prayer_action" value="archive">Archive</button>
        <?php elseif ($section === 'active'): ?>
          <button class="surfside-staff-button" name="prayer_action" value="answered">Mark Answered</button>
          <label class="surfside-prayer-extend">Extend<select onchange="if(this.value){this.form.querySelector('[data-extend-action]').value=this.value;this.form.querySelector('[data-extend-submit]').click();}"><option value="">Choose…</option><option value="extend-7">+7 days</option><option value="extend-14">+14 days</option><option value="extend-30">+30 days</option></select></label>
          <input type="hidden" name="prayer_action" value="" data-extend-action><button type="submit" hidden data-extend-submit></button>
          <button class="surfside-staff-button-secondary" name="prayer_action" value="archive">Remove</button>
        <?php endif; ?>
      </form>
    </article>
    <?php return ob_get_clean();
}

function surfside_tools_prayer_list_manager_panel() {
    $section = isset($_GET['section']) ? sanitize_key(wp_unslash($_GET['section'])) : 'pending';
    if (!in_array($section, array('pending','active','history'), true)) $section = 'pending';
    $items = surfside_tools_prayer_list_requests();
    $pending = array_values(array_filter($items, function($item){ return ($item['status'] ?? '') === 'pending'; }));
    $active = array_values(array_filter($items, 'surfside_tools_prayer_list_is_active'));
    $history = array_values(array_filter($items, function($item){
        return ($item['status'] ?? '') !== 'pending' && !surfside_tools_prayer_list_is_active($item);
    }));
    $lists = array('pending'=>$pending,'active'=>$active,'history'=>$history);
    $current = $lists[$section];

    usort($current, function($a,$b){ return absint($b['submitted_at'] ?? 0) <=> absint($a['submitted_at'] ?? 0); });
    ob_start(); ?>
    <div class="surfside-staff-back"><a href="<?php echo esc_url(function_exists('surfside_tools_member_engagement_url') ? surfside_tools_member_engagement_url() : surfside_tools_staff_page_url()); ?>">← Back to Member Engagement</a></div>
    <section class="surfside-staff-hero">
      <p class="surfside-staff-eyebrow">Member Engagement</p>
      <h1>Prayer Requests</h1>
      <p class="surfside-staff-muted">Review pending Church Prayer List requests, manage what is currently active, and keep a record of completed requests.</p>
    </section>
    <nav class="surfside-prayer-tabs" aria-label="Prayer request sections">
      <a class="<?php echo $section==='pending'?'is-active':''; ?>" href="<?php echo esc_url(surfside_tools_prayer_list_page_url('pending')); ?>">Pending <span><?php echo count($pending); ?></span></a>
      <a class="<?php echo $section==='active'?'is-active':''; ?>" href="<?php echo esc_url(surfside_tools_prayer_list_page_url('active')); ?>">Active <span><?php echo count($active); ?></span></a>
      <a class="<?php echo $section==='history'?'is-active':''; ?>" href="<?php echo esc_url(surfside_tools_prayer_list_page_url('history')); ?>">History <span><?php echo count($history); ?></span></a>
    </nav>
    <section class="surfside-staff-panel surfside-prayer-manager">
      <h2><?php echo esc_html(ucfirst($section)); ?> Prayer Requests</h2>
      <?php if (!$current): ?><p class="surfside-staff-muted">No <?php echo esc_html($section); ?> prayer requests.</p><?php endif; ?>
      <?php foreach ($current as $item) echo surfside_tools_prayer_list_render_request($item, $section); ?>
    </section>
    <style>
      .surfside-prayer-tabs{display:flex;gap:10px;flex-wrap:wrap;margin:0 0 22px}.surfside-prayer-tabs a{display:inline-flex;gap:8px;align-items:center;padding:10px 15px;border:1px solid #cbd7e2;border-radius:999px;background:#fff;color:#0b4f9c;text-decoration:none;font-weight:800}.surfside-prayer-tabs a.is-active{background:#0b4f9c;color:#fff;border-color:#0b4f9c}.surfside-prayer-tabs span{display:inline-grid;place-items:center;min-width:24px;height:24px;padding:0 7px;border-radius:999px;background:rgba(11,79,156,.1);font-size:12px}.surfside-prayer-tabs a.is-active span{background:rgba(255,255,255,.2)}.surfside-prayer-manager-card{margin-top:20px;padding:22px;border:1px solid rgba(7,27,58,.12);border-radius:14px;background:#fff}.surfside-prayer-manager-head{display:flex;align-items:center;justify-content:space-between;gap:12px}.surfside-prayer-status{padding:5px 9px;border-radius:999px;background:#eef4f7;color:#526270;font-size:12px;font-weight:800}.surfside-prayer-meta{color:#66727e;font-size:14px}.surfside-prayer-actions{display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-top:16px}.surfside-prayer-actions .surfside-staff-button,.surfside-prayer-actions .surfside-staff-button-secondary{width:auto}.surfside-prayer-extend{display:flex;align-items:center;gap:8px;font-weight:800;color:#26323d}.surfside-prayer-extend select{padding:10px 12px;border:1px solid #aeb9c4;border-radius:8px;background:#fff}@media(max-width:640px){.surfside-prayer-manager-head{align-items:flex-start;flex-direction:column}.surfside-prayer-actions{align-items:stretch}.surfside-prayer-actions .surfside-staff-button,.surfside-prayer-actions .surfside-staff-button-secondary,.surfside-prayer-extend{width:100%}.surfside-prayer-extend{justify-content:space-between}}
    </style>
    <?php return ob_get_clean();
}

function surfside_tools_prayer_list_review_panel() {
    return surfside_tools_prayer_list_manager_panel();
}
