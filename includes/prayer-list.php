<?php
/** Church Prayer List submission and staff review foundation. */
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
    );
    surfside_tools_prayer_list_save_requests($items);
}

function surfside_tools_prayer_list_pending_count() {
    return count(array_filter(surfside_tools_prayer_list_requests(), function($item){ return ($item['status'] ?? '') === 'pending'; }));
}

function surfside_tools_prayer_list_page_url() {
    return add_query_arg('surfside-prayer-review', '1', surfside_tools_staff_page_url());
}

function surfside_tools_prayer_list_add_email_review_link($args) {
    $message = isset($args['message']) ? (string) $args['message'] : '';
    if (strpos($message, 'Prayer Audience: Church Prayer List') === false) return $args;

    $review_url = surfside_tools_prayer_list_page_url();
    $reminder = "\n\nACTION REQUIRED\nThis request is awaiting review before it can appear in the Surfside app.\nReview Prayer Request: " . $review_url;
    $args['message'] = $message . $reminder;
    return $args;
}
add_filter('wp_mail', 'surfside_tools_prayer_list_add_email_review_link');

function surfside_tools_prayer_list_handle_review() {
    if (!is_user_logged_in() || !current_user_can('upload_files') || empty($_POST['surfside_prayer_review_nonce'])) return;
    if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['surfside_prayer_review_nonce'])), 'surfside_prayer_review')) return;
    $id = sanitize_text_field(wp_unslash($_POST['request_id'] ?? ''));
    $action = sanitize_key(wp_unslash($_POST['prayer_action'] ?? ''));
    if (!$id || !in_array($action, array('approve','private','archive'), true)) return;
    $items = surfside_tools_prayer_list_requests();
    foreach ($items as &$item) {
        if (($item['id'] ?? '') !== $id) continue;
        if ($action === 'approve') {
            $days = absint($item['duration_days'] ?? 14);
            $item['status'] = 'published';
            $item['approved_at'] = current_time('timestamp');
            $item['expires_at'] = $item['approved_at'] + ($days * DAY_IN_SECONDS);
        } else {
            $item['status'] = $action === 'private' ? 'private' : 'archived';
        }
        break;
    }
    unset($item);
    surfside_tools_prayer_list_save_requests($items);
    wp_safe_redirect(surfside_tools_prayer_list_page_url()); exit;
}
add_action('template_redirect', 'surfside_tools_prayer_list_handle_review');

function surfside_tools_prayer_list_review_panel() {
    $pending = array_values(array_filter(surfside_tools_prayer_list_requests(), function($item){ return ($item['status'] ?? '') === 'pending'; }));
    ob_start(); ?>
    <section class="surfside-staff-panel">
      <div class="surfside-staff-back"><a href="<?php echo esc_url(surfside_tools_staff_page_url()); ?>">&larr; Back to Staff Dashboard</a></div>
      <h2>Pending Prayer Requests</h2>
      <p class="surfside-staff-muted">Review requests submitted for the Church Prayer List before they appear in the app.</p>
      <?php if (!$pending): ?><p style="margin-top:24px"><strong>No prayer requests are awaiting review.</strong></p><?php endif; ?>
      <?php foreach ($pending as $item): ?>
        <article style="margin-top:24px;padding:22px;border:1px solid rgba(7,27,58,.12);border-radius:14px;background:#fff">
          <p><strong><?php echo esc_html(($item['name_display'] ?? '') === 'anonymous' ? 'Anonymous in prayer list' : ($item['name'] ?? '')); ?></strong></p>
          <p><?php echo nl2br(esc_html($item['message'] ?? '')); ?></p>
          <p class="surfside-staff-muted">Requested active period: <?php echo absint($item['duration_days'] ?? 14); ?> days &middot; Submitted <?php echo esc_html(wp_date('F j, Y g:i A', absint($item['submitted_at'] ?? 0))); ?></p>
          <form method="post" style="display:flex;gap:10px;flex-wrap:wrap;margin-top:16px">
            <?php wp_nonce_field('surfside_prayer_review','surfside_prayer_review_nonce'); ?>
            <input type="hidden" name="request_id" value="<?php echo esc_attr($item['id'] ?? ''); ?>">
            <button class="surfside-staff-button" style="width:auto" name="prayer_action" value="approve">Approve &amp; Publish</button>
            <button class="surfside-staff-button-secondary" style="width:auto" name="prayer_action" value="private">Keep Private</button>
            <button class="surfside-staff-button-secondary" style="width:auto" name="prayer_action" value="archive">Archive</button>
          </form>
        </article>
      <?php endforeach; ?>
    </section>
    <?php return ob_get_clean();
}
