<?php
/**
 * Weekly DOCX release queue. Staff can prepare content during the week while
 * the website and app keep the currently published documents until Saturday
 * 5:00 PM America/New_York.
 */
if (!defined('ABSPATH')) { exit; }

const SURFSIDE_TOOLS_WEEKLY_PENDING_OPTION = 'surfside_tools_weekly_pending';
const SURFSIDE_TOOLS_WEEKLY_RELEASE_HOOK = 'surfside_tools_weekly_activate_pending';

/** The church is in Florida; use an IANA zone so DST transitions are correct. */
function surfside_tools_weekly_release_timezone() {
    return new DateTimeZone('America/New_York');
}

/** Returns a UTC Unix epoch, strictly after $now. */
function surfside_tools_weekly_next_release_at($now = null) {
    $now = $now === null ? time() : (int) $now;
    $local = (new DateTimeImmutable('@' . $now))
        ->setTimezone(surfside_tools_weekly_release_timezone());
    // Monday=1 ... Saturday=6; this week's Saturday or the next.
    $days = (6 - (int) $local->format('N') + 7) % 7;
    $release = $local->setTime(17, 0, 0)->modify('+' . $days . ' days');
    if ($release->getTimestamp() <= $now) {
        $release = $release->modify('+7 days');
    }
    return $release->getTimestamp();
}

function surfside_tools_weekly_release_label($timestamp) {
    return wp_date('l, F j, Y \a\t g:i A T', (int) $timestamp, surfside_tools_weekly_release_timezone());
}

function surfside_tools_weekly_pending() {
    $pending = get_option(SURFSIDE_TOOLS_WEEKLY_PENDING_OPTION, array());
    return is_array($pending) && !empty($pending['publish_at'])
        ? $pending : array();
}

/**
 * Save exactly the reviewed sections. Replacing one section preserves the
 * other section of a previously staged release.
 */
function surfside_tools_weekly_schedule($announcements = null, $sermon = null, $now = null) {
    if (!is_user_logged_in() || !current_user_can('upload_files')) {
        return new WP_Error('surfside_weekly_not_allowed', 'You do not have permission to schedule weekly documents.');
    }
    // Refuse empty schedules. A null value means "keep the existing staged
    // section"; a non-null array means replace that section.
    if ($announcements === null && $sermon === null) {
        return new WP_Error('surfside_weekly_empty', 'Choose announcements or sermon notes to schedule.');
    }

    $now = $now === null ? time() : (int) $now;
    surfside_tools_weekly_maybe_activate($now);
    $previous = surfside_tools_weekly_pending();
    $release = surfside_tools_weekly_next_release_at($now);
    $pending = ($previous && (int) ($previous['publish_at'] ?? 0) === $release)
        ? $previous : array();

    if ($announcements !== null) {
        $items = array();
        foreach ((array) ($announcements['items'] ?? array()) as $item) {
            $clean = surfside_tools_clean_announcement_text(sanitize_textarea_field($item));
            if ($clean !== '') $items[] = $clean;
        }
        if (!$items) {
            return new WP_Error('surfside_weekly_announcements_empty', 'No announcements were available to schedule.');
        }
        $pending['announcements'] = array(
            'announcement_date' => sanitize_text_field($announcements['announcement_date'] ?? ''),
            'items' => $items,
        );
    }

    if ($sermon !== null) {
        $clean = array();
        foreach (array('title', 'series', 'date', 'main_heading', 'notes', 'prayer') as $field) {
            $clean[$field] = ($field === 'notes' || $field === 'prayer')
                ? sanitize_textarea_field($sermon[$field] ?? '')
                : sanitize_text_field($sermon[$field] ?? '');
        }
        if ($clean['title'] === '' && $clean['notes'] === '') {
            return new WP_Error('surfside_weekly_sermon_empty', 'No sermon notes were available to schedule.');
        }
        $pending['sermon'] = $clean;
    }

    $pending['publish_at'] = $release;
    $pending['updated_at'] = $now;
    unset($pending['retry_after'], $pending['last_error']);
    // Keep the single release atomic as a draft: never write public current
    // values or the sermon archive at the time of scheduling.
    $saved = update_option(SURFSIDE_TOOLS_WEEKLY_PENDING_OPTION, $pending, false);
    if (!$saved && get_option(SURFSIDE_TOOLS_WEEKLY_PENDING_OPTION) !== $pending) {
        return new WP_Error('surfside_weekly_queue_failed', 'The scheduled update could not be saved.');
    }

    wp_clear_scheduled_hook(SURFSIDE_TOOLS_WEEKLY_RELEASE_HOOK);
    if (!wp_schedule_single_event($release, SURFSIDE_TOOLS_WEEKLY_RELEASE_HOOK)) {
        // A request-time fallback exists, but do not imply the requested
        // publication time is guaranteed when WP-Cron cannot be scheduled.
        $pending['last_error'] = 'Automatic release event could not be scheduled.';
        update_option(SURFSIDE_TOOLS_WEEKLY_PENDING_OPTION, $pending, false);
        return new WP_Error('surfside_weekly_cron_failed',
            'Documents were staged, but the automatic release event could not be scheduled. Contact an administrator.');
    }
    return $pending;
}

