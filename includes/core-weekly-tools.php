<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Surfside Tools cache helpers.
 */
function surfside_tools_prevent_cache() {
    if (!defined('DONOTCACHEPAGE')) {
        define('DONOTCACHEPAGE', true);
    }

    if (!defined('DONOTCACHEOBJECT')) {
        define('DONOTCACHEOBJECT', true);
    }

    if (!defined('DONOTCACHEDB')) {
        define('DONOTCACHEDB', true);
    }

    nocache_headers();

    if (!headers_sent()) {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: Wed, 11 Jan 1984 05:00:00 GMT');
    }
}

function surfside_tools_purge_cache() {
    if (has_action('litespeed_purge_all')) {
        do_action('litespeed_purge_all');
    }

    if (function_exists('rocket_clean_domain')) {
        rocket_clean_domain();
    }

    if (function_exists('w3tc_flush_all')) {
        w3tc_flush_all();
    }

    if (function_exists('wp_cache_clear_cache')) {
        wp_cache_clear_cache();
    }
}


/**
 * Surfside Tools - Phase 1
 * Weekly Announcements DOCX Importer
 *
 * Shortcode:
 * [surfside_weekly_docx_importer]
 */

add_action('template_redirect', function () {
    $no_cache_pages = array(
        'weekly-import',
        'weekly-update',
        'dashboard',
        'calendar',
        'carousel-update'
    );

    if (is_page($no_cache_pages)) {
        if (!defined('DONOTCACHEPAGE')) {
            define('DONOTCACHEPAGE', true);
        }
        nocache_headers();
    }
});

function surfside_tools_get_message_entry_page_id() {
    $entry_page = get_page_by_title('Message Notes Entry');
    return $entry_page ? (int) $entry_page->ID : 0;
}

function surfside_tools_clean_announcement_text($text) {
    $text = html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8');

    // 6 th -> 6th
    $text = preg_replace('/(\d+)\s+(st|nd|rd|th)\b/i', '$1$2', $text);

    // July 6th-10th -> July 6th–10th
    $text = preg_replace('/(\d+(?:st|nd|rd|th)?)\s*-\s*(\d+(?:st|nd|rd|th)?)/i', '$1–$2', $text);

    // 4:00pm-6:00pm -> 4:00pm–6:00pm
    $text = preg_replace('/(\d{1,2}:\d{2}\s*(?:am|pm)?)\s*-\s*(\d{1,2}:\d{2}\s*(?:am|pm)?)/i', '$1–$2', $text);

    // Oct. 5th-Oct 10th -> Oct. 5th–Oct 10th
    $text = preg_replace('/([A-Za-z]{3,}\.?\s+\d+(?:st|nd|rd|th)?)\s*-\s*([A-Za-z]{3,}\.?\s+\d+(?:st|nd|rd|th)?)/i', '$1–$2', $text);

    // Fix punctuation spacing.
    $text = preg_replace('/\s+([,.;:!?])/', '$1', $text);

    // Add missing spaces after punctuation when Word text runs collapse.
    $text = preg_replace('/([.!?])([A-Z0-9])/', '$1 $2', $text);

    // Fix hyphenated line wraps.
    $text = preg_replace('/([a-z])-\s*\n\s*([a-z])/i', '$1$2', $text);

    // Add space after punctuation when Word XML runs collapse text.
    $text = preg_replace('/([.!?])([A-Z])/', '$1 $2', $text);

    // Normalize whitespace.
    $text = preg_replace('/[ \t]{2,}/', ' ', $text);

    return trim($text);
}


function surfside_tools_announcement_normalize_key($text) {
    $text = html_entity_decode((string) $text, ENT_QUOTES | ENT_XML1, 'UTF-8');
    $text = strtolower($text);
    return preg_replace('/[^a-z0-9]+/i', '', $text);
}

function surfside_tools_is_junk_paragraph($text) {
    $text = trim($text);
    if ($text === '' || $text === 'A') return true;
    if (preg_match('/^(?:[A-Za-z]+\s+\d{1,2}\/\d{1,2},\s*\d{4}\s*)+$/', $text)) return true;
    if (preg_match('/\b(left|right|top|bottom)-?\d{4,}/i', $text)) return true;
    return false;
}

function surfside_tools_docx_paragraph_text($paragraph) {
    $texts = $paragraph->getElementsByTagNameNS(
        'http://schemas.openxmlformats.org/wordprocessingml/2006/main',
        't'
    );
    $pieces = array();
    foreach ($texts as $text_node) {
        $pieces[] = $text_node->nodeValue;
    }
    return surfside_tools_clean_announcement_text(implode('', $pieces));
}

function surfside_tools_docx_paragraph_is_numbered($paragraph, $xpath) {
    $nodes = $xpath->query('.//w:numPr', $paragraph);
    return ($nodes && $nodes->length > 0);
}

function surfside_tools_detect_announcement_date_from_text($all_text) {
    // Prefer visible date like July 4/5, 2026 at the bottom of the Word file.
    if (preg_match_all('/\b(January|February|March|April|May|June|July|August|September|October|November|December)\s+\d{1,2}(?:\/\d{1,2})?,\s*\d{4}\b/i', $all_text, $matches)) {
        return end($matches[0]);
    }

    // Fallback: MM/DD/YYYY
    if (preg_match_all('/\b\d{1,2}\/\d{1,2}\/\d{4}\b/', $all_text, $matches)) {
        return end($matches[0]);
    }

    return '';
}

function surfside_tools_extract_numbered_announcements_from_docx($file_path) {
    if (!class_exists('ZipArchive')) {
        return new WP_Error('zip_missing', 'The PHP ZipArchive extension is not available on this server.');
    }
    if (!class_exists('DOMDocument')) {
        return new WP_Error('dom_missing', 'The PHP DOM extension is not available on this server.');
    }

    $zip = new ZipArchive();
    if ($zip->open($file_path) !== true) {
        return new WP_Error('zip_open_failed', 'Could not open the DOCX file.');
    }
    $xml = $zip->getFromName('word/document.xml');
    $zip->close();
    if (!$xml) {
        return new WP_Error('docx_xml_missing', 'Could not read word/document.xml from the DOCX file.');
    }

    $dom = new DOMDocument();
    $dom->preserveWhiteSpace = false;
    libxml_use_internal_errors(true);
    $loaded = $dom->loadXML($xml);
    libxml_clear_errors();
    if (!$loaded) {
        return new WP_Error('xml_parse_failed', 'Could not parse the DOCX XML.');
    }

    $xpath = new DOMXPath($dom);
    $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
    $paragraphs = $xpath->query('//w:p');

    $items = array();
    $current = '';
    $all_paragraph_text = array();
    $seen_paragraph_keys = array();
    $duplicate_paragraphs_removed = 0;
    $wrapped_continuations = 0;
    $junk_removed = 0;

    foreach ($paragraphs as $paragraph) {
        $text = surfside_tools_docx_paragraph_text($paragraph);
        if ($text !== '') {
            $all_paragraph_text[] = $text;
        }

        if (surfside_tools_is_junk_paragraph($text)) {
            $junk_removed++;
            continue;
        }

        // Some announcement documents contain duplicated hidden/text-box paragraphs.
        // Skip duplicate paragraph text before it can be appended to the previous item.
        $paragraph_key = surfside_tools_announcement_normalize_key($text);
        if ($paragraph_key !== '' && isset($seen_paragraph_keys[$paragraph_key])) {
            $duplicate_paragraphs_removed++;
            continue;
        }
        if ($paragraph_key !== '') {
            $seen_paragraph_keys[$paragraph_key] = true;
        }

        $is_numbered = surfside_tools_docx_paragraph_is_numbered($paragraph, $xpath);

        if ($is_numbered) {
            if ($current !== '') $items[] = trim($current);
            $current = $text;
        } else {
            // Treat wrapped/non-numbered paragraphs after a numbered item as continuation text.
            // Use a space, not paragraph breaks, because the secretary's Word docs wrap lines.
            if ($current !== '') {
                $current .= ' ' . $text;
                $wrapped_continuations++;
            }
        }
    }

    if ($current !== '') $items[] = trim($current);

    $items = array_values(array_filter($items, function($item) {
        return strlen($item) < 1500;
    }));

    $deduped = array();
    $seen = array();
    $duplicates_removed = 0;

    foreach ($items as $item) {
        $item = surfside_tools_clean_announcement_text($item);
        $key = surfside_tools_announcement_normalize_key($item);
        if ($key === '') continue;
        if (!isset($seen[$key])) {
            $seen[$key] = true;
            $deduped[] = $item;
        } else {
            $duplicates_removed++;
        }
    }

    $all_text = implode(' ', $all_paragraph_text);
    $detected_date = surfside_tools_detect_announcement_date_from_text($all_text);

    return array(
        'items' => $deduped,
        'date'  => $detected_date,
        'report' => array(
            'found' => count($deduped),
            'wrapped_continuations' => $wrapped_continuations,
            'junk_removed' => $junk_removed,
            'duplicates_removed' => $duplicates_removed,
            'duplicate_paragraphs_removed' => $duplicate_paragraphs_removed,
        )
    );
}

function surfside_tools_build_announcements_html($items) {
    if (empty($items)) return '';
    $html = '<ol class="surfside-announcement-list">';
    foreach ($items as $item) {
        $item = trim(preg_replace('/\s+/', ' ', $item));
        $html .= '<li>' . esc_html($item) . '</li>';
    }
    $html .= '</ol>';
    return $html;
}

function surfside_tools_get_announcements_data() {
    $data = get_option('surfside_tools_announcements_current');

    if (!is_array($data)) {
        $data = array();
    }

    return array(
        'announcement_date' => isset($data['announcement_date']) ? $data['announcement_date'] : '',
        'items'             => isset($data['items']) && is_array($data['items']) ? $data['items'] : array(),
        'announcements'     => isset($data['announcements']) ? $data['announcements'] : '',
        'timestamp'         => isset($data['timestamp']) ? (int) $data['timestamp'] : 0,
    );
}

function surfside_tools_backup_current_announcements() {
    $current = surfside_tools_get_announcements_data();

    if (!empty($current['announcement_date']) || !empty($current['items']) || !empty($current['announcements'])) {
        update_option('surfside_tools_announcements_backup', $current, false);
    }
}

