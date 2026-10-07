<?php
/** Automatically notify opted-in devices when Twitch starts a fresh broadcast. */
if (!defined('ABSPATH')) { exit; }

function surfside_tools_live_push_schedule($schedules) {
    $schedules['surfside_minute'] = array('interval'=>60, 'display'=>'Every minute (Surfside)');
    return $schedules;
}
add_filter('cron_schedules', 'surfside_tools_live_push_schedule');
function surfside_tools_live_push_ensure_schedule() {
    if (!wp_next_scheduled('surfside_tools_live_push_check')) {
        wp_schedule_event(time()+60, 'surfside_minute', 'surfside_tools_live_push_check');
    }
}
add_action('init', 'surfside_tools_live_push_ensure_schedule');
function surfside_tools_live_push_deactivate() {
    wp_clear_scheduled_hook('surfside_tools_live_push_check');
}

/** Pure decision: never alert from unknown/stale status or a long-running stream. */
function surfside_tools_live_push_candidate($status, $state, $now) {
    if (($status['status'] ?? '') !== 'live' || ($status['is_live'] ?? null) !== true) return '';
    $id = (string)($status['stream_id'] ?? '');
    $channel = (string)($status['channel'] ?? '');
    $started = strtotime((string)($status['started_at'] ?? ''));
    $checked = strtotime((string)($status['checked_at'] ?? ''));
    if ($id === '' || $channel === '' || !$started || !$checked) return '';
    if ($started > $now+30 || $now-$started > 600 || $checked > $now+30 || $now-$checked > 90) return '';
    $key = hash('sha256', $channel.'|'.$id);
    if (isset($state['seen'][$key])) return '';
    // Twitch may assign a new ID after a production reconnect.
    if (!empty($state['last_attempt']) && $now-(int)$state['last_attempt'] < 7200) return '';
    return $key;
}

function surfside_tools_live_push_check() {
    $now = time();
    $lock = 'surfside_tools_live_push_lock';
    // The durable claim below prevents replay even after a crashed send.
    if ((int)get_option($lock, 0) < $now-1800) delete_option($lock);
    if (!add_option($lock, $now, '', false)) return;
    try {
        $status = surfside_tools_twitch_live_status();
        $state = get_option('surfside_tools_live_push_state', array());
        $state = is_array($state) ? $state : array();
        $key = surfside_tools_live_push_candidate($status, $state, $now);
        update_option('surfside_tools_live_push_health', array(
            'checked_at'=>$now, 'status'=>$status['status'] ?? 'unknown',
        ), false);
        if ($key === '') return;
        $seen = is_array($state['seen'] ?? null) ? $state['seen'] : array();
        $seen = array_filter($seen, function($at) use ($now) { return (int)$at >= $now-30*DAY_IN_SECONDS; });
        $seen[$key] = $now;
        // Claim before outbound HTTP: ambiguous failures are never retried as duplicate alerts.
        update_option('surfside_tools_live_push_state', array('seen'=>$seen, 'last_attempt'=>$now), false);
        $result = surfside_tools_push_send(
            'Surfside is live',
            'Join us for worship. Tap to watch the livestream.',
            'worship',
            array('livestream')
        );
        $outcome = is_wp_error($result) ? $result->get_error_code() : 'submitted';
        update_option('surfside_tools_live_push_health', array(
            'checked_at'=>$now, 'status'=>'live', 'attempted_at'=>$now, 'outcome'=>$outcome,
        ), false);
        if (is_wp_error($result) && $outcome !== 'surfside_push_no_devices') {
            error_log('Surfside automatic livestream push failed: '.$outcome);
        }
    } finally {
        delete_option($lock);
    }
}
add_action('surfside_tools_live_push_check', 'surfside_tools_live_push_check');
