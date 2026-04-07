<?php
// Prevent direct access
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Delete all plugin options
delete_option('hsq_weather_settings');

// Delete all transients (cache)
global $wpdb;
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_hsq_weather_%'");
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_hsq_weather_%'");

// Delete user meta for theme preferences
delete_metadata('user', 0, 'hsq_weather_user_theme', '', true);