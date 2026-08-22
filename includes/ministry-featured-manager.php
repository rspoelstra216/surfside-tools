<?php
/** Featured Ministry control for the dedicated Ministry Manager. */
if (!defined('ABSPATH')) { exit; }

function surfside_tools_add_featured_ministry_manager_fields($output, $tag) {
    if ($tag !== 'surfside_staff_ministries_manager' || !is_user_logged_in() || !current_user_can('manage_options')) {
        return $output;
    }

    $ministries = array_values((array) surfside_tools_get_ministries());
    $index = 0;

    $output = preg_replace_callback(
        '~(<label class="surfside-information-field surfside-information-ministry-description">)~',
        function ($matches) use (&$index, $ministries) {
            $ministry = $ministries[$index] ?? array();
            $featured = array_key_exists('featured', $ministry) ? !empty($ministry['featured']) : true;
            $field = '<div class="surfside-ministry-featured">'
                . '<input type="hidden" name="ministries[' . esc_attr($index) . '][featured]" value="0">'
                . '<label class="surfside-information-checkbox">'
                . '<input type="checkbox" name="ministries[' . esc_attr($index) . '][featured]" value="1" ' . checked($featured, true, false) . '> '
                . '<strong>Featured Ministry</strong>'
                . '</label>'
                . '<span class="surfside-staff-muted">Show this ministry in the featured Serve &amp; Get Involved block.</span>'
                . '</div>';
            $index++;
            return $field . $matches[1];
        },
        $output,
        count($ministries)
    );

    // New ministries start non-featured until staff explicitly chooses otherwise.
    $output = preg_replace(
        '~(<template data-ministry-template>.*?<fieldset class="surfside-ministry-audiences">.*?</fieldset>)(<label class="surfside-information-field surfside-information-ministry-description">)~s',
        '$1<div class="surfside-ministry-featured"><input type="hidden" name="ministries[__INDEX__][featured]" value="0"><label class="surfside-information-checkbox"><input type="checkbox" name="ministries[__INDEX__][featured]" value="1"> <strong>Featured Ministry</strong></label><span class="surfside-staff-muted">Show this ministry in the featured Serve &amp; Get Involved block.</span></div>$2',
        $output,
        1
    );

    $output .= '<style>.surfside-ministry-featured{grid-column:1/-1;display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin:2px 0}.surfside-ministry-featured .surfside-staff-muted{font-size:.86rem}</style>';
    return $output;
}
add_filter('do_shortcode_tag', 'surfside_tools_add_featured_ministry_manager_fields', 20, 2);
