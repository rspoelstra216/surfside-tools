<?php
/**
 * Public, read-only sermon archive sourced from synchronized YouTube metadata.
 *
 * This shortcode never calls YouTube's API, modifies review choices, or
 * downloads video/media. The public page is provisioned as a DRAFT so staff
 * can review it before adding navigation or publishing it.
 */
if (!defined('ABSPATH')) { exit; }

const SURFSIDE_PUBLIC_MESSAGES_PAGE_SCHEMA = 1;
const SURFSIDE_PUBLIC_MESSAGES_PAGE_OPTION = 'surfside_tools_public_messages_page_schema';
const SURFSIDE_PUBLIC_MESSAGES_PER_PAGE = 12;
const SURFSIDE_PUBLIC_SERIES_PER_PAGE = 18;

/**
 * Uses the same precedence as staff Message Archive Manager: manual date wins
 * over detected YouTube-title date and only explicit exclusion hides a video.
 * If staff have not created review records yet, omit the review-table JOIN.
 */
function surfside_tools_public_messages_sql() {
    $ready = function_exists('surfside_tools_youtube_review_is_ready')
        && surfside_tools_youtube_review_is_ready();
    // The new column is populated by the next full sync after migration.
    // Never substitute playlistItems.snippet.publishedAt (playlist-added time).
    $publication_ready = $ready && (int) get_option(SURFSIDE_YT_SCHEMA_OPTION, 0) >= 2;
    $sermon = $ready ? 'COALESCE(r.manual_date, v.sermon_date)' : 'v.sermon_date';
    $fallback = $publication_ready
        ? "CASE WHEN r.disposition = 'include' THEN v.published_date ELSE NULL END"
        : 'NULL';
    $date = $publication_ready ? "COALESCE({$sermon}, {$fallback})" : $sermon;
    $source = $publication_ready
        ? "CASE WHEN {$sermon} IS NOT NULL THEN 'sermon'
                WHEN r.disposition = 'include' AND v.published_date IS NOT NULL THEN 'youtube'
                ELSE 'unknown' END"
        : "CASE WHEN {$sermon} IS NOT NULL THEN 'sermon' ELSE 'unknown' END";
    return array(
        'join' => $ready ? ' LEFT JOIN ' . surfside_tools_youtube_review_table() . ' r ON r.video_id = v.video_id' : '',
        'sermon_date' => $sermon,
        'date' => $date,
        'source' => $source,
        'visible' => $ready ? "(r.disposition IS NULL OR r.disposition <> 'exclude')" : '(1=1)',
    );
}

function surfside_tools_public_messages_master_id() {
    if ((int) get_option(SURFSIDE_YT_SCHEMA_OPTION, 0) < SURFSIDE_YT_SCHEMA_VERSION) return '';
    $state = surfside_tools_youtube_state();
    $master = isset($state['master_id']) ? (string) $state['master_id'] : '';
    return surfside_tools_youtube_valid_playlist_id($master) ? $master : '';
}

/**
 * Public URLs deliberately use sm_* names instead of WordPress query vars.
 * WordPress interprets ?year= (and some taxonomy query vars) as post archives,
 * which can replace the Messages page with "No posts were found" before this
 * shortcode runs. Keep every archive control in our own namespace.
 */
/** Sanitize and bound every public query parameter. */
function surfside_tools_public_messages_filters($source = null) {
    $source = is_array($source) ? $source : $_GET;
    $search = isset($source['sm_search']) && is_string($source['sm_search'])
        ? sanitize_text_field(wp_unslash($source['sm_search'])) : '';
    $year = isset($source['sm_year']) && is_string($source['sm_year'])
        ? trim(wp_unslash($source['sm_year'])) : '';
    $series = isset($source['sm_series']) && is_string($source['sm_series'])
        ? trim(wp_unslash($source['sm_series'])) : '';
    $video = isset($source['sm_video']) && is_string($source['sm_video'])
        ? trim(wp_unslash($source['sm_video'])) : '';
    $browse = isset($source['sm_view']) && is_string($source['sm_view'])
        ? sanitize_key(wp_unslash($source['sm_view'])) : '';
    return array(
        'q' => substr(trim($search), 0, 100),
        'year' => preg_match('/^(19|20)\d{2}$/', $year) ? $year : '',
        'series' => surfside_tools_youtube_valid_playlist_id($series) ? $series : '',
        'message' => preg_match('/^[A-Za-z0-9_-]{11}$/', $video) ? $video : '',
        'browse' => $browse === 'series' ? 'series' : 'messages',
        'page' => isset($source['sm_page']) ? max(1, min(100000, absint($source['sm_page']))) : 1,
    );
}

function surfside_tools_public_messages_page_url($args = array()) {
    $page_id = get_queried_object_id();
    $permalink = $page_id ? get_permalink($page_id) : '';
    $base = is_string($permalink) && $permalink !== '' ? $permalink : home_url('/messages/');
    // Draft previews must keep WordPress's preview URL; otherwise clicking a
    // sermon or series would navigate to the unpublished page and return 404.
    if ($page_id && get_post_status($page_id) !== 'publish' && current_user_can('edit_post', $page_id)) {
        $post = get_post($page_id);
        if ($post instanceof WP_Post) {
            $preview = get_preview_post_link($post);
            if (is_string($preview) && $preview !== '') $base = $preview;
        }
    }
    return $args ? add_query_arg($args, $base) : $base;
}

