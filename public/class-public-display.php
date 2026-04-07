<?php
class HSQ_Weather_Public_Display {
    
    private $api;
    private $cache;
    
    public function __construct() {
        $this->api = new HSQ_Weather_API();
        $this->cache = new HSQ_Weather_Cache();
        
        add_shortcode('hsq_weather', array($this, 'render_shortcode'));
        add_action('wp_ajax_hsq_weather_refresh', array($this, 'ajax_refresh_weather'));
        add_action('wp_ajax_nopriv_hsq_weather_refresh', array($this, 'ajax_refresh_weather'));
        add_action('wp_ajax_hsq_weather_toggle_unit', array($this, 'ajax_toggle_unit'));
        add_action('wp_ajax_nopriv_hsq_weather_toggle_unit', array($this, 'ajax_toggle_unit'));
        add_action('wp_ajax_hsq_weather_toggle_theme', array($this, 'ajax_toggle_theme'));
        add_action('wp_ajax_nopriv_hsq_weather_toggle_theme', array($this, 'ajax_toggle_theme'));
    }
    
    /**
     * Render shortcode
     */
    public function render_shortcode($atts) {
        $atts = shortcode_atts(array(
            'columns' => 3
        ), $atts, 'hsq_weather');
        
        $columns = intval($atts['columns']);
        if ($columns < 2) $columns = 2;
        if ($columns > 4) $columns = 4;
        
        $settings = get_option('hsq_weather_settings');
        
        if (empty($settings['cities'])) {
            return '<div class="hsq-weather-error">' . __('No cities added. Please add cities in plugin settings.', 'hsq-weather') . '</div>';
        }
        
        // Get user preferences
        $user_theme = isset($_COOKIE['hsq_weather_theme']) ? sanitize_text_field($_COOKIE['hsq_weather_theme']) : $settings['theme'];
        $user_unit = isset($_COOKIE['hsq_weather_unit']) ? sanitize_text_field($_COOKIE['hsq_weather_unit']) : $settings['unit'];
        
        // Get weather data
        $weather_data = $this->get_weather_data_for_cities($settings['cities']);
        
        ob_start();
        ?>
        <div class="hsq-weather-container hsq-theme-<?php echo esc_attr($user_theme); ?>" 
             data-refresh-time="<?php echo esc_attr($settings['refresh_time']); ?>"
             data-columns="<?php echo esc_attr($columns); ?>">
            
            <div class="hsq-weather-controls">
                <div class="hsq-unit-toggle">
                    <button class="hsq-unit-btn <?php echo $user_unit === 'celsius' ? 'active' : ''; ?>" data-unit="celsius">°C</button>
                    <button class="hsq-unit-btn <?php echo $user_unit === 'fahrenheit' ? 'active' : ''; ?>" data-unit="fahrenheit">°F</button>
                </div>
                <div class="hsq-theme-toggle">
                    <button class="hsq-theme-btn <?php echo $user_theme === 'light' ? 'active' : ''; ?>" data-theme="light">☀️</button>
                    <button class="hsq-theme-btn <?php echo $user_theme === 'dark' ? 'active' : ''; ?>" data-theme="dark">🌙</button>
                </div>
                <button class="hsq-refresh-btn">🔄</button>
            </div>
            
            <div class="hsq-weather-grid hsq-grid-cols-<?php echo esc_attr($columns); ?>">
                <?php foreach ($weather_data as $city_data): ?>
                    <?php include HSQ_WEATHER_PLUGIN_DIR . 'public/partials/weather-card-template.php'; ?>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get weather data for all cities
     */
    public function get_weather_data_for_cities($cities) {
        $weather_data = array();
        
        foreach ($cities as $city) {
            $weather = $this->api->get_weather($city['lat'], $city['lon']);
            
            if ($weather) {
                $weather_data[] = array(
                    'name' => $city['name'],
                    'temperature' => $weather['temperature'],
                    'humidity' => $weather['humidity'],
                    'wind_speed' => $weather['windspeed'],
                    'weather_code' => $weather['weathercode'],
                    'icon' => $this->api->get_icon($weather['weathercode']),
                    'last_update' => $weather['timestamp']
                );
            } else {
                $weather_data[] = array(
                    'name' => $city['name'],
                    'error' => __('Weather data unavailable', 'hsq-weather')
                );
            }
        }
        
        return $weather_data;
    }
    
    /**
     * AJAX: Refresh weather
     */
    public function ajax_refresh_weather() {
        check_ajax_referer('hsq_weather_nonce', 'nonce');
        
        $settings = get_option('hsq_weather_settings');
        
        if (empty($settings['cities'])) {
            wp_send_json_error('No cities found');
        }
        
        // Clear cache for all cities
        foreach ($settings['cities'] as $city) {
            $cache_key = 'hsq_weather_data_' . md5($city['lat'] . ',' . $city['lon']);
            $this->cache->delete($cache_key);
        }
        
        // Get fresh data
        $weather_data = $this->get_weather_data_for_cities($settings['cities']);
        
        ob_start();
        foreach ($weather_data as $index => $city_data) {
            include HSQ_WEATHER_PLUGIN_DIR . 'public/partials/weather-card-template.php';
        }
        $html = ob_get_clean();
        
        wp_send_json_success(array(
            'html' => $html,
            'timestamp' => current_time('timestamp')
        ));
    }
    
    /**
     * AJAX: Toggle temperature unit
     */
    public function ajax_toggle_unit() {
        check_ajax_referer('hsq_weather_nonce', 'nonce');
        
        $unit = sanitize_text_field($_POST['unit']);
        $settings = get_option('hsq_weather_settings');
        
        if (empty($settings['cities'])) {
            wp_send_json_error('No cities found');
        }
        
        $weather_data = $this->get_weather_data_for_cities($settings['cities']);
        
        // Convert temperatures if needed
        foreach ($weather_data as &$data) {
            if (isset($data['temperature'])) {
                if ($unit === 'fahrenheit') {
                    $data['temperature_f'] = $this->api->convert_celsius_to_fahrenheit($data['temperature']);
                } else {
                    $data['temperature_c'] = $data['temperature'];
                }
            }
        }
        
        ob_start();
        foreach ($weather_data as $index => $city_data) {
            include HSQ_WEATHER_PLUGIN_DIR . 'public/partials/weather-card-template.php';
        }
        $html = ob_get_clean();
        
        wp_send_json_success(array(
            'html' => $html,
            'unit' => $unit
        ));
    }
    
    /**
     * AJAX: Toggle theme
     */
    public function ajax_toggle_theme() {
        check_ajax_referer('hsq_weather_nonce', 'nonce');
        
        $theme = sanitize_text_field($_POST['theme']);
        
        wp_send_json_success(array(
            'theme' => $theme
        ));
    }
}

// Initialize public class
new HSQ_Weather_Public_Display();