function surfside_tools_save_announcements($announcement_date, $items) {
    surfside_tools_backup_current_announcements();

    $clean_items = array();

    foreach ($items as $item) {
        $item = surfside_tools_clean_announcement_text($item);

        if ($item !== '') {
            $clean_items[] = $item;
        }
    }

    $html = surfside_tools_build_announcements_html($clean_items);

    $data = array(
        'announcement_date' => sanitize_text_field($announcement_date),
        'items'             => $clean_items,
        'announcements'     => wp_kses_post($html),
        'timestamp'         => current_time('timestamp'),
    );

    update_option('surfside_tools_announcements_current', $data, false);

    surfside_tools_purge_cache();

    return true;
}

function surfside_tools_restore_previous_announcements() {
    $backup = get_option('surfside_tools_announcements_backup');

    if (empty($backup) || !is_array($backup)) {
        return new WP_Error('backup_missing', 'No previous announcement backup was found.');
    }

    update_option('surfside_tools_announcements_current', $backup, false);

    surfside_tools_purge_cache();

    return true;
}

function surfside_tools_announcements_shortcode() {
    $data = surfside_tools_get_announcements_data();

    $announcement_date = $data['announcement_date'];
    $announcements = $data['announcements'];

    ob_start();
    ?>
    <div class="surfside-announcements">
        <?php if ($announcement_date) : ?>
            <h2 class="announcement-date">
                Announcements for <?php echo esc_html($announcement_date); ?>
            </h2>
        <?php endif; ?>

        <?php if ($announcements) : ?>
            <div class="announcement-content">
                <?php echo wp_kses_post($announcements); ?>
            </div>
        <?php else : ?>
            <p>No announcements have been posted yet.</p>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

add_shortcode('surfside_tools_announcements', 'surfside_tools_announcements_shortcode');

function surfside_tools_importer_notice($message, $type = 'success') {
    return '<div class="surfside-docx-message ' . esc_attr($type) . '">' . wp_kses_post($message) . '</div>';
}

function surfside_weekly_docx_importer_shortcode() {
    surfside_tools_prevent_cache();
    if (!is_user_logged_in()) {
        $login_url = wp_login_url(get_permalink());
        return '<div class="weekly-update-login"><p>You must be logged in to import weekly announcements.</p><a class="wp-block-button__link wp-element-button" href="' . esc_url($login_url) . '">Log In to Continue</a></div>';
    }
    if (!current_user_can('upload_files')) {
        return '<p>You do not have permission to upload weekly update documents.</p>';
    }

    $message = '';
    $preview_html = '';
    $editable_items = array();
    $announcement_date_value = '';
    $report = array();

    // Restore previous backup.
    if (isset($_POST['surfside_tools_restore_nonce']) && wp_verify_nonce($_POST['surfside_tools_restore_nonce'], 'surfside_tools_restore_announcements')) {
        $restored = surfside_tools_restore_previous_announcements();
        if (is_wp_error($restored)) {
            $message = surfside_tools_importer_notice($restored->get_error_message(), 'error');
        } else {
            $message = surfside_tools_importer_notice('Previous announcements restored successfully.', 'success');
        }
    }

    // Save edited preview.
    if (isset($_POST['surfside_tools_save_preview_nonce']) && wp_verify_nonce($_POST['surfside_tools_save_preview_nonce'], 'surfside_tools_save_preview')) {
        $announcement_date_value = isset($_POST['announcement_date']) ? sanitize_text_field(wp_unslash($_POST['announcement_date'])) : '';
        $raw_items = isset($_POST['announcement_items']) && is_array($_POST['announcement_items']) ? wp_unslash($_POST['announcement_items']) : array();

        $editable_items = array();
        foreach ($raw_items as $item) {
            $clean = surfside_tools_clean_announcement_text(sanitize_textarea_field($item));
            if ($clean !== '') {
                $editable_items[] = $clean;
            }
        }

        if (empty($editable_items)) {
            $message = surfside_tools_importer_notice('No announcements were available to save.', 'error');
        } else {
            $saved = surfside_tools_save_announcements($announcement_date_value, $editable_items);
            if (is_wp_error($saved)) {
                $message = surfside_tools_importer_notice($saved->get_error_message(), 'error');
            } else {
                $message = surfside_tools_importer_notice(
                    '<strong>Weekly announcements published.</strong>',
                    'success'
                );
                $preview_html = surfside_tools_build_announcements_html($editable_items);
            }
        }
    }

    // Upload and parse DOCX.
    if (isset($_POST['surfside_docx_importer_nonce']) && wp_verify_nonce($_POST['surfside_docx_importer_nonce'], 'surfside_docx_importer_upload')) {
        $manual_date = '';

        if (empty($_FILES['weekly_docx']['name'])) {
            $message = surfside_tools_importer_notice('Please choose a DOCX file.', 'error');
        } else {
            $file = $_FILES['weekly_docx'];
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if ($extension !== 'docx') {
                $message = surfside_tools_importer_notice('Please upload a .docx file.', 'error');
            } else {
                require_once ABSPATH . 'wp-admin/includes/file.php';
                $upload = wp_handle_upload($file, array('test_form' => false));
                if (isset($upload['error'])) {
                    $message = surfside_tools_importer_notice(esc_html($upload['error']), 'error');
                } else {
                    $result = surfside_tools_extract_numbered_announcements_from_docx($upload['file']);
                    if (is_wp_error($result)) {
                        $message = surfside_tools_importer_notice($result->get_error_message(), 'error');
                    } elseif (empty($result['items'])) {
                        $message = surfside_tools_importer_notice('No numbered announcements were found.', 'error');
                    } else {
                        $editable_items = $result['items'];
                        $report = isset($result['report']) ? $result['report'] : array();
                        $announcement_date_value = $manual_date ? $manual_date : (isset($result['date']) ? $result['date'] : '');
                        $preview_html = surfside_tools_build_announcements_html($editable_items);

                        $message = surfside_tools_importer_notice(
                            'Please review then click <strong>Save to Website</strong>.',
                            'success'
                        );
                    }
                    if (!empty($upload['file']) && file_exists($upload['file'])) @unlink($upload['file']);
                }
            }
        }
    }

    $backup = get_option('surfside_tools_announcements_backup');

    ob_start(); ?>
    <div class="surfside-docx-importer">
        <h2>Import Weekly Announcements</h2>
        <p>Upload the weekly announcements as a <strong>.docx</strong> file. Surfside Tools will read the numbered list, clean Word formatting, let you preview/edit the result, and save it to the existing Announcements field.</p>

        <?php echo $message; ?>

        <form method="post" enctype="multipart/form-data" class="surfside-docx-upload-form">
            <?php wp_nonce_field('surfside_docx_importer_upload', 'surfside_docx_importer_nonce'); ?>
            <p><label for="weekly_docx"><strong>Weekly Announcements DOCX</strong></label><br><input type="file" id="weekly_docx" name="weekly_docx" accept=".docx" required></p>
            <p><button type="submit" class="wp-block-button__link wp-element-button">Upload & Preview</button></p>
        </form>

        <?php if (!empty($editable_items)) : ?>
            <hr>
            <h3>Editable Preview</h3>
            <p>Verify the date, review the announcements, make any quick edits, then save to the website.</p>

            <form method="post" class="surfside-docx-save-form">
                <?php wp_nonce_field('surfside_tools_save_preview', 'surfside_tools_save_preview_nonce'); ?>
                <p>
                    <label for="preview_announcement_date"><strong>Verify Announcement Date</strong></label><br>
                    <small>Please confirm the date before saving.</small><br>
                    <input type="text" id="preview_announcement_date" name="announcement_date" value="<?php echo esc_attr($announcement_date_value); ?>" placeholder="July 4/5, 2026" style="max-width:260px;width:100%;">
                </p>

                <?php foreach ($editable_items as $index => $item) : ?>
                    <div class="surfside-docx-edit-item">
                        <label><strong>Announcement #<?php echo esc_html($index + 1); ?></strong></label>
                        <textarea name="announcement_items[]" rows="4" style="width:100%;"><?php echo esc_textarea($item); ?></textarea>
                    </div>
                <?php endforeach; ?>

                <h3>Formatted Preview</h3>
                <div class="surfside-docx-preview">
                    <?php if ($announcement_date_value) : ?>
                        <h2 class="announcement-date">Announcements for <?php echo esc_html($announcement_date_value); ?></h2>
                    <?php endif; ?>
                    <?php echo wp_kses_post($preview_html); ?>
                </div>

                <p><button type="submit" class="wp-block-button__link wp-element-button">Save to Website</button></p>
            </form>
        <?php elseif ($preview_html) : ?>
            <hr><h3>Parsed Preview</h3><div class="surfside-docx-preview"><?php if ($announcement_date_value) : ?><h2 class="announcement-date">Announcements for <?php echo esc_html($announcement_date_value); ?></h2><?php endif; ?><?php echo wp_kses_post($preview_html); ?></div>
        <?php endif; ?>

        <?php if (!empty($backup) && is_array($backup)) : ?>
            <hr>
            <h3>Restore Previous Announcements</h3>
            <p>A backup from <?php echo esc_html(date_i18n('F j, Y g:i A', isset($backup['timestamp']) ? (int) $backup['timestamp'] : time())); ?> is available.</p>
            <form method="post">
                <?php wp_nonce_field('surfside_tools_restore_announcements', 'surfside_tools_restore_nonce'); ?>
                <p><button type="submit" class="wp-block-button__link wp-element-button">Restore Previous Announcements</button></p>
            </form>
        <?php endif; ?>
    </div>
    <?php return ob_get_clean();
}
add_shortcode('surfside_weekly_docx_importer', 'surfside_weekly_docx_importer_shortcode');




add_action('wp_head', function () {
    ?>
    <style>
        .surfside-message-notes .message-notes-main-heading {
            text-align: center;
            font-size: clamp(1.35rem, 2.5vw, 2rem);
            margin: 1.5rem 0 2rem;
            text-transform: uppercase;
            letter-spacing: 0.02em;
        }

        .surfside-message-notes .message-notes-callout {
            font-style: italic;
            border-left: 4px solid currentColor;
            padding-left: 1rem;
            margin: 1.5rem 0 2rem;
            opacity: 0.9;
        }

        .surfside-message-notes .message-prayer {
            width: 100%;
            max-width: 100%;
            margin: 2.5rem auto 0;
            padding: 1.5rem 1.75rem;
            border-radius: 16px;
            background: #f7f9fc;
            border: 1px solid rgba(15, 45, 82, 0.14);
            border-left: 6px solid #0f5ca8;
            box-sizing: border-box;
            box-shadow: 0 10px 28px rgba(15, 45, 82, 0.08);
        }

        .surfside-message-notes .message-prayer p {
            font-size: 1.05rem;
            line-height: 1.75;
            font-style: italic;
            margin-bottom: 0;
        }

        .surfside-message-notes .message-notes,
        .surfside-message-notes .message-prayer {
            max-width: 1000px;
            margin-left: auto;
            margin-right: auto;
        }

        .surfside-message-notes .message-prayer h3 {
            margin-top: 0;
        }
    
        .surfside-weekly-upload-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 1.25rem;
            margin: 1.5rem 0;
        }

        .surfside-weekly-upload-card,
        .surfside-weekly-review-section,
        .surfside-weekly-advanced {
            padding: 1.25rem;
            border-radius: 14px;
            background: #f5f5f8;
            margin: 1.5rem 0;
            box-sizing: border-box;
        }

        .surfside-weekly-upload-card h3,
        .surfside-weekly-review-section h3 {
            margin-top: 0;
        }

        .surfside-weekly-review-section .surfside-message-notes,
        .surfside-weekly-review-section .surfside-announcements {
            background: #fff;
            border-radius: 12px;
            padding: 1.25rem;
        }

        .surfside-weekly-publish-actions {
            margin-top: 2rem;
        }

        @media (max-width: 700px) {
            .surfside-weekly-upload-grid {
                grid-template-columns: 1fr;
            }
        }

    
        /* Surfside Weekly Update - polished layout */
        .surfside-weekly-update-tool {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            box-sizing: border-box;
        }

        .surfside-weekly-update-tool > p {
            max-width: 760px;
        }

        .surfside-weekly-update-tool .surfside-weekly-upload-grid {
            display: grid !important;
            grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
            gap: 2rem !important;
            width: 100% !important;
            max-width: 100% !important;
            margin: 2rem 0 !important;
        }

        .surfside-weekly-update-tool .surfside-weekly-upload-card {
            background: #ffffff !important;
            border: 1px solid #e6e6e6;
            border-radius: 18px;
            box-shadow: 0 4px 16px rgba(0,0,0,.05);
            padding: 2rem;
            min-height: 235px;
            box-sizing: border-box;
        }

        .surfside-weekly-update-tool .surfside-weekly-upload-card h3 {
            margin-top: 0;
            margin-bottom: 1rem;
            line-height: 1.15;
        }

        .surfside-weekly-update-tool .surfside-weekly-upload-card p {
            margin-bottom: 1.25rem;
        }

        .surfside-weekly-update-tool .surfside-weekly-advanced {
            background: #ffffff !important;
            border: 1px solid #e6e6e6;
            border-radius: 14px;
            box-shadow: 0 2px 10px rgba(0,0,0,.04);
            padding: 1rem 1.25rem;
            margin-top: 1.5rem;
        }

        .surfside-weekly-update-tool .surfside-weekly-review-section {
            background: #ffffff !important;
            border: 1px solid #e6e6e6;
            border-radius: 18px;
            box-shadow: 0 4px 16px rgba(0,0,0,.05);
            padding: 2rem;
            margin: 2rem 0;
            box-sizing: border-box;
        }

        .surfside-weekly-update-tool .surfside-docx-edit-item textarea,
        .surfside-weekly-update-tool textarea,
        .surfside-weekly-update-tool input[type="text"] {
            box-sizing: border-box;
        }

        .surfside-weekly-update-tool .surfside-weekly-review-section .surfside-message-notes,
        .surfside-weekly-update-tool .surfside-weekly-review-section .surfside-announcements {
            max-width: 100% !important;
            width: 100% !important;
            background: #fafafa;
            border: 1px solid #ececec;
            border-radius: 14px;
            padding: 1.5rem;
            box-sizing: border-box;
        }

        .surfside-weekly-update-tool .surfside-weekly-publish-actions {
            margin-top: 2rem;
            text-align: center;
        }

        @media (max-width: 800px) {
            .surfside-weekly-update-tool .surfside-weekly-upload-grid {
                grid-template-columns: 1fr !important;
                gap: 1.25rem !important;
            }

            .surfside-weekly-update-tool .surfside-weekly-upload-card,
            .surfside-weekly-update-tool .surfside-weekly-review-section {
                padding: 1.25rem;
            }
        }

    </style>
    <?php
});

