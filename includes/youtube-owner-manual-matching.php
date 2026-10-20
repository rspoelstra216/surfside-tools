<?php
/**
 * Explicit admin-reviewed NAS file associations for orphaned YouTube sermons.
 * Associates existing paths with video IDs without moving/renaming NAS files.
 */
if (!defined('ABSPATH')) { exit; }

const SURFSIDE_YT_OWNER_INVENTORY_OPTION = 'surfside_yt_owner_nas_inventory';
const SURFSIDE_YT_OWNER_MANUAL_OPTION = 'surfside_yt_owner_manual_paths';
const SURFSIDE_YT_OWNER_SIZE_OVERRIDE_OPTION = 'surfside_yt_owner_manual_size_overrides';

/** Remember the most recent valid inventory used to generate a manifest. */
function surfside_yt_owner_manual_save_inventory($index) {
    $paths = array();
    foreach ($index as $items) {
        foreach ($items as $item) {
            $path = (string) ($item['source_path'] ?? '');
            $size = (string) ($item['size_bytes'] ?? '');
            if (surfside_yt_owner_valid_source_path($path) && preg_match('/^[0-9]+$/D', $size)) {
                $paths[$path] = $size;
            }
        }
    }
    update_option(SURFSIDE_YT_OWNER_INVENTORY_OPTION, array('paths' => $paths, 'updated_at' => time()), false);
}

function surfside_yt_owner_manual_inventory() {
    $value = get_option(SURFSIDE_YT_OWNER_INVENTORY_OPTION, array());
    return is_array($value) && is_array($value['paths'] ?? null) ? $value : array('paths' => array());
}

function surfside_yt_owner_manual_saved() {
    $value = get_option(SURFSIDE_YT_OWNER_MANUAL_OPTION, array());
    return is_array($value) ? $value : array();
}

/** Separate manual-review notices appear beside matching controls, not the OAuth panel above. */
function surfside_yt_owner_manual_notice($message, $error = false) {
    set_transient('surfside_yt_owner_manual_notice_' . get_current_user_id(),
        array('text' => sanitize_text_field($message), 'error' => (bool) $error), 180);
}

/**
 * A size override is an explicitly approved association to a specific file and
 * byte count, not blanket permission to ignore future NAS or YouTube changes.
 */
function surfside_yt_owner_manual_overrides() {
    $value = get_option(SURFSIDE_YT_OWNER_SIZE_OVERRIDE_OPTION, array());
    return is_array($value) ? $value : array();
}

function surfside_yt_owner_manual_override_valid($id, $path, $local_size, $youtube_size) {
    $overrides = surfside_yt_owner_manual_overrides();
    $saved = $overrides[$id] ?? null;
    if (!is_array($saved)) return false;
    return ($saved['path'] ?? '') === $path
        && ($saved['local_size_bytes'] ?? '') === (string) $local_size
        && ($saved['youtube_size_bytes'] ?? '') === (string) $youtube_size;
}

/** Validate the chosen path against the freshly uploaded CSV on every export. */
function surfside_yt_owner_manual_apply($video_id, $automatic, $index, $detail) {
    if (($automatic['status'] ?? '') === 'matched') return $automatic;
    $saved = surfside_yt_owner_manual_saved();
    $path = (string) ($saved[$video_id] ?? '');
    if ($path === '') return $automatic;
    $found = null;
    foreach ($index[basename($path)] ?? array() as $candidate) {
        if ($candidate['source_path'] === $path) { $found = $candidate; break; }
    }
    if ($found === null) return array('status' => 'manual_path_missing', 'source_path' => '');
    $expected = (string) ($detail['size_bytes'] ?? '');
    if (preg_match('/^[0-9]+$/D', $expected)
        && ltrim($expected, '0') !== ltrim((string) $found['size_bytes'], '0')) {
        if (!surfside_yt_owner_manual_override_valid(
            $video_id, $path, (string) $found['size_bytes'], $expected)) {
            return array('status' => 'manual_size_mismatch', 'source_path' => '');
        }
        return array('status' => 'manually_matched_size_override', 'source_path' => $path);
    }
    return array('status' => preg_match('/^[0-9]+$/D', $expected)
        ? 'manually_matched' : 'manual_unverified_youtube_size', 'source_path' => $path);
}