/** One row per master-playlist video, regardless of number of series. */
function surfside_tools_public_messages_list($master, $filters) {
    global $wpdb;
    $tables = surfside_tools_youtube_tables();
    $sql = surfside_tools_public_messages_sql();
    $where = array('master.playlist_id = %s', $sql['visible']);
    $params = array($master);

    if ($filters['q'] !== '') {
        $like = '%' . $wpdb->esc_like($filters['q']) . '%';
        $where[] = "(v.title LIKE %s OR EXISTS (
            SELECT 1 FROM {$tables['members']} series_m
            INNER JOIN {$tables['playlists']} series_p ON series_p.playlist_id = series_m.playlist_id
            WHERE series_m.video_id = v.video_id AND series_p.category = 'series' AND series_p.title LIKE %s
        ))";
        $params[] = $like;
        $params[] = $like;
    }
    if ($filters['year'] !== '') {
        $where[] = "YEAR({$sql['date']}) = %d";
        $params[] = (int) $filters['year'];
    }
    if ($filters['series'] !== '') {
        $where[] = "EXISTS (
            SELECT 1 FROM {$tables['members']} selected_m
            WHERE selected_m.video_id = v.video_id AND selected_m.playlist_id = %s
        )";
        $params[] = $filters['series'];
    }
    $from = " FROM {$tables['videos']} v
        INNER JOIN {$tables['members']} master ON master.video_id = v.video_id
        {$sql['join']} WHERE " . implode(' AND ', $where);
    $count = (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*)' . $from, $params));
    $pages = max(1, (int) ceil($count / SURFSIDE_PUBLIC_MESSAGES_PER_PAGE));
    $page = min($pages, $filters['page']);
    $offset = ($page - 1) * SURFSIDE_PUBLIC_MESSAGES_PER_PAGE;
    $query = "SELECT v.video_id, v.title, v.thumbnail_url,
        {$sql['date']} AS message_date, {$sql['source']} AS date_source"
        . $from . " ORDER BY ({$sql['date']} IS NULL), {$sql['date']} DESC,
        v.uploaded_at DESC, v.video_id ASC LIMIT %d OFFSET %d";
    $rows = $wpdb->get_results($wpdb->prepare(
        $query, array_merge($params, array(SURFSIDE_PUBLIC_MESSAGES_PER_PAGE, $offset))
    ), ARRAY_A);
    return array('total' => $count, 'pages' => $pages, 'page' => $page,
        'rows' => is_array($rows) ? $rows : array());
}

/** Detail access is filtered independently; excluded videos cannot bypass the list. */
function surfside_tools_public_messages_detail($master, $id) {
    if (!preg_match('/^[A-Za-z0-9_-]{11}$/', (string) $id)) return null;
    global $wpdb;
    $t = surfside_tools_youtube_tables();
    $sql = surfside_tools_public_messages_sql();
    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT v.video_id, v.title, v.thumbnail_url,
                {$sql['date']} AS message_date, {$sql['source']} AS date_source
         FROM {$t['videos']} v
         INNER JOIN {$t['members']} m ON m.video_id = v.video_id
         {$sql['join']}
         WHERE m.playlist_id = %s AND v.video_id = %s AND {$sql['visible']} LIMIT 1",
        $master, $id
    ), ARRAY_A);
    return is_array($row) ? $row : null;
}

/**
 * Series discovery is a tiny, bounded local query (up to 500 rows).
 * Excluded series are omitted, as are series with no publicly visible
 * sermons. Series hiding never hides the same sermon from All Messages.
 */
function surfside_tools_public_messages_series($master) {
    global $wpdb;
    $t = surfside_tools_youtube_tables();
    $sql = surfside_tools_public_messages_sql();
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT p.playlist_id, p.title, COUNT(DISTINCT v.video_id) AS message_count,
                MAX({$sql['date']}) AS newest_date,
                MAX(v.thumbnail_url) AS sample_thumbnail
         FROM {$t['playlists']} p
         INNER JOIN {$t['members']} series_m ON series_m.playlist_id = p.playlist_id
         INNER JOIN {$t['members']} master_m ON master_m.video_id = series_m.video_id
              AND master_m.playlist_id = %s
         INNER JOIN {$t['videos']} v ON v.video_id = series_m.video_id
         {$sql['join']}
         WHERE p.category = 'series' AND {$sql['visible']}
         GROUP BY p.playlist_id, p.title
         ORDER BY newest_date DESC, p.title ASC
         LIMIT 500", $master
    ), ARRAY_A);
    $hidden = function_exists('surfside_tools_youtube_review_excluded_series')
        ? surfside_tools_youtube_review_excluded_series() : array();
    if (!is_array($rows)) return array();
    return array_values(array_filter($rows, function($row) use ($hidden) {
        return !empty($row['message_count']) && empty($hidden[$row['playlist_id']]);
    }));
}

/**
 * The featured message should be the newest known SERMON, never an undated
 * video that was uploaded after it. Publication dates only organize fallback
 * video listings, not the church's "Latest message" claim.
 */
