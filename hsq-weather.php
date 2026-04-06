<?php
/**
 * Plugin Name: HSQ-Weather
 * Plugin URI: https://github.com/Aneeqahmed-hsq/hsq-weather
 * Description: Display weather for multiple cities with dark/light theme, custom CSS, wind speed, humidity, and weather icons. No API key required! Supports small cities like Neelum, Muzaffarabad.
 * Version: 1.0.0
 * Author: Aneeq Ahmed
 * Author URI: https://github.com/Aneeqahmed-hsq
 * License: GPL v2 or later
 * Text Domain: hsq-weather
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('HSQ_WEATHER_VERSION', '1.0.0');
define('HSQ_WEATHER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('HSQ_WEATHER_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include required files
require_once HSQ_WEATHER_PLUGIN_DIR . 'includes/weather-api.php';
require_once HSQ_WEATHER_PLUGIN_DIR . 'admin/settings-page.php';
require_once HSQ_WEATHER_PLUGIN_DIR . 'admin/city-manager.php';
require_once HSQ_WEATHER_PLUGIN_DIR . 'public/shortcode.php';

// Activation hook
register_activation_hook(__FILE__, 'hsq_weather_activate');
function hsq_weather_activate() {
    // Create default settings
    if (!get_option('hsq_weather_settings')) {
        $defaults = array(
            'theme' => 'light',
            'unit' => 'celsius',
            'refresh_time' => 30,
            'custom_css' => '',
            'show_wind' => true,
            'show_humidity' => true,
            'show_icons' => true
        );
        add_option('hsq_weather_settings', $defaults);
    }
    
    // Create default cities array
    if (!get_option('hsq_weather_cities')) {
        $default_cities = array();
        add_option('hsq_weather_cities', $default_cities);
    }
}

// Enqueue styles and scripts
add_action('wp_enqueue_scripts', 'hsq_weather_enqueue_assets');
function hsq_weather_enqueue_assets() {
    wp_enqueue_style('hsq-weather-style', HSQ_WEATHER_PLUGIN_URL . 'public/css/style.css', array(), HSQ_WEATHER_VERSION);
    wp_enqueue_script('hsq-weather-script', HSQ_WEATHER_PLUGIN_URL . 'public/js/script.js', array('jquery'), HSQ_WEATHER_VERSION, true);
    
    // Pass settings to JavaScript
    $settings = get_option('hsq_weather_settings', array());
    wp_localize_script('hsq-weather-script', 'hsq_weather_ajax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'refresh_time' => isset($settings['refresh_time']) ? $settings['refresh_time'] * 60 * 1000 : 30 * 60 * 1000
    ));
}

// Add admin menu
add_action('admin_menu', 'hsq_weather_add_admin_menu');
function hsq_weather_add_admin_menu() {
    add_menu_page(
        'HSQ-Weather',
        'HSQ-Weather',
        'manage_options',
        'hsq-weather',
        'hsq_weather_settings_page',
        'dashicons-cloud',
        30
    );
    
    add_submenu_page(
        'hsq-weather',
        'Manage Cities',
        'Manage Cities',
        'manage_options',
        'hsq-weather-cities',
        'hsq_weather_city_manager_page'
    );
}

// AJAX handlers for weather refresh
add_action('wp_ajax_hsq_weather_refresh', 'hsq_weather_ajax_refresh');
add_action('wp_ajax_nopriv_hsq_weather_refresh', 'hsq_weather_ajax_refresh');
function hsq_weather_ajax_refresh() {
    $city_name = sanitize_text_field($_POST['city_name']);
    
    $weather_data = hsq_weather_get_data($city_name);
    
    if ($weather_data) {
        wp_send_json_success($weather_data);
    } else {
        wp_send_json_error('Unable to fetch weather data');
    }
}
?>