/** Save or remove a single deliberate association; only master-playlist IDs qualify. */
function surfside_yt_owner_manual_handle_post() {
    if (!is_user_logged_in() || !current_user_can('manage_options')) {
        wp_die('Not authorized.', '', array('response' => 403));
    }
    check_admin_referer('surfside_yt_owner_manual', 'surfside_yt_owner_manual_nonce');
    $operation = isset($_POST['manual_action']) && is_string($_POST['manual_action'])
        ? sanitize_key(wp_unslash($_POST['manual_action'])) : '';
    $id = isset($_POST['video_id']) && is_string($_POST['video_id'])
        ? sanitize_text_field(wp_unslash($_POST['video_id'])) : '';
    $rows = surfside_yt_owner_master_rows();
    $valid = !is_wp_error($rows) && preg_match('/^[A-Za-z0-9_-]{11}$/D', $id);
    if ($valid) {
        $valid = false;
        foreach ($rows as $row) {
            if ((string) $row['video_id'] === $id) { $valid = true; break; }
        }
    }
    if (!$valid) {
        surfside_yt_owner_manual_notice('Invalid YouTube ID or video no longer in the master Messages playlist.', true);
    } elseif ($operation === 'remove') {
        $saved = surfside_yt_owner_manual_saved();
        $overrides = surfside_yt_owner_manual_overrides();
        unset($saved[$id], $overrides[$id]);
        update_option(SURFSIDE_YT_OWNER_MANUAL_OPTION, $saved, false);
        update_option(SURFSIDE_YT_OWNER_SIZE_OVERRIDE_OPTION, $overrides, false);
        surfside_yt_owner_manual_notice('Removed the manual file association.');
    } elseif ($operation === 'save') {
        $path = isset($_POST['source_path']) && is_string($_POST['source_path'])
            ? trim(wp_unslash($_POST['source_path'])) : '';
        $inventory = surfside_yt_owner_manual_inventory();
        $saved = surfside_yt_owner_manual_saved();
        $size = $inventory['paths'][$path] ?? null;
        $details = get_option(SURFSIDE_YT_OWNER_FILES_OPTION, array());
        $expected = is_array($details) ? (string) ($details[$id]['size_bytes'] ?? '') : '';
        $mismatch = $size !== null && preg_match('/^[0-9]+$/D', $expected)
            && ltrim($expected, '0') !== ltrim((string) $size, '0');
        $size_confirmed = isset($_POST['allow_size_mismatch']) && (string) $_POST['allow_size_mismatch'] === '1';
        if (!isset($_POST['confirmed']) || (string) $_POST['confirmed'] !== '1') {
            surfside_yt_owner_manual_notice('Confirm that you reviewed the actual recording before saving.', true);
        } elseif (!surfside_yt_owner_valid_source_path($path) || $size === null) {
            surfside_yt_owner_manual_notice('Select an existing MP4 path from the latest imported inventory.', true);
        } elseif ($mismatch && !$size_confirmed) {
            surfside_yt_owner_manual_notice(
                'Size differs: NAS ' . number_format((float) $size) . ' bytes; YouTube '
                . number_format((float) $expected)
                . ' bytes. Verify the actual recording, then check the size-difference confirmation to save.',
                true
            );
        } elseif (in_array($path, array_diff_key($saved, array($id => true)), true)) {
            surfside_yt_owner_manual_notice('That NAS path is already assigned to another YouTube ID.', true);
        } else {
            $saved[$id] = $path;
            $overrides = surfside_yt_owner_manual_overrides();
            if ($mismatch) {
                $overrides[$id] = array(
                    'path' => $path,
                    'local_size_bytes' => (string) $size,
                    'youtube_size_bytes' => $expected,
                );
            } else {
                unset($overrides[$id]);
            }
            update_option(SURFSIDE_YT_OWNER_MANUAL_OPTION, $saved, false);
            update_option(SURFSIDE_YT_OWNER_SIZE_OVERRIDE_OPTION, $overrides, false);
            surfside_yt_owner_manual_notice(
                'Saved manual NAS association for YouTube ID ' . $id
                . ($mismatch ? ' with an explicitly verified size difference.' : '.')
            );
        }
    } else {
        surfside_yt_owner_manual_notice('Unknown manual matching action.', true);
    }
    wp_safe_redirect(surfside_tools_r2_manager_url(array('matching' => 'manual')) . '#surfside-owner-manual-matching', 303);
    exit;
}
add_action('admin_post_surfside_yt_owner_manual', 'surfside_yt_owner_manual_handle_post');