/**
 * =========================================================
 * Surfside Tools - Phase 2
 * Sermon Notes DOCX Importer
 *
 * Shortcodes:
 * [surfside_sermon_docx_importer]
 * [surfside_tools_message]
 * =========================================================
 */

function surfside_tools_docx_node_text_excluding_textboxes($node) {
    $texts = array();

    foreach ($node->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 't') as $text_node) {
        $parent = $text_node->parentNode;
        $inside_textbox = false;

        while ($parent) {
            if ($parent->localName === 'txbxContent' || $parent->localName === 'textbox' || $parent->localName === 'txbx') {
                $inside_textbox = true;
                break;
            }

            if ($parent->isSameNode($node)) {
                break;
            }

            $parent = $parent->parentNode;
        }

        if (!$inside_textbox) {
            $texts[] = $text_node->nodeValue;
        }
    }

    return trim(implode('', $texts));
}

function surfside_tools_docx_textbox_lines($textbox_node) {
    $lines = array();

    foreach ($textbox_node->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'p') as $paragraph) {
        $text = surfside_tools_docx_paragraph_text($paragraph);
        $text = surfside_tools_clean_sermon_text($text);

        if ($text !== '') {
            $lines[] = $text;
        }
    }

    return $lines;
}

function surfside_tools_is_sermon_control_line($line) {
    $line = trim($line);

    if ($line === '' || surfside_tools_is_sermon_date_line($line)) {
        return true;
    }

    if (preg_match('/^(CHRIST\s*ABOVE\s*ALL\s*-\s*COLOSSIANS|INSIDE\s*OUT\s*-\s*KNOWING\s*YOUR\s*IDENTITY)$/i', $line)) {
        return true;
    }

    if (preg_match('/^CITIZENS OF AN ETERNAL KINGDOM$/i', $line)) {
        return true;
    }

    return false;
}

function surfside_tools_textbox_is_sermon_header($box_lines) {
    $has_date = false;
    $has_series = false;

    foreach ((array) $box_lines as $line) {
        $line = trim($line);

        if ($line === '') {
            continue;
        }

        if (surfside_tools_extract_sermon_date_from_line($line) !== '') {
            $has_date = true;
        }

        if (preg_match('/^(CHRIST\s*ABOVE\s*ALL\s*-\s*COLOSSIANS|INSIDE\s*OUT\s*-\s*KNOWING\s*YOUR\s*IDENTITY)$/i', $line)) {
            $has_series = true;
        }
    }

    // Pastor Erick's title block is a text box containing title, series, and date.
    // Do not filter it as a normal gray callout, or the public message loses its header.
    return $has_date && $has_series;
}

function surfside_tools_extract_docx_paragraphs($file_path) {
    if (!class_exists('ZipArchive')) {
        return new WP_Error('zip_missing', 'The PHP ZipArchive extension is not available on this server.');
    }

    if (!class_exists('DOMDocument')) {
        return new WP_Error('dom_missing', 'The PHP DOM extension is not available on this server.');
    }

    $zip = new ZipArchive();

    if ($zip->open($file_path) !== true) {
        return new WP_Error('zip_open_failed', 'Could not open the DOCX file.');
    }

    $xml = $zip->getFromName('word/document.xml');
    $zip->close();

    if (!$xml) {
        return new WP_Error('docx_xml_missing', 'Could not read word/document.xml from the DOCX file.');
    }

    $dom = new DOMDocument();
    $dom->preserveWhiteSpace = false;

    libxml_use_internal_errors(true);
    $loaded = $dom->loadXML($xml);
    libxml_clear_errors();

    if (!$loaded) {
        return new WP_Error('xml_parse_failed', 'Could not parse the DOCX XML.');
    }

    $xpath = new DOMXPath($dom);
    $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

    $paragraphs = $xpath->query('//w:body//w:p[not(ancestor::w:txbxContent)]');
    $lines = array();
    $seen_textboxes = array();

    foreach ($paragraphs as $paragraph) {
        // Read only the real paragraph text. Word also places text-box text inside
        // the paragraph's descendants; including it here creates repeated/merged lines.
        $text = surfside_tools_docx_node_text_excluding_textboxes($paragraph);
        $text = surfside_tools_clean_sermon_text($text);

        if ($text !== '' && (!surfside_tools_is_junk_paragraph($text) || surfside_tools_extract_sermon_date_from_line($text) !== '')) {
            $lines[] = $text;
        }

        // Then read any visible text boxes anchored to this paragraph as intentional
        // callout/prayer blocks. They are marked so the renderer does not have to guess.
        foreach ($xpath->query('.//w:txbxContent', $paragraph) as $textbox) {
            $box_lines = surfside_tools_docx_textbox_lines($textbox);

            if (empty($box_lines)) {
                continue;
            }

            $box_text = surfside_tools_clean_sermon_text(implode(' ', $box_lines));
            $box_key = surfside_tools_sermon_normalize_key($box_text);

            if ($box_key === '' || isset($seen_textboxes[$box_key])) {
                continue;
            }

            $seen_textboxes[$box_key] = true;

            if (surfside_tools_textbox_is_sermon_header($box_lines)) {
                foreach ($box_lines as $box_line) {
                    $box_line = surfside_tools_clean_sermon_text($box_line);

                    if ($box_line !== '') {
                        $lines[] = $box_line;
                    }
                }
                continue;
            }

            $box_lines = array_values(array_filter($box_lines, function ($line) {
                return !surfside_tools_is_sermon_control_line($line);
            }));

            if (empty($box_lines)) {
                continue;
            }

            $box_text = surfside_tools_clean_sermon_text(implode(' ', $box_lines));

            if (preg_match('/^(Dear Lord|Lord,|Father,|Lord Jesus|Dear God)/i', $box_text)) {
                $lines[] = '[[PRAYER]] ' . $box_text;
            } else {
                $lines[] = '[[CALLOUT]] ' . $box_text;
            }
        }
    }

    return $lines;
}

