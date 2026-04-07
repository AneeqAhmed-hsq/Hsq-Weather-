<?php
class HSQ_Weather_Admin_Settings {
    
    private $api;
    private $cache;
    
    public function __construct() {
        $this->api = new HSQ_Weather_API();
        $this->cache = new HSQ_Weather_Cache();
        
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        
        // AJAX handlers
        add_action('wp_ajax_hsq_weather_add_city', array($this, 'ajax_add_city'));
        add_action('wp_ajax_hsq_weather_delete_city', array($this, 'ajax_delete_city'));
        add_action('wp_ajax_hsq_weather_reorder_cities', array($this, 'ajax_reorder_cities'));
        add_action('wp_ajax_hsq_weather_search_city', array($this, 'ajax_search_city'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('HSQ Weather', 'hsq-weather'),
            __('HSQ Weather', 'hsq-weather'),
            'manage_options',
            'hsq-weather-settings',
            array($this, 'render_settings_page'),
            'dashicons-cloud',
            30
        );
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        register_setting(
            'hsq_weather_settings_group',
            'hsq_weather_settings',
            array($this, 'sanitize_settings')
        );
    }
    
    /**
     * Sanitize settings
     */
    public function sanitize_settings($input) {
        $output = get_option('hsq_weather_settings', array());
        
        if (isset($input['columns'])) {
            $output['columns'] = intval($input['columns']);
            if ($output['columns'] < 2) $output['columns'] = 2;
            if ($output['columns'] > 4) $output['columns'] = 4;
        }
        
        if (isset($input['theme'])) {
            $output['theme'] = sanitize_text_field($input['theme']);
        }
        
        if (isset($input['refresh_time'])) {
            $output['refresh_time'] = intval($input['refresh_time']);
        }
        
        $output['show_wind'] = isset($input['show_wind']) ? 1 : 0;
        $output['show_humidity'] = isset($input['show_humidity']) ? 1 : 0;
        
        if (isset($input['unit'])) {
            $output['unit'] = sanitize_text_field($input['unit']);
        }
        
        if (isset($input['custom_css'])) {
            $output['custom_css'] = wp_kses_post($input['custom_css']);
        }
        
        if (isset($input['cities']) && is_array($input['cities'])) {
            $output['cities'] = array_map(function($city) {
                return array(
                    'name' => sanitize_text_field($city['name']),
                    'lat' => floatval($city['lat']),
                    'lon' => floatval($city['lon'])
                );
            }, $input['cities']);
        }
        
        // Clear cache when settings are saved
        $this->cache->clear_all();
        
        return $output;
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page() {
        $settings = get_option('hsq_weather_settings');
        $cities = isset($settings['cities']) ? $settings['cities'] : array();
        ?>
        <div class="wrap hsq-weather-settings">
            <h1><?php _e('HSQ Weather Settings', 'hsq-weather'); ?></h1>
            
            <div class="hsq-settings-container">
                <div class="hsq-settings-main">
                    <form method="post" action="options.php">
                        <?php settings_fields('hsq_weather_settings_group'); ?>
                        
                        <!-- Cities Management -->
                        <div class="hsq-section">
                            <h2><?php _e('Manage Cities', 'hsq-weather'); ?></h2>
                            
                            <div class="hsq-add-city">
                                <input type="text" id="hsq-city-search" placeholder="<?php _e('Search city name...', 'hsq-weather'); ?>" autocomplete="off">
                                <div id="hsq-search-results" style="display:none;"></div>
                                <button type="button" id="hsq-add-city-btn" class="button button-primary"><?php _e('Add City', 'hsq-weather'); ?></button>
                            </div>
                            
                            <div class="hsq-cities-list">
                                <h3><?php _e('Cities (Drag to reorder)', 'hsq-weather'); ?></h3>
                                <ul id="hsq-cities-sortable">
                                    <?php foreach ($cities as $index => $city): ?>
                                        <li data-index="<?php echo $index; ?>" data-lat="<?php echo esc_attr($city['lat']); ?>" data-lon="<?php echo esc_attr($city['lon']); ?>">
                                            <span class="drag-handle">⋮⋮</span>
                                            <span class="city-name"><?php echo esc_html($city['name']); ?></span>
                                            <input type="hidden" name="hsq_weather_settings[cities][<?php echo $index; ?>][name]" value="<?php echo esc_attr($city['name']); ?>">
                                            <input type="hidden" name="hsq_weather_settings[cities][<?php echo $index; ?>][lat]" value="<?php echo esc_attr($city['lat']); ?>">
                                            <input type="hidden" name="hsq_weather_settings[cities][<?php echo $index; ?>][lon]" value="<?php echo esc_attr($city['lon']); ?>">
                                            <button type="button" class="hsq-delete-city button button-small"><?php _e('Delete', 'hsq-weather'); ?></button>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                        
                        <!-- Display Settings -->
                        <div class="hsq-section">
                            <h2><?php _e('Display Settings', 'hsq-weather'); ?></h2>
                            
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><?php _e('Default Columns', 'hsq-weather'); ?></th>
                                    <td>
                                        <select name="hsq_weather_settings[columns]">
                                            <option value="2" <?php selected($settings['columns'], 2); ?>>2 Columns</option>
                                            <option value="3" <?php selected($settings['columns'], 3); ?>>3 Columns</option>
                                            <option value="4" <?php selected($settings['columns'], 4); ?>>4 Columns</option>
                                        </select>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row"><?php _e('Default Theme', 'hsq-weather'); ?></th>
                                    <td>
                                        <select name="hsq_weather_settings[theme]">
                                            <option value="light" <?php selected($settings['theme'], 'light'); ?>>Light</option>
                                            <option value="dark" <?php selected($settings['theme'], 'dark'); ?>>Dark</option>
                                        </select>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row"><?php _e('Refresh Time', 'hsq-weather'); ?></th>
                                    <td>
                                        <select name="hsq_weather_settings[refresh_time]">
                                            <option value="300" <?php selected($settings['refresh_time'], 300); ?>>5 minutes</option>
                                            <option value="900" <?php selected($settings['refresh_time'], 900); ?>>15 minutes</option>
                                            <option value="1800" <?php selected($settings['refresh_time'], 1800); ?>>30 minutes</option>
                                            <option value="3600" <?php selected($settings['refresh_time'], 3600); ?>>1 hour</option>
                                        </select>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row"><?php _e('Temperature Unit', 'hsq-weather'); ?></th>
                                    <td>
                                        <select name="hsq_weather_settings[unit]">
                                            <option value="celsius" <?php selected($settings['unit'], 'celsius'); ?>>Celsius (°C)</option>
                                            <option value="fahrenheit" <?php selected($settings['unit'], 'fahrenheit'); ?>>Fahrenheit (°F)</option>
                                        </select>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row"><?php _e('Show Weather Details', 'hsq-weather'); ?></th>
                                    <td>
                                        <label>
                                            <input type="checkbox" name="hsq_weather_settings[show_wind]" value="1" <?php checked($settings['show_wind'], 1); ?>>
                                            <?php _e('Show Wind Speed', 'hsq-weather'); ?>
                                        </label><br>
                                        <label>
                                            <input type="checkbox" name="hsq_weather_settings[show_humidity]" value="1" <?php checked($settings['show_humidity'], 1); ?>>
                                            <?php _e('Show Humidity', 'hsq-weather'); ?>
                                        </label>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        
                        <!-- Custom CSS -->
                        <div class="hsq-section">
                            <h2><?php _e('Custom CSS', 'hsq-weather'); ?></h2>
                            <textarea name="hsq_weather_settings[custom_css]" rows="10" cols="50" class="large-text code"><?php echo esc_textarea($settings['custom_css']); ?></textarea>
                            <p class="description"><?php _e('Add your custom CSS to style the weather cards.', 'hsq-weather'); ?></p>
                        </div>
                        
                        <?php submit_button(); ?>
                    </form>
                </div>
                
                <div class="hsq-settings-sidebar">
                    <div class="hsq-box">
                        <h3><?php _e('Shortcode Usage', 'hsq-weather'); ?></h3>
                        <code>[hsq_weather]</code>
                        <p><?php _e('Default 3 columns', 'hsq-weather'); ?></p>
                        <code>[hsq_weather columns="2"]</code>
                        <p><?php _e('2 columns', 'hsq-weather'); ?></p>
                        <code>[hsq_weather columns="4"]</code>
                        <p><?php _e('4 columns', 'hsq-weather'); ?></p>
                    </div>
                    
                    <div class="hsq-box">
                        <h3><?php _e('Information', 'hsq-weather'); ?></h3>
                        <p><?php _e('Weather data provided by', 'hsq-weather'); ?> <a href="https://open-meteo.com/" target="_blank">Open-Meteo</a></p>
                        <p><?php _e('No API key required!', 'hsq-weather'); ?></p>
                        <p><?php _e('Version:', 'hsq-weather'); ?> <?php echo HSQ_WEATHER_VERSION; ?></p>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * AJAX: Add city
     */
    public function ajax_add_city() {
        check_ajax_referer('hsq_weather_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $city_name = sanitize_text_field($_POST['city_name']);
        
        // Get coordinates
        $coordinates = $this->api->get_coordinates($city_name);
        
        if ($coordinates) {
            $settings = get_option('hsq_weather_settings');
            $city_data = array(
                'name' => $coordinates['name'] . ($coordinates['country'] ? ', ' . $coordinates['country'] : ''),
                'lat' => $coordinates['latitude'],
                'lon' => $coordinates['longitude']
            );
            
            if (!isset($settings['cities'])) {
                $settings['cities'] = array();
            }
            
            $settings['cities'][] = $city_data;
            update_option('hsq_weather_settings', $settings);
            
            // Clear cache
            $this->cache->clear_all();
            
            wp_send_json_success($city_data);
        } else {
            wp_send_json_error(__('City not found. Please try another name.', 'hsq-weather'));
        }
    }
    
    /**
     * AJAX: Delete city
     */
    public function ajax_delete_city() {
        check_ajax_referer('hsq_weather_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $index = intval($_POST['index']);
        $settings = get_option('hsq_weather_settings');
        
        if (isset($settings['cities'][$index])) {
            array_splice($settings['cities'], $index, 1);
            update_option('hsq_weather_settings', $settings);
            
            // Clear cache
            $this->cache->clear_all();
            
            wp_send_json_success();
        } else {
            wp_send_json_error(__('City not found', 'hsq-weather'));
        }
    }
    
    /**
     * AJAX: Reorder cities
     */
    public function ajax_reorder_cities() {
        check_ajax_referer('hsq_weather_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $order = $_POST['order'];
        $settings = get_option('hsq_weather_settings');
        $reordered = array();
        
        foreach ($order as $index) {
            if (isset($settings['cities'][$index])) {
                $reordered[] = $settings['cities'][$index];
            }
        }
        
        $settings['cities'] = $reordered;
        update_option('hsq_weather_settings', $settings);
        
        wp_send_json_success();
    }
    
    /**
     * AJAX: Search city
     */
    public function ajax_search_city() {
        check_ajax_referer('hsq_weather_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $search_term = sanitize_text_field($_POST['search']);
        
        if (strlen($search_term) < 2) {
            wp_send_json_error('Search term too short');
        }
        
        $url = 'https://geocoding-api.open-meteo.com/v1/search?name=' . urlencode($search_term) . '&count=5&language=en&format=json';
        
        $response = wp_remote_get($url);
        
        if (is_wp_error($response)) {
            wp_send_json_error('API error');
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        $results = array();
        if (!empty($data['results'])) {
            foreach ($data['results'] as $city) {
                $results[] = array(
                    'name' => $city['name'] . ($city['country'] ? ', ' . $city['country'] : ''),
                    'lat' => $city['latitude'],
                    'lon' => $city['longitude']
                );
            }
        }
        
        wp_send_json_success($results);
    }
}

// Initialize admin class
new HSQ_Weather_Admin_Settings();
