<?php
/**
 * Plugin Name: Surfside Tools
 * Description: Custom Surfside website tools for weekly announcements and sermon notes publishing.
 * Version: 3.2.0
 * Author: Surfside Community Fellowship
 */

if (!defined('ABSPATH')) { exit; }

define('SURFSIDE_TOOLS_VERSION', '3.2.0');
define('SURFSIDE_TOOLS_PATH', plugin_dir_path(__FILE__));
define('SURFSIDE_TOOLS_URL', plugin_dir_url(__FILE__));

require_once SURFSIDE_TOOLS_PATH . 'includes/core-weekly-tools.php';
require_once SURFSIDE_TOOLS_PATH . 'includes/site-information.php';
require_once SURFSIDE_TOOLS_PATH . 'includes/ministries-model.php';
require_once SURFSIDE_TOOLS_PATH . 'includes/app-management.php';
require_once SURFSIDE_TOOLS_PATH . 'includes/featured-home-announcement.php';
remove_action('init', 'surfside_tools_ensure_featured_announcement_page', 84);
require_once SURFSIDE_TOOLS_PATH . 'includes/youversion.php';
require_once SURFSIDE_TOOLS_PATH . 'includes/youversion-settings.php';
require_once SURFSIDE_TOOLS_PATH . 'includes/youversion-mobile-api.php';
require_once SURFSIDE_TOOLS_PATH . 'includes/youversion-website.php';
require_once SURFSIDE_TOOLS_PATH . 'includes/contact-management.php';
require_once SURFSIDE_TOOLS_PATH . 'includes/prayer-list.php';
require_once SURFSIDE_TOOLS_PATH . 'includes/prayer-member-status.php';
require_once SURFSIDE_TOOLS_PATH . 'includes/contact-form.php';
require_once SURFSIDE_TOOLS_PATH . 'includes/mobile-api.php';
require_once SURFSIDE_TOOLS_PATH . 'includes/push-notifications.php';
require_once SURFSIDE_TOOLS_PATH . 'includes/design-system.php';
require_once SURFSIDE_TOOLS_PATH . 'includes/weekend-services.php';
require_once SURFSIDE_TOOLS_PATH . 'includes/contact-details.php';
require_once SURFSIDE_TOOLS_PATH . 'includes/plan-visit-details.php';
require_once SURFSIDE_TOOLS_PATH . 'includes/adult-ministries.php';
require_once SURFSIDE_TOOLS_PATH . 'includes/watch-live-stream.php';
require_once SURFSIDE_TOOLS_PATH . 'includes/homepage-ready-to-visit.php';
require_once SURFSIDE_TOOLS_PATH . 'includes/header.php';
require_once SURFSIDE_TOOLS_PATH . 'includes/footer.php';
require_once SURFSIDE_TOOLS_PATH . 'includes/firebase-staff-auth.php';
require_once SURFSIDE_TOOLS_PATH . 'includes/firebase-staff-login.php';
require_once SURFSIDE_TOOLS_PATH . 'includes/firebase-staff-login-fix.php';
require_once SURFSIDE_TOOLS_PATH . 'includes/staff-dashboard.php';
require_once SURFSIDE_TOOLS_PATH . 'includes/mobile-app-dashboard.php';
require_once SURFSIDE_TOOLS_PATH . 'includes/mobile-app-home-manager.php';
remove_action('init', 'surfside_tools_ensure_mobile_app_home_page', 83);
require_once SURFSIDE_TOOLS_PATH . 'includes/site-information-manager.php';
require_once SURFSIDE_TOOLS_PATH . 'includes/ministries-manager.php';
require_once SURFSIDE_TOOLS_PATH . 'includes/ministry-featured-manager.php';
require_once SURFSIDE_TOOLS_PATH . 'includes/ministry-ui-polish.php';
require_once SURFSIDE_TOOLS_PATH . 'includes/ministry-publishing.php';
require_once SURFSIDE_TOOLS_PATH . 'includes/site-management-hub.php';
require_once SURFSIDE_TOOLS_PATH . 'includes/site-settings-hub.php';
require_once SURFSIDE_TOOLS_PATH . 'includes/calendar-manager.php';
require_once SURFSIDE_TOOLS_PATH . 'includes/calendar-event-groups.php';
require_once SURFSIDE_TOOLS_PATH . 'includes/calendar-classifications.php';
require_once SURFSIDE_TOOLS_PATH . 'includes/ministry-events.php';
require_once SURFSIDE_TOOLS_PATH . 'includes/calendar-day-details.php';
require_once SURFSIDE_TOOLS_PATH . 'includes/calendar-simple-overflow-layout.php';
require_once SURFSIDE_TOOLS_PATH . 'includes/calendar-month-navigation.php';
require_once SURFSIDE_TOOLS_PATH . 'includes/calendar-print.php';
require_once SURFSIDE_TOOLS_PATH . 'includes/calendar-integration.php';
require_once SURFSIDE_TOOLS_PATH . 'includes/calendar-action-branding.php';
require_once SURFSIDE_TOOLS_PATH . 'includes/calendar-event-images.php';
require_once SURFSIDE_TOOLS_PATH . 'includes/today-at-surfside.php';
require_once SURFSIDE_TOOLS_PATH . 'includes/portal-foundation.php';
require_once SURFSIDE_TOOLS_PATH . 'includes/calendar-suggestions.php';
require_once SURFSIDE_TOOLS_PATH . 'includes/calendar-suggestion-duplicates.php';
require_once SURFSIDE_TOOLS_PATH . 'includes/calendar-suggestion-completion.php';
require_once SURFSIDE_TOOLS_PATH . 'includes/calendar-suggestion-one-click.php';
require_once SURFSIDE_TOOLS_PATH . 'includes/calendar-suggestion-locations.php';
require_once SURFSIDE_TOOLS_PATH . 'includes/calendar-suggestion-location-search-fix.php';
require_once SURFSIDE_TOOLS_PATH . 'includes/calendar-manager-refinements.php';
require_once SURFSIDE_TOOLS_PATH . 'includes/saved-places-settings.php';
require_once SURFSIDE_TOOLS_PATH . 'includes/productivity-finish.php';
require_once SURFSIDE_TOOLS_PATH . 'includes/productivity-modal-tracking.php';
require_once SURFSIDE_TOOLS_PATH . 'includes/frontend-settings.php';
require_once SURFSIDE_TOOLS_PATH . 'includes/church-settings-polish.php';
require_once SURFSIDE_TOOLS_PATH . 'includes/google-places-regression-fix.php';
require_once SURFSIDE_TOOLS_PATH . 'includes/final-productivity-fixes.php';
require_once SURFSIDE_TOOLS_PATH . 'includes/weekly-update-native-google-places.php';
require_once SURFSIDE_TOOLS_PATH . 'includes/location-clarity.php';
require_once SURFSIDE_TOOLS_PATH . 'includes/homepage-manager.php';
require_once SURFSIDE_TOOLS_PATH . 'includes/homepage-life-section.php';
require_once SURFSIDE_TOOLS_PATH . 'includes/homepage-page-registration-fix.php';
require_once SURFSIDE_TOOLS_PATH . 'includes/homepage-manager-compact.php';
require_once SURFSIDE_TOOLS_PATH . 'includes/homepage-manager-drag-fix.php';
require_once SURFSIDE_TOOLS_PATH . 'includes/homepage-carousel-cache-sync.php';
require_once SURFSIDE_TOOLS_PATH . 'includes/homepage-carousel-full-width.php';
require_once SURFSIDE_TOOLS_PATH . 'includes/visual-utilities.php';
require_once SURFSIDE_TOOLS_PATH . 'includes/visual-utilities-settings.php';
require_once SURFSIDE_TOOLS_PATH . 'includes/integrations-page-polish.php';
require_once SURFSIDE_TOOLS_PATH . 'includes/integrations-layout-finish.php';
require_once SURFSIDE_TOOLS_PATH . 'includes/dashboard-intelligence.php';
require_once SURFSIDE_TOOLS_PATH . 'includes/member-engagement.php';
require_once SURFSIDE_TOOLS_PATH . 'includes/prayer-dashboard-alert.php';
require_once SURFSIDE_TOOLS_PATH . 'includes/dashboard-recent-activity.php';
require_once SURFSIDE_TOOLS_PATH . 'includes/dashboard-polish.php';
require_once SURFSIDE_TOOLS_PATH . 'includes/admin.php';