function surfside_tools_clean_sermon_text($text) {
    $text = html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8');

    // Normalize line endings and spacing.
    $text = str_replace(array("\r\n", "\r"), "\n", $text);
    $text = preg_replace('/[ \t]{2,}/', ' ', $text);

    // Fix ordinal suffixes: 250 th -> 250th, 6 th -> 6th.
    $text = preg_replace('/(\d+)\s+(st|nd|rd|th)\b/i', '$1$2', $text);

    // Fix reference splits and normalize Unicode dashes:
    // Colossians 3:5- 8 NIV / Colossians 3:5–8 NIV -> Colossians 3:5-8 NIV.
    $text = preg_replace('/(\d+:\d+[a-z]?)\s*[-\x{2010}\x{2011}\x{2012}\x{2013}\x{2014}\x{2212}]\s*(\d+[a-z]?)/iu', '$1-$2', $text);

    // Fix book/reference line breaks that became spaces oddly.
    $text = preg_replace('/\s+([,.;:!?])/', '$1', $text);
    // Add missing spaces after punctuation when Word text runs collapse.
    $text = preg_replace('/([.!?])([A-Z0-9])/', '$1 $2', $text);


    // Fix hyphenated Word wraps.
    $text = preg_replace('/([a-z])-\s*\n\s*([a-z])/i', '$1$2', $text);

    return trim($text);
}

function surfside_tools_extract_sermon_date_from_line($line) {
    if (preg_match('/\b(?:January|February|March|April|May|June|July|August|September|October|November|December|Jan\.?|Feb\.?|Mar\.?|Apr\.?|May|Jun\.?|Jul\.?|Aug\.?|Sep\.?|Sept\.?|Oct\.?|Nov\.?|Dec\.?)\s+\d{1,2}(?:\/\d{1,2})?\s*,?\s*\d{4}(?:\s*[-–]\s*[^\n]+)?/i', $line, $m)) {
        return trim($m[0]);
    }

    return '';
}

function surfside_tools_is_sermon_date_line($line) {
    return surfside_tools_extract_sermon_date_from_line($line) !== '';
}

function surfside_tools_is_all_caps_heading($line) {
    $line = trim($line);

    if (strlen($line) < 6) {
        return false;
    }

    if (preg_match('/^\d+[\)\.]/', $line)) {
        return false;
    }

    // Allow punctuation common in sermon headings.
    return (bool) preg_match('/^[A-Z0-9\s\?\!\:\;\,\-’\'"&\.]+$/u', $line);
}

function surfside_tools_is_sermon_point_line($line) {
    return (bool) preg_match('/^\d+[\)\.]\s+/', trim($line));
}

function surfside_tools_bible_books_regex() {
    return 'Genesis|Exodus|Leviticus|Numbers|Deuteronomy|Joshua|Judges|Ruth|Samuel|Kings|Chronicles|Ezra|Nehemiah|Esther|Job|Psalms?|Proverbs|Ecclesiastes|Song of Songs|Isaiah|Jeremiah|Lamentations|Ezekiel|Daniel|Hosea|Joel|Amos|Obadiah|Jonah|Micah|Nahum|Habakkuk|Zephaniah|Haggai|Zechariah|Malachi|Matthew|Mark|Luke|John|Acts|Romans|Corinthians|Galatians|Ephesians|Philippians|Colossians|Thessalonians|Timothy|Titus|Philemon|Hebrews|James|Peter|Jude|Revelation';
}


function surfside_tools_collapse_repeated_title($title) {
    $title = trim($title);

    if ($title === '') {
        return '';
    }

    $len = strlen($title);

    if ($len % 2 === 0) {
        $left = substr($title, 0, $len / 2);
        $right = substr($title, $len / 2);

        if (strtolower($left) === strtolower($right)) {
            return trim($left);
        }
    }

    return $title;
}

function surfside_tools_collapse_repeated_sentence_text($text) {
    $text = trim($text);

    if ($text === '') {
        return '';
    }

    // Collapse exact repeated blocks: Text Text, or Text Text Text.
    for ($parts = 3; $parts >= 2; $parts--) {
        if (strlen($text) % $parts === 0) {
            $chunk_len = strlen($text) / $parts;
            $chunk = trim(substr($text, 0, $chunk_len));
            $rebuilt = trim(str_repeat($chunk, $parts));

            if ($chunk !== '' && strtolower($rebuilt) === strtolower($text)) {
                return $chunk;
            }
        }
    }

    // Repeated sentence pattern.
    if (preg_match('/^(.{25,}?[.!?])(?:\s+\1)+$/u', $text, $m)) {
        return trim($m[1]);
    }

    return $text;
}

function surfside_tools_sermon_normalize_key($text) {
    return strtolower(preg_replace('/[^a-z0-9]+/i', '', trim((string) $text)));
}

function surfside_tools_sermon_line_contains_existing_parts($line, $existing_lines) {
    $line_key = surfside_tools_sermon_normalize_key($line);

    if ($line_key === '') {
        return false;
    }

    $covered = '';

    foreach ($existing_lines as $existing) {
        $existing_key = surfside_tools_sermon_normalize_key($existing);

        if ($existing_key !== '' && strpos($line_key, $existing_key) !== false) {
            $covered .= $existing_key;
        }
    }

    return strlen($covered) >= (strlen($line_key) * 0.70);
}

function surfside_tools_sermon_remove_artifact_lines($lines) {
    $clean = array();
    $seen = array();
    $total = count($lines);

    foreach ($lines as $i => $line) {
        $line = trim($line);
        $key = surfside_tools_sermon_normalize_key($line);

        if ($key === '') {
            continue;
        }

        // Word text boxes often emit one long aggregate paragraph containing the same
        // visible text plus the individual paragraphs. Depending on the DOCX, that
        // aggregate can appear before or after the real paragraphs, so compare against
        // already-kept lines and the next few source lines.
        if (strlen($line) > 120) {
            $nearby = array_merge($clean, array_slice($lines, $i + 1, min(10, $total - $i - 1)));

            if (surfside_tools_sermon_line_contains_existing_parts($line, $nearby)) {
                continue;
            }
        }

        if (!isset($seen[$key])) {
            $seen[$key] = true;
            $clean[] = $line;
        }
    }

    return $clean;
}

function surfside_tools_detect_sermon_header($lines) {
    $header = array(
        'title' => '',
        'series' => '',
        'date' => '',
        'date_index' => null,
    );

    $series_regex = '/(CHRIST\s*ABOVE\s*ALL\s*-\s*COLOSSIANS|INSIDE\s*OUT\s*-\s*KNOWING\s*YOUR\s*IDENTITY)/i';

    // 1) Best case: separate lines: TITLE / SERIES / DATE.
    foreach ($lines as $i => $line) {
        $date = surfside_tools_extract_sermon_date_from_line($line);

        if ($date !== '' && isset($lines[$i - 1]) && preg_match($series_regex, $lines[$i - 1])) {
            $header['date'] = $date;
            $header['series'] = trim($lines[$i - 1]);
            $header['title'] = isset($lines[$i - 2]) ? surfside_tools_collapse_repeated_title($lines[$i - 2]) : '';
            $header['date_index'] = $i;
            return $header;
        }
    }

    // 2) Collapsed Word text box: TITLE + SERIES + DATE in one paragraph.
    foreach ($lines as $i => $line) {
        $date = surfside_tools_extract_sermon_date_from_line($line);

        if ($date === '') {
            continue;
        }

        if (preg_match($series_regex, $line, $sm, PREG_OFFSET_CAPTURE)) {
            $series = trim($sm[0][0]);
            $series_pos = $sm[0][1];
            $title_part = trim(substr($line, 0, $series_pos));

            // The collapsed header often repeats the title before the series.
            $title_part = surfside_tools_collapse_repeated_title($title_part);

            $header['date'] = $date;
            $header['series'] = $series;
            $header['title'] = $title_part;
            $header['date_index'] = $i;

            if ($header['title'] !== '') {
                return $header;
            }
        }
    }

    // 3) Fallback: find series anywhere, date anywhere, then infer title from nearby all-caps line.
    foreach ($lines as $i => $line) {
        if ($header['series'] === '' && preg_match($series_regex, $line, $m)) {
            $header['series'] = trim($m[0]);
        }

        if ($header['date'] === '') {
            $date = surfside_tools_extract_sermon_date_from_line($line);
            if ($date !== '') {
                $header['date'] = $date;
                $header['date_index'] = $i;
            }
        }
    }

    // Infer title from the first clean all-caps line that is not series/date/main heading.
    foreach ($lines as $i => $line) {
        $candidate = trim($line);

        if ($candidate === '') {
            continue;
        }

        if (preg_match($series_regex, $candidate) || surfside_tools_is_sermon_date_line($candidate)) {
            continue;
        }

        if (surfside_tools_is_sermon_point_line($candidate)) {
            break;
        }

        if (surfside_tools_is_all_caps_heading($candidate)) {
            // Skip likely main heading if the next real line is point 1.
            if (isset($lines[$i + 1]) && surfside_tools_is_sermon_point_line($lines[$i + 1])) {
                continue;
            }

            // If the candidate is a collapsed header, trim before series if present.
            if (preg_match($series_regex, $candidate, $sm, PREG_OFFSET_CAPTURE)) {
                $candidate = trim(substr($candidate, 0, $sm[0][1]));
            }

            $candidate = surfside_tools_collapse_repeated_title($candidate);

            if ($candidate !== '') {
                $header['title'] = $candidate;
                break;
            }
        }
    }

    return $header;
}


