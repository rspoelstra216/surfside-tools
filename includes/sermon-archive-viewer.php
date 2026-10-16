<?php
/**
 * Read-only archive browser for the front-end Surfside staff dashboard.
 *
 * The archive table is created by sermon publishing, not by this viewer.
 * Browsing does not modify archived records, current notes, or backups.
 */
if (!defined('ABSPATH')) {
    exit;
}

function surfside_tools_sermon_archive_viewer_url($args = array()) {
    $base = surfside_tools_staff_page_url('');
    return add_query_arg(array_merge(array('view' => 'sermon-archive'), (array) $args), $base);
}

/**
 * Fetch a small page of lightweight archive metadata; long-form notes are loaded
 * only when a staff member opens one particular sermon.
 */
function surfside_tools_sermon_archive_viewer_search($search, $page, $page_size = 20) {
    global $wpdb;
    $table = surfside_tools_sermon_archive_table();
    $where = '';
    $params = array();

    if ($search !== '') {
        $like = '%' . $wpdb->esc_like($search) . '%';
        $where = ' WHERE (title LIKE %s OR series LIKE %s OR message_date LIKE %s OR notes LIKE %s)';
        $params = array($like, $like, $like, $like);
    }

    $count_sql = "SELECT COUNT(*) FROM {$table}{$where}";
    $count = (int) ($params
        ? $wpdb->get_var($wpdb->prepare($count_sql, $params))
        : $wpdb->get_var($count_sql));

    $page_count = max(1, (int) ceil($count / $page_size));
    $page = min(max(1, (int) $page), $page_count);
    $offset = ($page - 1) * $page_size;

    $list_sql = "SELECT id, title, series, message_date FROM {$table}{$where} ORDER BY id DESC LIMIT %d OFFSET %d";
    $rows = $wpdb->get_results($wpdb->prepare($list_sql, array_merge($params, array($page_size, $offset))), ARRAY_A);

    return array(
        'rows' => is_array($rows) ? $rows : array(),
        'total' => $count,
        'page' => $page,
        'pages' => $page_count,
    );
}

function surfside_tools_sermon_archive_viewer_record($id) {
    if (!$id) {
        return null;
    }

    global $wpdb;
    $table = surfside_tools_sermon_archive_table();
    $record = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT id, title, series, message_date, main_heading, notes, prayer FROM {$table} WHERE id = %d",
            $id
        ),
        ARRAY_A
    );

    return is_array($record) ? $record : null;
}

