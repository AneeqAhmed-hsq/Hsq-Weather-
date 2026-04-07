<?php
/**
 * Weather Card Template
 * 
 * Available variables:
 * $city_data - Array containing weather information
 * $settings - Plugin settings
 * $user_unit - Current temperature unit
 */
?>
<div class="hsq-weather-card <?php echo isset($city_data['error']) ? 'hsq-error' : ''; ?>">
    <div class="hsq-card-header">
        <div class="hsq-city-name"><?php echo esc_html($city_data['name']); ?></div>
        <div class="hsq-weather-icon"><?php echo isset($city_data['icon']) ? esc_html($city_data['icon']) : '🌡️'; ?></div>
    </div>
    
    <div class="hsq-card-body">
        <?php if (isset($city_data['error'])): ?>
            <div class="hsq-error-message"><?php echo esc_html($city_data['error']); ?></div>
        <?php else: ?>
            <div class="hsq-temperature">
                <?php 
                $temp = $user_unit === 'fahrenheit' ? 
                    $this->api->convert_celsius_to_fahrenheit($city_data['temperature']) : 
                    $city_data['temperature'];
                $unit_symbol = $user_unit === 'fahrenheit' ? '°F' : '°C';
                ?>
                <span class="hsq-temp-value"><?php echo esc_html($temp); ?></span>
                <span class="hsq-temp-unit"><?php echo esc_html($unit_symbol); ?></span>
            </div>
            
            <?php if (!empty($settings['show_wind']) && isset($city_data['wind_speed'])): ?>
                <div class="hsq-wind">
                    <span class="hsq-label">💨 <?php _e('Wind:', 'hsq-weather'); ?></span>
                    <span class="hsq-value"><?php echo esc_html($city_data['wind_speed']); ?> km/h</span>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($settings['show_humidity']) && isset($city_data['humidity'])): ?>
                <div class="hsq-humidity">
                    <span class="hsq-label">💧 <?php _e('Humidity:', 'hsq-weather'); ?></span>
                    <span class="hsq-value"><?php echo esc_html($city_data['humidity']); ?>%</span>
                </div>
            <?php endif; ?>
            
            <div class="hsq-last-update">
                <small><?php _e('Updated:', 'hsq-weather'); ?> <?php echo human_time_diff($city_data['last_update'], current_time('timestamp')); ?> <?php _e('ago', 'hsq-weather'); ?></small>
            </div>
        <?php endif; ?>
    </div>
</div>