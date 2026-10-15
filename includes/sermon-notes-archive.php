<?php
/**
 * Permanent, text-only sermon notes archive. The current weekly message remains
 * the authoritative live display; this table preserves published snapshots.
 */
if (!defined('ABSPATH')) { exit; }

function surfside_tools_sermon_archive_table() {
    global $wpdb;
    return $wpdb->prefix . 'surfside_sermon_notes_archive';
}

function surfside_tools_sermon_archive_install() {
    global $wpdb;
    $table = surfside_tools_sermon_archive_table();
    $charset = $wpdb->get_charset_collate();
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta("CREATE TABLE $table (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        message_key char(64) NOT NULL,
        sermon_date varchar(100) NOT NULL DEFAULT '',
        title text NOT NULL,
        series text NOT NULL,
        main_heading text NOT NULL,
        notes longtext NOT NULL,
        prayer longtext NOT NULL,
        published_at datetime NOT NULL,
        updated_at datetime NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY message_key (message_key),
        KEY sermon_date (sermon_date)
    ) $charset;");
    update_option('surfside_sermon_archive_schema', '1', false);
}

add_action('init', function () {
    if (get_option('surfside_sermon_archive_schema') !== '1') {
        surfside_tools_sermon_archive_install();
    }
}, 5);

/**
 * Both the standalone sermon importer and Weekly Update use the same current
 * message option. Hooking the completed option write preserves both workflows.
 */
function surfside_tools_sermon_archive_capture($old_value, $new_value) {
    if (!is_array($new_value)) return;
    $date = sanitize_text_field($new_value['date'] ?? '');
    $title = sanitize_text_field($new_value['title'] ?? '');
    $notes = sanitize_textarea_field($new_value['notes'] ?? '');
    if ($date === '' || ($title === '' && $notes === '')) return;

    global $wpdb;
    $table = surfside_tools_sermon_archive_table();
    $key = hash('sha256', strtolower(trim($date)) . '|' . strtolower(trim($title)));
    $now = current_time('mysql');
    $existing = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE message_key = %s", $key));
    $row = array(
        'sermon_date' => $date,
        'title' => $title,
        'series' => sanitize_text_field($new_value['series'] ?? ''),
        'main_heading' => sanitize_text_field($new_value['main_heading'] ?? ''),
        'notes' => $notes,
        'prayer' => sanitize_textarea_field($new_value['prayer'] ?? ''),
        'updated_at' => $now,
    );
    if ($existing) {
        $wpdb->update($table, $row, array('id' => (int) $existing));
    } else {
        $row['message_key'] = $key;
        $row['published_at'] = $now;
        $wpdb->insert($table, $row);
    }
}
add_action('update_option_surfside_tools_message_current', 'surfside_tools_sermon_archive_capture', 10, 2);
add_action('add_option_surfside_tools_message_current', function ($option, $value) {
    surfside_tools_sermon_archive_capture(null, $value);
}, 10, 2);