function surfside_yt_owner_manual_frontend_dispatch() {
    if (!is_page('site-management') || !isset($_GET['view'])
        || sanitize_key(wp_unslash($_GET['view'])) !== 'roku-media') return;
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action'])
        && is_string($_POST['action'])
        && sanitize_key(wp_unslash($_POST['action'])) === 'surfside_yt_owner_manual') {
        surfside_yt_owner_manual_handle_post();
    }
}
add_action('template_redirect', 'surfside_yt_owner_manual_frontend_dispatch', 2);

/** Only show videos not automatically matched; suggest same-size paths, never silently assign them. */
function surfside_yt_owner_manual_panel() {
    if (!current_user_can('manage_options')) return '';
    $inventory = surfside_yt_owner_manual_inventory();
    $paths = $inventory['paths'];
    $messages = surfside_yt_owner_master_rows();
    if (is_wp_error($messages)) return '<p>' . esc_html($messages->get_error_message()) . '</p>';
    $details = get_option(SURFSIDE_YT_OWNER_FILES_OPTION, array());
    if (!is_array($details)) $details = array();
    $saved = surfside_yt_owner_manual_saved();
    $notice_key = 'surfside_yt_owner_manual_notice_' . get_current_user_id();
    $notice = get_transient($notice_key);
    delete_transient($notice_key);
    $index = array();
    foreach ($paths as $path => $size) {
        $index[basename($path)][] = array('source_path' => $path, 'size_bytes' => $size);
    }

    // Automatic paths are reserved; suggestions never override confirmed exact matches.
    $assigned_paths = array_values($saved);
    $unmatched = array();
    $matched_paths = array();
    foreach ($messages as $message) {
        $id = (string) $message['video_id'];
        $auto = surfside_yt_owner_match_file($details[$id] ?? array(), $index);
        if ($auto['status'] === 'matched') {
            $matched_paths[$auto['source_path']] = true;
        } else {
            $unmatched[] = array('message' => $message, 'status' => $auto['status']);
        }
    }
    $available_by_size = array();
    foreach ($paths as $path => $size) {
        if (isset($matched_paths[$path]) || in_array($path, $assigned_paths, true)) continue;
        $available_by_size[$size][] = $path;
    }

    $review_rows = array();
    $counts = array('suggested' => 0, 'review' => 0, 'saved' => 0, 'valid_saved' => 0);
    foreach ($unmatched as $item) {
        $message = $item['message'];
        $id = (string) $message['video_id'];
        $expected = (string) ($details[$id]['size_bytes'] ?? '');
        $candidates = $available_by_size[$expected] ?? array();
        $suggestion = count($candidates) === 1 ? $candidates[0] : '';
        $manual_path = (string) ($saved[$id] ?? '');
        $saved_result = $manual_path !== '' ? surfside_yt_owner_manual_apply($id,
            array('status' => $item['status'], 'source_path' => ''), $index, $details[$id] ?? array()) : array();
        $saved_status = (string) ($saved_result['status'] ?? '');
        $valid_saved = in_array($saved_status,
            array('manually_matched', 'manual_unverified_youtube_size', 'manually_matched_size_override'), true);
        $group = $manual_path !== '' ? 'saved' : ($suggestion !== '' ? 'suggested' : 'review');
        ++$counts[$group];
        if ($valid_saved) ++$counts['valid_saved'];
        $review_rows[] = array(
            'message' => $message,
            'id' => $id,
            'status' => $item['status'],
            'group' => $group,
            'suggestion' => $suggestion,
            'manual_path' => $manual_path,
            'current_path' => $manual_path !== '' ? $manual_path : $suggestion,
            'saved_status' => $saved_status,
            'valid_saved' => $valid_saved,
            'size_override' => $saved_status === 'manually_matched_size_override',
            'youtube_name' => (string) ($details[$id]['file_name'] ?? ''),
        );
    }

    // Suggested, then unsuggested, then already reviewed: useful even without JS.
    $order = array('suggested' => 0, 'review' => 1, 'saved' => 2);
    usort($review_rows, function ($a, $b) use ($order) {
        return $order[$a['group']] <=> $order[$b['group']];
    });
    $auto_count = count($messages) - count($unmatched);
    $pending_count = count($unmatched) - $counts['valid_saved'];
    $match_count = $auto_count + $counts['valid_saved'];
    ob_start(); ?>
    <section id="surfside-owner-manual-matching" class="surfside-owner-match">
      <style>
        #surfside-owner-manual-matching { margin-top:24px; padding-top:16px; border-top:1px solid #d6d9de; }
        #surfside-owner-manual-matching .som-stats { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:8px; margin:14px 0; }
        #surfside-owner-manual-matching .som-stat { border:1px solid #d6d9de; border-radius:7px; padding:10px 12px; min-width:0; }
        #surfside-owner-manual-matching .som-stat strong { display:block; font-size:22px; line-height:1.25; }
        #surfside-owner-manual-matching .som-stat span { display:block; font-size:12px; }
        #surfside-owner-manual-matching .som-toolbar { display:flex; flex-wrap:wrap; gap:10px; align-items:end; justify-content:space-between; margin:16px 0 10px; }
        #surfside-owner-manual-matching .som-search { flex:1 1 230px; min-width:0; }
        #surfside-owner-manual-matching .som-search label { display:block; font-weight:600; margin-bottom:5px; }
        #surfside-owner-manual-matching .som-search input { width:100%; max-width:420px; min-height:38px; }
        #surfside-owner-manual-matching .som-filters { display:flex; flex-wrap:wrap; gap:6px; }
        #surfside-owner-manual-matching .som-filters button { border:1px solid #b4bbc5; border-radius:5px; background:transparent; color:inherit; padding:7px 10px; cursor:pointer; font:inherit; font-size:13px; }
        #surfside-owner-manual-matching .som-filters button[aria-pressed="true"] { background:#e9eef5; border-color:#6a7b91; font-weight:700; }
        #surfside-owner-manual-matching .som-filters button:focus-visible,
        #surfside-owner-manual-matching summary:focus-visible { outline:2px solid #1976d2; outline-offset:3px; }
        #surfside-owner-manual-matching .som-table { border:1px solid #d6d9de; border-radius:7px; overflow:hidden; }
        #surfside-owner-manual-matching .som-heading,
        #surfside-owner-manual-matching .som-row-head { display:grid; grid-template-columns:minmax(0,1fr) 112px 154px 70px; gap:10px; align-items:center; }
        #surfside-owner-manual-matching .som-heading { background:#f5f6f8; padding:9px 12px; font-size:12px; font-weight:700; }
        #surfside-owner-manual-matching .som-item { border-top:1px solid #d6d9de; }
        #surfside-owner-manual-matching .som-item:first-of-type { border-top:0; }
        #surfside-owner-manual-matching .som-item[hidden] { display:none !important; }
        #surfside-owner-manual-matching .som-row-head { padding:10px 12px; cursor:pointer; list-style:none; }
        #surfside-owner-manual-matching .som-row-head::-webkit-details-marker { display:none; }
        #surfside-owner-manual-matching .som-item[open] > .som-row-head { background:#f5f7fa; }
        #surfside-owner-manual-matching .som-title { display:block; font-weight:600; line-height:1.35; }
        #surfside-owner-manual-matching .som-id { display:block; font-size:12px; opacity:.75; margin-top:3px; font-family:monospace; }
        #surfside-owner-manual-matching .som-date { font-size:13px; }
        #surfside-owner-manual-matching .som-status { font-size:12px; line-height:1.35; }
        #surfside-owner-manual-matching .som-open { font-size:13px; text-align:right; font-weight:600; }
        #surfside-owner-manual-matching .som-item[open] .som-open { text-decoration:underline; }
        #surfside-owner-manual-matching .som-body { padding:12px; border-top:1px solid #e5e7eb; }
        #surfside-owner-manual-matching .som-body p { overflow-wrap:anywhere; margin:0 0 10px; }
        #surfside-owner-manual-matching .som-body label.som-path-label { display:block; font-weight:600; }
        #surfside-owner-manual-matching .som-body input[name="source_path"] { display:block; width:100%; max-width:900px; margin:5px 0 8px; box-sizing:border-box; }
        #surfside-owner-manual-matching .som-body .som-confirm { display:block; margin:10px 0; font-weight:400; }
        #surfside-owner-manual-matching .som-body .som-remove { margin-top:7px; }
        #surfside-owner-manual-matching .som-empty { padding:14px 12px; }
        #surfside-owner-manual-matching .som-results { margin:10px 0; }
        #surfside-owner-manual-matching .som-notice { padding:10px 12px; border:1px solid #a5b6c9; border-radius:6px; margin:12px 0; }
        #surfside-owner-manual-matching .som-notice[role="alert"] { border-color:#cf5b4a; background:#fff2f0; color:#7f2216; }
        #surfside-owner-manual-matching .som-notice[role="status"] { border-color:#6b9b74; background:#eff8f0; color:#1b5529; }
        @media (max-width:720px) {
          #surfside-owner-manual-matching .som-stats { grid-template-columns:repeat(2,minmax(0,1fr)); }
          #surfside-owner-manual-matching .som-heading { display:none; }
          #surfside-owner-manual-matching .som-row-head { grid-template-columns:minmax(0,1fr) auto; gap:5px 10px; }
          #surfside-owner-manual-matching .som-date { grid-column:1; grid-row:2; }
          #surfside-owner-manual-matching .som-status { grid-column:1; grid-row:3; }
          #surfside-owner-manual-matching .som-open { grid-column:2; grid-row:1 / 4; }
        }
      </style>
      <h3>Manually match orphaned sermons</h3>
      <?php if (is_array($notice)) : ?>
        <p class="som-notice" role="<?php echo !empty($notice['error']) ? 'alert' : 'status'; ?>"><?php echo esc_html((string) ($notice['text'] ?? '')); ?></p>
      <?php endif; ?>
      <p class="surfside-yt-muted">Keep your NAS filenames unchanged. Same-size suggestions are <strong>not confirmed matches</strong>: verify the actual recording before saving. Saved paths are rechecked against the latest inventory whenever you download a manifest.</p>
      <?php if (!$paths) : ?>
        <p><strong>No saved NAS inventory yet.</strong> Select your current inventory CSV above and click <em>Download matched R2 manifest</em> once. Return here to review orphaned recordings.</p>
      <?php else : ?>
        <div class="som-stats" aria-label="Matching progress">
          <div class="som-stat"><strong><?php echo esc_html($auto_count); ?></strong><span>Automatic matches</span></div>
          <div class="som-stat"><strong><?php echo esc_html($counts['valid_saved']); ?></strong><span>Saved manual matches</span></div>
          <div class="som-stat"><strong><?php echo esc_html($pending_count); ?></strong><span>Still need matching</span></div>
          <div class="som-stat"><strong><?php echo esc_html($counts['suggested']); ?></strong><span>Same-size suggestions</span></div>
        </div>
        <p class="surfside-yt-muted"><?php echo esc_html($match_count); ?> of <?php echo esc_html(count($messages)); ?> messages have an automatic or valid saved match (subject to revalidation in the manifest). <?php echo esc_html(count($paths)); ?> NAS MP4s inventoried; last loaded <?php echo esc_html(wp_date('M j, Y g:i a', (int) ($inventory['updated_at'] ?? time()))); ?>.</p>
        <datalist id="surfside-owner-inventory-paths">
          <?php foreach (array_keys($paths) as $path) : ?>
            <option value="<?php echo esc_attr($path); ?>"></option>
          <?php endforeach; ?>
        </datalist>
        <?php if (!$review_rows) : ?>
          <p>Every playlist video has an automatic filename match.</p>
        <?php else : ?>
          <div class="som-toolbar">
            <div class="som-search">
              <label for="som-search-input">Search sermons</label>
              <input type="search" id="som-search-input" placeholder="Title, date, YouTube ID, filename or NAS path" autocomplete="off">
            </div>
            <div class="som-filters" role="group" aria-label="Filter recordings by matching status">
              <button type="button" data-som-filter="suggested" aria-pressed="true">Suggested (<?php echo esc_html($counts['suggested']); ?>)</button>
              <button type="button" data-som-filter="review" aria-pressed="false">Needs review (<?php echo esc_html($counts['review']); ?>)</button>
              <button type="button" data-som-filter="saved" aria-pressed="false">Saved (<?php echo esc_html($counts['saved']); ?>)</button>
              <button type="button" data-som-filter="all" aria-pressed="false">All (<?php echo esc_html(count($review_rows)); ?>)</button>
            </div>
          </div>
          <p class="som-results surfside-yt-muted" id="som-results" role="status" aria-live="polite"></p>
          <div class="som-table" id="som-list">
            <div class="som-heading" aria-hidden="true">
              <span>Sermon</span><span>Date</span><span>Match status</span><span>Action</span>
            </div>
            <?php foreach ($review_rows as $row) :
              $message = $row['message'];
              $id = $row['id'];
              $group = $row['group'];
              $status_label = $group === 'saved'
                ? ($row['size_override'] ? 'Saved · verified size difference'
                    : ($row['valid_saved'] ? 'Saved match' : 'Saved · needs review'))
                : ($group === 'suggested' ? 'Same-size suggestion' : 'No suggestion');
              $search_text = implode(' ', array(
                (string) $message['title'], (string) ($message['sermon_date'] ?? ''),
                $id, $row['youtube_name'], $row['current_path'],
              )); ?>
              <details class="som-item" data-som-state="<?php echo esc_attr($group); ?>"
                  data-som-search="<?php echo esc_attr($search_text); ?>">
                <summary class="som-row-head">
                  <span><span class="som-title"><?php echo esc_html((string) $message['title']); ?></span>
                    <span class="som-id"><?php echo esc_html($id); ?></span></span>
                  <span class="som-date"><?php echo esc_html((string) ($message['sermon_date'] ?? '')); ?></span>
                  <span class="som-status"><?php echo esc_html($status_label); ?></span>
                  <span class="som-open">Review <span aria-hidden="true">▾</span></span>
                </summary>
                <div class="som-body">
                  <p>YouTube video: <a href="<?php echo esc_url('https://www.youtube.com/watch?v=' . rawurlencode($id)); ?>" target="_blank" rel="noopener noreferrer">Open recording</a>.
                  Original uploaded filename: <strong><?php echo esc_html($row['youtube_name'] !== '' ? $row['youtube_name'] : 'Unavailable'); ?></strong>.
                  <?php if ($row['manual_path'] !== '') : ?>
                    Current saved status: <strong><?php echo esc_html($row['saved_status']); ?></strong>.
                  <?php elseif ($row['suggestion'] !== '') : ?>
                    The suggested NAS path has the same byte size; check the recording before saving.
                  <?php else : ?>
                    No unique same-size candidate found. Choose a verified NAS recording manually; files with differing known YouTube byte sizes cannot be saved.
                  <?php endif; ?></p>
                  <form method="post" action="<?php echo esc_url(surfside_tools_r2_manager_url()); ?>">
                    <input type="hidden" name="action" value="surfside_yt_owner_manual">
                    <input type="hidden" name="manual_action" value="save">
                    <input type="hidden" name="video_id" value="<?php echo esc_attr($id); ?>">
                    <?php wp_nonce_field('surfside_yt_owner_manual', 'surfside_yt_owner_manual_nonce'); ?>
                    <label class="som-path-label">NAS MP4 path
                      <input type="text" name="source_path" list="surfside-owner-inventory-paths"
                        value="<?php echo esc_attr($row['current_path']); ?>" maxlength="1500" required autocomplete="off">
                    </label>
                    <label class="som-confirm">
                      <input type="checkbox" name="confirmed" value="1" required>
                      I verified that this NAS recording is the correct YouTube sermon.
                    </label>
                    <label class="som-confirm">
                      <input type="checkbox" name="allow_size_mismatch" value="1" <?php echo $row['size_override'] ? 'checked' : ''; ?>>
                      If file sizes differ, I also verified the recording's content and approve using this NAS MP4 despite the size difference.
                    </label>
                    <button type="submit"><?php echo $row['manual_path'] !== '' ? 'Update match' : 'Save match'; ?></button>
                  </form>
                  <?php if ($row['manual_path'] !== '') : ?>
                    <form class="som-remove" method="post" action="<?php echo esc_url(surfside_tools_r2_manager_url()); ?>">
                      <input type="hidden" name="action" value="surfside_yt_owner_manual">
                      <input type="hidden" name="manual_action" value="remove">
                      <input type="hidden" name="video_id" value="<?php echo esc_attr($id); ?>">
                      <?php wp_nonce_field('surfside_yt_owner_manual', 'surfside_yt_owner_manual_nonce'); ?>
                      <button type="submit">Remove saved association</button>
                    </form>
                  <?php endif; ?>
                </div>
              </details>
            <?php endforeach; ?>
          </div>
          <p class="som-empty" id="som-no-results" hidden>No recordings match this filter and search.</p>
          <script>
          (function () {
            var root = document.getElementById('surfside-owner-manual-matching');
            if (!root) return;
            var input = root.querySelector('#som-search-input');
            var buttons = Array.prototype.slice.call(root.querySelectorAll('[data-som-filter]'));
            var rows = Array.prototype.slice.call(root.querySelectorAll('.som-item'));
            var results = root.querySelector('#som-results');
            var empty = root.querySelector('#som-no-results');
            var filter = 'suggested';
            function update() {
              var needle = input.value.trim().toLowerCase();
              var visible = 0;
              rows.forEach(function (row) {
                var matching = (filter === 'all' || row.getAttribute('data-som-state') === filter)
                  && row.getAttribute('data-som-search').toLowerCase().indexOf(needle) !== -1;
                row.hidden = !matching;
                if (matching) visible += 1;
              });
              buttons.forEach(function (button) {
                button.setAttribute('aria-pressed', String(button.getAttribute('data-som-filter') === filter));
              });
              results.textContent = visible + ' recording' + (visible === 1 ? '' : 's') + ' shown';
              empty.hidden = visible !== 0;
            }
            buttons.forEach(function (button) {
              button.addEventListener('click', function () {
                filter = button.getAttribute('data-som-filter');
                update();
              });
            });
            input.addEventListener('input', update);
            update();
          }());
          </script>
        <?php endif; ?>
      <?php endif; ?>
    </section>
    <?php return ob_get_clean();
}
