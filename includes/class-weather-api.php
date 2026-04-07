<?php
class HSQ_Weather_API {
    
    /**
     * Get coordinates from city name using Open-Meteo Geocoding API
     */
    public function get_coordinates($city_name) {
        $city_name = sanitize_text_field($city_name);
        $cache_key = 'hsq_weather_geo_' . md5($city_name);
        
        // Check cache first
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return $cached;
        }
        
        $url = 'https://geocoding-api.open-meteo.com/v1/search?name=' . urlencode($city_name) . '&count=1&language=en&format=json';
        
        $response = wp_remote_get($url, array(
            'timeout' => 10,
            'headers' => array('User-Agent' => 'HSQ-Weather-Plugin/1.0')
        ));
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!empty($data['results'][0])) {
            $result = array(
                'name' => $data['results'][0]['name'],
                'latitude' => $data['results'][0]['latitude'],
                'longitude' => $data['results'][0]['longitude'],
                'country' => isset($data['results'][0]['country']) ? $data['results'][0]['country'] : ''
            );
            
            // Cache for 30 days (geocoding rarely changes)
            set_transient($cache_key, $result, 30 * DAY_IN_SECONDS);
            return $result;
        }
        
        return false;
    }
    
    /**
     * Get weather data from coordinates
     */
    public function get_weather($lat, $lon) {
        $lat = floatval($lat);
        $lon = floatval($lon);
        $cache_key = 'hsq_weather_data_' . md5($lat . ',' . $lon);
        
        // Check cache first
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return $cached;
        }
        
        $url = 'https://api.open-meteo.com/v1/forecast?latitude=' . $lat . '&longitude=' . $lon . 
               '&current_weather=true&hourly=temperature_2m,relative_humidity_2m,windspeed_10m&timezone=auto';
        
        $response = wp_remote_get($url, array(
            'timeout' => 10,
            'headers' => array('User-Agent' => 'HSQ-Weather-Plugin/1.0')
        ));
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['current_weather'])) {
            $weather = array(
                'temperature' => $data['current_weather']['temperature'],
                'windspeed' => $data['current_weather']['windspeed'],
                'weathercode' => $data['current_weather']['weathercode'],
                'humidity' => isset($data['hourly']['relative_humidity_2m'][0]) ? $data['hourly']['relative_humidity_2m'][0] : 0,
                'timestamp' => current_time('timestamp')
            );
            
            // Get settings for cache duration
            $settings = get_option('hsq_weather_settings');
            $cache_duration = isset($settings['refresh_time']) ? intval($settings['refresh_time']) : 300;
            
            // Cache based on refresh time setting
            set_transient($cache_key, $weather, $cache_duration);
            return $weather;
        }
        
        return false;
    }
    
    /**
     * Get weather icon based on weather code
     */
    public function get_icon($weather_code) {
        $icons = array(
            0 => '☀️',      // Clear sky
            1 => '🌤️',      // Mainly clear
            2 => '⛅',       // Partly cloudy
            3 => '☁️',       // Overcast
            45 => '🌫️',     // Fog
            48 => '🌫️',     // Depositing rime fog
            51 => '🌧️',     // Light drizzle
            53 => '🌧️',     // Moderate drizzle
            55 => '🌧️',     // Dense drizzle
            56 => '🌧️',     // Light freezing drizzle
            57 => '🌧️',     // Dense freezing drizzle
            61 => '🌧️',     // Slight rain
            63 => '🌧️',     // Moderate rain
            65 => '🌧️',     // Heavy rain
            66 => '🌧️',     // Light freezing rain
            67 => '🌧️',     // Heavy freezing rain
            71 => '❄️',     // Slight snow fall
            73 => '❄️',     // Moderate snow fall
            75 => '❄️',     // Heavy snow fall
            77 => '❄️',     // Snow grains
            80 => '🌧️',     // Slight rain showers
            81 => '🌧️',     // Moderate rain showers
            82 => '🌧️',     // Violent rain showers
            85 => '❄️',     // Slight snow showers
            86 => '❄️',     // Heavy snow showers
            95 => '⛈️',     // Thunderstorm
            96 => '⛈️',     // Thunderstorm with slight hail
            99 => '⛈️'      // Thunderstorm with heavy hail
        );
        
        return isset($icons[$weather_code]) ? $icons[$weather_code] : '🌡️';
    }
    
    /**
     * Convert Celsius to Fahrenheit
     */
    public function convert_celsius_to_fahrenheit($celsius) {
        return round(($celsius * 9/5) + 32, 1);
    }
    
    /**
     * Get temperature in requested unit
     */
    public function get_temperature($celsius, $unit = 'celsius') {
        if ($unit === 'fahrenheit') {
            return $this->convert_celsius_to_fahrenheit($celsius);
        }
        return $celsius;
    }
    
    /**
     * Get temperature unit symbol
     */
    public function get_unit_symbol($unit = 'celsius') {
        return $unit === 'fahrenheit' ? '°F' : '°C';
    }
}