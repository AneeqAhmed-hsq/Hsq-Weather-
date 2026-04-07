<?php
class HSQ_Weather_Cache {
    
    /**
     * Set cache with key, data, and expiry
     */
    public function set($key, $data, $expiry = 300) {
        $full_key = 'hsq_weather_' . $key;
        set_transient($full_key, $data, $expiry);
        return true;
    }
    
    /**
     * Get cache by key
     */
    public function get($key) {
        $full_key = 'hsq_weather_' . $key;
        return get_transient($full_key);
    }
    
    /**
     * Delete specific cache
     */
    public function delete($key) {
        $full_key = 'hsq_weather_' . $key;
        delete_transient($full_key);
        return true;
    }
    
    /**
     * Clear all plugin cache
     */
    public function clear_all() {
        global $wpdb;
        
        // Delete all transients with our prefix
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                '_transient_hsq_weather_%'
            )
        );
        
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                '_transient_timeout_hsq_weather_%'
            )
        );
        
        return true;
    }
    
    /**
     * Check if cache exists
     */
    public function exists($key) {
        $full_key = 'hsq_weather_' . $key;
        return get_transient($full_key) !== false;
    }
}