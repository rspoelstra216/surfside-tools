<?php
/**
 * Explicit admin-reviewed NAS file associations for orphaned YouTube sermons.
 * Associates existing paths with video IDs without moving/renaming NAS files.
 */
if (!defined('ABSPATH')) { exit; }

const SURFSIDE_YT_OWNER_INVENTORY_OPTION = 'surfside_yt_owner_nas_inventory';
const SURFSIDE_YT_OWNER_MANUAL_OPTION = 'surfside_yt_owner_manual_paths';

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
        return array('status' => 'manual_size_mismatch', 'source_path' => '');
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
        surfside_yt_owner_notice('Invalid YouTube ID or video no longer in the master Messages playlist.', true);
    } elseif ($operation === 'remove') {
        $saved = surfside_yt_owner_manual_saved();
        unset($saved[$id]);
        update_option(SURFSIDE_YT_OWNER_MANUAL_OPTION, $saved, false);
        surfside_yt_owner_notice('Removed the manual file association.');
    } elseif ($operation === 'save') {
        $path = isset($_POST['source_path']) && is_string($_POST['source_path'])
            ? trim(wp_unslash($_POST['source_path'])) : '';
        $inventory = surfside_yt_owner_manual_inventory();
        $saved = surfside_yt_owner_manual_saved();
        $size = $inventory['paths'][$path] ?? null;
        $details = get_option(SURFSIDE_YT_OWNER_FILES_OPTION, array());
        $expected = is_array($details) ? (string) ($details[$id]['size_bytes'] ?? '') : '';
        if (!isset($_POST['confirmed']) || (string) $_POST['confirmed'] !== '1') {
            surfside_yt_owner_notice('Confirm that you reviewed the actual recording before saving.', true);
        } elseif (!surfside_yt_owner_valid_source_path($path) || $size === null) {
            surfside_yt_owner_notice('Select an existing MP4 path from the latest imported inventory.', true);
        } elseif (preg_match('/^[0-9]+$/D', $expected)
            && ltrim($expected, '0') !== ltrim((string) $size, '0')) {
            surfside_yt_owner_notice('File size differs from the YouTube original; association was not saved.', true);
        } elseif (in_array($path, array_diff_key($saved, array($id => true)), true)) {
            surfside_yt_owner_notice('That NAS path is already assigned to another YouTube ID.', true);
        } else {
            $saved[$id] = $path;
            update_option(SURFSIDE_YT_OWNER_MANUAL_OPTION, $saved, false);
            surfside_yt_owner_notice('Saved manual NAS association for YouTube ID ' . $id . '.');
        }
    } else {
        surfside_yt_owner_notice('Unknown manual matching action.', true);
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
    $index = array();
    foreach ($paths as $path => $size) {
        $index[basename($path)][] = array('source_path' => $path, 'size_bytes' => $size);
    }
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
    $candidate_count = 0;
    foreach ($unmatched as $item) {
        $id = (string) $item['message']['video_id'];
        if (isset($saved[$id])) continue;
        $expected = (string) ($details[$id]['size_bytes'] ?? '');
        if (count($available_by_size[$expected] ?? array()) === 1) ++$candidate_count;
    }
    ob_start(); ?>
    <div id="surfside-owner-manual-matching" style="margin-top:24px;border-top:1px solid #ddd;padding-top:16px">
      <h3>Manually match orphaned sermons</h3>
      <p class="surfside-yt-muted">Keep your NAS filenames unchanged. Save a verified local MP4 path for a YouTube ID; future manifest downloads use the association only if the path still exists in the newly uploaded inventory. Suggested files have matching byte sizes, but <strong>you must verify the recording</strong> before saving.</p>
      <?php if (!$paths) : ?>
        <p><strong>No saved NAS inventory yet.</strong> Select your current inventory CSV above and click <em>Download matched R2 manifest</em> once. Return to this page to review unmatched recordings.</p>
      <?php else : ?>
        <p><strong><?php echo esc_html(count($paths)); ?></strong> inventoried MP4 paths;
        <strong><?php echo esc_html(count($unmatched)); ?></strong> videos not automatically matched;
        <strong><?php echo esc_html($candidate_count); ?></strong> unique same-size suggestions;
        <strong><?php echo esc_html(count($saved)); ?></strong> saved associations.
        <span class="surfside-yt-muted">Inventory loaded <?php echo esc_html(wp_date('M j, Y g:i a', (int) ($inventory['updated_at'] ?? time()))); ?>.</span></p>
        <datalist id="surfside-owner-inventory-paths">
          <?php foreach (array_keys($paths) as $path) : ?>
            <option value="<?php echo esc_attr($path); ?>"></option>
          <?php endforeach; ?>
        </datalist>
        <?php if (!$unmatched) : ?>
          <p>Every playlist video has an automatic filename match.</p>
        <?php endif; ?>
        <?php foreach ($unmatched as $item) :
            $message = $item['message'];
            $id = (string) $message['video_id'];
            $expected = (string) ($details[$id]['size_bytes'] ?? '');
            $candidates = $available_by_size[$expected] ?? array();
            $suggestion = count($candidates) === 1 ? $candidates[0] : '';
            $manual_path = (string) ($saved[$id] ?? '');
            $current_path = $manual_path !== '' ? $manual_path : $suggestion;
            $saved_result = $manual_path !== '' ? surfside_yt_owner_manual_apply($id,
                array('status' => $item['status'], 'source_path' => ''), $index, $details[$id] ?? array()) : array(); ?>
          <div style="border:1px solid #ddd;border-radius:6px;padding:12px;margin:12px 0">
            <p style="margin:0 0 6px"><strong><?php echo esc_html((string) $message['title']); ?></strong>
              (<?php echo esc_html((string) ($message['sermon_date'] ?? '')); ?>)
              <a href="<?php echo esc_url('https://www.youtube.com/watch?v=' . rawurlencode($id)); ?>" target="_blank" rel="noopener noreferrer">View YouTube video</a></p>
            <p class="surfside-yt-muted" style="margin:0 0 8px">ID: <code><?php echo esc_html($id); ?></code> · YouTube original: <?php echo esc_html((string) ($details[$id]['file_name'] ?? 'unavailable')); ?> ·
              <?php echo esc_html($manual_path !== '' ? 'Saved: ' . ($saved_result['status'] ?? '') : ($suggestion !== '' ? 'One same-size suggestion' : $item['status'])); ?></p>
            <form method="post" action="<?php echo esc_url(surfside_tools_r2_manager_url()); ?>">
              <input type="hidden" name="action" value="surfside_yt_owner_manual">
              <input type="hidden" name="manual_action" value="save">
              <input type="hidden" name="video_id" value="<?php echo esc_attr($id); ?>">
              <?php wp_nonce_field('surfside_yt_owner_manual', 'surfside_yt_owner_manual_nonce'); ?>
              <label>NAS MP4 path
                <input type="text" name="source_path" list="surfside-owner-inventory-paths" value="<?php echo esc_attr($current_path); ?>" maxlength="1500" style="width:100%;max-width:800px" required autocomplete="off">
              </label>
              <label style="display:block;margin:8px 0">
                <input type="checkbox" name="confirmed" value="1" required> I verified that this NAS recording is the correct YouTube sermon.
              </label>
              <button type="submit"><?php echo $manual_path !== '' ? 'Update match' : 'Save match'; ?></button>
            </form>
            <?php if ($manual_path !== '') : ?>
              <form method="post" action="<?php echo esc_url(surfside_tools_r2_manager_url()); ?>" style="margin-top:6px">
                <input type="hidden" name="action" value="surfside_yt_owner_manual">
                <input type="hidden" name="manual_action" value="remove">
                <input type="hidden" name="video_id" value="<?php echo esc_attr($id); ?>">
                <?php wp_nonce_field('surfside_yt_owner_manual', 'surfside_yt_owner_manual_nonce'); ?>
                <button type="submit">Remove saved association</button>
              </form>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
    <?php return ob_get_clean();
}
