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
        // Main dashboard page
        add_menu_page(
            __('HSQ Weather', 'hsq-weather'),
            __('HSQ Weather', 'hsq-weather'),
            'manage_options',
            'hsq-weather',
            array($this, 'render_dashboard_page'),
            'dashicons-cloud',
            30
        );

        // Submenus
        add_submenu_page(
            'hsq-weather',
            __('Getting Started', 'hsq-weather'),
            __('Getting Started', 'hsq-weather'),
            'manage_options',
            'hsq-weather-getting-started',
            array($this, 'render_getting_started_page')
        );
        add_submenu_page(
            'hsq-weather',
            __('Blocks', 'hsq-weather'),
            __('Blocks', 'hsq-weather'),
            'manage_options',
            'hsq-weather-blocks',
            array($this, 'render_blocks_page')
        );
        add_submenu_page(
            'hsq-weather',
            __('Saved Templates', 'hsq-weather'),
            __('Saved Templates', 'hsq-weather'),
            'manage_options',
            'hsq-weather-templates',
            array($this, 'render_templates_page')
        );
        add_submenu_page(
            'hsq-weather',
            __('Settings', 'hsq-weather'),
            __('Settings', 'hsq-weather'),
            'manage_options',
            'hsq-weather-settings',
            array($this, 'render_settings_page')
        );
        add_submenu_page(
            'hsq-weather',
            __('Manage Weather', 'hsq-weather'),
            __('Manage Weather', 'hsq-weather'),
            'manage_options',
            'hsq-weather-manage',
            array($this, 'render_manage_weather_page')
        );
        add_submenu_page(
            'hsq-weather',
            __('Add New Weather Tools', 'hsq-weather'),
            __('Add New Weather Tools', 'hsq-weather'),
            'manage_options',
            'hsq-weather-tools',
            array($this, 'render_tools_page')
        );
        add_submenu_page(
            'hsq-weather',
            __('Upgrade to Pro', 'hsq-weather'),
            __('<span style="color: #ff9800;">⬆ Upgrade to Pro</span>', 'hsq-weather'),
            'manage_options',
            'hsq-weather-pro',
            array($this, 'redirect_to_pro_page')
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
            $output['cities'] = array();
            foreach ($input['cities'] as $city) {
                $output['cities'][] = array(
                    'name' => sanitize_text_field($city['name']),
                    'lat' => floatval($city['lat']),
                    'lon' => floatval($city['lon'])
                );
            }
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
     * Render admin header tabs
     */
    public function render_admin_header($active = 'blocks') {
        $tabs = array(
            'getting-started' => __('Getting Started', 'hsq-weather'),
            'blocks' => __('Blocks', 'hsq-weather'),
            'templates' => __('Saved Templates', 'hsq-weather'),
            'settings' => __('Settings', 'hsq-weather'),
            'lite-vs-pro' => __('Lite vs Pro', 'hsq-weather'),
            'about' => __('About Us', 'hsq-weather'),
        );
        ?>
        <div class="hsq-admin-header">
            <div class="hsq-admin-title">
                <div class="hsq-admin-logo">☁️</div>
                <div>
                    <h1><?php _e('Location Weather', 'hsq-weather'); ?></h1>
                    <p><?php _e('Manage weather blocks, settings and templates.', 'hsq-weather'); ?></p>
                </div>
            </div>
            <nav class="hsq-admin-tabs">
                <?php foreach ($tabs as $slug => $label): ?>
                    <a href="?page=hsq-weather-<?php echo esc_attr($slug); ?>" class="hsq-admin-tab<?php echo $active === $slug ? ' active' : ''; ?>"><?php echo esc_html($label); ?></a>
                <?php endforeach; ?>
            </nav>
        </div>
        <?php
    }

    /**
     * Render Dashboard Page
     */
    public function render_dashboard_page() {
        $this->render_admin_header('getting-started');
        ?>
        <div class="wrap hsq-weather-dashboard">
            <h1><?php _e('HSQ Weather Dashboard', 'hsq-weather'); ?></h1>
            <div class="hsq-dashboard-grid">
                <div class="hsq-dashboard-card">
                    <h3><?php _e('Weather Statistics', 'hsq-weather'); ?></h3>
                    <?php 
                    $settings = get_option('hsq_weather_settings');
                    $city_count = isset($settings['cities']) ? count($settings['cities']) : 0;
                    ?>
                    <p><?php echo sprintf(__('Total Cities: %d', 'hsq-weather'), $city_count); ?></p>
                    <p><?php _e('API Status: Active', 'hsq-weather'); ?></p>
                    <p><?php _e('Cache: Enabled', 'hsq-weather'); ?></p>
                </div>
                <div class="hsq-dashboard-card">
                    <h3><?php _e('Quick Actions', 'hsq-weather'); ?></h3>
                    <a href="?page=hsq-weather-manage" class="button button-primary"><?php _e('Manage Cities', 'hsq-weather'); ?></a>
                    <a href="?page=hsq-weather-settings" class="button"><?php _e('Settings', 'hsq-weather'); ?></a>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render Getting Started Page
     */
    public function render_getting_started_page() {
        ?>
        <div class="wrap hsq-getting-started">
            <div class="hsq-welcome-header">
                <h1><?php _e('Welcome to HSQ Weather!', 'hsq-weather'); ?></h1>
                <p><?php _e('Thank you for installing HSQ Weather! This guide will help you get started with the plugin.', 'hsq-weather'); ?></p>
            </div>
            
            <div class="hsq-video-section">
                <h2><?php _e('Getting Started Video', 'hsq-weather'); ?></h2>
                <div class="hsq-video-wrapper">
                    <iframe width="560" height="315" src="https://www.youtube.com/embed/dQw4w9WgXcQ" frameborder="0" allowfullscreen></iframe>
                </div>
            </div>
            
            <div class="hsq-steps-grid">
                <div class="hsq-step">
                    <div class="step-number">1</div>
                    <h3><?php _e('Add Cities', 'hsq-weather'); ?></h3>
                    <p><?php _e('Go to Manage Weather page and add your desired cities.', 'hsq-weather'); ?></p>
                    <a href="?page=hsq-weather-manage" class="button"><?php _e('Add Cities →', 'hsq-weather'); ?></a>
                </div>
                
                <div class="hsq-step">
                    <div class="step-number">2</div>
                    <h3><?php _e('Configure Settings', 'hsq-weather'); ?></h3>
                    <p><?php _e('Customize columns, theme, refresh time and more.', 'hsq-weather'); ?></p>
                    <a href="?page=hsq-weather-settings" class="button"><?php _e('Configure →', 'hsq-weather'); ?></a>
                </div>
                
                <div class="hsq-step">
                    <div class="step-number">3</div>
                    <h3><?php _e('Display Weather', 'hsq-weather'); ?></h3>
                    <p><?php _e('Use shortcode [hsq_weather] on any page or post.', 'hsq-weather'); ?></p>
                    <code>[hsq_weather]</code>
                </div>
            </div>
            
            <div class="hsq-support-box">
                <h3><?php _e('Need Help?', 'hsq-weather'); ?></h3>
                <p><?php _e('For personalized assistance, reach out to our support team.', 'hsq-weather'); ?></p>
                <a href="#" class="button button-primary"><?php _e('Ask Now', 'hsq-weather'); ?></a>
                <a href="#" class="button"><?php _e('Join Community', 'hsq-weather'); ?></a>
            </div>
        </div>
        <?php
    }

    /**
     * Render Blocks Page - Professional Layout
     */
    public function render_blocks_page() {
        $this->render_admin_header('blocks');
        $blocks = array(
            array('title' => 'Weather Card', 'icon' => '🌤️', 'description' => 'Display single city weather in beautiful card layout', 'pro' => false, 'slug' => 'weather-card'),
            array('title' => 'Weather Horizontal', 'icon' => '🟦', 'description' => 'Display horizontal weather cards in a slider', 'pro' => false, 'slug' => 'weather-horizontal'),
            array('title' => 'AQI - Minimal Card', 'icon' => 'AQI', 'description' => 'Display air quality index with a compact card', 'pro' => false, 'slug' => 'air-quality'),
            array('title' => 'Weather Grid', 'icon' => '🌍', 'description' => 'Display weather in a responsive grid layout', 'pro' => false, 'slug' => 'weather-grid'),
            array('title' => 'Weather Tabs', 'icon' => '📑', 'description' => 'Switch between city weather tabs quickly', 'pro' => false, 'slug' => 'weather-tabs'),
            array('title' => 'Weather Table', 'icon' => '📊', 'description' => 'Display weather data in a clean table format', 'pro' => false, 'slug' => 'weather-table'),
            array('title' => 'Radar Map by Windy', 'icon' => '🗺️', 'description' => 'Interactive radar map with weather overlays', 'pro' => false, 'slug' => 'radar-map'),
            array('title' => 'Detailed Forecast', 'icon' => '📆', 'description' => '7-day weather forecast with charts', 'pro' => true, 'slug' => 'detailed-forecast'),
            array('title' => 'AQI - Detailed Air Quality', 'icon' => 'AQI', 'description' => 'Extended air quality details for cities', 'pro' => true, 'slug' => 'aqi-detailed'),
            array('title' => 'Weather Accordion', 'icon' => '📋', 'description' => 'Collapsible weather details for multiple cities', 'pro' => false, 'slug' => 'weather-accordion'),
            array('title' => 'Weather Map by OWM', 'icon' => '🗺️', 'description' => 'Weather map integration from OpenWeatherMap', 'pro' => true, 'slug' => 'weather-map-owm'),
            array('title' => 'Historical Weather Data', 'icon' => '📈', 'description' => 'Display past weather data and trends', 'pro' => true, 'slug' => 'historical-weather'),
            array('title' => 'Historical Air Quality Data', 'icon' => 'AQI', 'description' => 'View historical pollutant levels over time', 'pro' => true, 'slug' => 'historical-aqi'),
            array('title' => 'Sun & Moon Times', 'icon' => '🌙', 'description' => 'Sunrise, sunset, moonrise and moonset times', 'pro' => false, 'slug' => 'sun-moon'),
            array('title' => 'Section Heading', 'icon' => 'H', 'description' => 'Add elegant section headings anywhere', 'pro' => false, 'slug' => 'section-heading'),
            array('title' => 'Location Weather Shortcode', 'icon' => '📍', 'description' => 'Display weather using shortcode for any location', 'pro' => false, 'slug' => 'shortcode')
        );
        $pro_blocks = 0;
        foreach ($blocks as $block) {
            if (! empty($block['pro'])) {
                $pro_blocks++;
            }
        }
        ?>
        <div class="wrap hsq-blocks-page">
            <div class="hsq-blocks-header">
                <div class="hsq-blocks-header-top">
                    <div>
                        <h1><?php _e('Location Weather Blocks', 'hsq-weather'); ?></h1>
                        <p><?php _e('Browse available weather blocks and enable them for use inside Gutenberg.', 'hsq-weather'); ?></p>
                    </div>
                    <a href="?page=hsq-weather-settings" class="button button-primary hsq-blocks-action"><?php _e('Block Settings', 'hsq-weather'); ?></a>
                </div>
                <div class="hsq-blocks-summary">
                    <div class="hsq-summary-item">
                        <span><?php echo count($blocks); ?></span>
                        <strong><?php _e('Blocks Available', 'hsq-weather'); ?></strong>
                    </div>
                    <div class="hsq-summary-item">
                        <span><?php echo esc_html($pro_blocks); ?></span>
                        <strong><?php _e('Pro Blocks', 'hsq-weather'); ?></strong>
                    </div>
                    <div class="hsq-summary-item">
                        <span>0</span>
                        <strong><?php _e('Enabled', 'hsq-weather'); ?></strong>
                    </div>
                </div>
            </div>

            <div class="hsq-blocks-grid">
                <?php foreach ($blocks as $block): ?>
                    <div class="hsq-block-card<?php echo $block['pro'] ? ' hsq-pro-card' : ''; ?>">
                        <div class="hsq-block-card-main">
                            <div class="hsq-block-card-icon"><?php echo esc_html($block['icon']); ?></div>
                            <div class="hsq-block-card-info">
                                <h3><?php echo esc_html($block['title']); ?><?php if ($block['pro']): ?> <span class="hsq-pro-label"><?php _e('Pro', 'hsq-weather'); ?></span><?php endif; ?></h3>
                                <p><?php echo esc_html($block['description']); ?></p>
                                <div class="hsq-block-card-links">
                                    <a href="#" class="hsq-block-link"><?php _e('Docs', 'hsq-weather'); ?></a>
                                    <span class="hsq-block-divider">•</span>
                                    <a href="#" class="hsq-block-link"><?php _e('Demo', 'hsq-weather'); ?></a>
                                </div>
                            </div>
                        </div>
                        <div class="hsq-block-card-actions">
                            <label class="hsq-toggle-switch">
                                <input type="checkbox" checked>
                                <span class="hsq-slider"></span>
                            </label>
                            <span class="hsq-block-status"><?php _e('Enabled', 'hsq-weather'); ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="hsq-blocks-footer">
                <a href="#" class="hsq-full-features-btn">
                    <?php _e('See Full Features →', 'hsq-weather'); ?>
                </a>
            </div>
        </div>
        <?php
    }

    /**
     * Render Templates Page
     */
    public function render_templates_page() {
        $templates = get_option('hsq_weather_templates', array());
        ?>
        <div class="wrap hsq-templates-page">
            <h1><?php _e('Saved Templates', 'hsq-weather'); ?></h1>
            
            <button class="button button-primary hsq-save-current"><?php _e('Save Current Layout as Template', 'hsq-weather'); ?></button>
            
            <div class="hsq-templates-grid">
                <?php if (empty($templates)): ?>
                    <p><?php _e('No templates saved yet. Create your first template!', 'hsq-weather'); ?></p>
                <?php else: ?>
                    <?php foreach ($templates as $template): ?>
                        <div class="hsq-template-card">
                            <h3><?php echo esc_html($template['name']); ?></h3>
                            <p><?php echo sprintf(__('Cities: %d', 'hsq-weather'), count($template['cities'])); ?></p>
                            <button class="button hsq-load-template" data-id="<?php echo $template['id']; ?>"><?php _e('Load', 'hsq-weather'); ?></button>
                            <button class="button hsq-delete-template" data-id="<?php echo $template['id']; ?>"><?php _e('Delete', 'hsq-weather'); ?></button>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render Manage Weather Page (Cities Management)
     */
    public function render_manage_weather_page() {
        $settings = get_option('hsq_weather_settings');
        $cities = isset($settings['cities']) ? $settings['cities'] : array();
        ?>
        <div class="wrap hsq-manage-weather">
            <h1><?php _e('Manage Weather Cities', 'hsq-weather'); ?></h1>
            
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
                            <button type="button" class="hsq-delete-city button button-small"><?php _e('Delete', 'hsq-weather'); ?></button>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        <?php
    }

    /**
     * Render Tools Page
     */
    public function render_tools_page() {
        ?>
        <div class="wrap hsq-tools-page">
            <h1><?php _e('Add New Weather Tools', 'hsq-weather'); ?></h1>
            
            <div class="hsq-tools-grid">
                <div class="hsq-tool-card">
                    <div class="tool-icon">🌡️</div>
                    <h3><?php _e('Air Quality Index', 'hsq-weather'); ?></h3>
                    <p><?php _e('Display air quality data with your weather info', 'hsq-weather'); ?></p>
                    <button class="button hsq-install-tool" data-tool="aqi"><?php _e('Install', 'hsq-weather'); ?></button>
                </div>
                
                <div class="hsq-tool-card">
                    <div class="tool-icon">📅</div>
                    <h3><?php _e('7-Day Forecast', 'hsq-weather'); ?></h3>
                    <p><?php _e('Extended weather forecast for the week', 'hsq-weather'); ?></p>
                    <button class="button hsq-install-tool" data-tool="forecast"><?php _e('Install', 'hsq-weather'); ?></button>
                </div>
                
                <div class="hsq-tool-card">
                    <div class="tool-icon">🎨</div>
                    <h3><?php _e('Weather Widget', 'hsq-weather'); ?></h3>
                    <p><?php _e('Sidebar widget for weather display', 'hsq-weather'); ?></p>
                    <button class="button hsq-install-tool" data-tool="widget"><?php _e('Install', 'hsq-weather'); ?></button>
                </div>
                
                <div class="hsq-tool-card">
                    <div class="tool-icon">📊</div>
                    <h3><?php _e('Weather Analytics', 'hsq-weather'); ?></h3>
                    <p><?php _e('Track weather views and interactions', 'hsq-weather'); ?></p>
                    <button class="button hsq-install-tool" data-tool="analytics"><?php _e('Install', 'hsq-weather'); ?></button>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Redirect to Pro Page
     */
    public function redirect_to_pro_page() {
        wp_redirect('https://yourwebsite.com/hsq-weather-pro');
        exit;
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