function surfside_tools_public_messages_latest($master) {
    global $wpdb;
    $t = surfside_tools_youtube_tables();
    $sql = surfside_tools_public_messages_sql();
    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT v.video_id, v.title, v.thumbnail_url,
                {$sql['sermon_date']} AS message_date, 'sermon' AS date_source
         FROM {$t['videos']} v
         INNER JOIN {$t['members']} m ON m.video_id = v.video_id
         {$sql['join']}
         WHERE m.playlist_id = %s AND {$sql['visible']} AND {$sql['sermon_date']} IS NOT NULL
         ORDER BY {$sql['sermon_date']} DESC, v.video_id ASC LIMIT 1", $master
    ), ARRAY_A);
    return is_array($row) ? $row : null;
}

/** Only list years that contain at least one publicly visible, dated video. */
function surfside_tools_public_messages_years($master) {
    global $wpdb;
    $t = surfside_tools_youtube_tables();
    $sql = surfside_tools_public_messages_sql();
    $values = $wpdb->get_col($wpdb->prepare(
        "SELECT DISTINCT YEAR({$sql['date']}) AS display_year
         FROM {$t['videos']} v
         INNER JOIN {$t['members']} m ON m.video_id = v.video_id
         {$sql['join']}
         WHERE m.playlist_id = %s AND {$sql['visible']} AND {$sql['date']} IS NOT NULL
         ORDER BY display_year DESC LIMIT 150", $master
    ));
    if (!is_array($values)) return array();
    $years = array();
    foreach ($values as $value) {
        $year = (int) $value;
        if ($year >= 1900 && $year <= 2100) $years[$year] = $year;
    }
    rsort($years, SORT_NUMERIC);
    return array_values($years);
}

function surfside_tools_public_messages_video_series($master, $id, $series_rows) {
    global $wpdb;
    $t = surfside_tools_youtube_tables();
    $titles = $wpdb->get_results($wpdb->prepare(
        "SELECT p.playlist_id, p.title
         FROM {$t['playlists']} p
         INNER JOIN {$t['members']} m ON m.playlist_id = p.playlist_id
         WHERE m.video_id = %s AND p.category = 'series'
         ORDER BY p.title ASC LIMIT 50", $id
    ), ARRAY_A);
    $allowed = array_fill_keys(array_column($series_rows, 'playlist_id'), true);
    if (!is_array($titles)) return array();
    return array_values(array_filter($titles, function($row) use ($allowed) {
        return !empty($allowed[$row['playlist_id']]);
    }));
}

/**
 * Do not present a video's YouTube publication date as the sermon date.
 * It is a fallback ONLY for videos staff explicitly included.
 */
function surfside_tools_public_messages_date_label($value, $source = 'sermon') {
    if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return 'Date not available';
    }
    $time = strtotime($value . ' 12:00:00 UTC');
    if (!$time) return 'Date not available';
    $label = wp_date('F j, Y', $time, new DateTimeZone('UTC'));
    return $source === 'youtube' ? 'Published on YouTube: ' . $label : $label;
}

/**
 * The YouTube title is preserved in the library and message detail.
 * Only the prominent landing-page hero may shorten the predictable format:
 * "Message Title – Series – Week N – DD Month YYYY".
 * Other titles, including those containing meaningful dashes, are unchanged.
 */
function surfside_tools_public_messages_featured_heading($title) {
    $original = trim((string) $title);
    $result = array('heading' => $original, 'context' => '');
    $parts = preg_split('/\s+[–—]\s+|\s+-\s+/u', $original);
    if (!is_array($parts) || count($parts) < 3
        || !function_exists('surfside_tools_youtube_sermon_date')) {
        return $result;
    }

    $date_part = trim($parts[count($parts) - 1]);
    $week_part = trim($parts[count($parts) - 2]);
    if (surfside_tools_youtube_sermon_date($date_part) === ''
        || !preg_match('/^week\s+\d+(?:\s+of\s+\d+)?$/i', $week_part)) {
        return $result;
    }
    $heading = trim($parts[0]);
    $context_parts = array_map('trim', array_slice($parts, 1, -1));
    $context_parts = array_filter($context_parts, static function ($part) { return $part !== ''; });
    if ($heading === '' || !$context_parts) return $result;
    return array('heading' => $heading, 'context' => implode(' · ', $context_parts));
}

function surfside_tools_public_messages_args($filters) {
    $args = array();
    if ($filters['q'] !== '') $args['sm_search'] = $filters['q'];
    if ($filters['year'] !== '') $args['sm_year'] = $filters['year'];
    if ($filters['series'] !== '') $args['sm_series'] = $filters['series'];
    if ($filters['browse'] === 'series') $args['sm_view'] = 'series';
    if ($filters['page'] > 1) $args['sm_page'] = $filters['page'];
    return $args;
}