function surfside_tools_is_prayer_stop_line($line, $title = '', $series = '', $main_heading = '') {
    $line = trim($line);

    if ($line === '') {
        return true;
    }

    // Stop before duplicated sermon structure.
    if (surfside_tools_is_sermon_point_line($line)) {
        return true;
    }

    if (preg_match('/^[–-]\s+/', $line)) {
        return true;
    }

    if (surfside_tools_is_sermon_date_line($line)) {
        return true;
    }

    if ($title !== '' && strcasecmp($line, $title) === 0) {
        return true;
    }

    if ($series !== '' && strcasecmp($line, $series) === 0) {
        return true;
    }

    if ($main_heading !== '' && strcasecmp($line, $main_heading) === 0) {
        return true;
    }

    // Stop on all-caps headings except short quoted/callout material.
    if (surfside_tools_is_all_caps_heading($line)) {
        return true;
    }

    // Stop if a duplicate Scripture reference line appears after prayer.
    $books = surfside_tools_bible_books_regex();
    if (preg_match('/^((?:[1-3]\s*)?(?:' . $books . ')\s+\d+:\d+[a-z]?(?:\s*-\s*\d+[a-z]?)?(?:,\s*\d+[a-z]?(?:\s*-\s*\d+[a-z]?)?)*\s+[A-Z]{2,5}\b)$/i', $line)) {
        return true;
    }

    return false;
}

function surfside_tools_clean_prayer_text($text) {
    $text = trim($text);

    // If duplicated reflection questions were appended to the same line, cut them off.
    $text = preg_replace('/\s+[–-]\s+(Are|Is|Have|Do|Does|Did|Will|Can|Should|Would|Could)\b.*$/i', '', $text);

    // Cut off repeated title/heading fragments that sometimes appear after Amen.
    $text = preg_replace('/(Amen\.)\s+.+$/i', '$1', $text);

    return trim($text);
}

function surfside_tools_find_sermon_main_heading($lines, $first_point_index, $title = '', $series = '', $date = '') {
    if ($first_point_index === null) {
        return '';
    }

    $series_regex = '/(CHRIST\s*ABOVE\s*ALL\s*-\s*COLOSSIANS|INSIDE\s*OUT\s*-\s*KNOWING\s*YOUR\s*IDENTITY)/i';

    for ($i = $first_point_index - 1; $i >= 0; $i--) {
        $candidate = trim($lines[$i]);

        if ($candidate === '') {
            continue;
        }

        if ($title !== '' && strcasecmp($candidate, $title) === 0) {
            continue;
        }

        if ($series !== '' && strcasecmp($candidate, $series) === 0) {
            continue;
        }

        if ($date !== '' && strcasecmp($candidate, $date) === 0) {
            continue;
        }

        if (surfside_tools_is_sermon_date_line($candidate) || preg_match($series_regex, $candidate)) {
            continue;
        }

        // Pastor Erick's main sermon question is normally the all-caps question
        // immediately above point 1, for example: HOW CAN I WALK WITH JESUS DAILY?
        if (surfside_tools_is_all_caps_heading($candidate) && strpos($candidate, '?') !== false) {
            return $candidate;
        }
    }

    for ($i = $first_point_index - 1; $i >= 0; $i--) {
        $candidate = trim($lines[$i]);

        if (
            $candidate !== '' &&
            !surfside_tools_is_sermon_date_line($candidate) &&
            !preg_match($series_regex, $candidate) &&
            surfside_tools_is_all_caps_heading($candidate)
        ) {
            return $candidate;
        }
    }

    return '';
}


function surfside_tools_parse_sermon_docx($file_path) {
    $lines = surfside_tools_extract_docx_paragraphs($file_path);

    if (is_wp_error($lines)) {
        return $lines;
    }

    if (empty($lines)) {
        return new WP_Error('sermon_empty', 'No sermon text was found in the DOCX file.');
    }

    // Clean and remove duplicate/artifact paragraphs while preserving order.
    $cleaned_lines = array();

    foreach ($lines as $line) {
        $line = surfside_tools_clean_sermon_text($line);
        $line = surfside_tools_collapse_repeated_sentence_text($line);

        if ($line !== '') {
            $cleaned_lines[] = $line;
        }
    }

    $lines = surfside_tools_sermon_remove_artifact_lines($cleaned_lines);

    $header = surfside_tools_detect_sermon_header($lines);

    $title = $header['title'];
    $series = $header['series'];
    $date = $header['date'];
    $date_index = $header['date_index'];

    // Date fallback: if the header detector misses it, scan every extracted line.
    if ($date === '') {
        foreach ($lines as $i => $line) {
            $found_date = surfside_tools_extract_sermon_date_from_line($line);

            if ($found_date !== '') {
                $date = $found_date;

                if ($date_index === null) {
                    $date_index = $i;
                }

                break;
            }
        }
    }


    $series_regex = '/(CHRIST\s*ABOVE\s*ALL\s*-\s*COLOSSIANS|INSIDE\s*OUT\s*-\s*KNOWING\s*YOUR\s*IDENTITY)/i';

    // Find first numbered point.
    $first_point_index = null;

    foreach ($lines as $i => $line) {
        if (surfside_tools_is_sermon_point_line($line)) {
            $first_point_index = $i;
            break;
        }
    }

    // Main heading is usually the all-caps question immediately before point 1.
    $main_heading = surfside_tools_find_sermon_main_heading($lines, $first_point_index, $title, $series, $date);

    // Final fallback for Word text boxes: fill any missing header fields from lines before point 1.
    if ($first_point_index !== null && ($title === '' || $series === '' || $date === '')) {
        $header_fallback = surfside_tools_detect_sermon_header(array_slice($lines, 0, $first_point_index + 1));

        if ($title === '' && !empty($header_fallback['title'])) {
            $title = $header_fallback['title'];
        }

        if ($series === '' && !empty($header_fallback['series'])) {
            $series = $header_fallback['series'];
        }

        if ($date === '' && !empty($header_fallback['date'])) {
            $date = $header_fallback['date'];
        }
    }

    $body_start = ($main_heading !== '') ? array_search($main_heading, $lines, true) : 0;

    if ($body_start === false) {
        $body_start = 0;
    }

    $body_end = count($lines);

    // Stop before the prayer/closing section when possible.
    if ($first_point_index !== null) {
        for ($i = $first_point_index + 1; $i < count($lines); $i++) {
            $candidate = trim($lines[$i]);

            if (
                preg_match('/^(\[\[PRAYER\]\]\s*)?(Dear Lord|Lord,|Father,|Lord Jesus|Dear God)/i', $candidate) ||
                ($title !== '' && $candidate === $title) ||
                ($series !== '' && $candidate === $series) ||
                surfside_tools_is_sermon_date_line($candidate)
            ) {
                $body_end = $i;
                break;
            }
        }
    }

    $body_lines = array_slice($lines, $body_start, max(0, $body_end - $body_start));
    $notes_text = surfside_tools_prepare_sermon_notes_plaintext($body_lines);

    // Safety net: if the source DOCX exposes the main headline outside the normal
    // paragraph flow, keep it visible at the top of the formatted notes.
    if ($main_heading !== '' && stripos($notes_text, $main_heading) !== 0) {
        $notes_text = trim($main_heading . "\n" . $notes_text);
    }

    // If a Word text box marked as prayer landed inside the notes flow, separate it
    // before rendering so it can use the dedicated Closing Prayer card style.
    $prayer = '';
    $notes_lines_for_prayer = array_values(array_filter(array_map('trim', explode("\n", $notes_text))));
    $kept_note_lines = array();

    foreach ($notes_lines_for_prayer as $note_line) {
        if (preg_match('/^\[\[(PRAYER|CALLOUT)\]\]\s*(Dear Lord|Lord,|Father,|Lord Jesus|Dear God)\b/i', $note_line)) {
            if ($prayer === '') {
                $prayer = surfside_tools_clean_prayer_text(trim(preg_replace('/^\[\[(PRAYER|CALLOUT)\]\]\s*/i', '', $note_line)));
            }
            continue;
        }

        $kept_note_lines[] = $note_line;
    }

    $notes_text = trim(implode("\n", $kept_note_lines));

    // Collect prayer/closing material after the body.
    // Stop as soon as duplicated sermon structure appears again.
    if ($body_end < count($lines)) {
        $after_body = array_slice($lines, $body_end);
        $clean_after_body = array();
        $started_prayer = false;

        foreach ($after_body as $line) {
            $line = trim(surfside_tools_collapse_repeated_sentence_text($line));

            if ($line === '') {
                continue;
            }

            if (!$started_prayer) {
                if (preg_match('/^(\[\[PRAYER\]\]\s*)?(Dear Lord|Lord,|Father,|Lord Jesus|Dear God)/i', $line)) {
                    $started_prayer = true;
                    $clean_after_body[] = preg_replace('/^\[\[PRAYER\]\]\s*/', '', $line);
                }

                continue;
            }

            if (surfside_tools_is_prayer_stop_line($line, $title, $series, $main_heading)) {
                break;
            }

            $clean_after_body[] = $line;
        }

        if (!empty($clean_after_body)) {
            $unique_after_body = array();
            $seen_after_body = array();

            foreach ($clean_after_body as $line) {
                $key = strtolower(preg_replace('/[^a-z0-9]+/i', '', $line));

                if ($key === '') {
                    continue;
                }

                if (!isset($seen_after_body[$key])) {
                    $seen_after_body[$key] = true;
                    $unique_after_body[] = $line;
                }
            }

            $prayer = surfside_tools_clean_prayer_text(trim(implode(' ', $unique_after_body)));
        }
    }

    return array(
        'title' => surfside_tools_title_case_if_needed($title),
        'series' => $series,
        'date' => $date,
        'main_heading' => $main_heading,
        'notes' => $notes_text,
        'prayer' => $prayer,
        'raw_lines' => $lines,
    );
}

function surfside_tools_title_case_if_needed($text) {
    $text = trim($text);

    if ($text === '') {
        return '';
    }

    // Convert obvious all-caps titles to nicer display title case.
    if (preg_match('/^[A-Z0-9\s\?\!\:\;\,\-’\'"&\.]+$/u', $text)) {
        return ucwords(strtolower($text));
    }

    return $text;
}

