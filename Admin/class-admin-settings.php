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
            __('Add New Weather', 'hsq-weather'),
            __('Add New Weather', 'hsq-weather'),
            'manage_options',
            'hsq-weather-add-new',
            array($this, 'render_add_new_weather_page')
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
        $this->render_admin_header('settings');
        $settings = get_option('hsq_weather_settings');
        $cities = isset($settings['cities']) ? $settings['cities'] : array();
        $active_section = isset($_GET['section']) ? sanitize_text_field($_GET['section']) : 'advanced-controls';
        ?>
        <style>
            .hsq-settings-layout { display: grid; grid-template-columns: 300px 1fr; gap: 24px; margin: 24px 0; }
            .hsq-settings-sidebar { background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; padding: 0; overflow: hidden; height: fit-content; }
            .hsq-settings-sidebar-item { padding: 16px 20px; border-bottom: 1px solid #eee; cursor: pointer; color: #374151; font-weight: 500; transition: all 0.2s ease; }
            .hsq-settings-sidebar-item:last-child { border-bottom: none; }
            .hsq-settings-sidebar-item:hover { background: #f3f4f6; }
            .hsq-settings-sidebar-item.active { background: #f3f4f6; color: #ea580c; border-left: 4px solid #ea580c; padding-left: 16px; }
            .hsq-settings-sidebar-label { font-size: 12px; text-transform: uppercase; letter-spacing: 0.08em; color: #9ca3af; padding: 12px 20px; font-weight: 700; }
            .hsq-settings-content { background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; padding: 24px; }
            .hsq-settings-content h2 { margin-top: 0; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #eee; }
            .hsq-section { display: none; }
            .hsq-section.active { display: block; }
            @media (max-width: 768px) { .hsq-settings-layout { grid-template-columns: 1fr; } .hsq-settings-sidebar { display: flex; gap: 0; margin-bottom: 20px; } .hsq-settings-sidebar-item { flex: 1; padding: 12px; text-align: center; border: 1px solid #e5e7eb; } }
        </style>
        <div class="wrap hsq-weather-settings">
            <div class="hsq-settings-layout">
                <!-- Sidebar Navigation -->
                <div class="hsq-settings-sidebar">
                    <div class="hsq-settings-sidebar-label"><?php _e('Settings', 'hsq-weather'); ?></div>
                    <div class="hsq-settings-sidebar-item<?php echo $active_section === 'weather-api-key' ? ' active' : ''; ?>" onclick="location.href='?page=hsq-weather-settings&section=weather-api-key'">☁️ <?php _e('Weather API Key', 'hsq-weather'); ?></div>
                    <div class="hsq-settings-sidebar-item<?php echo $active_section === 'advanced-controls' ? ' active' : ''; ?>" onclick="location.href='?page=hsq-weather-settings&section=advanced-controls'">⚙️ <?php _e('Advanced Controls', 'hsq-weather'); ?></div>
                    <div class="hsq-settings-sidebar-item<?php echo $active_section === 'custom-css' ? ' active' : ''; ?>" onclick="location.href='?page=hsq-weather-settings&section=custom-css'"><?php _e('Additional CSS & JS', 'hsq-weather'); ?></div>
                </div>

                <!-- Main Content -->
                <div class="hsq-settings-content">
                    <form method="post" action="options.php">
                        <?php settings_fields('hsq_weather_settings_group'); ?>

                        <!-- Weather API Key Section -->
                        <div class="hsq-section<?php echo $active_section === 'weather-api-key' ? ' active' : ''; ?>">
                            <h2><?php _e('Weather API Key', 'hsq-weather'); ?></h2>
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><?php _e('API Provider', 'hsq-weather'); ?></th>
                                    <td>
                                        <p><strong><?php _e('Open-Meteo (Recommended)', 'hsq-weather'); ?></strong></p>
                                        <p class="description"><?php _e('No API key needed. Free weather data with no registration required.', 'hsq-weather'); ?></p>
                                    </td>
                                </tr>
                            </table>
                        </div>

                        <!-- Advanced Controls Section -->
                        <div class="hsq-section<?php echo $active_section === 'advanced-controls' ? ' active' : ''; ?>">
                            <h2><?php _e('Advanced Controls', 'hsq-weather'); ?></h2>
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><?php _e('Clean-up Data on Deletion', 'hsq-weather'); ?></th>
                                    <td>
                                        <label>
                                            <input type="checkbox" name="hsq_weather_settings[cleanup_on_delete]" value="1" <?php checked($settings['cleanup_on_delete'], 1); ?>>
                                            <?php _e('Remove all plugin data when uninstalled', 'hsq-weather'); ?>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php _e('Skip Cache for Weather Update', 'hsq-weather'); ?></th>
                                    <td>
                                        <label>
                                            <input type="checkbox" name="hsq_weather_settings[skip_cache]" value="1" <?php checked($settings['skip_cache'], 1); ?>>
                                            <?php _e('Always fetch fresh weather data', 'hsq-weather'); ?>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php _e('Cache', 'hsq-weather'); ?></th>
                                    <td>
                                        <label>
                                            <input type="checkbox" name="hsq_weather_settings[enable_cache]" value="1" <?php checked($settings['enable_cache'], 1); ?>>
                                            <?php _e('Use cached weather data', 'hsq-weather'); ?>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php _e('Purge Cache', 'hsq-weather'); ?></th>
                                    <td>
                                        <button type="button" class="button button-primary" id="hsq-purge-cache"><?php _e('Delete', 'hsq-weather'); ?></button>
                                        <p class="description"><?php _e('Clear all cached weather data', 'hsq-weather'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php _e('Contribute to Location Weather', 'hsq-weather'); ?></th>
                                    <td>
                                        <label>
                                            <input type="checkbox" name="hsq_weather_settings[contribute_data]" value="1" <?php checked($settings['contribute_data'], 1); ?>>
                                            <?php _e('We collect non-sensitive data to fix bugs faster, make smarter decisions, and build features that truly matter to you. ', 'hsq-weather'); ?><a href="#" target="_blank"><?php _e('See what we collect', 'hsq-weather'); ?></a>
                                        </label>
                                    </td>
                                </tr>
                            </table>
                        </div>

                        <!-- Custom CSS & JS Section -->
                        <div class="hsq-section<?php echo $active_section === 'custom-css' ? ' active' : ''; ?>">
                            <h2><?php _e('Additional CSS & JS', 'hsq-weather'); ?></h2>
                            
                            <h3><?php _e('Custom CSS', 'hsq-weather'); ?></h3>
                            <textarea name="hsq_weather_settings[custom_css]" rows="10" cols="50" class="large-text code"><?php echo esc_textarea($settings['custom_css']); ?></textarea>
                            <p class="description"><?php _e('Add your custom CSS to style the weather cards.', 'hsq-weather'); ?></p>

                            <h3 style="margin-top: 30px;"><?php _e('Custom JavaScript', 'hsq-weather'); ?></h3>
                            <textarea name="hsq_weather_settings[custom_js]" rows="10" cols="50" class="large-text code"><?php echo esc_textarea($settings['custom_js']); ?></textarea>
                            <p class="description"><?php _e('Add your custom JavaScript. Note: Only use vanilla JS.', 'hsq-weather'); ?></p>
                        </div>

                        <div style="margin-top: 30px;">
                            <?php submit_button(__('Save Changes', 'hsq-weather')); ?>
                            <button type="button" class="button" onclick="location.href='?page=hsq-weather-settings'" style="margin-left: 10px;"><?php _e('Reset', 'hsq-weather'); ?></button>
                        </div>
                    </form>
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
            <style>
                .hsq-blocks-page .hsq-blocks-header { background: #ffffff; border: 1px solid #e5e7eb; border-radius: 18px; padding: 24px 24px 20px; box-shadow: 0 12px 30px rgba(15, 23, 42, 0.05); }
                .hsq-blocks-page .hsq-blocks-header-top { display: flex; justify-content: space-between; align-items: flex-start; gap: 18px; flex-wrap: wrap; }
                .hsq-blocks-page .hsq-blocks-header-top h1 { margin: 0 0 8px; font-size: 32px; }
                .hsq-blocks-page .hsq-blocks-header-top p { margin: 0; color: #4b5563; font-size: 15px; max-width: 720px; }
                .hsq-blocks-page .hsq-blocks-summary { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 16px; margin-top: 20px; }
                .hsq-blocks-page .hsq-summary-item { background: #f8fafc; border: 1px solid #e5e7eb; border-radius: 16px; padding: 18px 20px; box-shadow: inset 0 0 0 1px rgba(255,255,255,0.4); }
                .hsq-blocks-page .hsq-summary-item span { display: block; font-size: 28px; font-weight: 700; color: #111827; }
                .hsq-blocks-page .hsq-summary-item strong { font-size: 12px; color: #6b7280; text-transform: uppercase; letter-spacing: .08em; margin-top: 4px; display: block; }
                .hsq-blocks-page .hsq-blocks-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(360px, 1fr)); gap: 18px; margin-top: 24px; }
                .hsq-blocks-page .hsq-block-card { background: #ffffff !important; border: 1px solid #e5e7eb !important; border-radius: 20px !important; padding: 22px !important; display: grid !important; grid-template-columns: 1fr auto !important; align-items: center !important; gap: 20px !important; box-shadow: 0 18px 40px rgba(15, 23, 42, 0.06) !important; }
                .hsq-blocks-page .hsq-block-card-main { display: flex !important; gap: 18px !important; align-items: flex-start !important; }
                .hsq-blocks-page .hsq-block-card-icon { width: 56px; height: 56px; display: flex; align-items: center; justify-content: center; border-radius: 18px; background: linear-gradient(135deg, #eef2ff 0%, #e0f2fe 100%); color: #111827; font-size: 22px; flex-shrink: 0; }
                .hsq-blocks-page .hsq-block-card-info h3 { margin: 0 0 10px; font-size: 18px; color: #111827; display: flex; gap: 10px; flex-wrap: wrap; }
                .hsq-blocks-page .hsq-block-card-info p { margin: 0 0 14px; color: #6b7280; line-height: 1.7; font-size: 14px; }
                .hsq-blocks-page .hsq-block-card-links { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; }
                .hsq-blocks-page .hsq-block-link { color: #2563eb; font-size: 13px; text-decoration: none; }
                .hsq-blocks-page .hsq-block-link:hover { color: #1d4ed8; }
                .hsq-blocks-page .hsq-block-divider { color: #d1d5db; font-size: 12px; }
                .hsq-blocks-page .hsq-block-card-actions { display: flex; align-items: center; justify-content: flex-end; gap: 14px; flex-direction: column; }
                .hsq-blocks-page .hsq-block-status { color: #4b5563; font-size: 13px; font-weight: 600; }
                .hsq-blocks-page .hsq-full-features-btn { display: inline-flex; background: #111827; color: #ffffff; padding: 12px 30px; border-radius: 9999px; font-weight: 600; text-decoration: none; transition: transform 0.2s ease, box-shadow 0.2s ease; box-shadow: 0 8px 24px rgba(15, 23, 42, 0.08); margin-top: 30px; }
                .hsq-blocks-page .hsq-full-features-btn:hover { transform: translateY(-2px); box-shadow: 0 12px 28px rgba(15, 23, 42, 0.12); }
                @media (max-width: 768px) { .hsq-blocks-page .hsq-blocks-header-top { flex-direction: column; } .hsq-blocks-page .hsq-block-card { grid-template-columns: 1fr; } .hsq-blocks-page .hsq-block-card-actions { flex-direction: row; justify-content: space-between; width: 100%; } }
            </style>
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
        $templates = get_option('hsq_weather_templates', array());
        ?>
        <style>
            .hsq-manage-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
            .hsq-manage-header h1 { margin: 0; }
            .hsq-manage-table { width: 100%; border-collapse: collapse; background: #fff; border: 1px solid #ccd0d4; }
            .hsq-manage-table thead { background: #f5f5f5; }
            .hsq-manage-table th { padding: 15px; text-align: left; border-bottom: 1px solid #ccd0d4; font-weight: 600; color: #23282d; }
            .hsq-manage-table td { padding: 15px; border-bottom: 1px solid #eee; }
            .hsq-manage-table tbody tr:hover { background: #f9f9f9; }
            .hsq-manage-empty { text-align: center; padding: 60px 20px; color: #666; }
            .hsq-action-links { display: flex; gap: 10px; }
            .hsq-action-links a { color: #0073aa; text-decoration: none; font-size: 13px; }
            .hsq-action-links a:hover { color: #005a87; }
            .hsq-layout-badge { display: inline-block; padding: 4px 10px; background: #e8f5e9; color: #2e7d32; border-radius: 4px; font-size: 12px; }
            .hsq-filter-tabs { margin-bottom: 15px; }
            .hsq-filter-tabs a { display: inline-block; padding: 8px 12px; margin-right: 15px; text-decoration: none; color: #666; }
            .hsq-filter-tabs a.active { color: #0073aa; border-bottom: 3px solid #0073aa; }
        </style>
        <div class="wrap hsq-manage-weather">
            <div class="hsq-manage-header">
                <h1><?php _e('Manage Weather', 'hsq-weather'); ?></h1>
                <a href="?page=hsq-weather-add-new" class="button button-primary"><?php _e('Add New Weather', 'hsq-weather'); ?></a>
            </div>

            <div class="hsq-filter-tabs">
                <a href="#" class="active"><?php _e('All', 'hsq-weather'); ?> (<?php echo count($templates); ?>)</a>
            </div>

            <?php if (empty($templates)): ?>
                <div class="hsq-manage-empty">
                    <p><?php _e('No posts found.', 'hsq-weather'); ?></p>
                </div>
            <?php else: ?>
                <table class="hsq-manage-table">
                    <thead>
                        <tr>
                            <th><input type="checkbox"></th>
                            <th><?php _e('Title', 'hsq-weather'); ?></th>
                            <th><?php _e('Shortcode', 'hsq-weather'); ?></th>
                            <th><?php _e('Layout', 'hsq-weather'); ?></th>
                            <th><?php _e('Date', 'hsq-weather'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($templates as $template): ?>
                            <tr>
                                <td><input type="checkbox"></td>
                                <td>
                                    <strong><a href="#"><?php echo esc_html($template['name']); ?></a></strong>
                                    <div class="hsq-action-links">
                                        <a href="#"><?php _e('Edit', 'hsq-weather'); ?></a>
                                        <a href="#"><?php _e('Delete', 'hsq-weather'); ?></a>
                                        <a href="#"><?php _e('Duplicate', 'hsq-weather'); ?></a>
                                    </div>
                                </td>
                                <td><code>[hsq_weather id="<?php echo esc_attr($template['id']); ?>"]</code></td>
                                <td><span class="hsq-layout-badge"><?php echo isset($template['layout']) ? esc_html($template['layout']) : 'Grid'; ?></span></td>
                                <td><?php echo isset($template['created']) ? date('M d, Y', strtotime($template['created'])) : 'N/A'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render Add New Weather Page
     */
    public function render_add_new_weather_page() {
        $layouts = array(
            'vertical-card' => array('icon' => '📋', 'name' => 'Vertical Card', 'desc' => 'Clean vertical layout', 'preview' => '<div style="display: grid; gap: 8px;"><div style="height: 26px; background: #f3f4f6; border-radius: 4px;"></div><div style="display: grid; grid-template-columns: 86px 1fr; gap: 8px;"><div style="height: 50px; background: #f3f4f6; border-radius: 8px;"></div><div style="display: grid; gap: 6px;"><div style="height: 12px; background: #f3f4f6; border-radius: 6px;"></div><div style="height: 12px; width: 70%; background: #f3f4f6; border-radius: 6px;"></div></div></div><div style="height: 8px; background: #f3f4f6; border-radius: 4px;"></div><div style="height: 8px; background: #f3f4f6; border-radius: 4px;"></div></div>'),
            'horizontal' => array('icon' => '➡️', 'name' => 'Horizontal', 'desc' => 'Side-by-side layout', 'preview' => '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;"><div style="height: 70px; background: #f3f4f6; border-radius: 8px;"></div><div style="display: grid; gap: 8px;"><div style="height: 16px; background: #f3f4f6; border-radius: 6px;"></div><div style="display: grid; gap: 6px;"><div style="height: 12px; background: #f3f4f6; border-radius: 6px;"></div><div style="height: 12px; background: #f3f4f6; border-radius: 6px; width: 60%;"></div></div></div></div>'),
            'table' => array('icon' => '📊', 'name' => 'Table', 'desc' => 'Table format', 'preview' => '<div style="display: grid; gap: 6px;"><div style="height: 14px; background: #f3f4f6; border-radius: 6px;"></div><div style="display: grid; gap: 6px;"><div style="height: 12px; background: #f3f4f6; border-radius: 6px;"></div><div style="height: 12px; background: #f3f4f6; border-radius: 6px;"></div></div></div>'),
            'tabs' => array('icon' => '📑', 'name' => 'Tabs', 'desc' => 'Tabbed view', 'preview' => '<div style="display: grid; gap: 6px;"><div style="display: flex; gap: 4px;"><div style="flex: 1; height: 14px; background: #f3f4f6; border-radius: 6px;"></div><div style="flex: 1; height: 14px; background: #f3f4f6; border-radius: 6px;"></div></div><div style="height: 12px; background: #f3f4f6; border-radius: 6px;"></div><div style="height: 12px; background: #f3f4f6; border-radius: 6px;"></div></div>'),
            'accordion' => array('icon' => '📁', 'name' => 'Accordion', 'desc' => 'Collapsible items', 'preview' => '<div style="display: grid; gap: 8px;"><div style="height: 14px; background: #f3f4f6; border-radius: 6px;"></div><div style="height: 14px; background: #f3f4f6; border-radius: 6px;"></div><div style="height: 14px; background: #f3f4f6; border-radius: 6px;"></div></div>'),
            'grid' => array('icon' => '🔲', 'name' => 'Grid', 'desc' => 'Grid layout', 'preview' => '<div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px;"><div style="height: 40px; background: #f3f4f6; border-radius: 8px;"></div><div style="height: 40px; background: #f3f4f6; border-radius: 8px;"></div><div style="height: 40px; background: #f3f4f6; border-radius: 8px;"></div><div style="height: 40px; background: #f3f4f6; border-radius: 8px;"></div></div>'),
            'combined' => array('icon' => '🔗', 'name' => 'Combined', 'desc' => 'Mixed layout', 'preview' => '<div style="display: grid; gap: 8px;"><div style="height: 30px; background: #f3f4f6; border-radius: 8px;"></div><div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;"><div style="height: 28px; background: #f3f4f6; border-radius: 8px;"></div><div style="height: 28px; background: #f3f4f6; border-radius: 8px;"></div></div></div>'),
            'weather-map' => array('icon' => '🗺️', 'name' => 'Weather Map', 'desc' => 'Interactive map', 'preview' => '<div style="height: 90px; background: #f3f4f6; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: #9ca3af;">Map preview</div>')
        );

        $templates = array(
            'template-one' => __('Template One', 'hsq-weather'),
            'template-two' => __('Template Two', 'hsq-weather'),
            'template-three' => __('Template Three', 'hsq-weather'),
            'template-four' => __('Template Four', 'hsq-weather'),
            'template-five' => __('Template Five', 'hsq-weather'),
            'template-six' => __('Template Six', 'hsq-weather'),
        );
        
        // Sample weather data for preview
        $sample_weather = array(
            'city' => 'London, GB',
            'temperature' => 32,
            'condition' => 'Partly Cloudy',
            'humidity' => 65,
            'wind_speed' => 12,
            'pressure' => 1013,
            'visibility' => 10,
            'feels_like' => 28
        );
        ?>
        <style>
            .hsq-add-weather-header { background: linear-gradient(135deg, #fb923c 0%, #f97316 100%); color: white; padding: 24px; border-radius: 10px; margin-bottom: 30px; display: flex; justify-content: space-between; align-items: center; }
            .hsq-add-weather-header h1 { margin: 0; font-size: 28px; }
            .hsq-add-weather-header .badge { background: rgba(255,255,255,0.3); padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; }
            .hsq-form-section { background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; padding: 24px; margin-bottom: 24px; }
            .hsq-form-section h2 { margin-top: 0; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #eee; font-size: 18px; }
            .hsq-layout-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 15px; margin: 20px 0; }
            .hsq-layout-card { border: 2px solid #e5e7eb; border-radius: 10px; padding: 15px; text-align: center; cursor: pointer; transition: all 0.2s ease; background: #fff; }
            .hsq-layout-card:hover { border-color: #fb923c; background: #fff9f5; }
            .hsq-layout-card.active { border-color: #fb923c; background: #fff9f5; box-shadow: 0 0 0 3px rgba(249, 115, 22, 0.1); }
            .hsq-layout-card .icon { font-size: 32px; margin-bottom: 10px; display: block; }
            .hsq-layout-card .name { font-weight: 600; font-size: 13px; color: #111827; }
            .hsq-layout-card .desc { font-size: 11px; color: #6b7280; margin-top: 5px; }
            .hsq-preview-section { background: #f8fafc; border: 2px dashed #d1d5db; border-radius: 10px; padding: 30px; margin: 24px 0; min-height: 300px; }
            .hsq-preview-title { font-size: 14px; font-weight: 600; color: #6b7280; margin-bottom: 15px; text-transform: uppercase; }
            .hsq-preview-content { background: white; border-radius: 8px; padding: 20px; }
            .hsq-form-group { margin-bottom: 20px; }
            .hsq-form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #111827; }
            .hsq-form-group input[type="text"], .hsq-form-group select { width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px; }
            .hsq-form-group input:focus, .hsq-form-group select:focus { outline: none; border-color: #fb923c; box-shadow: 0 0 0 3px rgba(249, 115, 22, 0.1); }
            .hsq-tabs { display: flex; gap: 0; border-bottom: 1px solid #e5e7eb; margin: 0 -24px 24px -24px; }
            .hsq-tabs button { flex: 1; padding: 12px; border: none; background: transparent; cursor: pointer; color: #6b7280; border-bottom: 3px solid transparent; font-weight: 600; transition: all 0.2s ease; }
            .hsq-tabs button.active { color: #fb923c; border-bottom-color: #fb923c; }
            .hsq-tab-content { display: none; }
            .hsq-tab-content.active { display: block; }
            .hsq-two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
            .hsq-full-width { grid-column: 1 / -1; }
            .hsq-save-actions { display: flex; gap: 10px; margin-top: 30px; }
            
            /* Preview Layouts */
            .hsq-preview-vertical { display: flex; justify-content: center; }
            .hsq-preview-vertical .hsq-weather-card { width: 100%; max-width: 360px; border-radius: 16px; border: 1px solid #e5e7eb; background: #fff; padding: 20px; }
            .hsq-preview-vertical .hsq-card-header, .hsq-preview-tabs .hsq-card-header, .hsq-preview-grid .hsq-card-header { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 12px; }
            .hsq-preview-vertical .hsq-city-name, .hsq-preview-tabs .hsq-city-name, .hsq-preview-grid .hsq-city-name { font-size: 18px; font-weight: 700; color: #111827; }
            .hsq-preview-vertical .hsq-weather-icon, .hsq-preview-tabs .hsq-weather-icon, .hsq-preview-grid .hsq-weather-icon { font-size: 28px; }
            .hsq-preview-vertical .hsq-card-body, .hsq-preview-tabs .hsq-card-body, .hsq-preview-grid .hsq-card-body { display: grid; gap: 10px; }
            .hsq-preview-vertical .hsq-temperature, .hsq-preview-tabs .hsq-temperature, .hsq-preview-grid .hsq-temperature { font-size: 32px; font-weight: 700; color: #1e40af; }
            .hsq-preview-vertical .hsq-wind, .hsq-preview-tabs .hsq-wind, .hsq-preview-grid .hsq-wind { font-size: 14px; color: #4b5563; }
            .hsq-preview-vertical .hsq-humidity, .hsq-preview-tabs .hsq-humidity, .hsq-preview-grid .hsq-humidity { font-size: 14px; color: #4b5563; }
            .hsq-preview-horizontal { overflow-x: auto; width: 100%; }
            .hsq-preview-horizontal .hsq-horizontal-wrapper { display: flex; gap: 20px; padding: 10px 0; min-width: min-content; }
            .hsq-preview-horizontal .hsq-horizontal-card { min-width: 200px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 16px; padding: 20px; text-align: center; color: white; transition: transform 0.3s ease; }
            .hsq-preview-horizontal .hsq-horizontal-card:hover { transform: translateY(-4px); }
            .hsq-preview-horizontal .hsq-city-name { font-size: 18px; font-weight: 700; margin-bottom: 10px; }
            .hsq-preview-horizontal .hsq-weather-icon { font-size: 36px; margin: 10px 0; display: block; }
            .hsq-preview-horizontal .hsq-temperature { font-size: 28px; font-weight: 700; }
            .hsq-preview-table { width: 100%; font-size: 13px; border-collapse: collapse; }
            .hsq-preview-table th, .hsq-preview-table td { padding: 10px 12px; border-bottom: 1px solid #eee; text-align: left; }
            .hsq-preview-table th { background: #f3f4f6; font-weight: 600; }
            .hsq-preview-tabs { display: flex; gap: 8px; margin-bottom: 15px; }
            .hsq-preview-tabs button { padding: 8px 14px; background: #e5e7eb; border: none; border-radius: 999px; cursor: pointer; font-size: 12px; font-weight: 600; color: #4b5563; }
            .hsq-preview-tabs button.active { background: #fb923c; color: white; }
            .hsq-preview-tabs-container { border: 1px solid #e5e7eb; border-radius: 16px; overflow: hidden; background: white; }
            .hsq-preview-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; }
            .hsq-preview-grid .hsq-weather-card { border-radius: 16px; border: 1px solid #e5e7eb; background: #fff; padding: 18px; }
            .hsq-preview-tabs .hsq-weather-card { padding: 18px; }
            .hsq-templates-section { margin-top: 20px; }
            .hsq-template-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(110px, 1fr)); gap: 12px; margin-top: 14px; }
            .hsq-template-card { border: 2px solid #e5e7eb; border-radius: 18px; padding: 16px; background: #fff; cursor: pointer; transition: all 0.2s ease; display: flex; flex-direction: column; align-items: stretch; min-height: 210px; }
            .hsq-template-card.active, .hsq-template-card:hover { border-color: #667eea; box-shadow: 0 14px 35px rgba(102, 126, 234, 0.12); }
            .hsq-template-thumb { display: flex; flex-direction: column; gap: 8px; background: #f8fafc; border: 1px solid #e5e7eb; border-radius: 16px; padding: 14px; min-height: 150px; }
            .hsq-template-row { height: 10px; background: #e5e7eb; border-radius: 999px; }
            .hsq-template-row.short { width: 55%; margin-left: auto; }
            .hsq-template-row.half { width: 45%; }
            .hsq-template-thumb .hsq-template-card-icon { width: 100%; height: 64px; background: #e5e7eb; border-radius: 14px; }
            .hsq-template-card .name { margin-top: auto; font-weight: 700; color: #111827; }
            @media (max-width: 768px) { .hsq-two-col { grid-template-columns: 1fr; } .hsq-preview-horizontal .hsq-horizontal-wrapper { min-width: auto; flex-wrap: wrap; } }
        </style>
        <div class="wrap">
            <div class="hsq-add-weather-header">
                <div>
                    <h1>☁️ <?php _e('Add New Weather', 'hsq-weather'); ?></h1>
                </div>
                <div class="badge"><?php _e('ALL FEATURES FREE', 'hsq-weather'); ?></div>
            </div>

            <form method="post" action="">
                <!-- Title Section -->
                <div class="hsq-form-section">
                    <div class="hsq-form-group">
                        <label><?php _e('Add Title', 'hsq-weather'); ?></label>
                        <input type="text" name="weather_title" placeholder="<?php _e('Enter weather template name', 'hsq-weather'); ?>">
                    </div>
                </div>

                <!-- Layout Templates with Preview -->
                <div class="hsq-form-section">
                    <h2><?php _e('Choose Layout Template', 'hsq-weather'); ?></h2>
                    <div class="hsq-layout-grid">
                        <?php foreach ($layouts as $key => $layout): ?>
                            <?php $active_class = $key === 'horizontal' ? ' active' : ''; ?>
                            <div class="hsq-layout-card<?php echo $active_class; ?>" data-layout="<?php echo esc_attr($key); ?>" onclick="selectLayout('<?php echo esc_attr($key); ?>')">
                                <span class="icon"><?php echo $layout['icon']; ?></span>
                                <div class="name"><?php echo esc_html($layout['name']); ?></div>
                                <div class="desc"><?php echo esc_html($layout['desc']); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="hsq-templates-section" style="display: none;">
                        <h2><?php _e('Templates', 'hsq-weather'); ?></h2>
                        <div class="hsq-template-grid">
                            <?php foreach ($templates as $key => $label): ?>
                                <?php $template_active = $key === 'template-one' ? ' active' : ''; ?>
                                <div class="hsq-template-card<?php echo $template_active; ?>" data-template="<?php echo esc_attr($key); ?>" onclick="selectTemplate('<?php echo esc_attr($key); ?>')">
                                    <div class="hsq-template-thumb">
                                        <div class="hsq-template-card-icon"></div>
                                        <div class="hsq-template-row"></div>
                                        <div class="hsq-template-row half"></div>
                                        <div class="hsq-template-row short"></div>
                                    </div>
                                    <div class="name"><?php echo esc_html($label); ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <p class="description"><?php _e('To create eye-catching Weather Layouts with Graph Charts and access to advanced customizations, Upgrade to Pro!', 'hsq-weather'); ?></p>
                </div>

                <!-- Live Preview Section -->
                <div class="hsq-preview-section">
                    <div class="hsq-preview-title"><?php _e('Live Preview', 'hsq-weather'); ?></div>
                    <div class="hsq-preview-content" id="hsq-preview">
                        <div style="text-align: center; color: #999; padding: 40px;">
                            <?php _e('Select a layout to see preview', 'hsq-weather'); ?>
                        </div>
                    </div>
                </div>

                <!-- Display Settings Tabs -->
                <div class="hsq-form-section">
                    <div class="hsq-tabs">
                        <button type="button" class="tab-btn active" data-tab="my-items"><?php _e('My Items', 'hsq-weather'); ?></button>
                        <button type="button" class="tab-btn" data-tab="craft"><?php _e('Craft', 'hsq-weather'); ?></button>
                        <button type="button" class="tab-btn" data-tab="customization"><?php _e('Customization', 'hsq-weather'); ?></button>
                    </div>

                    <!-- My Items Tab -->
                    <div class="hsq-tab-content active" data-tab="my-items">
                        <h3><?php _e('Display Weather For Specific Location', 'hsq-weather'); ?></h3>
                        <div class="hsq-two-col">
                            <div class="hsq-form-group">
                                <label><?php _e('City Name', 'hsq-weather'); ?></label>
                                <input type="text" id="city-name" placeholder="<?php _e('London, GB', 'hsq-weather'); ?>" onchange="updatePreview()">
                                <small><?php _e('Write your city name and country code only', 'hsq-weather'); ?></small>
                            </div>
                            <div class="hsq-form-group">
                                <label><?php _e('Custom Location Name', 'hsq-weather'); ?></label>
                                <input type="text" placeholder="">
                            </div>
                            <div class="hsq-form-group">
                                <label><input type="checkbox"> <?php _e('Location From Custom Fields', 'hsq-weather'); ?></label>
                            </div>
                            <div class="hsq-form-group">
                                <label><?php _e('Display Weather For Visitors Location (Auto Detect)', 'hsq-weather'); ?></label>
                                <button type="button" class="button"><?php _e('Select Location', 'hsq-weather'); ?></button>
                            </div>
                        </div>
                    </div>

                    <!-- Craft Tab -->
                    <div class="hsq-tab-content" data-tab="craft">
                        <p><?php _e('Craft customization options', 'hsq-weather'); ?></p>
                    </div>

                    <!-- Customization Tab -->
                    <div class="hsq-tab-content" data-tab="customization">
                        <p><?php _e('Customization options', 'hsq-weather'); ?></p>
                    </div>
                </div>

                <!-- Measurement Units -->
                <div class="hsq-form-section">
                    <h2><?php _e('Measurement Units', 'hsq-weather'); ?></h2>
                    <div class="hsq-two-col">
                        <div class="hsq-form-group">
                            <label><?php _e('Display Temperature Unit', 'hsq-weather'); ?></label>
                            <select>
                                <option><?php _e('Celsius (°C)', 'hsq-weather'); ?></option>
                                <option><?php _e('Fahrenheit (°F)', 'hsq-weather'); ?></option>
                            </select>
                        </div>
                        <div class="hsq-form-group">
                            <label><?php _e('Pressure Unit', 'hsq-weather'); ?></label>
                            <select>
                                <option><?php _e('Millibar (mb)', 'hsq-weather'); ?></option>
                            </select>
                        </div>
                        <div class="hsq-form-group">
                            <label><?php _e('Precipitation Unit', 'hsq-weather'); ?></label>
                            <select>
                                <option><?php _e('Millimeter (mm/m)', 'hsq-weather'); ?></option>
                            </select>
                        </div>
                        <div class="hsq-form-group">
                            <label><?php _e('Wind Speed Unit', 'hsq-weather'); ?></label>
                            <select>
                                <option><?php _e('Mph per hour (mph)', 'hsq-weather'); ?></option>
                            </select>
                        </div>
                        <div class="hsq-form-group">
                            <label><?php _e('Visibility Unit', 'hsq-weather'); ?></label>
                            <select>
                                <option><?php _e('Kilometers', 'hsq-weather'); ?></option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Save Actions -->
                <div class="hsq-save-actions">
                    <button type="submit" class="button button-primary button-hero"><?php _e('✓ SAVE WEATHER', 'hsq-weather'); ?></button>
                    <button type="button" class="button" onclick="window.history.back();"><?php _e('Cancel', 'hsq-weather'); ?></button>
                </div>
            </form>
        </div>

        <script>
        const sampleWeather = <?php echo json_encode($sample_weather); ?>;
        const templateOptions = {
            'template-one': { city: sampleWeather.city, icon: '☀️', temp: sampleWeather.temperature, wind: sampleWeather.wind_speed },
            'template-two': { city: 'Paris', icon: '⛅', temp: sampleWeather.temperature - 3, wind: sampleWeather.wind_speed + 5 },
            'template-three': { city: 'Berlin', icon: '🌧️', temp: sampleWeather.temperature - 1, wind: sampleWeather.wind_speed + 2 },
            'template-four': { city: 'Tokyo', icon: '🌤️', temp: sampleWeather.temperature + 2, wind: Math.max(sampleWeather.wind_speed - 3, 0) },
            'template-five': { city: 'Sydney', icon: '🌦️', temp: sampleWeather.temperature + 4, wind: sampleWeather.wind_speed + 1 },
            'template-six': { city: 'Dubai', icon: '☀️', temp: sampleWeather.temperature + 7, wind: sampleWeather.wind_speed + 3 },
        };
        let selectedLayout = 'horizontal';
        let selectedTemplate = 'template-one';

        function selectLayout(layout) {
            selectedLayout = layout;
            document.querySelectorAll('.hsq-layout-card').forEach(card => card.classList.remove('active'));
            const selectedCard = document.querySelector('[data-layout="' + layout + '"]');
            if (selectedCard) {
                selectedCard.classList.add('active');
            }
            const templateSection = document.querySelector('.hsq-templates-section');
            if (templateSection) {
                templateSection.style.display = layout === 'vertical-card' ? 'block' : 'none';
            }
            updatePreview();
        }

        function selectTemplate(template) {
            selectedTemplate = template;
            document.querySelectorAll('.hsq-template-card').forEach(card => card.classList.remove('active'));
            const selectedCard = document.querySelector('[data-template="' + template + '"]');
            if (selectedCard) {
                selectedCard.classList.add('active');
            }
            updatePreview();
        }

        function updatePreview() {
            const preview = document.getElementById('hsq-preview');
            if (!preview || !selectedLayout) return;

            let html = '';
            switch(selectedLayout) {
                case 'vertical-card':
                    html = `<div class="hsq-preview-vertical">
                        <div class="hsq-weather-card">
                            <div class="hsq-card-header">
                                <div class="hsq-city-name">${sampleWeather.city}</div>
                                <div class="hsq-weather-icon">☀️</div>
                            </div>
                            <div class="hsq-card-body">
                                <div class="hsq-temperature">${sampleWeather.temperature}°C</div>
                                <div class="hsq-wind">💨 Wind: ${sampleWeather.wind_speed} km/h</div>
                                <div class="hsq-humidity">💧 Humidity: ${sampleWeather.humidity}%</div>
                            </div>
                        </div>
                    </div>`;
                    break;
                case 'horizontal':
                    const templateData = templateOptions[selectedTemplate] || templateOptions['template-one'];
                    html = `<div class="hsq-preview-horizontal">
                        <div class="hsq-horizontal-wrapper">
                            <div class="hsq-horizontal-card">
                                <div class="hsq-city-name">${sampleWeather.city}</div>
                                <div class="hsq-weather-icon">☀️</div>
                                <div class="hsq-temperature">${sampleWeather.temperature}°C</div>
                                <div class="hsq-wind">💨 ${sampleWeather.wind_speed} km/h</div>
                            </div>
                            <div class="hsq-horizontal-card">
                                <div class="hsq-city-name">${templateData.city}</div>
                                <div class="hsq-weather-icon">${templateData.icon}</div>
                                <div class="hsq-temperature">${templateData.temp}°C</div>
                                <div class="hsq-wind">💨 ${templateData.wind} km/h</div>
                            </div>
                        </div>
                    </div>`;
                    break;
                case 'table':
                    html = `<table class="hsq-preview-table" style="width: 100%;">
                        <tr><th>Property</th><th>Value</th></tr>
                        <tr><td>Temperature</td><td>${sampleWeather.temperature}°C</td></tr>
                        <tr><td>Condition</td><td>${sampleWeather.condition}</td></tr>
                        <tr><td>Humidity</td><td>${sampleWeather.humidity}%</td></tr>
                        <tr><td>Wind Speed</td><td>${sampleWeather.wind_speed} km/h</td></tr>
                        <tr><td>Pressure</td><td>${sampleWeather.pressure} mb</td></tr>
                    </table>`;
                    break;
                case 'tabs':
                    html = `<div class="hsq-preview-tabs-container">
                        <div class="hsq-preview-tabs">
                            <button class="active">Now</button>
                            <button>Tomorrow</button>
                            <button>Weekly</button>
                        </div>
                        <div class="hsq-weather-card">
                            <div class="hsq-card-header">
                                <div class="hsq-city-name">${sampleWeather.city}</div>
                                <div class="hsq-weather-icon">⛅</div>
                            </div>
                            <div class="hsq-card-body">
                                <div class="hsq-temperature">${sampleWeather.temperature}°C</div>
                                <div class="hsq-wind">💨 Wind: ${sampleWeather.wind_speed} km/h</div>
                                <div class="hsq-humidity">💧 Humidity: ${sampleWeather.humidity}%</div>
                            </div>
                        </div>
                    </div>`;
                    break;
                case 'accordion':
                    html = `<div>
                        <div style="background: #f3f4f6; padding: 10px; margin-bottom: 5px; cursor: pointer; border-radius: 4px;">
                            <strong>▼ Current Weather</strong>
                        </div>
                        <div style="padding: 10px; background: #fafafa; border-radius: 4px; font-size: 13px;">
                            <div>Temperature: ${sampleWeather.temperature}°C</div>
                            <div>Condition: ${sampleWeather.condition}</div>
                            <div>Humidity: ${sampleWeather.humidity}%</div>
                        </div>
                    </div>`;
                    break;
                case 'grid':
                    html = `<div class="hsq-preview-grid">
                        <div class="hsq-weather-card">
                            <div class="hsq-card-header">
                                <div class="hsq-city-name">${sampleWeather.city}</div>
                                <div class="hsq-weather-icon">☀️</div>
                            </div>
                            <div class="hsq-card-body">
                                <div class="hsq-temperature">${sampleWeather.temperature}°C</div>
                                <div class="hsq-wind">💨 ${sampleWeather.wind_speed} km/h</div>
                            </div>
                        </div>
                        <div class="hsq-weather-card">
                            <div class="hsq-card-header">
                                <div class="hsq-city-name">Berlin</div>
                                <div class="hsq-weather-icon">🌧️</div>
                            </div>
                            <div class="hsq-card-body">
                                <div class="hsq-temperature">${sampleWeather.temperature - 1}°C</div>
                                <div class="hsq-wind">💨 ${sampleWeather.wind_speed + 2} km/h</div>
                            </div>
                        </div>
                        <div class="hsq-weather-card">
                            <div class="hsq-card-header">
                                <div class="hsq-city-name">Tokyo</div>
                                <div class="hsq-weather-icon">🌤️</div>
                            </div>
                            <div class="hsq-card-body">
                                <div class="hsq-temperature">${sampleWeather.temperature + 2}°C</div>
                                <div class="hsq-wind">💨 ${sampleWeather.wind_speed - 3} km/h</div>
                            </div>
                        </div>
                        <div class="hsq-weather-card">
                            <div class="hsq-card-header">
                                <div class="hsq-city-name">Sydney</div>
                                <div class="hsq-weather-icon">🌦️</div>
                            </div>
                            <div class="hsq-card-body">
                                <div class="hsq-temperature">${sampleWeather.temperature + 4}°C</div>
                                <div class="hsq-wind">💨 ${sampleWeather.wind_speed + 1} km/h</div>
                            </div>
                        </div>
                    </div>`;
                    break;
                case 'combined':
                    html = `<div style="display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px;">
                        <div class="hsq-weather-card">
                            <div class="hsq-card-header">
                                <div class="hsq-city-name">${sampleWeather.city}</div>
                                <div class="hsq-weather-icon">☀️</div>
                            </div>
                            <div class="hsq-card-body">
                                <div class="hsq-temperature">${sampleWeather.temperature}°C</div>
                                <div class="hsq-wind">💨 ${sampleWeather.wind_speed} km/h</div>
                            </div>
                        </div>
                        <div class="hsq-weather-card">
                            <div class="hsq-card-header">
                                <div class="hsq-city-name">Forecast</div>
                                <div class="hsq-weather-icon">🌤️</div>
                            </div>
                            <div class="hsq-card-body">
                                <div class="hsq-temperature">${sampleWeather.temperature + 1}°C</div>
                                <div class="hsq-humidity">💧 ${sampleWeather.humidity}%</div>
                            </div>
                        </div>
                    </div>`;
                    break;
                case 'weather-map':
                    html = `<div style="text-align: center; padding: 30px; background: #e0f2fe; border-radius: 6px;">
                        🗺️ Interactive Weather Map<br>
                        <small>${sampleWeather.city}</small>
                    </div>`;
                    break;
            }
            preview.innerHTML = html;
        }

        function initHsqAddNewWeatherPage() {
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const tab = this.dataset.tab;
                    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
                    document.querySelectorAll('.hsq-tab-content').forEach(c => c.classList.remove('active'));
                    this.classList.add('active');
                    document.querySelector('[data-tab="' + tab + '"]').classList.add('active');
                });
            });

            selectLayout('horizontal');
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initHsqAddNewWeatherPage);
        } else {
            initHsqAddNewWeatherPage();
        }
        </script>
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