/** Existing metadata and staff decisions, no upstream API work or writes. */
function surfside_tools_public_message_archive_shortcode() {
    $master = surfside_tools_public_messages_master_id();
    $filters = surfside_tools_public_messages_filters();
    $args = surfside_tools_public_messages_args($filters);
    $series_rows = $master !== '' ? surfside_tools_public_messages_series($master) : array();
    $allowed_series = array_fill_keys(array_column($series_rows, 'playlist_id'), true);
    $invalid_series = $filters['series'] !== '' && empty($allowed_series[$filters['series']]);

    $detail = null;
    $list = null;
    if ($master !== '' && !$invalid_series && $filters['browse'] !== 'series' && $filters['message'] !== '') {
        $detail = surfside_tools_public_messages_detail($master, $filters['message']);
    } elseif ($master !== '' && !$invalid_series && $filters['browse'] !== 'series') {
        $list = surfside_tools_public_messages_list($master, $filters);
    }
    $is_default = $filters['q'] === '' && $filters['year'] === ''
        && $filters['series'] === '' && $filters['page'] === 1
        && $filters['browse'] === 'messages';
    $list_url = surfside_tools_public_messages_page_url(array_diff_key($args, array('sm_view' => true)));
    $series_url = surfside_tools_public_messages_page_url(array('sm_view' => 'series'));
    $featured = $list !== null && $is_default
        ? surfside_tools_public_messages_latest($master) : null;
    $featured_heading = $featured
        ? surfside_tools_public_messages_featured_heading($featured['title']) : null;
    $years = $list !== null && !$invalid_series
        ? surfside_tools_public_messages_years($master) : array();
    ob_start();
    ?>
    <section class="surfside-public-messages" aria-labelledby="surfside-public-messages-title">
        <header class="surfside-public-messages__intro">
            <p class="surfside-public-messages__eyebrow">Surfside Community Fellowship</p>
            <h1 id="surfside-public-messages-title">Messages &amp; Sermons</h1>
            <p>Explore biblical teaching from Surfside—watch a recent message, find a sermon series, or search the full archive.</p>
        </header>

        <?php if ($master === '') : ?>
            <div class="surfside-public-messages__empty">
                <h2>Messages are coming soon</h2>
                <p>We're preparing our sermon archive. In the meantime, you can watch messages on <a href="https://www.youtube.com/@addpastor" target="_blank" rel="noopener noreferrer">our YouTube channel</a>.</p>
            </div>
        <?php else : ?>
            <nav class="surfside-public-messages__tabs" aria-label="Explore messages">
                <a <?php echo $filters['browse'] === 'messages' ? 'aria-current="page"' : ''; ?> href="<?php echo esc_url(surfside_tools_public_messages_page_url()); ?>">All Messages</a>
                <a <?php echo $filters['browse'] === 'series' ? 'aria-current="page"' : ''; ?> href="<?php echo esc_url($series_url); ?>">Browse by Series</a>
            </nav>

            <?php if ($filters['browse'] === 'series') :
                $series_page_count = max(1, (int) ceil(count($series_rows) / SURFSIDE_PUBLIC_SERIES_PER_PAGE));
                $series_page = min($series_page_count, $filters['page']);
                $visible_series = array_slice($series_rows,
                    ($series_page - 1) * SURFSIDE_PUBLIC_SERIES_PER_PAGE, SURFSIDE_PUBLIC_SERIES_PER_PAGE);
                ?>
                <section class="surfside-public-messages__section" aria-labelledby="surfside-public-messages-series-heading">
                    <div class="surfside-public-messages__section-heading">
                        <div>
                            <p class="surfside-public-messages__eyebrow">Discover</p>
                            <h2 id="surfside-public-messages-series-heading">Sermon Series</h2>
                        </div>
                        <span><?php echo esc_html(number_format_i18n(count($series_rows))); ?> series</span>
                    </div>
                    <?php if (!$visible_series) : ?>
                        <p>No sermon series are available yet.</p>
                    <?php else : ?>
                        <div class="surfside-public-messages__grid surfside-public-messages__grid--series">
                            <?php foreach ($visible_series as $series) :
                                $url = surfside_tools_public_messages_page_url(array('sm_series' => $series['playlist_id']));
                                ?>
                                <a class="surfside-public-messages__series" href="<?php echo esc_url($url); ?>">
                                    <?php if (!empty($series['sample_thumbnail'])) : ?>
                                        <img loading="lazy" src="<?php echo esc_url($series['sample_thumbnail']); ?>" alt="">
                                    <?php else : ?><span class="surfside-public-messages__thumb-placeholder" aria-hidden="true">▶</span><?php endif; ?>
                                    <span class="surfside-public-messages__series-info">
                                        <strong><?php echo esc_html($series['title']); ?></strong>
                                        <small><?php echo esc_html(number_format_i18n((int) $series['message_count'])); ?> message<?php echo (int) $series['message_count'] === 1 ? '' : 's'; ?></small>
                                    </span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                        <?php surfside_tools_public_messages_pagination($series_page, $series_page_count,
                            array('sm_view' => 'series')); ?>
                    <?php endif; ?>
                </section>
            <?php elseif ($filters['message'] !== '') : ?>
                <article class="surfside-public-messages__detail">
                    <p><a class="surfside-public-messages__back" href="<?php echo esc_url($list_url); ?>">← Back to messages</a></p>
                    <?php if (!$detail || $invalid_series) : ?>
                        <h2>Message unavailable</h2>
                        <p>This message isn't available in the public archive.</p>
                    <?php else :
                        $watch_url = 'https://www.youtube.com/watch?v=' . rawurlencode($detail['video_id']);
                        $related_series = surfside_tools_public_messages_video_series($master, $detail['video_id'], $series_rows);
                        ?>
                        <p class="surfside-public-messages__eyebrow">Watch a message</p>
                        <h2><?php echo esc_html($detail['title']); ?></h2>
                        <p class="surfside-public-messages__date"><?php echo esc_html(surfside_tools_public_messages_date_label($detail['message_date'], $detail['date_source'] ?? 'sermon')); ?></p>
                        <div class="surfside-public-messages__player">
                            <iframe loading="lazy" title="<?php echo esc_attr('Watch ' . $detail['title']); ?>"
                                src="<?php echo esc_url('https://www.youtube-nocookie.com/embed/' . $detail['video_id']); ?>"
                                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                                referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>
                        </div>
                        <p><a class="surfside-public-messages__button surfside-public-messages__button--outline"
                            href="<?php echo esc_url($watch_url); ?>" rel="noopener noreferrer" target="_blank">Watch on YouTube ↗</a></p>
                        <?php if ($related_series) : ?>
                            <div class="surfside-public-messages__related">
                                <h3>Part of a series</h3>
                                <?php foreach ($related_series as $related) : ?>
                                    <a class="surfside-public-messages__pill" href="<?php echo esc_url(
                                        surfside_tools_public_messages_page_url(array('sm_series'=>$related['playlist_id']))
                                    ); ?>"><?php echo esc_html($related['title']); ?> →</a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </article>
            <?php else : ?>
                <?php if ($featured) : ?>
                    <section class="surfside-public-messages__featured" aria-label="Latest message">
                        <div class="surfside-public-messages__featured-copy">
                            <p class="surfside-public-messages__eyebrow">Latest message</p>
                            <h2 title="<?php echo esc_attr($featured['title']); ?>"><?php echo esc_html($featured_heading['heading']); ?></h2>
                            <?php if ($featured_heading['context'] !== '') : ?>
                                <p class="surfside-public-messages__featured-context"><?php echo esc_html($featured_heading['context']); ?></p>
                            <?php endif; ?>
                            <p class="surfside-public-messages__featured-date"><?php echo esc_html(surfside_tools_public_messages_date_label($featured['message_date'], $featured['date_source'] ?? 'sermon')); ?></p>
                            <a class="surfside-public-messages__button" href="<?php echo esc_url(
                                surfside_tools_public_messages_page_url(array('sm_video' => $featured['video_id']))
                            ); ?>">Watch Latest Message <span aria-hidden="true">→</span></a>
                        </div>
                        <a class="surfside-public-messages__featured-art" href="<?php echo esc_url(
                            surfside_tools_public_messages_page_url(array('sm_video' => $featured['video_id']))
                        ); ?>" aria-label="<?php echo esc_attr('Watch ' . $featured['title']); ?>">
                            <?php if ($featured['thumbnail_url']) : ?>
                                <img src="<?php echo esc_url($featured['thumbnail_url']); ?>" alt="">
                            <?php else : ?><span aria-hidden="true">▶</span><?php endif; ?>
                            <span class="surfside-public-messages__play" aria-hidden="true">▶</span>
                        </a>
                    </section>
                <?php endif; ?>

                <?php if ($is_default && $series_rows) : ?>
                    <section class="surfside-public-messages__section" aria-labelledby="surfside-series-preview">
                        <div class="surfside-public-messages__section-heading">
                            <h2 id="surfside-series-preview">Explore by Series</h2>
                            <a href="<?php echo esc_url($series_url); ?>">View all series →</a>
                        </div>
                        <div class="surfside-public-messages__grid surfside-public-messages__grid--series surfside-public-messages__grid--series-preview">
                            <?php foreach (array_slice($series_rows, 0, 3) as $s) : ?>
                                <a class="surfside-public-messages__series" href="<?php echo esc_url(
                                    surfside_tools_public_messages_page_url(array('sm_series' => $s['playlist_id']))
                                ); ?>">
                                    <?php if (!empty($s['sample_thumbnail'])) : ?>
                                        <img loading="lazy" src="<?php echo esc_url($s['sample_thumbnail']); ?>" alt="">
                                    <?php else : ?>
                                        <span class="surfside-public-messages__thumb-placeholder" aria-hidden="true">▶</span>
                                    <?php endif; ?>
                                    <span class="surfside-public-messages__series-info">
                                        <strong><?php echo esc_html($s['title']); ?></strong>
                                        <small><?php echo esc_html(number_format_i18n((int) $s['message_count'])); ?> message<?php echo (int) $s['message_count'] === 1 ? '' : 's'; ?></small>
                                    </span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endif; ?>

                <section class="surfside-public-messages__section" aria-labelledby="surfside-message-list-title">
                    <div class="surfside-public-messages__section-heading">
                        <div>
                            <p class="surfside-public-messages__eyebrow">The archive</p>
                            <h2 id="surfside-message-list-title">All Messages</h2>
                        </div>
                    </div>
                    <form method="get" action="<?php echo esc_url(surfside_tools_public_messages_page_url()); ?>"
                        class="surfside-public-messages__filters" role="search">
                        <div class="surfside-public-messages__filter-search">
                            <label for="surfside-message-search">Search messages or series</label>
                            <input id="surfside-message-search" name="sm_search" type="search"
                                value="<?php echo esc_attr($filters['q']); ?>" placeholder="Search the archive">
                        </div>
                        <div>
                            <label for="surfside-message-year">Year</label>
                            <select id="surfside-message-year" name="sm_year">
                                <option value="">All years</option>
                                <?php foreach ($years as $yr) : ?>
                                    <option value="<?php echo esc_attr((string) $yr); ?>" <?php selected($filters['year'], (string) $yr); ?>>
                                        <?php echo esc_html((string) $yr); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="surfside-message-series">Series</label>
                            <select id="surfside-message-series" name="sm_series">
                                <option value="">All series</option>
                                <?php foreach ($series_rows as $s) : ?>
                                    <option value="<?php echo esc_attr($s['playlist_id']); ?>" <?php selected($filters['series'], $s['playlist_id']); ?>>
                                        <?php echo esc_html($s['title']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div><button type="submit">Search</button></div>
                    </form>
                    <?php if ($filters['q'] !== '' || $filters['year'] !== '' || $filters['series'] !== '') : ?>
                        <p class="surfside-public-messages__filter-reset"><a href="<?php echo esc_url(
                            surfside_tools_public_messages_page_url()
                        ); ?>">Clear filters</a></p>
                    <?php endif; ?>
                    <p class="surfside-public-messages__count">
                        <?php echo esc_html(number_format_i18n($invalid_series ? 0 : $list['total'])); ?> messages found
                    </p>
                    <?php if ($invalid_series || !$list || !$list['rows']) : ?>
                        <div class="surfside-public-messages__empty">No messages match your search. Try a different term or filter.</div>
                    <?php else : ?>
                        <div class="surfside-public-messages__grid surfside-public-messages__grid--videos">
                            <?php foreach ($list['rows'] as $row) :
                                if ($featured && $row['video_id'] === $featured['video_id']) continue;
                                // Keep the reader's search, series, year, and page when
                                // opening a message so Back returns to the same results.
                                $detail_args = $args;
                                $detail_args['sm_video'] = $row['video_id'];
                                ?>
                                <a class="surfside-public-messages__video" href="<?php echo esc_url(
                                    surfside_tools_public_messages_page_url($detail_args)
                                ); ?>">
                                    <span class="surfside-public-messages__video-art">
                                        <?php if ($row['thumbnail_url']) : ?>
                                            <img loading="lazy" alt="" src="<?php echo esc_url($row['thumbnail_url']); ?>">
                                        <?php else : ?><span class="surfside-public-messages__thumb-placeholder" aria-hidden="true">▶</span><?php endif; ?>
                                        <span class="surfside-public-messages__play" aria-hidden="true">▶</span>
                                    </span>
                                    <span class="surfside-public-messages__video-info">
                                        <strong><?php echo esc_html($row['title']); ?></strong>
                                        <small><?php echo esc_html(surfside_tools_public_messages_date_label($row['message_date'], $row['date_source'] ?? 'sermon')); ?></small>
                                    </span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                        <?php surfside_tools_public_messages_pagination($list['page'], $list['pages'], $args); ?>
                    <?php endif; ?>
                </section>
            <?php endif; ?>
        <?php endif; ?>
    </section>
    <?php
    surfside_tools_public_messages_styles();
    return ob_get_clean();
}

