<?php
/** Surface Church Prayer List review work in the staff dashboard. */
if (!defined('ABSPATH')) { exit; }

function surfside_tools_prayer_dashboard_alert_script() {
    if (!is_user_logged_in() || !current_user_can('upload_files') || !function_exists('surfside_tools_prayer_list_pending_count')) return;
    $count = surfside_tools_prayer_list_pending_count();
    if ($count < 1) return;
    $url = surfside_tools_prayer_list_page_url();
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function(){
      var shell=document.querySelector('.surfside-staff-shell');
      if(!shell) return;
      var summary=shell.querySelector('.surfside-dashboard-summary');
      var message=<?php echo wp_json_encode($count === 1 ? '1 prayer request is awaiting review.' : $count . ' prayer requests are awaiting review.'); ?>;
      var url=<?php echo wp_json_encode($url); ?>;
      if(summary && summary.classList.contains('surfside-dashboard-summary-good')){
        summary.classList.remove('surfside-dashboard-summary-good'); summary.classList.add('surfside-dashboard-summary-attention');
        summary.innerHTML='<h3>1 item needs attention</h3><p>Choose an item below to open the page where it can be resolved.</p><ul class="surfside-dashboard-alert-list"></ul>';
      } else if(summary){
        var h=summary.querySelector('h3'); if(h){var n=parseInt(h.textContent,10)||0;n++;h.textContent=n+' items need attention';}
      }
      var list=summary&&summary.querySelector('.surfside-dashboard-alert-list');
      if(list){var li=document.createElement('li');li.className='surfside-dashboard-alert-warning';var a=document.createElement('a');a.href=url;a.innerHTML='<span class="surfside-dashboard-alert-dot" aria-hidden="true"></span>'+message;li.appendChild(a);list.appendChild(li);}
    });
    </script>
    <?php
}
add_action('wp_footer','surfside_tools_prayer_dashboard_alert_script',40);

function surfside_tools_prayer_dashboard_review_override($output) {
    if (empty($_GET['surfside-prayer-review']) || sanitize_key(wp_unslash($_GET['surfside-prayer-review'])) !== '1') return $output;
    if (!is_user_logged_in()) return function_exists('surfside_tools_staff_login_box') ? surfside_tools_staff_login_box() : $output;
    if (!current_user_can('upload_files')) return '<p>You do not have permission to access Surfside staff tools.</p>';
    return '<div class="surfside-staff-shell">' . surfside_tools_prayer_list_review_panel() . '</div>';
}
add_filter('do_shortcode_tag', function($output,$tag){
    return $tag === 'surfside_staff_dashboard' ? surfside_tools_prayer_dashboard_review_override($output) : $output;
}, 20, 2);