function surfside_tools_prepare_sermon_notes_plaintext($body_lines) {
    $books = surfside_tools_bible_books_regex();
    $ref_regex = '/((?:[1-3]\s*)?(?:' . $books . ')\s+\d+:\d+[a-z]?(?:\s*-\s*\d+[a-z]?)?(?:,\s*\d+[a-z]?(?:\s*-\s*\d+[a-z]?)?)*\s+[A-Z]{2,5}\b)/i';

    $out = array();
    $current = '';

    $flush_current = function () use (&$out, &$current) {
        if (trim($current) !== '') {
            $out[] = trim($current);
            $current = '';
        }
    };

    $add_text_with_references = function ($line) use (&$out, &$current, $flush_current, $ref_regex) {
        $parts = preg_split($ref_regex, $line, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

        foreach ($parts as $part) {
            $part = trim($part);

            if ($part === '') {
                continue;
            }

            if (preg_match($ref_regex, $part)) {
                $flush_current();
                $out[] = $part;
            } else {
                $current .= ($current ? ' ' : '') . $part;
            }
        }
    };

    foreach ($body_lines as $line) {
        $line = surfside_tools_clean_sermon_text($line);

        if ($line === '') {
            continue;
        }

        if (strpos($line, '[[CALLOUT]]') === 0 || strpos($line, '[[PRAYER]]') === 0 || surfside_tools_is_all_caps_heading($line) || surfside_tools_is_sermon_point_line($line) || preg_match('/^[–-]\s+/', $line)) {
            $flush_current();
            $out[] = $line;
            continue;
        }

        $add_text_with_references($line);
    }

    $flush_current();

    return trim(implode("\n", $out));
}

function surfside_tools_render_sermon_notes_html($notes_text) {
    $text = str_replace(array("\r\n", "\r"), "\n", $notes_text);
    $lines = array_values(array_filter(array_map('trim', explode("\n", $text))));

    $books = surfside_tools_bible_books_regex();
    $ref_regex = '/((?:[1-3]\s*)?(?:' . $books . ')\s+\d+:\d+[a-z]?(?:\s*-\s*\d+[a-z]?)?(?:,\s*\d+[a-z]?(?:\s*-\s*\d+[a-z]?)?)*\s+[A-Z]{2,5}\b)/i';

    $blocks = array();
    $current = '';
    $last_block_type = '';

    $flush_current = function () use (&$blocks, &$current, &$last_block_type) {
        if (trim($current) !== '') {
            $blocks[] = array('type' => 'paragraph', 'text' => trim($current));
            $last_block_type = 'paragraph';
            $current = '';
        }
    };

    $add_text = function ($chunk) use (&$blocks, &$current, &$last_block_type, $ref_regex, $flush_current) {
        $parts = preg_split($ref_regex, $chunk, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

        foreach ($parts as $part) {
            $part = trim($part);

            if ($part === '') {
                continue;
            }

            if (preg_match($ref_regex, $part)) {
                $flush_current();
                $blocks[] = array('type' => 'reference', 'text' => $part);
                $last_block_type = 'reference';
                continue;
            }

            if (preg_match('/^[–-]\s+(.+)$/u', $part, $m)) {
                $flush_current();
                $blocks[] = array('type' => 'question', 'text' => trim($m[1]));
                $last_block_type = 'question';
                continue;
            }

            $current .= ($current ? ' ' : '') . $part;
        }
    };

    $has_seen_point = false;

    foreach ($lines as $line) {
        if (strpos($line, '[[PRAYER]]') === 0) {
            $flush_current();
            $blocks[] = array('type' => 'prayer', 'text' => trim(substr($line, 10)));
            $last_block_type = 'prayer';
            continue;
        }

        if (strpos($line, '[[CALLOUT]]') === 0) {
            $flush_current();
            $callout_text = trim(substr($line, 11));
            if (preg_match('/^(Dear Lord|Lord,|Father,|Lord Jesus|Dear God)\b/i', $callout_text)) {
                $blocks[] = array('type' => 'prayer', 'text' => $callout_text);
                $last_block_type = 'prayer';
            } else {
                $blocks[] = array('type' => 'callout', 'text' => $callout_text);
                $last_block_type = 'callout';
            }
            continue;
        }

        if (preg_match('/^[–-]\s+(.+)$/u', $line, $m)) {
            $flush_current();
            $blocks[] = array('type' => 'question', 'text' => trim($m[1]));
            $last_block_type = 'question';
            continue;
        }

        if (surfside_tools_is_sermon_point_line($line)) {
            $flush_current();
            $blocks[] = array('type' => 'point', 'text' => $line);
            $last_block_type = 'point';
            $has_seen_point = true;
            continue;
        }

        if (
            !$has_seen_point &&
            surfside_tools_is_all_caps_heading($line) &&
            !surfside_tools_is_sermon_point_line($line)
        ) {
            $flush_current();
            $blocks[] = array('type' => 'main_heading', 'text' => $line);
            $last_block_type = 'main_heading';
            continue;
        }

        if (
            $has_seen_point &&
            surfside_tools_is_all_caps_heading($line) &&
            !surfside_tools_is_sermon_point_line($line)
        ) {
            $flush_current();
            $blocks[] = array('type' => 'callout', 'text' => $line);
            $last_block_type = 'callout';
            continue;
        }

        $add_text($line);
    }

    $flush_current();

    $html = '';

    foreach ($blocks as $block) {
        $safe = esc_html($block['text']);

        if ($block['type'] === 'main_heading') {
            $html .= '<h2 class="message-notes-main-heading">' . $safe . '</h2>';
        } elseif ($block['type'] === 'point') {
            $html .= '<h3 class="message-notes-point">' . $safe . '</h3>';
        } elseif ($block['type'] === 'reference') {
            $html .= '<p class="message-notes-reference">' . $safe . '</p>';
        } elseif ($block['type'] === 'question') {
            $html .= '<p class="message-notes-question">' . $safe . '</p>';
        } elseif ($block['type'] === 'callout') {
            $html .= '<p class="message-notes-callout">' . $safe . '</p>';
        } elseif ($block['type'] === 'prayer') {
            $html .= '<div class="message-prayer message-prayer-inline"><h3>Closing Prayer</h3><p>' . $safe . '</p></div>';
        } else {
            $html .= '<p>' . $safe . '</p>';
        }
    }

    return $html;
}

function surfside_tools_get_message_data() {
    $data = get_option('surfside_tools_message_current');

    if (!is_array($data)) {
        $data = array();
    }

    return array(
        'title' => isset($data['title']) ? $data['title'] : '',
        'series' => isset($data['series']) ? $data['series'] : '',
        'date' => isset($data['date']) ? $data['date'] : '',
        'main_heading' => isset($data['main_heading']) ? $data['main_heading'] : '',
        'notes' => isset($data['notes']) ? $data['notes'] : '',
        'prayer' => isset($data['prayer']) ? $data['prayer'] : '',
        'timestamp' => isset($data['timestamp']) ? (int) $data['timestamp'] : 0,
    );
}

function surfside_tools_backup_current_message() {
    $current = surfside_tools_get_message_data();

    if (!empty($current['title']) || !empty($current['notes'])) {
        update_option('surfside_tools_message_backup', $current, false);
    }
}

function surfside_tools_save_message($data) {
    surfside_tools_backup_current_message();

    $clean = array(
        'title' => sanitize_text_field(isset($data['title']) ? $data['title'] : ''),
        'series' => sanitize_text_field(isset($data['series']) ? $data['series'] : ''),
        'date' => sanitize_text_field(isset($data['date']) ? $data['date'] : ''),
        'main_heading' => sanitize_text_field(isset($data['main_heading']) ? $data['main_heading'] : ''),
        'notes' => sanitize_textarea_field(isset($data['notes']) ? $data['notes'] : ''),
        'prayer' => sanitize_textarea_field(isset($data['prayer']) ? $data['prayer'] : ''),
        'timestamp' => current_time('timestamp'),
    );

    update_option('surfside_tools_message_current', $clean, false);

    surfside_tools_purge_cache();

    return true;
}

function surfside_tools_notes_without_main_heading($notes, $main_heading) {
    $notes = (string) $notes;
    $main_heading = trim((string) $main_heading);

    if ($notes === '' || $main_heading === '') {
        return $notes;
    }

    $lines = preg_split('/\r\n|\r|\n/', $notes);

    if (!empty($lines) && strcasecmp(trim($lines[0]), $main_heading) === 0) {
        array_shift($lines);
        return trim(implode("\n", $lines));
    }

    return $notes;
}

function surfside_tools_message_shortcode() {
    $data = surfside_tools_get_message_data();

    ob_start();
    ?>
    <div class="surfside-message-notes">
        <?php if ($data['series']) : ?>
            <p class="message-series"><?php echo esc_html($data['series']); ?></p>
        <?php endif; ?>

        <?php if ($data['title']) : ?>
            <h1><?php echo esc_html($data['title']); ?></h1>
        <?php endif; ?>

        <?php if ($data['date']) : ?>
            <p class="message-date"><?php echo esc_html($data['date']); ?></p>
        <?php endif; ?>

        <?php if ($data['notes']) : ?>
            <?php $notes_for_display = surfside_tools_notes_without_main_heading($data['notes'], isset($data['main_heading']) ? $data['main_heading'] : ''); ?>
            <?php if (!empty($data['main_heading'])) : ?>
                <h2 class="message-notes-main-heading"><?php echo esc_html($data['main_heading']); ?></h2>
            <?php endif; ?>
            <div class="message-notes">
                <?php echo wp_kses_post(surfside_tools_render_sermon_notes_html($notes_for_display)); ?>
            </div>
        <?php else : ?>
            <p>No message notes have been posted yet.</p>
        <?php endif; ?>

        <?php if ($data['prayer']) : ?>
            <div class="message-prayer">
                <h3>Closing Prayer</h3>
                <p><?php echo esc_html($data['prayer']); ?></p>
            </div>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

add_shortcode('surfside_tools_message', 'surfside_tools_message_shortcode');

function surfside_sermon_docx_importer_shortcode() {
    surfside_tools_prevent_cache();
    if (!is_user_logged_in()) {
        $login_url = wp_login_url(get_permalink());
        return '<div class="weekly-update-login"><p>You must be logged in to import sermon notes.</p><a class="wp-block-button__link wp-element-button" href="' . esc_url($login_url) . '">Log In to Continue</a></div>';
    }

    if (!current_user_can('upload_files')) {
        return '<p>You do not have permission to upload sermon notes documents.</p>';
    }

    $message = '';
    $parsed = null;
    $preview_html = '';

    // Save edited preview.
    if (isset($_POST['surfside_tools_save_sermon_nonce']) && wp_verify_nonce($_POST['surfside_tools_save_sermon_nonce'], 'surfside_tools_save_sermon')) {
        $data = array(
            'title' => isset($_POST['message_title']) ? wp_unslash($_POST['message_title']) : '',
            'series' => isset($_POST['message_series']) ? wp_unslash($_POST['message_series']) : '',
            'date' => isset($_POST['message_date']) ? wp_unslash($_POST['message_date']) : '',
            'main_heading' => isset($_POST['message_main_heading']) ? wp_unslash($_POST['message_main_heading']) : '',
            'notes' => isset($_POST['message_notes']) ? wp_unslash($_POST['message_notes']) : '',
            'prayer' => isset($_POST['message_prayer']) ? wp_unslash($_POST['message_prayer']) : '',
        );

        surfside_tools_save_message($data);
        $message = surfside_tools_importer_notice('<strong>Sermon notes published.</strong>', 'success');

        $parsed = array(
            'title' => sanitize_text_field($data['title']),
            'series' => sanitize_text_field($data['series']),
            'date' => sanitize_text_field($data['date']),
            'main_heading' => sanitize_text_field($data['main_heading']),
            'notes' => sanitize_textarea_field($data['notes']),
            'prayer' => sanitize_textarea_field($data['prayer']),
        );
    }

    // Upload and parse sermon DOCX.
    if (isset($_POST['surfside_sermon_docx_importer_nonce']) && wp_verify_nonce($_POST['surfside_sermon_docx_importer_nonce'], 'surfside_sermon_docx_importer_upload')) {
        if (empty($_FILES['sermon_docx']['name'])) {
            $message = surfside_tools_importer_notice('Please choose a sermon notes DOCX file.', 'error');
        } else {
            $file = $_FILES['sermon_docx'];
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

            if ($extension !== 'docx') {
                $message = surfside_tools_importer_notice('Please upload a .docx file.', 'error');
            } else {
                require_once ABSPATH . 'wp-admin/includes/file.php';
                $upload = wp_handle_upload($file, array('test_form' => false));

                if (isset($upload['error'])) {
                    $message = surfside_tools_importer_notice(esc_html($upload['error']), 'error');
                } else {
                    $result = surfside_tools_parse_sermon_docx($upload['file']);

                    if (is_wp_error($result)) {
                        $message = surfside_tools_importer_notice($result->get_error_message(), 'error');
                    } else {
                        $parsed = $result;
                        $message = surfside_tools_importer_notice('Please review then click <strong>Save Sermon Notes</strong>.', 'success');
                    }

                    if (!empty($upload['file']) && file_exists($upload['file'])) {
                        @unlink($upload['file']);
                    }
                }
            }
        }
    }

    if (is_array($parsed)) {
        $preview_html = surfside_tools_render_sermon_notes_html(surfside_tools_notes_without_main_heading($parsed['notes'], isset($parsed['main_heading']) ? $parsed['main_heading'] : ''));
    }

    ob_start();
    ?>
    <div class="surfside-sermon-docx-importer">
        <h2>Import Sermon Notes</h2>
        <p>Upload the sermon notes as a <strong>.docx</strong> file. Surfside Tools will detect the title, series, date, sermon points, Scripture references, and reflection questions.</p>

        <?php echo $message; ?>

        <form method="post" enctype="multipart/form-data" class="surfside-sermon-docx-upload-form">
            <?php wp_nonce_field('surfside_sermon_docx_importer_upload', 'surfside_sermon_docx_importer_nonce'); ?>
            <p><label for="sermon_docx"><strong>Sermon Notes DOCX</strong></label><br><input type="file" id="sermon_docx" name="sermon_docx" accept=".docx" required></p>
            <p><button type="submit" class="wp-block-button__link wp-element-button">Upload & Preview</button></p>
        </form>

        <?php if (is_array($parsed)) : ?>
            <hr>
            <h3>Editable Preview</h3>
            <p>Review the message details and notes below. Make any quick edits, then save to the website.</p>

            <form method="post" class="surfside-sermon-save-form">
                <?php wp_nonce_field('surfside_tools_save_sermon', 'surfside_tools_save_sermon_nonce'); ?>

                <p><label><strong>Message Title</strong></label><br><input type="text" name="message_title" value="<?php echo esc_attr($parsed['title']); ?>" style="width:100%;max-width:650px;"></p>
                <p><label><strong>Series</strong></label><br><input type="text" name="message_series" value="<?php echo esc_attr($parsed['series']); ?>" style="width:100%;max-width:650px;"></p>
                <p><label><strong>Message Date</strong></label><br><input type="text" name="message_date" value="<?php echo esc_attr($parsed['date']); ?>" style="width:100%;max-width:300px;"></p>
                <p><label><strong>Main Heading</strong></label><br><input type="text" name="message_main_heading" value="<?php echo esc_attr($parsed['main_heading']); ?>" style="width:100%;max-width:850px;"></p>

                <p><label><strong>Message Notes</strong></label><br>
                <textarea name="message_notes" rows="18" style="width:100%;"><?php echo esc_textarea($parsed['notes']); ?></textarea></p>

                <p><label><strong>Prayer / Closing Text</strong></label><br>
                <textarea name="message_prayer" rows="5" style="width:100%;"><?php echo esc_textarea($parsed['prayer']); ?></textarea></p>

                <h3>Formatted Preview</h3>
                <div class="surfside-message-notes">
                    <?php if ($parsed['series']) : ?>
                        <p class="message-series"><?php echo esc_html($parsed['series']); ?></p>
                    <?php endif; ?>

                    <?php if ($parsed['title']) : ?>
                        <h1><?php echo esc_html($parsed['title']); ?></h1>
                    <?php endif; ?>

                    <?php if ($parsed['date']) : ?>
                        <p class="message-date"><?php echo esc_html($parsed['date']); ?></p>
                    <?php endif; ?>

                    <?php if (!empty($parsed['main_heading'])) : ?>
                        <h2 class="message-notes-main-heading"><?php echo esc_html($parsed['main_heading']); ?></h2>
                    <?php endif; ?>

                    <div class="message-notes">
                        <?php echo wp_kses_post($preview_html); ?>
                    </div>

                    <?php if (!empty($parsed['prayer'])) : ?>
                        <div class="message-prayer">
                            <h3>Closing Prayer</h3>
                            <p><?php echo esc_html($parsed['prayer']); ?></p>
                        </div>
                    <?php endif; ?>
                </div>

                <p><button type="submit" class="wp-block-button__link wp-element-button">Save Sermon Notes</button></p>
            </form>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

add_shortcode('surfside_sermon_docx_importer', 'surfside_sermon_docx_importer_shortcode');



/**
 * =========================================================
 * Surfside Tools - Unified Weekly Update Workflow
 *
 * Shortcode:
 * [surfside_weekly_update]
 *
 * Upload announcements and/or sermon notes together, review,
 * then publish either or both in one workflow.
 * =========================================================
 */

function surfside_tools_weekly_update_shortcode() {
    surfside_tools_prevent_cache();

    if (!is_user_logged_in()) {
        $login_url = wp_login_url(get_permalink());
        return '<div class="weekly-update-login"><p>You must be logged in to publish weekly updates.</p><a class="wp-block-button__link wp-element-button" href="' . esc_url($login_url) . '">Log In to Continue</a></div>';
    }

    if (!current_user_can('upload_files')) {
        return '<p>You do not have permission to upload weekly update documents.</p>';
    }

    $message = '';
    $announcements_items = array();
    $announcement_date_value = '';
    $sermon = null;
    $announcement_preview_html = '';
    $sermon_preview_html = '';

    // Publish reviewed content.
    if (isset($_POST['surfside_tools_weekly_publish_nonce']) && wp_verify_nonce($_POST['surfside_tools_weekly_publish_nonce'], 'surfside_tools_weekly_publish')) {
        $published = array();

        if (isset($_POST['publish_announcements']) && $_POST['publish_announcements'] === '1') {
            $announcement_date_value = isset($_POST['announcement_date']) ? sanitize_text_field(wp_unslash($_POST['announcement_date'])) : '';
            $raw_items = isset($_POST['announcement_items']) && is_array($_POST['announcement_items']) ? wp_unslash($_POST['announcement_items']) : array();

            $announcements_items = array();

            foreach ($raw_items as $item) {
                $clean = surfside_tools_clean_announcement_text(sanitize_textarea_field($item));

                if ($clean !== '') {
                    $announcements_items[] = $clean;
                }
            }

            if (!empty($announcements_items)) {
                surfside_tools_save_announcements($announcement_date_value, $announcements_items);
                $published[] = 'Announcements';
                $announcement_preview_html = surfside_tools_build_announcements_html($announcements_items);
            }
        }

        if (isset($_POST['publish_sermon']) && $_POST['publish_sermon'] === '1') {
            $sermon = array(
                'title' => isset($_POST['message_title']) ? wp_unslash($_POST['message_title']) : '',
                'series' => isset($_POST['message_series']) ? wp_unslash($_POST['message_series']) : '',
                'date' => isset($_POST['message_date']) ? wp_unslash($_POST['message_date']) : '',
                'main_heading' => isset($_POST['message_main_heading']) ? wp_unslash($_POST['message_main_heading']) : '',
                'notes' => isset($_POST['message_notes']) ? wp_unslash($_POST['message_notes']) : '',
                'prayer' => isset($_POST['message_prayer']) ? wp_unslash($_POST['message_prayer']) : '',
            );

            if (trim($sermon['title']) !== '' || trim($sermon['notes']) !== '') {
                surfside_tools_save_message($sermon);
                $published[] = 'Sermon Notes';
                $sermon['title'] = sanitize_text_field($sermon['title']);
                $sermon['series'] = sanitize_text_field($sermon['series']);
                $sermon['date'] = sanitize_text_field($sermon['date']);
                $sermon['main_heading'] = sanitize_text_field($sermon['main_heading']);
                $sermon['notes'] = sanitize_textarea_field($sermon['notes']);
                $sermon['prayer'] = sanitize_textarea_field($sermon['prayer']);
                $sermon_preview_html = surfside_tools_render_sermon_notes_html(surfside_tools_notes_without_main_heading($sermon['notes'], isset($sermon['main_heading']) ? $sermon['main_heading'] : ''));
            }
        }

        if (!empty($published)) {
            $message = surfside_tools_importer_notice('<strong>Weekly update published.</strong><br>' . esc_html(implode(' and ', $published)) . ' updated.', 'success');
        } else {
            $message = surfside_tools_importer_notice('Nothing was selected to publish.', 'error');
        }
    }

    // Restore announcements.
    if (isset($_POST['surfside_tools_restore_nonce']) && wp_verify_nonce($_POST['surfside_tools_restore_nonce'], 'surfside_tools_restore_announcements')) {
        $restored = surfside_tools_restore_previous_announcements();

        if (is_wp_error($restored)) {
            $message = surfside_tools_importer_notice($restored->get_error_message(), 'error');
        } else {
            $message = surfside_tools_importer_notice('Previous announcements restored successfully.', 'success');
        }
    }

    // Upload and parse selected files.
    if (isset($_POST['surfside_tools_weekly_upload_nonce']) && wp_verify_nonce($_POST['surfside_tools_weekly_upload_nonce'], 'surfside_tools_weekly_upload')) {
        $parsed_any = false;
        $errors = array();

        require_once ABSPATH . 'wp-admin/includes/file.php';

        // Announcements DOCX.
        if (!empty($_FILES['weekly_announcements_docx']['name'])) {
            $file = $_FILES['weekly_announcements_docx'];
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

            if ($extension !== 'docx') {
                $errors[] = 'Announcements file must be a .docx file.';
            } else {
                $upload = wp_handle_upload($file, array('test_form' => false));

                if (isset($upload['error'])) {
                    $errors[] = 'Announcements upload error: ' . esc_html($upload['error']);
                } else {
                    $result = surfside_tools_extract_numbered_announcements_from_docx($upload['file']);

                    if (is_wp_error($result)) {
                        $errors[] = 'Announcements error: ' . $result->get_error_message();
                    } elseif (!empty($result['items'])) {
                        $announcements_items = $result['items'];
                        $announcement_date_value = isset($result['date']) ? $result['date'] : '';
                        $announcement_preview_html = surfside_tools_build_announcements_html($announcements_items);
                        $parsed_any = true;
                    } else {
                        $errors[] = 'No numbered announcements were found.';
                    }

                    if (!empty($upload['file']) && file_exists($upload['file'])) {
                        @unlink($upload['file']);
                    }
                }
            }
        }

        // Sermon Notes DOCX.
        if (!empty($_FILES['weekly_sermon_docx']['name'])) {
            $file = $_FILES['weekly_sermon_docx'];
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

            if ($extension !== 'docx') {
                $errors[] = 'Sermon notes file must be a .docx file.';
            } else {
                $upload = wp_handle_upload($file, array('test_form' => false));

                if (isset($upload['error'])) {
                    $errors[] = 'Sermon notes upload error: ' . esc_html($upload['error']);
                } else {
                    $result = surfside_tools_parse_sermon_docx($upload['file']);

                    if (is_wp_error($result)) {
                        $errors[] = 'Sermon notes error: ' . $result->get_error_message();
                    } else {
                        $sermon = $result;
                        $sermon_preview_html = surfside_tools_render_sermon_notes_html(surfside_tools_notes_without_main_heading($sermon['notes'], isset($sermon['main_heading']) ? $sermon['main_heading'] : ''));
                        $parsed_any = true;
                    }

                    if (!empty($upload['file']) && file_exists($upload['file'])) {
                        @unlink($upload['file']);
                    }
                }
            }
        }

        if ($parsed_any) {
            $message = surfside_tools_importer_notice('Please review then click <strong>Publish Weekly Update</strong>.', 'success');
        } elseif (empty($errors)) {
            $message = surfside_tools_importer_notice('Please choose at least one DOCX file to upload.', 'error');
        }

        if (!empty($errors)) {
            $message .= surfside_tools_importer_notice(implode('<br>', array_map('esc_html', $errors)), 'error');
        }
    }

    $announcement_backup = get_option('surfside_tools_announcements_backup');

    ob_start();
    ?>
    <div class="surfside-weekly-update-tool">
        <h2>Surfside Weekly Update</h2>
        <p>Upload the weekly announcements and/or sermon notes. Leave either file blank if you only need to update one section.</p>

        <?php echo $message; ?>

        <?php if (empty($announcements_items) && !is_array($sermon)) : ?>
            <form method="post" enctype="multipart/form-data" class="surfside-weekly-update-upload-form">
                <?php wp_nonce_field('surfside_tools_weekly_upload', 'surfside_tools_weekly_upload_nonce'); ?>

                <div class="surfside-weekly-upload-grid">
                    <div class="surfside-weekly-upload-card">
                        <h3>Weekly Announcements</h3>
                        <p>Upload this week&#8217;s announcements.</p>
                        <input type="file" name="weekly_announcements_docx" accept=".docx">
                    </div>

                    <div class="surfside-weekly-upload-card">
                        <h3>Sermon Notes</h3>
                        <p>Upload this week&#8217;s sermon notes.</p>
                        <input type="file" name="weekly_sermon_docx" accept=".docx">
                    </div>
                </div>

                <p><button type="submit" class="wp-block-button__link wp-element-button">Upload & Review</button></p>
            </form>

            <details class="surfside-weekly-advanced">
                <summary>Advanced Options</summary>

                <?php if (!empty($announcement_backup) && is_array($announcement_backup)) : ?>
                    <h3>Restore Previous Announcements</h3>
                    <p>A backup from <?php echo esc_html(date_i18n('F j, Y g:i A', isset($announcement_backup['timestamp']) ? (int) $announcement_backup['timestamp'] : time())); ?> is available.</p>
                    <form method="post">
                        <?php wp_nonce_field('surfside_tools_restore_announcements', 'surfside_tools_restore_nonce'); ?>
                        <p><button type="submit" class="wp-block-button__link wp-element-button">Restore Previous Announcements</button></p>
                    </form>
                <?php else : ?>
                    <p>No announcement backup is currently available.</p>
                <?php endif; ?>
            </details>
        <?php endif; ?>

        <?php if (!empty($announcements_items) || is_array($sermon)) : ?>
            <hr>
            <h3>Review Weekly Update</h3>

            <form method="post" class="surfside-weekly-update-publish-form">
                <?php wp_nonce_field('surfside_tools_weekly_publish', 'surfside_tools_weekly_publish_nonce'); ?>

                <?php if (!empty($announcements_items)) : ?>
                    <section class="surfside-weekly-review-section">
                        <h3>Announcements</h3>

                        <input type="hidden" name="publish_announcements" value="1">

                        <p>
                            <label><strong>Announcement Date</strong></label><br>
                            <small>Please verify before publishing.</small><br>
                            <input type="text" name="announcement_date" value="<?php echo esc_attr($announcement_date_value); ?>" placeholder="July 4/5, 2026" style="max-width:260px;width:100%;">
                        </p>

                        <?php foreach ($announcements_items as $index => $item) : ?>
                            <div class="surfside-docx-edit-item">
                                <label><strong>Announcement #<?php echo esc_html($index + 1); ?></strong></label>
                                <textarea name="announcement_items[]" rows="4" style="width:100%;"><?php echo esc_textarea($item); ?></textarea>
                            </div>
                        <?php endforeach; ?>

                        <h4>Formatted Announcements Preview</h4>
                        <div class="surfside-announcements">
                            <?php if ($announcement_date_value) : ?>
                                <h2 class="announcement-date">Announcements for <?php echo esc_html($announcement_date_value); ?></h2>
                            <?php endif; ?>

                            <div class="announcement-content">
                                <?php echo wp_kses_post($announcement_preview_html); ?>
                            </div>
                        </div>
                    </section>
                <?php endif; ?>

                <?php if (is_array($sermon)) : ?>
                    <section class="surfside-weekly-review-section">
                        <h3>Sermon Notes</h3>

                        <input type="hidden" name="publish_sermon" value="1">

                        <p><label><strong>Message Title</strong></label><br><input type="text" name="message_title" value="<?php echo esc_attr($sermon['title']); ?>" style="width:100%;max-width:650px;"></p>
                        <p><label><strong>Series</strong></label><br><input type="text" name="message_series" value="<?php echo esc_attr($sermon['series']); ?>" style="width:100%;max-width:650px;"></p>
                        <p><label><strong>Message Date</strong></label><br><input type="text" name="message_date" value="<?php echo esc_attr($sermon['date']); ?>" style="width:100%;max-width:300px;"></p>
                        <p><label><strong>Main Heading</strong></label><br><input type="text" name="message_main_heading" value="<?php echo esc_attr($sermon['main_heading']); ?>" style="width:100%;max-width:850px;"></p>

                        <p><label><strong>Message Notes</strong></label><br>
                        <textarea name="message_notes" rows="18" style="width:100%;"><?php echo esc_textarea($sermon['notes']); ?></textarea></p>

                        <p><label><strong>Prayer / Closing Text</strong></label><br>
                        <textarea name="message_prayer" rows="5" style="width:100%;"><?php echo esc_textarea($sermon['prayer']); ?></textarea></p>

                        <h4>Formatted Sermon Preview</h4>
                        <div class="surfside-message-notes">
                            <?php if ($sermon['series']) : ?>
                                <p class="message-series"><?php echo esc_html($sermon['series']); ?></p>
                            <?php endif; ?>

                            <?php if ($sermon['title']) : ?>
                                <h1><?php echo esc_html($sermon['title']); ?></h1>
                            <?php endif; ?>

                            <?php if ($sermon['date']) : ?>
                                <p class="message-date"><?php echo esc_html($sermon['date']); ?></p>
                            <?php endif; ?>

                            <?php if (!empty($sermon['main_heading'])) : ?>
                                <h2 class="message-notes-main-heading"><?php echo esc_html($sermon['main_heading']); ?></h2>
                            <?php endif; ?>

                            <div class="message-notes">
                                <?php echo wp_kses_post($sermon_preview_html); ?>
                            </div>

                            <?php if (!empty($sermon['prayer'])) : ?>
                                <div class="message-prayer">
                                    <h3>Closing Prayer</h3>
                                    <p><?php echo esc_html($sermon['prayer']); ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </section>
                <?php endif; ?>

                <p class="surfside-weekly-publish-actions">
                    <button type="submit" class="wp-block-button__link wp-element-button">Publish Weekly Update</button>
                </p>
            </form>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

add_shortcode('surfside_weekly_update', 'surfside_tools_weekly_update_shortcode');