function surfside_tools_public_messages_pagination($page, $pages, $args) {
    if ($pages < 2) return;
    ?>
    <nav class="surfside-public-messages__pagination" aria-label="Archive pages">
        <?php if ($page > 1) :
            $previous = $args;
            $previous['sm_page'] = $page - 1;
            ?>
            <a href="<?php echo esc_url(surfside_tools_public_messages_page_url($previous)); ?>">← Previous</a>
        <?php else : ?><span></span><?php endif; ?>
        <span>Page <?php echo esc_html(number_format_i18n($page)); ?> of <?php echo esc_html(number_format_i18n($pages)); ?></span>
        <?php if ($page < $pages) :
            $next = $args;
            $next['sm_page'] = $page + 1;
            ?>
            <a href="<?php echo esc_url(surfside_tools_public_messages_page_url($next)); ?>">Next →</a>
        <?php else : ?><span></span><?php endif; ?>
    </nav>
    <?php
}

/**
 * Scoped styles are printed alongside the shortcode so they work on preview
 * pages too, without a global stylesheet or any additional visitor-wide hook.
 */
function surfside_tools_public_messages_styles() {
    ?>
    <style id="surfside-public-messages-styles">
    .surfside-public-messages{--s-blue:#0d447c;--s-navy:#0b2546;--s-muted:#526579;--s-border:#dce6ef;max-width:1200px;margin:0 auto;padding:clamp(18px,4vw,48px);color:var(--s-navy);box-sizing:border-box;font-family:inherit}
    .surfside-public-messages *{box-sizing:border-box}
    .surfside-public-messages h1,.surfside-public-messages h2,.surfside-public-messages h3,.surfside-public-messages p{overflow-wrap:anywhere}
    .surfside-public-messages__intro{text-align:center;max-width:790px;margin:0 auto 28px}
    .surfside-public-messages__eyebrow{font-size:12px!important;font-weight:800!important;color:#176ca1!important;letter-spacing:.11em;text-transform:uppercase;margin:0 0 9px!important}
    .surfside-public-messages__intro h1{font-size:clamp(36px,5.2vw,64px)!important;line-height:1.08!important;color:var(--s-navy)!important;letter-spacing:-.03em;margin:0 0 15px!important}
    .surfside-public-messages__intro>p:last-child{font-size:clamp(16px,1.6vw,20px);line-height:1.6;color:var(--s-muted);margin:0}
    .surfside-public-messages__tabs{display:flex;justify-content:center;flex-wrap:wrap;gap:8px;margin:0 0 30px}
    .surfside-public-messages__tabs a{padding:11px 19px;text-decoration:none!important;color:var(--s-blue)!important;background:#f1f6fa;border-radius:999px;border:1px solid var(--s-border);font-weight:750}
    .surfside-public-messages__tabs a[aria-current="page"]{background:var(--s-blue);color:#fff!important;border-color:var(--s-blue)}
    .surfside-public-messages__featured{display:grid;grid-template-columns:minmax(0,.92fr) minmax(0,1.08fr);align-items:center;background:#eef5fa;border-radius:20px;overflow:hidden;margin:10px 0 36px;border:1px solid var(--s-border)}
    .surfside-public-messages__featured-copy{min-width:0;padding:clamp(24px,3.2vw,40px);display:flex;flex-direction:column;justify-content:center;align-items:flex-start}
    .surfside-public-messages__featured-copy h2{font-size:clamp(26px,2.8vw,38px)!important;line-height:1.16!important;letter-spacing:-.018em;margin:3px 0 12px!important;color:var(--s-navy)!important}
    .surfside-public-messages__featured-copy .surfside-public-messages__featured-context{font-size:14px;line-height:1.45;color:#455c75;margin:0 0 12px}
    .surfside-public-messages__featured-copy .surfside-public-messages__featured-date{font-size:14px;color:var(--s-muted);margin:0 0 22px}
    .surfside-public-messages__button{display:inline-flex;align-items:center;gap:8px;padding:13px 19px;background:var(--s-blue);color:#fff!important;text-decoration:none!important;border-radius:9px;font-weight:750;border:1px solid var(--s-blue)}
    .surfside-public-messages__button:hover,.surfside-public-messages__button:focus{background:#07325d;color:#fff!important}
    .surfside-public-messages__button--outline{background:#fff;color:var(--s-blue)!important}
    .surfside-public-messages__featured-art,.surfside-public-messages__video-art{display:block;position:relative;aspect-ratio:16/9;overflow:hidden;background:#112b4a}
    .surfside-public-messages__featured-art{width:100%;height:auto;min-height:0;aspect-ratio:16/9;align-self:center}
    .surfside-public-messages__featured-art img,.surfside-public-messages__video-art img,.surfside-public-messages__series>img{width:100%;height:100%;object-fit:cover;display:block}
    .surfside-public-messages__featured-art img{object-fit:contain}
    .surfside-public-messages__play{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);border-radius:999px;background:rgba(4,28,55,.8);color:#fff;font-size:20px;display:grid;place-items:center;width:54px;height:54px;border:1px solid rgba(255,255,255,.65)}
    .surfside-public-messages__section{margin:24px 0 40px}
    .surfside-public-messages__section-heading{display:flex;flex-wrap:wrap;justify-content:space-between;align-items:end;gap:14px;margin:0 0 18px}
    .surfside-public-messages__section-heading h2{font-size:clamp(25px,2.5vw,34px)!important;margin:0!important;color:var(--s-navy)!important;line-height:1.2}
    .surfside-public-messages__section-heading>a,.surfside-public-messages__filter-reset a,.surfside-public-messages__back{color:var(--s-blue)!important;font-weight:750;text-decoration:none}
    .surfside-public-messages__pill{display:inline-flex;align-items:center;gap:6px;padding:11px 14px;border-radius:9px;background:#f2f7fb;border:1px solid var(--s-border);color:var(--s-blue)!important;text-decoration:none!important;font-weight:700}
    .surfside-public-messages__pill:hover{background:#e3f0fb}
    .surfside-public-messages__filters{display:grid;grid-template-columns:minmax(220px,2fr) minmax(105px,.7fr) minmax(155px,1.2fr) auto;gap:12px;align-items:end;background:#f5f8fb;border:1px solid var(--s-border);padding:18px;border-radius:14px}
    .surfside-public-messages__filters label{display:block;font-size:14px;font-weight:750;margin:0 0 7px;color:var(--s-navy)}
    .surfside-public-messages__filters input,.surfside-public-messages__filters select{width:100%;max-width:100%;height:45px;border:1px solid #b7c9d9;background:#fff;border-radius:8px;padding:9px 11px;font:inherit;color:var(--s-navy)}
    .surfside-public-messages__filters button{height:45px;background:var(--s-blue);color:#fff;border:0;border-radius:8px;padding:0 22px;font:inherit;font-weight:750;cursor:pointer}
    .surfside-public-messages__filter-reset{margin:10px 0 0}
    .surfside-public-messages__count{font-size:14px;color:var(--s-muted);margin:18px 0}
    .surfside-public-messages__grid{display:grid;gap:20px}
    .surfside-public-messages__grid--videos{grid-template-columns:repeat(3,minmax(0,1fr))}
    .surfside-public-messages__grid--series{grid-template-columns:repeat(3,minmax(0,1fr))}
    .surfside-public-messages__video,.surfside-public-messages__series{display:block;border-radius:14px;overflow:hidden;border:1px solid var(--s-border);text-decoration:none!important;color:var(--s-navy)!important;background:#fff;box-shadow:0 5px 18px rgba(12,35,60,.045);transition:box-shadow .2s,transform .2s}
    .surfside-public-messages__video:hover,.surfside-public-messages__series:hover{transform:translateY(-2px);box-shadow:0 10px 24px rgba(12,35,60,.12)}
    .surfside-public-messages__video-art{aspect-ratio:16/9}
    .surfside-public-messages__video-info,.surfside-public-messages__series-info{display:block;padding:15px 16px}
    .surfside-public-messages__video-info strong,.surfside-public-messages__series-info strong{display:block;font-size:16px;line-height:1.45;color:var(--s-navy)}
    .surfside-public-messages__video-info small,.surfside-public-messages__series-info small{display:block;color:var(--s-muted);font-size:13px;margin-top:8px}
    .surfside-public-messages__series>img,.surfside-public-messages__series>.surfside-public-messages__thumb-placeholder{display:block;aspect-ratio:16/9;height:auto;object-fit:cover}
    .surfside-public-messages__series>img{width:100%}
    .surfside-public-messages__thumb-placeholder{display:grid;place-items:center;background:#e6eef6;color:var(--s-blue);font-size:32px}
    .surfside-public-messages__detail{max-width:940px;margin:18px auto 50px}
    .surfside-public-messages__detail h2{font-size:clamp(28px,3.3vw,44px)!important;line-height:1.15!important;color:var(--s-navy)!important;margin:10px 0 12px!important}
    .surfside-public-messages__date{color:var(--s-muted);margin-bottom:20px}
    .surfside-public-messages__player{position:relative;width:100%;aspect-ratio:16/9;margin:22px 0;border-radius:14px;overflow:hidden;background:#101f30}
    .surfside-public-messages__player iframe{border:0;position:absolute;inset:0;width:100%;height:100%}
    .surfside-public-messages__related{margin:26px 0}
    .surfside-public-messages__related h3{font-size:19px!important;margin:0 0 12px!important}
    .surfside-public-messages__related a{margin:0 8px 8px 0}
    .surfside-public-messages__empty{padding:28px;background:#f2f7fb;border:1px solid var(--s-border);border-radius:14px;line-height:1.6}
    .surfside-public-messages__empty h2{margin-top:0}
    .surfside-public-messages__pagination{display:flex;justify-content:space-between;align-items:center;gap:12px;margin:28px 0 10px;color:var(--s-muted);font-size:14px}
    .surfside-public-messages__pagination a{color:var(--s-blue)!important;font-weight:750;text-decoration:none}
    @media(max-width:900px){.surfside-public-messages__featured{grid-template-columns:minmax(0,1fr)}.surfside-public-messages__featured-art{width:100%;aspect-ratio:16/9}.surfside-public-messages__filters{grid-template-columns:repeat(2,minmax(0,1fr))}.surfside-public-messages__grid--videos,.surfside-public-messages__grid--series{grid-template-columns:repeat(2,minmax(0,1fr))}}
    @media(max-width:550px){.surfside-public-messages{padding:18px 8px}.surfside-public-messages__intro{margin-bottom:22px}.surfside-public-messages__tabs a{flex:1;text-align:center}.surfside-public-messages__featured{margin-bottom:26px}.surfside-public-messages__featured-copy{padding:24px 20px}.surfside-public-messages__featured-copy h2{font-size:clamp(25px,7vw,31px)!important}.surfside-public-messages__filters{grid-template-columns:1fr}.surfside-public-messages__grid--videos,.surfside-public-messages__grid--series{grid-template-columns:1fr}.surfside-public-messages__grid{gap:14px}.surfside-public-messages__section{margin:22px 0}.surfside-public-messages__series-info,.surfside-public-messages__video-info{padding:13px 15px}}
    </style>
    <?php
}
add_shortcode('surfside_message_archive', 'surfside_tools_public_message_archive_shortcode');

/**
 * Safely provision a draft page once, on a real WordPress admin request only.
 * Existing user-authored /messages/ pages are never overwritten or published.
 */
function surfside_tools_public_messages_provision_page() {
    if (!is_admin() || !current_user_can('manage_options')) return;
    if ((int) get_option(SURFSIDE_PUBLIC_MESSAGES_PAGE_OPTION, 0) >= SURFSIDE_PUBLIC_MESSAGES_PAGE_SCHEMA) return;
    $page = get_page_by_path('messages', OBJECT, 'page');
    if (!$page) {
        $result = wp_insert_post(array(
            'post_title' => 'Messages',
            'post_name' => 'messages',
            'post_content' => '[surfside_message_archive]',
            'post_status' => 'draft',
            'post_type' => 'page',
        ), true);
        if (is_wp_error($result) || !$result) return;
    }
    update_option(SURFSIDE_PUBLIC_MESSAGES_PAGE_OPTION, SURFSIDE_PUBLIC_MESSAGES_PAGE_SCHEMA, false);
}
add_action('admin_init', 'surfside_tools_public_messages_provision_page', 8);