function surfside_tools_sermon_archive_viewer_styles() {
    wp_register_style('surfside-tools-sermon-archive-viewer', false, array(), SURFSIDE_TOOLS_VERSION);
    wp_enqueue_style('surfside-tools-sermon-archive-viewer');
    wp_add_inline_style('surfside-tools-sermon-archive-viewer', <<<'CSS'
.surfside-sermon-archive .surfside-staff-hero { margin-bottom: 24px; }
.surfside-sermon-archive-panel { background:#fff; border:1px solid rgba(7,27,58,.12); border-radius:16px; padding:clamp(18px,4vw,30px); box-shadow:0 8px 24px rgba(7,27,58,.055); margin-bottom:22px; }
.surfside-sermon-archive-search { display:flex; gap:10px; flex-wrap:wrap; align-items:end; }
.surfside-sermon-archive-search label { display:block; font-weight:700; color:#071b3a; margin-bottom:7px; }
.surfside-sermon-archive-search-field { flex:1 1 270px; min-width:0; }
.surfside-sermon-archive-search input { box-sizing:border-box; width:100%; min-height:46px; border:1px solid #aebaca; border-radius:8px; padding:10px 12px; font:inherit; }
.surfside-sermon-archive-search button { cursor:pointer; background:#0b4f9c; border:0; border-radius:8px; color:#fff; padding:12px 22px; min-height:46px; font:inherit; font-weight:700; }
.surfside-sermon-archive-clear { display:inline-flex; align-items:center; min-height:46px; color:#0b4f9c; font-weight:700; }
.surfside-sermon-archive-list { list-style:none; padding:0; margin:12px 0 0; }
.surfside-sermon-archive-list li { margin:0; border-top:1px solid #e4eaf2; }
.surfside-sermon-archive-list a { display:flex; align-items:center; justify-content:space-between; gap:14px; padding:17px 2px; text-decoration:none; color:#071b3a; }
.surfside-sermon-archive-list a:hover,.surfside-sermon-archive-list a:focus { background:#f4f8fe; }
.surfside-sermon-archive-list strong { display:block; font-size:18px; }
.surfside-sermon-archive-list small { display:block; color:#56657c; font-size:14px; margin-top:5px; }
.surfside-sermon-archive-arrow { font-size:25px; color:#0b4f9c; }
.surfside-sermon-archive-pagination { display:flex; align-items:center; justify-content:space-between; gap:10px; flex-wrap:wrap; margin-top:20px; }
.surfside-sermon-archive-pagination a,.surfside-sermon-archive-return { display:inline-block; color:#0b4f9c; text-decoration:none; font-weight:700; }
.surfside-sermon-archive-detail h2 { color:#071b3a; font-size:clamp(28px,4vw,38px); margin:8px 0; }
.surfside-sermon-archive-detail .surfside-sermon-archive-meta { color:#53647a; margin:0 0 26px; }
.surfside-sermon-archive-detail .surfside-message-notes { max-width:100%; }
.surfside-sermon-archive-detail .surfside-message-notes h2 { font-size:clamp(21px,3vw,27px); }
.surfside-sermon-archive-prayer { white-space:pre-wrap; }
.surfside-sermon-archive-empty { color:#51627a; padding:14px 0 0; }
@media (max-width:600px) {
 .surfside-sermon-archive-search button { width:100%; }
 .surfside-sermon-archive-list a { align-items:flex-start; }
}
CSS
    );
}

/** Staff-only, read-only viewer opened from dashboard/?view=sermon-archive. */
function surfside_tools_staff_sermon_archive_view() {
    if (function_exists('surfside_tools_prevent_cache')) {
        surfside_tools_prevent_cache();
    }
    if (function_exists('surfside_tools_staff_enqueue_styles')) {
        surfside_tools_staff_enqueue_styles();
    }
    if (!is_user_logged_in()) {
        return surfside_tools_staff_login_box('Please log in to view archived sermon notes.');
    }
    if (!current_user_can('upload_files')) {
        return '<div class="surfside-staff-shell"><p>You do not have permission to view archived sermon notes.</p></div>';
    }

    surfside_tools_sermon_archive_viewer_styles();

    $search_input = isset($_GET['archive_q']) && is_string($_GET['archive_q'])
        ? sanitize_text_field(wp_unslash($_GET['archive_q']))
        : '';
    $search = substr(trim($search_input), 0, 100);
    $requested_page = isset($_GET['archive_page']) ? absint($_GET['archive_page']) : 1;
    $requested_id = isset($_GET['archive_id']) ? absint($_GET['archive_id']) : 0;
    $query_args = $search !== '' ? array('archive_q' => $search) : array();
    $list_url = surfside_tools_sermon_archive_viewer_url($query_args);
    $archive_ready = (int) get_option(SURFSIDE_TOOLS_SERMON_ARCHIVE_SCHEMA_OPTION, 0)
        >= SURFSIDE_TOOLS_SERMON_ARCHIVE_SCHEMA_VERSION;

    $record = null;
    $list = null;
    if ($archive_ready) {
        if ($requested_id) {
            $record = surfside_tools_sermon_archive_viewer_record($requested_id);
        } else {
            $list = surfside_tools_sermon_archive_viewer_search($search, $requested_page);
        }
    }

    ob_start();
    ?>
    <div class="surfside-staff-shell surfside-sermon-archive">
        <div class="surfside-staff-back"><a href="<?php echo esc_url(surfside_tools_staff_page_url('')); ?>">← Back to Dashboard</a></div>
        <section class="surfside-staff-hero">
            <p class="surfside-staff-eyebrow">Weekly Update</p>
            <h1>Archived Sermon Notes</h1>
            <p class="surfside-staff-muted">Browse notes preserved during weekly publishing. Archived notes are read-only; publishing still happens through Weekly Update.</p>
        </section>

        <?php if (!$archive_ready) : ?>
            <section class="surfside-sermon-archive-panel">
                <p class="surfside-sermon-archive-empty">No sermon archive has been initialized yet. Notes will appear after the next successful sermon publication.</p>
            </section>
        <?php elseif ($requested_id) : ?>
            <section class="surfside-sermon-archive-panel">
                <a class="surfside-sermon-archive-return" href="<?php echo esc_url($list_url); ?>">← All Archived Notes</a>
                <?php if (!$record) : ?>
                    <p class="surfside-sermon-archive-empty">This archived sermon could not be found.</p>
                <?php else :
                    $main_heading = (string) ($record['main_heading'] ?? '');
                    $notes = (string) ($record['notes'] ?? '');
                    $notes_for_display = surfside_tools_notes_without_main_heading($notes, $main_heading);
                    ?>
                    <article class="surfside-sermon-archive-detail">
                        <h2><?php echo esc_html($record['title'] ?: 'Untitled Sermon'); ?></h2>
                        <p class="surfside-sermon-archive-meta">
                            <?php
                            echo esc_html(implode(' · ', array_filter(array(
                                (string) ($record['series'] ?? ''),
                                (string) ($record['message_date'] ?? ''),
                            ))));
                            ?>
                        </p>
                        <div class="surfside-message-notes">
                            <?php if ($main_heading !== '') : ?>
                                <h2 class="message-notes-main-heading"><?php echo esc_html($main_heading); ?></h2>
                            <?php endif; ?>
                            <?php if ($notes !== '') : ?>
                                <div class="message-notes"><?php echo wp_kses_post(surfside_tools_render_sermon_notes_html($notes_for_display)); ?></div>
                            <?php else : ?>
                                <p>No sermon notes were saved for this message.</p>
                            <?php endif; ?>
                            <?php if (!empty($record['prayer'])) : ?>
                                <h3>Closing Prayer</h3>
                                <p class="surfside-sermon-archive-prayer"><?php echo esc_html($record['prayer']); ?></p>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endif; ?>
            </section>
        <?php else : ?>
            <section class="surfside-sermon-archive-panel">
                <form class="surfside-sermon-archive-search" method="get" action="<?php echo esc_url(surfside_tools_staff_page_url('')); ?>">
                    <input type="hidden" name="view" value="sermon-archive">
                    <div class="surfside-sermon-archive-search-field">
                        <label for="surfside-archive-query">Search archived notes</label>
                        <input id="surfside-archive-query" type="search" name="archive_q"
                            value="<?php echo esc_attr($search); ?>"
                            placeholder="Title, series, date, or words in the notes">
                    </div>
                    <button type="submit">Search</button>
                    <?php if ($search !== '') : ?><a class="surfside-sermon-archive-clear" href="<?php echo esc_url(surfside_tools_sermon_archive_viewer_url()); ?>">Clear</a><?php endif; ?>
                </form>
            </section>
            <section class="surfside-sermon-archive-panel">
                <h2>Saved Messages</h2>
                <p class="surfside-staff-muted">
                    <?php echo esc_html(sprintf('%d archived message%s%s', $list['total'], $list['total'] === 1 ? '' : 's', $search !== '' ? ' matching your search' : '')); ?>
                </p>
                <?php if (empty($list['rows'])) : ?>
                    <p class="surfside-sermon-archive-empty"><?php echo $search !== '' ? 'No archived messages match that search.' : 'No sermon notes have been archived yet.'; ?></p>
                <?php else : ?>
                    <ul class="surfside-sermon-archive-list">
                        <?php foreach ($list['rows'] as $item) : ?>
                            <li><a href="<?php echo esc_url(surfside_tools_sermon_archive_viewer_url(array_merge($query_args, array('archive_id' => (int) $item['id'])))); ?>">
                                <span>
                                    <strong><?php echo esc_html($item['title'] ?: 'Untitled Sermon'); ?></strong>
                                    <small><?php echo esc_html(implode(' · ', array_filter(array((string) $item['series'], (string) $item['message_date'])))); ?></small>
                                </span>
                                <span class="surfside-sermon-archive-arrow" aria-hidden="true">›</span>
                            </a></li>
                        <?php endforeach; ?>
                    </ul>
                    <?php if ($list['pages'] > 1) : ?>
                        <nav class="surfside-sermon-archive-pagination" aria-label="Archive pages">
                            <span>
                                <?php if ($list['page'] > 1) : ?>
                                    <a href="<?php echo esc_url(surfside_tools_sermon_archive_viewer_url(array_merge($query_args, array('archive_page' => $list['page'] - 1)))); ?>">← Previous</a>
                                <?php endif; ?>
                            </span>
                            <span><?php echo esc_html(sprintf('Page %d of %d', $list['page'], $list['pages'])); ?></span>
                            <span>
                                <?php if ($list['page'] < $list['pages']) : ?>
                                    <a href="<?php echo esc_url(surfside_tools_sermon_archive_viewer_url(array_merge($query_args, array('archive_page' => $list['page'] + 1)))); ?>">Next →</a>
                                <?php endif; ?>
                            </span>
                        </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}
