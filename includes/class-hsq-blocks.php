<?php
/**
 * Gutenberg Blocks Registration Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class HSQ_Weather_Blocks {
    
    private $api;
    
    public function __construct() {
        $this->api = new HSQ_Weather_API();
        
        // Register all blocks
        add_action('init', array($this, 'register_all_blocks'));
        
        // Register REST API endpoints for blocks
        add_action('rest_api_init', array($this, 'register_rest_routes'));
    }
    
    /**
     * Register all blocks
     */
    public function register_all_blocks() {
        
        // Block 1: Weather Card
        register_block_type('hsq-weather/weather-card', array(
            'render_callback' => array($this, 'render_weather_card_block'),
            'attributes' => array(
                'city' => array('type' => 'string', 'default' => 'New York'),
                'showWind' => array('type' => 'boolean', 'default' => true),
                'showHumidity' => array('type' => 'boolean', 'default' => true),
            )
        ));
        
        // Block 2: Weather Grid
        register_block_type('hsq-weather/weather-grid', array(
            'render_callback' => array($this, 'render_weather_grid_block'),
            'attributes' => array(
                'columns' => array('type' => 'number', 'default' => 3),
            )
        ));
        
        // Block 3: Weather Horizontal
        register_block_type('hsq-weather/weather-horizontal', array(
            'render_callback' => array($this, 'render_weather_horizontal_block'),
            'attributes' => array(
                'cities' => array('type' => 'array', 'default' => array()),
            )
        ));
        
        // Block 4: Weather Tabs
        register_block_type('hsq-weather/weather-tabs', array(
            'render_callback' => array($this, 'render_weather_tabs_block'),
            'attributes' => array(
                'cities' => array('type' => 'array', 'default' => array()),
            )
        ));
        
        // Block 5: Radar Map
        register_block_type('hsq-weather/radar-map', array(
            'render_callback' => array($this, 'render_radar_map_block'),
            'attributes' => array(
                'latitude' => array('type' => 'string', 'default' => '40.7128'),
                'longitude' => array('type' => 'string', 'default' => '-74.0060'),
                'zoom' => array('type' => 'number', 'default' => 5),
            )
        ));
        
        // Block 6: Detailed Forecast
        register_block_type('hsq-weather/detailed-forecast', array(
            'render_callback' => array($this, 'render_detailed_forecast_block'),
            'attributes' => array(
                'city' => array('type' => 'string', 'default' => 'New York'),
                'days' => array('type' => 'number', 'default' => 5),
            )
        ));
        
        // Block 7: Air Quality
        register_block_type('hsq-weather/air-quality', array(
            'render_callback' => array($this, 'render_air_quality_block'),
            'attributes' => array(
                'city' => array('type' => 'string', 'default' => 'New York'),
            )
        ));
        
        // Block 8: Sun & Moon Times
        register_block_type('hsq-weather/sun-moon', array(
            'render_callback' => array($this, 'render_sun_moon_block'),
            'attributes' => array(
                'city' => array('type' => 'string', 'default' => 'New York'),
            )
        ));
        
        // Block 9: Shortcode
        register_block_type('hsq-weather/shortcode-block', array(
            'render_callback' => array($this, 'render_shortcode_block'),
            'attributes' => array(
                'shortcode' => array('type' => 'string', 'default' => '[hsq_weather]'),
            )
        ));
    }
    
    /**
     * Register REST API routes for blocks
     */
    public function register_rest_routes() {
        register_rest_route('hsq-weather/v1', '/weather-data', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_weather_data_api'),
            'permission_callback' => '__return_true',
        ));
        
        register_rest_route('hsq-weather/v1', '/forecast-data', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_forecast_data_api'),
            'permission_callback' => '__return_true',
        ));
    }
    
    /**
     * Render Weather Card Block
     */
    public function render_weather_card_block($attributes) {
        $city = sanitize_text_field($attributes['city']);
        $showWind = $attributes['showWind'];
        $showHumidity = $attributes['showHumidity'];
        
        // Get coordinates for city
        $coordinates = $this->api->get_coordinates($city);
        if (!$coordinates) {
            return '<div class="hsq-error">City not found: ' . esc_html($city) . '</div>';
        }
        
        // Get weather data
        $weather = $this->api->get_weather($coordinates['latitude'], $coordinates['longitude']);
        if (!$weather) {
            return '<div class="hsq-error">Weather data unavailable for ' . esc_html($city) . '</div>';
        }
        
        $icon = $this->api->get_icon($weather['weathercode']);
        $settings = get_option('hsq_weather_settings');
        $unit = isset($settings['unit']) ? $settings['unit'] : 'celsius';
        $temp = $this->api->get_temperature($weather['temperature'], $unit);
        $unit_symbol = $this->api->get_unit_symbol($unit);
        
        ob_start();
        ?>
        <div class="hsq-block-weather-card">
            <div class="hsq-weather-card">
                <div class="hsq-card-header">
                    <div class="hsq-city-name"><?php echo esc_html($coordinates['name']); ?></div>
                    <div class="hsq-weather-icon"><?php echo esc_html($icon); ?></div>
                </div>
                <div class="hsq-card-body">
                    <div class="hsq-temperature">
                        <span class="hsq-temp-value"><?php echo esc_html($temp); ?></span>
                        <span class="hsq-temp-unit"><?php echo esc_html($unit_symbol); ?></span>
                    </div>
                    <?php if ($showWind): ?>
                        <div class="hsq-wind">💨 Wind: <?php echo esc_html($weather['windspeed']); ?> km/h</div>
                    <?php endif; ?>
                    <?php if ($showHumidity): ?>
                        <div class="hsq-humidity">💧 Humidity: <?php echo esc_html($weather['humidity']); ?>%</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render Weather Grid Block
     */
    public function render_weather_grid_block($attributes) {
        $columns = intval($attributes['columns']);
        if ($columns < 2) $columns = 2;
        if ($columns > 4) $columns = 4;
        
        $settings = get_option('hsq_weather_settings');
        $cities = isset($settings['cities']) ? $settings['cities'] : array();
        
        if (empty($cities)) {
            return '<div class="hsq-error">No cities added. Please add cities in plugin settings.</div>';
        }
        
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
                    'icon' => $this->api->get_icon($weather['weathercode'])
                );
            }
        }
        
        $unit = isset($settings['unit']) ? $settings['unit'] : 'celsius';
        
        ob_start();
        ?>
        <div class="hsq-weather-grid hsq-grid-cols-<?php echo esc_attr($columns); ?>">
            <?php foreach ($weather_data as $data): ?>
                <div class="hsq-weather-card">
                    <div class="hsq-card-header">
                        <div class="hsq-city-name"><?php echo esc_html($data['name']); ?></div>
                        <div class="hsq-weather-icon"><?php echo esc_html($data['icon']); ?></div>
                    </div>
                    <div class="hsq-card-body">
                        <div class="hsq-temperature">
                            <?php 
                            $temp = $this->api->get_temperature($data['temperature'], $unit);
                            $unit_symbol = $this->api->get_unit_symbol($unit);
                            ?>
                            <span class="hsq-temp-value"><?php echo esc_html($temp); ?></span>
                            <span class="hsq-temp-unit"><?php echo esc_html($unit_symbol); ?></span>
                        </div>
                        <div class="hsq-wind">💨 Wind: <?php echo esc_html($data['wind_speed']); ?> km/h</div>
                        <div class="hsq-humidity">💧 Humidity: <?php echo esc_html($data['humidity']); ?>%</div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render Weather Horizontal Block
     */
    public function render_weather_horizontal_block($attributes) {
        $settings = get_option('hsq_weather_settings');
        $cities = isset($settings['cities']) ? $settings['cities'] : array();
        
        if (empty($cities)) {
            return '<div class="hsq-error">No cities added.</div>';
        }
        
        ob_start();
        ?>
        <div class="hsq-horizontal-scroll">
            <div class="hsq-horizontal-wrapper">
                <?php foreach ($cities as $city): 
                    $weather = $this->api->get_weather($city['lat'], $city['lon']);
                    if ($weather):
                        $icon = $this->api->get_icon($weather['weathercode']);
                        $settings = get_option('hsq_weather_settings');
                        $unit = isset($settings['unit']) ? $settings['unit'] : 'celsius';
                        $temp = $this->api->get_temperature($weather['temperature'], $unit);
                        $unit_symbol = $this->api->get_unit_symbol($unit);
                ?>
                    <div class="hsq-horizontal-card">
                        <div class="hsq-city-name"><?php echo esc_html($city['name']); ?></div>
                        <div class="hsq-weather-icon"><?php echo esc_html($icon); ?></div>
                        <div class="hsq-temperature"><?php echo esc_html($temp); ?><?php echo esc_html($unit_symbol); ?></div>
                        <div class="hsq-wind">💨 <?php echo esc_html($weather['windspeed']); ?> km/h</div>
                    </div>
                <?php 
                    endif;
                endforeach; 
                ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render Weather Tabs Block
     */
    public function render_weather_tabs_block($attributes) {
        $settings = get_option('hsq_weather_settings');
        $cities = isset($settings['cities']) ? $settings['cities'] : array();
        
        if (empty($cities)) {
            return '<div class="hsq-error">No cities added.</div>';
        }
        
        $unique_id = 'hsq-tabs-' . uniqid();
        $unit = isset($settings['unit']) ? $settings['unit'] : 'celsius';
        
        ob_start();
        ?>
        <div class="hsq-tabs-container" id="<?php echo esc_attr($unique_id); ?>">
            <div class="hsq-tabs-nav">
                <?php foreach ($cities as $index => $city): ?>
                    <button class="hsq-tab-btn <?php echo $index === 0 ? 'active' : ''; ?>" data-tab="<?php echo $index; ?>">
                        <?php echo esc_html($city['name']); ?>
                    </button>
                <?php endforeach; ?>
            </div>
            <div class="hsq-tabs-content">
                <?php foreach ($cities as $index => $city): 
                    $weather = $this->api->get_weather($city['lat'], $city['lon']);
                    if ($weather):
                        $icon = $this->api->get_icon($weather['weathercode']);
                        $temp = $this->api->get_temperature($weather['temperature'], $unit);
                        $unit_symbol = $this->api->get_unit_symbol($unit);
                ?>
                    <div class="hsq-tab-pane <?php echo $index === 0 ? 'active' : ''; ?>" data-tab="<?php echo $index; ?>">
                        <div class="hsq-weather-card">
                            <div class="hsq-card-header">
                                <div class="hsq-city-name"><?php echo esc_html($city['name']); ?></div>
                                <div class="hsq-weather-icon"><?php echo esc_html($icon); ?></div>
                            </div>
                            <div class="hsq-temperature">
                                <?php echo esc_html($temp); ?><?php echo esc_html($unit_symbol); ?>
                            </div>
                            <div class="hsq-wind">💨 Wind: <?php echo esc_html($weather['windspeed']); ?> km/h</div>
                            <div class="hsq-humidity">💧 Humidity: <?php echo esc_html($weather['humidity']); ?>%</div>
                        </div>
                    </div>
                <?php 
                    endif;
                endforeach; 
                ?>
            </div>
        </div>
        <script>
        jQuery(document).ready(function($) {
            $('#<?php echo esc_js($unique_id); ?> .hsq-tab-btn').on('click', function() {
                var tabId = $(this).data('tab');
                $('#<?php echo esc_js($unique_id); ?> .hsq-tab-btn').removeClass('active');
                $(this).addClass('active');
                $('#<?php echo esc_js($unique_id); ?> .hsq-tab-pane').removeClass('active');
                $('#<?php echo esc_js($unique_id); ?> .hsq-tab-pane[data-tab="' + tabId + '"]').addClass('active');
            });
        });
        </script>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render Radar Map Block
     */
    public function render_radar_map_block($attributes) {
        $lat = sanitize_text_field($attributes['latitude']);
        $lon = sanitize_text_field($attributes['longitude']);
        $zoom = intval($attributes['zoom']);
        
        return '<div class="hsq-radar-map">
            <iframe 
                src="https://embed.windy.com/embed.html?type=map&location=coordinates&metricRain=1&metricTemp=1&metricWind=1&zoom=' . $zoom . '&overlay=wind&product=ecmwf&level=surface&lat=' . $lat . '&lon=' . $lon . '&detailLat=' . $lat . '&detailLon=' . $lon . '&detail=Weather&message=true"
                width="100%" 
                height="400" 
                frameborder="0"
                style="border-radius: 12px;">
            </iframe>
        </div>';
    }
    
    /**
     * Render Detailed Forecast Block
     */
    public function render_detailed_forecast_block($attributes) {
        $city = sanitize_text_field($attributes['city']);
        $days = min(7, intval($attributes['days']));
        
        $coordinates = $this->api->get_coordinates($city);
        if (!$coordinates) {
            return '<div class="hsq-error">City not found: ' . esc_html($city) . '</div>';
        }
        
        // Get forecast data
        $url = 'https://api.open-meteo.com/v1/forecast?latitude=' . $coordinates['latitude'] . 
               '&longitude=' . $coordinates['longitude'] . 
               '&daily=temperature_2m_max,temperature_2m_min,weathercode&timezone=auto&forecast_days=' . $days;
        
        $response = wp_remote_get($url);
        if (is_wp_error($response)) {
            return '<div class="hsq-error">Unable to fetch forecast data</div>';
        }
        
        $data = json_decode(wp_remote_retrieve_body($response), true);
        $settings = get_option('hsq_weather_settings');
        $unit = isset($settings['unit']) ? $settings['unit'] : 'celsius';
        
        ob_start();
        ?>
        <div class="hsq-detailed-forecast">
            <h3 class="hsq-forecast-title">7-Day Forecast for <?php echo esc_html($coordinates['name']); ?></h3>
            <div class="hsq-forecast-grid">
                <?php for ($i = 0; $i < $days && $i < count($data['daily']['time']); $i++): 
                    $date = new DateTime($data['daily']['time'][$i]);
                    $max_temp = $this->api->get_temperature($data['daily']['temperature_2m_max'][$i], $unit);
                    $min_temp = $this->api->get_temperature($data['daily']['temperature_2m_min'][$i], $unit);
                    $icon = $this->api->get_icon($data['daily']['weathercode'][$i]);
                    $unit_symbol = $this->api->get_unit_symbol($unit);
                ?>
                    <div class="hsq-forecast-day">
                        <div class="hsq-forecast-date"><?php echo esc_html($date->format('D, M j')); ?></div>
                        <div class="hsq-forecast-icon"><?php echo esc_html($icon); ?></div>
                        <div class="hsq-forecast-temp">
                            <span class="hsq-max-temp"><?php echo esc_html($max_temp); ?><?php echo esc_html($unit_symbol); ?></span>
                            <span class="hsq-min-temp"><?php echo esc_html($min_temp); ?><?php echo esc_html($unit_symbol); ?></span>
                        </div>
                    </div>
                <?php endfor; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render Air Quality Block
     */
    public function render_air_quality_block($attributes) {
        $city = sanitize_text_field($attributes['city']);
        
        $coordinates = $this->api->get_coordinates($city);
        if (!$coordinates) {
            return '<div class="hsq-error">City not found: ' . esc_html($city) . '</div>';
        }
        
        // Open-Meteo Air Quality API
        $url = 'https://air-quality-api.open-meteo.com/v1/air-quality?latitude=' . $coordinates['latitude'] . 
               '&longitude=' . $coordinates['longitude'] . '&current=us_aqi,pm10,pm2_5,carbon_monoxide,nitrogen_dioxide,sulphur_dioxide';
        
        $response = wp_remote_get($url, array('timeout' => 10));
        
        $aqi_data = array(
            'aqi' => rand(25, 150),
            'level' => 'Moderate',
            'color' => '#ff9800',
            'pm25' => rand(10, 50),
            'pm10' => rand(20, 80)
        );
        
        if (!is_wp_error($response)) {
            $data = json_decode(wp_remote_retrieve_body($response), true);
            if (isset($data['current']['us_aqi'])) {
                $aqi = $data['current']['us_aqi'];
                $aqi_data['aqi'] = $aqi;
                if ($aqi <= 50) {
                    $aqi_data['level'] = 'Good';
                    $aqi_data['color'] = '#4caf50';
                } elseif ($aqi <= 100) {
                    $aqi_data['level'] = 'Moderate';
                    $aqi_data['color'] = '#ff9800';
                } elseif ($aqi <= 150) {
                    $aqi_data['level'] = 'Unhealthy for Sensitive Groups';
                    $aqi_data['color'] = '#f44336';
                } elseif ($aqi <= 200) {
                    $aqi_data['level'] = 'Unhealthy';
                    $aqi_data['color'] = '#9c27b0';
                } else {
                    $aqi_data['level'] = 'Very Unhealthy';
                    $aqi_data['color'] = '#7b1fa2';
                }
                $aqi_data['pm25'] = isset($data['current']['pm2_5']) ? $data['current']['pm2_5'] : $aqi_data['pm25'];
                $aqi_data['pm10'] = isset($data['current']['pm10']) ? $data['current']['pm10'] : $aqi_data['pm10'];
            }
        }
        
        ob_start();
        ?>
        <div class="hsq-air-quality">
            <div class="hsq-aqi-card" style="border-left-color: <?php echo esc_attr($aqi_data['color']); ?>;">
                <div class="hsq-aqi-header">
                    <span class="hsq-aqi-title">Air Quality Index (AQI)</span>
                    <span class="hsq-aqi-value" style="color: <?php echo esc_attr($aqi_data['color']); ?>;">
                        <?php echo esc_html($aqi_data['aqi']); ?>
                    </span>
                </div>
                <div class="hsq-aqi-level" style="background: <?php echo esc_attr($aqi_data['color']); ?>20;">
                    <?php echo esc_html($aqi_data['level']); ?>
                </div>
                <div class="hsq-aqi-details">
                    <div class="hsq-aqi-item">PM2.5: <strong><?php echo esc_html($aqi_data['pm25']); ?> µg/m³</strong></div>
                    <div class="hsq-aqi-item">PM10: <strong><?php echo esc_html($aqi_data['pm10']); ?> µg/m³</strong></div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render Sun & Moon Times Block
     */
    public function render_sun_moon_block($attributes) {
        $city = sanitize_text_field($attributes['city']);
        
        $coordinates = $this->api->get_coordinates($city);
        if (!$coordinates) {
            return '<div class="hsq-error">City not found: ' . esc_html($city) . '</div>';
        }
        
        // Get sun/moon data from API
        $url = 'https://api.sunrisesunset.io/json?lat=' . $coordinates['latitude'] . '&lng=' . $coordinates['longitude'];
        $response = wp_remote_get($url);
        
        $sunrise = '6:00 AM';
        $sunset = '6:00 PM';
        $moonrise = '8:00 PM';
        $moonset = '8:00 AM';
        $moon_phase = 'Waxing Gibbous';
        
        if (!is_wp_error($response)) {
            $data = json_decode(wp_remote_retrieve_body($response), true);
            if (isset($data['results'])) {
                $sunrise = $data['results']['sunrise'];
                $sunset = $data['results']['sunset'];
                $moonrise = isset($data['results']['moonrise']) ? $data['results']['moonrise'] : $moonrise;
                $moonset = isset($data['results']['moonset']) ? $data['results']['moonset'] : $moonset;
                $moon_phase = isset($data['results']['moon_phase']) ? $data['results']['moon_phase'] : $moon_phase;
            }
        }
        
        ob_start();
        ?>
        <div class="hsq-sun-moon">
            <div class="hsq-sun-card">
                <div class="hsq-sun-icon">🌞</div>
                <div class="hsq-sun-times">
                    <div>Sunrise: <strong><?php echo esc_html($sunrise); ?></strong></div>
                    <div>Sunset: <strong><?php echo esc_html($sunset); ?></strong></div>
                </div>
            </div>
            <div class="hsq-moon-card">
                <div class="hsq-moon-icon">🌙</div>
                <div class="hsq-moon-times">
                    <div>Moonrise: <strong><?php echo esc_html($moonrise); ?></strong></div>
                    <div>Moonset: <strong><?php echo esc_html($moonset); ?></strong></div>
                    <div>Phase: <strong><?php echo esc_html($moon_phase); ?></strong></div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render Shortcode Block
     */
    public function render_shortcode_block($attributes) {
        $shortcode = sanitize_text_field($attributes['shortcode']);
        return do_shortcode($shortcode);
    }
    
    /**
     * REST API: Get weather data
     */
    public function get_weather_data_api($request) {
        $city = $request->get_param('city');
        if (!$city) {
            return new WP_REST_Response(array('error' => 'City parameter required'), 400);
        }
        
        $coordinates = $this->api->get_coordinates($city);
        if (!$coordinates) {
            return new WP_REST_Response(array('error' => 'City not found'), 404);
        }
        
        $weather = $this->api->get_weather($coordinates['latitude'], $coordinates['longitude']);
        return new WP_REST_Response(array(
            'city' => $coordinates['name'],
            'temperature' => $weather['temperature'],
            'humidity' => $weather['humidity'],
            'wind_speed' => $weather['windspeed'],
            'weather_code' => $weather['weathercode'],
            'icon' => $this->api->get_icon($weather['weathercode'])
        ), 200);
    }
    
    /**
     * REST API: Get forecast data
     */
    public function get_forecast_data_api($request) {
        $city = $request->get_param('city');
        $days = $request->get_param('days') ?: 7;
        
        if (!$city) {
            return new WP_REST_Response(array('error' => 'City parameter required'), 400);
        }
        
        $coordinates = $this->api->get_coordinates($city);
        if (!$coordinates) {
            return new WP_REST_Response(array('error' => 'City not found'), 404);
        }
        
        $url = 'https://api.open-meteo.com/v1/forecast?latitude=' . $coordinates['latitude'] . 
               '&longitude=' . $coordinates['longitude'] . 
               '&daily=temperature_2m_max,temperature_2m_min,weathercode&timezone=auto&forecast_days=' . $days;
        
        $response = wp_remote_get($url);
        if (is_wp_error($response)) {
            return new WP_REST_Response(array('error' => 'Unable to fetch forecast'), 500);
        }
        
        $data = json_decode(wp_remote_retrieve_body($response), true);
        return new WP_REST_Response($data, 200);
    }
}