/**
 * Release once the timestamp has passed. The cron hook runs on site traffic;
 * calling this from public data readers is an independent delayed-cron
 * fallback, useful when staff publishes have been staged for days.
 */
function surfside_tools_weekly_maybe_activate($now = null) {
    static $running = false;
    if ($running) return false;

    $now = $now === null ? time() : (int) $now;
    $pending = surfside_tools_weekly_pending();
    if (!$pending || (int) $pending['publish_at'] > $now ||
        (!empty($pending['retry_after']) && (int) $pending['retry_after'] > $now)) {
        return false;
    }

    // A cron invocation and a public REST/page read may arrive at the same
    // instant. A short database-backed lock avoids double publication and
    // rotating backups twice across PHP workers.
    $lock_name = 'surfside_tools_weekly_release_lock';
    if (!add_option($lock_name, $now, '', false)) {
        $lock_time = (int) get_option($lock_name, 0);
        if ($lock_time && $now - $lock_time <= 120) return false;
        delete_option($lock_name); // Release abandoned locks after 2 minutes.
        if (!add_option($lock_name, $now, '', false)) return false;
    }

    // Re-read under the lock: another request may have finished publication.
    $pending = surfside_tools_weekly_pending();
    if (!$pending || (int) $pending['publish_at'] > $now ||
        (!empty($pending['retry_after']) && (int) $pending['retry_after'] > $now)) {
        delete_option($lock_name);
        return false;
    }

    $running = true;
    $error = null;
    // Attempt to write the message and archive before changing announcements.
    // If archival storage is unavailable, existing public content stays live.
    if (!empty($pending['sermon'])) {
        $result = surfside_tools_save_message($pending['sermon']);
        if (is_wp_error($result)) $error = $result;
    }

    if (!$error && !empty($pending['announcements'])) {
        $item = $pending['announcements'];
        $result = surfside_tools_save_announcements($item['announcement_date'], $item['items']);
        if (is_wp_error($result)) $error = $result;
    }

    if ($error) {
        $pending['retry_after'] = $now + 300;
        $pending['last_error'] = sanitize_text_field($error->get_error_message());
        update_option(SURFSIDE_TOOLS_WEEKLY_PENDING_OPTION, $pending, false);
        wp_clear_scheduled_hook(SURFSIDE_TOOLS_WEEKLY_RELEASE_HOOK);
        wp_schedule_single_event($pending['retry_after'], SURFSIDE_TOOLS_WEEKLY_RELEASE_HOOK);
        $running = false;
        delete_option($lock_name);
        return $error;
    }

    delete_option(SURFSIDE_TOOLS_WEEKLY_PENDING_OPTION);
    wp_clear_scheduled_hook(SURFSIDE_TOOLS_WEEKLY_RELEASE_HOOK);
    surfside_tools_purge_cache();
    $running = false;
    delete_option($lock_name);
    return true;
}
add_action(SURFSIDE_TOOLS_WEEKLY_RELEASE_HOOK, 'surfside_tools_weekly_maybe_activate');

/** Cancel the draft without changing any public document or archive. */
function surfside_tools_weekly_cancel_pending() {
    if (!is_user_logged_in() || !current_user_can('upload_files')) {
        return new WP_Error('surfside_weekly_not_allowed', 'You do not have permission to cancel a scheduled update.');
    }
    if (!surfside_tools_weekly_pending()) {
        return new WP_Error('surfside_weekly_nothing_to_cancel', 'There is no scheduled update to cancel.');
    }
    delete_option(SURFSIDE_TOOLS_WEEKLY_PENDING_OPTION);
    wp_clear_scheduled_hook(SURFSIDE_TOOLS_WEEKLY_RELEASE_HOOK);
    return true;
}
