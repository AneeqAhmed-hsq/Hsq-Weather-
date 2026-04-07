<?php
// Prevent direct access
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Delete all plugin options
delete_option('hsq_weather_settings');

// Delete all transients (cache)
global $wpdb;
$query = $wpdb->prepare("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", '_transient_hsq_weather_%');
$transient_names = $wpdb->get_col($query);
foreach ($transient_names as $option_name) {
    $transient_name = str_replace('_transient_', '', $option_name);
    delete_transient($transient_name);
}

// Delete user meta for theme preferences
delete_metadata('user', 0, 'hsq_weather_user_theme', '', true);