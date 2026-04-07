<?php
/**
 * Plugin Name: HSQ-Weather
 * Plugin URI: https://github.com/hsq/weather
 * Description: A powerful weather plugin for WordPress with multi-city support, no API key required, using Open-Meteo API.
 * Version: 1.0.0
 * Author: Aneeq Ahmed 
 * Author URI: https://github.com/hsq
 * License: GPL v3 or later
 * Text Domain: hsq-weather
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Debug helper for plugin load/runtime failures
if (!function_exists('hsq_weather_debug_log')) {
    function hsq_weather_debug_log($message) {
        $log_file = defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR . '/hsq-weather-debug.log' : dirname(__FILE__) . '/hsq-weather-debug.log';
        if (is_array($message) || is_object($message)) {
            $message = print_r($message, true);
        }
        $timestamp = date('Y-m-d H:i:s');
        error_log("[$timestamp] HSQ Weather: $message\n", 3, $log_file);
    }

    function hsq_weather_shutdown_handler() {
        $error = error_get_last();
        if ($error !== null) {
            hsq_weather_debug_log('Shutdown error: ' . print_r($error, true));
        }
    }

    register_shutdown_function('hsq_weather_shutdown_handler');

    set_error_handler(function($errno, $errstr, $errfile, $errline) {
        hsq_weather_debug_log("PHP error [$errno] $errstr in $errfile on line $errline");
        return false;
    });
}

// Define plugin constants
define('HSQ_WEATHER_VERSION', '1.0.0');
define('HSQ_WEATHER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('HSQ_WEATHER_PLUGIN_URL', plugin_dir_url(__FILE__));
define('HSQ_WEATHER_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Include required files
require_once HSQ_WEATHER_PLUGIN_DIR . 'includes/class-weather-api.php';
require_once HSQ_WEATHER_PLUGIN_DIR . 'includes/class-weather-cache.php';
require_once HSQ_WEATHER_PLUGIN_DIR . 'Admin/class-admin-settings.php';
require_once HSQ_WEATHER_PLUGIN_DIR . 'public/class-public-display.php';

// Activation hook
register_activation_hook(__FILE__, 'hsq_weather_activate');
function hsq_weather_activate() {
    // Default settings
    $default_settings = array(
        'cities' => array(
            array('name' => 'New York', 'lat' => 40.7128, 'lon' => -74.0060),
            array('name' => 'London', 'lat' => 51.5074, 'lon' => -0.1278),
            array('name' => 'Tokyo', 'lat' => 35.6895, 'lon' => 139.6917)
        ),
        'columns' => 3,
        'theme' => 'light',
        'refresh_time' => 300,
        'show_wind' => 1,
        'show_humidity' => 1,
        'unit' => 'celsius',
        'custom_css' => ''
    );
    
    if (!get_option('hsq_weather_settings')) {
        add_option('hsq_weather_settings', $default_settings);
    }
    
    // Clear cache on activation
    $cache = new HSQ_Weather_Cache();
    $cache->clear_all();
}

// Deactivation hook
register_deactivation_hook(__FILE__, 'hsq_weather_deactivate');
function hsq_weather_deactivate() {
    // Clear all cache on deactivation
    $cache = new HSQ_Weather_Cache();
    $cache->clear_all();
}

// Initialize plugin
add_action('init', 'hsq_weather_init');
function hsq_weather_init() {
    // Load text domain for translations
    load_plugin_textdomain('hsq-weather', false, dirname(HSQ_WEATHER_PLUGIN_BASENAME) . '/languages');
}

// Enqueue frontend scripts and styles
add_action('wp_enqueue_scripts', 'hsq_weather_enqueue_frontend');
function hsq_weather_enqueue_frontend() {
    // Check if shortcode exists on page
    global $post;
    if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'hsq_weather')) {
        wp_enqueue_style('hsq-weather-public', HSQ_WEATHER_PLUGIN_URL . 'public/css/public-style.css', array(), HSQ_WEATHER_VERSION);
        wp_enqueue_script('hsq-weather-public', HSQ_WEATHER_PLUGIN_URL . 'public/js/public-script.js', array('jquery'), HSQ_WEATHER_VERSION, true);
        
        wp_localize_script('hsq-weather-public', 'hsq_weather_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('hsq_weather_nonce')
        ));
        
        // Add custom CSS
        $settings = get_option('hsq_weather_settings');
        if (!empty($settings['custom_css'])) {
            wp_add_inline_style('hsq-weather-public', $settings['custom_css']);
        }
    }
}

// Enqueue admin scripts and styles
add_action('admin_enqueue_scripts', 'hsq_weather_enqueue_admin');
function hsq_weather_enqueue_admin($hook) {
    if ($hook == 'toplevel_page_hsq-weather-settings') {
        wp_enqueue_style('hsq-weather-admin', HSQ_WEATHER_PLUGIN_URL . 'admin/css/admin-style.css', array(), HSQ_WEATHER_VERSION);
        wp_enqueue_script('jquery-ui-sortable');
        wp_enqueue_script('hsq-weather-admin', HSQ_WEATHER_PLUGIN_URL . 'admin/js/admin-script.js', array('jquery', 'jquery-ui-sortable'), HSQ_WEATHER_VERSION, true);
        
        wp_localize_script('hsq-weather-admin', 'hsq_weather_admin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('hsq_weather_admin_nonce'),
            'confirm_delete' => __('Are you sure you want to delete this city?', 'hsq-weather')
        ));
    }
}