<?php
/**
 * Phase 3 staff-facing Weekly Update status and scheduled document review.
 *
 * Display only: all staging, release and archive writes remain in
 * weekly-schedule.php and core-weekly-tools.php.
 */
if (!defined('ABSPATH')) { exit; }

/** Keep published versus scheduled documents visually distinct. */
function surfside_tools_weekly_staff_status_markup($scheduled, $next_release) {
    $live_announcements = surfside_tools_get_announcements_data();
    $live_message = surfside_tools_get_message_data();
    $live_count = count((array) ($live_announcements['items'] ?? array()));
    $has_legacy_announcements = trim((string) ($live_announcements['announcements'] ?? '')) !== '';
    $live_date = trim((string) ($live_announcements['announcement_date'] ?? ''));
    $live_title = trim((string) ($live_message['title'] ?? ''));
    $scheduled = is_array($scheduled) ? $scheduled : array();
    $has_schedule = !empty($scheduled['publish_at']);
    $announcement_draft = !empty($scheduled['announcements']) && is_array($scheduled['announcements'])
        ? $scheduled['announcements'] : array();
    $sermon_draft = !empty($scheduled['sermon']) && is_array($scheduled['sermon'])
        ? $scheduled['sermon'] : array();
    $date = $has_schedule ? absint($scheduled['publish_at']) : absint($next_release);

    ob_start();
    ?>
    <div class="surfside-weekly-status-grid" aria-label="Weekly document status">
        <section class="surfside-weekly-state-card surfside-weekly-state-live" aria-labelledby="surfside-weekly-live-title">
            <div class="surfside-weekly-state-header">
                <span class="surfside-weekly-state-eyebrow"><span class="surfside-weekly-state-dot" aria-hidden="true"></span> Live now</span>
                <span class="surfside-weekly-state-channel">Website &amp; app</span>
            </div>
            <h3 id="surfside-weekly-live-title">Currently published</h3>
            <dl class="surfside-weekly-doc-list">
                <div>
                    <dt>Announcements</dt>
                    <dd><?php echo esc_html(($live_count || $has_legacy_announcements)
                        ? ($live_date ?: ($live_count ? $live_count . ' announcements' : 'Published'))
                        : 'None published'); ?></dd>
                </div>
                <div>
                    <dt>Sermon notes</dt>
                    <dd><?php echo esc_html($live_title ?: 'None published'); ?></dd>
                </div>
            </dl>
            <p class="surfside-weekly-state-footnote">These are the documents people see until the next scheduled release.</p>
        </section>

        <section class="surfside-weekly-state-card surfside-weekly-state-upcoming<?php echo !empty($scheduled['last_error']) ? ' surfside-weekly-state-error' : ''; ?>" aria-labelledby="surfside-weekly-upcoming-title">
            <div class="surfside-weekly-state-header">
                <span class="surfside-weekly-state-eyebrow">
                    <?php echo $has_schedule ? 'Scheduled update' : 'Next release'; ?>
                </span>
                <span class="surfside-weekly-state-channel">5:00 PM Eastern</span>
            </div>
            <h3 id="surfside-weekly-upcoming-title"><?php echo esc_html(
                $has_schedule
                    ? (!empty($scheduled['last_error']) ? 'Release needs attention' : 'Ready for Saturday')
                    : 'Nothing scheduled yet'
            ); ?></h3>
            <p class="surfside-weekly-release-date">
                <?php echo esc_html(surfside_tools_weekly_release_label($date)); ?>
            </p>
            <?php if ($has_schedule) : ?>
                <dl class="surfside-weekly-doc-list">
                    <div>
                        <dt>Announcements</dt>
                        <dd><?php echo esc_html($announcement_draft
                            ? ((string) ($announcement_draft['announcement_date'] ?? '') ?: count((array) ($announcement_draft['items'] ?? array())) . ' announcements')
                            : 'Keep current version'); ?></dd>
                    </div>
                    <div>
                        <dt>Sermon notes</dt>
                        <dd><?php echo esc_html($sermon_draft
                            ? ((string) ($sermon_draft['title'] ?? '') ?: 'Untitled message')
                            : 'Keep current version'); ?></dd>
                    </div>
                </dl>
                <?php if (!empty($scheduled['last_error'])) : ?>
                    <p class="surfside-weekly-schedule-error" role="alert">Automatic release needs attention: <?php echo esc_html($scheduled['last_error']); ?></p>
                <?php endif; ?>

                <details class="surfside-weekly-draft-preview">
                    <summary>Review scheduled documents</summary>
                    <?php if ($announcement_draft) : ?>
                        <section class="surfside-weekly-draft-section">
                            <h4>Scheduled announcements</h4>
                            <?php if (!empty($announcement_draft['announcement_date'])) : ?>
                                <p><?php echo esc_html($announcement_draft['announcement_date']); ?></p>
                            <?php endif; ?>
                            <ol>
                                <?php foreach ((array) ($announcement_draft['items'] ?? array()) as $item) : ?>
                                    <li><?php echo esc_html($item); ?></li>
                                <?php endforeach; ?>
                            </ol>
                        </section>
                    <?php endif; ?>
                    <?php if ($sermon_draft) : ?>
                        <section class="surfside-weekly-draft-section">
                            <h4>Scheduled sermon notes</h4>
                            <p><strong><?php echo esc_html($sermon_draft['title'] ?? 'Untitled message'); ?></strong></p>
                            <?php if (!empty($sermon_draft['series'])) : ?>
                                <p><?php echo esc_html($sermon_draft['series']); ?></p>
                            <?php endif; ?>
                            <?php if (!empty($sermon_draft['date'])) : ?>
                                <p><?php echo esc_html($sermon_draft['date']); ?></p>
                            <?php endif; ?>
                            <?php if (!empty($sermon_draft['main_heading'])) : ?>
                                <h5><?php echo esc_html($sermon_draft['main_heading']); ?></h5>
                            <?php endif; ?>
                            <div class="surfside-weekly-draft-text"><?php echo nl2br(esc_html($sermon_draft['notes'] ?? '')); ?></div>
                            <?php if (!empty($sermon_draft['prayer'])) : ?>
                                <p><strong>Closing prayer</strong></p>
                                <div class="surfside-weekly-draft-text"><?php echo nl2br(esc_html($sermon_draft['prayer'])); ?></div>
                            <?php endif; ?>
                        </section>
                    <?php endif; ?>
                    <?php if (!$announcement_draft || !$sermon_draft) : ?>
                        <p>Any document not scheduled here will keep its currently published version.</p>
                    <?php endif; ?>
                </details>

                <form method="post" class="surfside-weekly-cancel-form"
                    onsubmit="return confirm('Cancel the scheduled weekly documents? The currently published documents will stay live.');">
                    <?php wp_nonce_field('surfside_tools_weekly_cancel', 'surfside_tools_weekly_cancel_nonce'); ?>
                    <button type="submit" class="surfside-staff-button-secondary">Cancel Scheduled Update</button>
                </form>
            <?php else : ?>
                <p class="surfside-weekly-state-footnote">Upload and review either document below, then schedule it for Saturday. Current documents stay live until then.</p>
            <?php endif; ?>
        </section>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Only enqueue the polish stylesheet on the staff Weekly Update page.
 * Do this before wp_head rather than from shortcode rendering.
 */
function surfside_tools_weekly_workspace_enqueue_styles() {
    if (!is_user_logged_in() || !current_user_can('upload_files') || !is_page()) return;
    $post = get_queried_object();
    if (!($post instanceof WP_Post) ||
        trim((string) get_page_uri($post->ID), '/') !== 'dashboard/weekly-update') return;

    $relative = 'assets/css/weekly-workspace.css';
    $version = function_exists('surfside_tools_workspace_asset_version')
        ? surfside_tools_workspace_asset_version($relative) : SURFSIDE_TOOLS_VERSION;
    wp_enqueue_style('surfside-weekly-workspace', SURFSIDE_TOOLS_URL . $relative,
        array(), $version);
}
add_action('wp_enqueue_scripts', 'surfside_tools_weekly_workspace_enqueue_styles', 20);
