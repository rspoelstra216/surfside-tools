<?php
/**
 * Permanent text-only sermon note storage.
 *
 * The archive is written only when staff explicitly publish a message. It does
 * not run migrations or database queries during ordinary public page requests.
 */
if (!defined('ABSPATH')) {
    exit;
}

const SURFSIDE_TOOLS_SERMON_ARCHIVE_SCHEMA_OPTION = 'surfside_tools_sermon_archive_schema';
const SURFSIDE_TOOLS_SERMON_ARCHIVE_SCHEMA_VERSION = 1;

function surfside_tools_sermon_archive_table() {
    global $wpdb;
    return $wpdb->prefix . 'surfside_sermon_archive';
}

/** Create the small archive table on the first staff message publish only. */
function surfside_tools_sermon_archive_ensure_table() {
    if ((int) get_option(SURFSIDE_TOOLS_SERMON_ARCHIVE_SCHEMA_OPTION, 0) >= SURFSIDE_TOOLS_SERMON_ARCHIVE_SCHEMA_VERSION) {
        return true;
    }

    global $wpdb;
    $table = surfside_tools_sermon_archive_table();
    $collation = $wpdb->get_charset_collate();

    if (!function_exists('dbDelta')) {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    }

    dbDelta("CREATE TABLE {$table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        message_key char(64) NOT NULL,
        message_date varchar(100) NOT NULL DEFAULT '',
        title varchar(255) NOT NULL DEFAULT '',
        series varchar(255) NOT NULL DEFAULT '',
        main_heading text NOT NULL,
        notes longtext NOT NULL,
        prayer longtext NOT NULL,
        created_at datetime NOT NULL,
        updated_at datetime NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY message_key (message_key),
        KEY message_date (message_date)
    ) {$collation};");

    $found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table)));
    if ($found !== $table) {
        return new WP_Error('surfside_sermon_archive_unavailable', 'The sermon notes archive could not be initialized. Current notes were not replaced.');
    }

    update_option(SURFSIDE_TOOLS_SERMON_ARCHIVE_SCHEMA_OPTION, SURFSIDE_TOOLS_SERMON_ARCHIVE_SCHEMA_VERSION, false);
    return true;
}

/**
 * One record per sermon (date + title), not one record per upload.
 * Undated entries use a content fingerprint to avoid merging unrelated weeks.
 */
function surfside_tools_sermon_archive_key($message) {
    $date = strtolower(trim((string) ($message['date'] ?? '')));
    $title = strtolower(trim((string) ($message['title'] ?? '')));
    $notes = trim((string) ($message['notes'] ?? ''));

    $date = preg_replace('/\s+/', ' ', $date);
    $title = preg_replace('/\s+/', ' ', $title);

    $identity = $date . "\n" . $title;
    if ($date === '' || $title === '') {
        $identity .= "\n" . hash('sha256', $notes);
    }

    return hash('sha256', $identity);
}

/** Insert or update a published sermon without accumulating repeated copies. */
function surfside_tools_sermon_archive_store($message) {
    if (!is_array($message)) {
        return true;
    }
    $title = sanitize_text_field($message['title'] ?? '');
    $notes = sanitize_textarea_field($message['notes'] ?? '');
    if (trim($title) === '' && trim($notes) === '') {
        return true;
    }

    global $wpdb;
    $table = surfside_tools_sermon_archive_table();
    $key = surfside_tools_sermon_archive_key($message);
    $now = current_time('mysql', true);
    $data = array(
        'message_date' => sanitize_text_field($message['date'] ?? ''),
        'title' => $title,
        'series' => sanitize_text_field($message['series'] ?? ''),
        'main_heading' => sanitize_text_field($message['main_heading'] ?? ''),
        'notes' => $notes,
        'prayer' => sanitize_textarea_field($message['prayer'] ?? ''),
        'updated_at' => $now,
    );

    $existing_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE message_key = %s", $key));
    if ($existing_id) {
        $written = $wpdb->update($table, $data, array('id' => (int) $existing_id));
    } else {
        $data['message_key'] = $key;
        $data['created_at'] = $now;
        $written = $wpdb->insert($table, $data);
    }

    if ($written === false) {
        return new WP_Error('surfside_sermon_archive_save_failed', 'The sermon notes could not be archived. Current notes were not replaced.');
    }
    return true;
}

/**
 * Called by the existing Weekly Update and standalone sermon publishing paths.
 * Preserve the rotating backup and current message while they are still present,
 * then store the newly published notes. No new weekly staff steps are needed.
 */
function surfside_tools_sermon_archive_on_publish($message) {
    $ready = surfside_tools_sermon_archive_ensure_table();
    if (is_wp_error($ready)) {
        return $ready;
    }

    $messages = array(
        get_option('surfside_tools_message_backup', array()),
        surfside_tools_get_message_data(),
        $message,
    );

    foreach ($messages as $entry) {
        $result = surfside_tools_sermon_archive_store($entry);
        if (is_wp_error($result)) {
            return $result;
        }
    }
    return true;
}
