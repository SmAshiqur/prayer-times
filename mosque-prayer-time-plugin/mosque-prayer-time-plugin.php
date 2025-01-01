<?php
/**
 * Plugin Name: Mosque Prayer Time Plugin
 * Plugin URI: https://wordpress.org/plugins/muslim-prayer-time/
 * Description: Accurate prayer timings for all world timezones, ensuring seamless scheduling for users worldwide.
 * Version: 1.2.26
 * Requires at least: 6.4.1
 * Requires PHP: 7.2
 * Author: Masjid Solutions
 * Author URI: https://masjidsolutions.net/
 * License: GPLv2
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: mpt
 * Update URI: #Github-URL
 */

require_once plugin_dir_path(__FILE__) . 'includes/class-mosque-info-input.php';

/**
 * Main Plugin Class
 */
class Mosque_Prayer_Time_Plugin {

    public function __construct() {
        // Admin menu and settings
        add_action('admin_menu', array($this, 'add_menu_page'));
        add_action('admin_init', array($this, 'initialize_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_styles'));


        // Render prayer times widget based on settings
        if (get_option('mosque_prayer_time_activated_secure_api')) {
            add_action('wp_footer', array($this, 'render_prayer_times_based_on_position'));
        }

        // Enqueue admin scripts
        add_action('admin_enqueue_scripts', array($this, 'enqueue_custom_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));

        // Register shortcode
        add_action('init', array($this, 'register_shortcodes'));
        add_action('wp_ajax_render_shortcode', array($this, 'handle_shortcode_rendering'));

    }

    /**
     * Add admin menu page
     */
    public function add_menu_page() {
        add_menu_page('Mosque Prayer Time Admin Page', 'Mosque Prayer Time', 'manage_options', 'mosque-prayer-time-settings', array($this, 'settings_page'));
    }
    public function enqueue_admin_styles() {
        wp_enqueue_style('prayer-times-admin-styles', plugin_dir_url(__FILE__) . 'assets/css/admin-styles.css');
        wp_enqueue_style(
            'prayer-times-admin-preview-modal',
            plugin_dir_url(__FILE__) . 'assets/css/admin-preview-modal.css'
        );
    }
    public function enqueue_admin_scripts() {
        wp_enqueue_script(
            'prayer-times-admin-preview-modal',
            plugin_dir_url(__FILE__) . 'assets/js/admin-preview-modal.js',
            array('jquery'),
            '1.0',
            true
        );
    
        // Pass data as an array to the script
        wp_localize_script('prayer-times-admin-preview-modal', 'PrayerTimesAdmin', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
        ));
    }
    
    
    public function handle_shortcode_rendering() {
        if (!isset($_POST['shortcode'])) {
            wp_send_json_error('No shortcode provided', 400);
        }
    
        $shortcode = sanitize_text_field(wp_unslash($_POST['shortcode']));
        $output = do_shortcode($shortcode);
    
        // Return plain HTML instead of JSON to prevent escaping issues
        echo $output;
        wp_die(); // Important to terminate the request properly
    }
    
    
    
    
    /**
     * Initialize plugin settings
     */
    public function initialize_settings() {
        Mosque_Info_Input::initialize_settings();

        // Register settings
        register_setting('mosque_prayer_time_settings', 'mosque_prayer_time_activated_geo');
        register_setting('mosque_prayer_time_settings', 'mosque_prayer_time_activated_secure_api');
        register_setting('mosque_prayer_time_settings', 'mosque_prayer_time_display_position');

        // Add fields
        add_settings_field('mosque_prayer_time_activated_geo', 'Footer Widget - GEO Location', array($this, 'activated_callback'), 'mosque_prayer_time_settings', 'mosque_prayer_time_section', array('field_name' => 'mosque_prayer_time_activated_geo'));
        add_settings_field('mosque_prayer_time_activated_secure_api', 'Footer Widget - SECURE API', array($this, 'activated_callback'), 'mosque_prayer_time_settings', 'mosque_prayer_time_section', array('field_name' => 'mosque_prayer_time_activated_secure_api'));
        add_settings_field('mosque_prayer_time_display_position', 'Widget Display Position', array($this, 'position_dropdown_callback'), 'mosque_prayer_time_settings', 'mosque_prayer_time_section');

        // Add settings section
        add_settings_section('mosque_prayer_time_section', 'Plugin Settings', array($this, 'section_callback'), 'mosque_prayer_time_settings');
    }

    /**
     * Render settings section callback
     */
    public function section_callback() {
        echo '<p>Activate or deactivate the plugin and choose the display position.</p>';
    }

    /**
     * Render activated callback
     */
    public function activated_callback($args) {
        $field_name = $args['field_name'];
        $activated = get_option($field_name);
        $opposite_field_name = ($field_name === 'mosque_prayer_time_activated_geo') ? 'mosque_prayer_time_activated_secure_api' : 'mosque_prayer_time_activated_geo';

        if ($activated) {
            update_option($opposite_field_name, 0);
        }

        echo '<label>';
        echo '<input type="checkbox" name="' . esc_attr($field_name) . '" value="1" ' . checked(1, $activated, false) . '> Activate';
        echo '</label>';
    }

    /**
     * Render position dropdown callback
     */
    public function position_dropdown_callback() {
        $position = get_option('mosque_prayer_time_display_position', 'footer');
        ?>
        <select name="mosque_prayer_time_display_position">
            <option value="footer" <?php selected($position, 'footer'); ?>>Footer</option>
            <option value="sidebar" <?php selected($position, 'sidebar'); ?>>Sidebar</option>
        </select>
        <?php
    }

    /**
     * Render settings page
     */
    public function settings_page() {
        ?>
        <div class="wrap">
            <h1>Mosque Prayer Time Admin Page</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('mosque_prayer_time_settings');
                do_settings_sections('mosque_prayer_time_settings');
                submit_button();
                ?>
            </form>
    
            <!-- Instructions Section -->
            <div class="shortcode-instructions">
                <h2>How to Use the Prayer Time Widget</h2>
                <p>Use the shortcode below to add the prayer times widget to any page or post:</p>
                <code>[prayer_times_body_widget style="horizontal"]</code> - For the horizontal layout<br>
                <code>[prayer_times_body_widget style="vertical"]</code> - For the vertical layout<br>
                <p>Simply copy and paste the shortcode into your page builder or editor. The style parameter determines the layout.</p>
            </div>
    
            <!-- Preview Buttons -->
            <div class="widget-preview-buttons" style="margin-top: 20px;">
                <button type="button" id="preview-horizontal" class="button button-primary">Preview Horizontal</button>
                <button type="button" id="preview-vertical" class="button button-secondary">Preview Vertical</button>
            </div>
    
            <!-- Modal Structure -->
            <div id="widget-preview-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.7); z-index: 9999; justify-content: center; align-items: center;">
                <div style="background: #fff; padding: 20px; border-radius: 8px; width: 80%; max-width: 800px; text-align: center; position: relative;">
                    <button id="close-preview-modal" style="position: absolute; top: 10px; right: 10px; background: transparent; border: none; font-size: 20px; cursor: pointer;">&times;</button>
                    <h2 id="widget-preview-title" style="margin-bottom: 20px;">Widget Previews</h2>
                    <div id="widget-preview-content" style="text-align: center;">
                        <div class="widget-preview">
                            <div style="display: flex; gap: 20px;">
                                <div style="flex: 1;">
                                    <h3>Horizontal View</h3>
                                    <?php echo do_shortcode('[prayer_times_body_widget style="horizontal"]'); ?>
                                </div>
                                <div style="flex: 1;">
                                    <h3>Vertical View</h3>
                                    <?php echo do_shortcode('[prayer_times_body_widget style="vertical"]'); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    
    

    /**
     * Render prayer times widget based on position
     */
    public function render_prayer_times_based_on_position() {
        $position = get_option('mosque_prayer_time_display_position', 'footer');
        wp_enqueue_style('mosque-prayer-time-style', plugin_dir_url(__FILE__) . 'assets/css/prayer-times.css');

        if ($position === 'footer') {
            $this->render_prayer_times('footer');
        } elseif ($position === 'sidebar') {
            $this->render_prayer_times('sidebar');
        }
    }

    private function render_prayer_times($location) {
        $mosque_name = get_option('mosque_prayer_time_mosque_name');
        $mosque_slug = get_option('mosque_prayer_time_mosque_slug');
        $response = $this->prayer_times_api($mosque_slug);
        $prayer_array = $response ? json_decode($response) : null;

        if ($prayer_array) {
            $class_prefix = $location === 'footer' ? 'prayer-times-footer' : 'prayer-times-sidebar';
            ?>
            <div class="<?php echo esc_attr($class_prefix); ?>">
                <div class="<?php echo esc_attr($class_prefix . '-header'); ?>">
                    Prayer Times - <?php echo esc_html($mosque_name); ?>
                    <span class="<?php echo esc_attr($class_prefix . '-toggle'); ?>">&#9650;</span>
                </div>
                <div class="<?php echo esc_attr($class_prefix . '-content'); ?>">
                    <table class="<?php echo esc_attr($class_prefix . '-table'); ?>">
                        <thead>
                            <tr>
                                <th>Salah</th>
                                <th>Adhan</th>
                                <th>Iqamah</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            foreach (['fajr', 'dhuhr', 'asr', 'maghrib', 'isha'] as $key) {
                                if (isset($prayer_array->$key) && $prayer_array->$key->isShow) {
                                    $salah = $prayer_array->$key;
                                    ?>
                                    <tr>
                                        <td>
                                            <img src="<?php echo plugin_dir_url(__FILE__) . 'assets/icons/' . $key . '.svg'; ?>" alt="<?php echo ucfirst($key); ?>" style="width: 16px; height: 16px; vertical-align: middle; margin-right: 5px;">
                                            <?php echo esc_html($prayer_array->$key->salahName); ?>
                                        </td>
                                        <td><?php echo esc_html($prayer_array->$key->salahTime); ?></td>
                                        <td><?php echo esc_html($prayer_array->$key->salahIqamahTime); ?></td>
                                    </tr>
                                    <?php
                                }
                            }

                            if (!empty($prayer_array->jummahTimes)) {
                                foreach ($prayer_array->jummahTimes as $jummah) {
                                    ?>
                                    <tr>
                                        <td>Jumu'ah</td>
                                        <td><?php echo esc_html($jummah->jummahTime); ?></td>
                                        <td><?php echo esc_html($jummah->iqamahTime); ?></td>
                                    </tr>
                                    <?php
                                }
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <script>
                document.querySelectorAll('.<?php echo esc_js($class_prefix . "-toggle"); ?>').forEach(toggle => {
                    toggle.addEventListener('click', function () {
                        const content = this.closest('.<?php echo esc_js($class_prefix); ?>').querySelector('.<?php echo esc_js($class_prefix . "-content"); ?>');
                        if (content.style.display === 'none' || content.style.display === '') {
                            content.style.display = 'block';
                            this.innerHTML = '&#9660;';
                        } else {
                            content.style.display = 'none';
                            this.innerHTML = '&#9650;';
                        }
                    });
                });
            </script>
            <?php
        } else {
            echo '<div>Error fetching prayer times</div>';
        }
    }

    private function prayer_times_api($mosque_slug) {
        $curl = curl_init();
        $base_url = 'https://secure-api.net/api/v1';
        $end_point = '/company/prayer/daily/schedule';
        $query_parameter = '?slug=' . urlencode($mosque_slug);

        curl_setopt_array($curl, array(
            CURLOPT_URL => $base_url . $end_point . $query_parameter,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
        ));

        $response = curl_exec($curl);

        if (curl_errno($curl)) {
            error_log('Prayer Times API Error: ' . curl_error($curl));
            $response = false;
        }

        curl_close($curl);
        return $response;
    }
    

    /**
     * Enqueue custom scripts
     */
    public function enqueue_custom_scripts() {
        wp_enqueue_script('mosque-custom-script', plugin_dir_url(__FILE__) . 'assets/js/custom-script.js', array('jquery'), '1.0', true);
    }

    /**
     * Register shortcode
     */
    public function register_shortcodes() {
        add_shortcode('prayer_times_body_widget', array($this, 'render_prayer_time_body_widget'));
    }

    /**
     * Shortcode for Prayer Time Body Widget
     */
    public function render_prayer_time_body_widget($atts) {
        // Get the style parameter
        $atts = shortcode_atts(array(
            'style' => 'horizontal', // Default style is horizontal
        ), $atts, 'prayer_times_body_widget');
    
        $style = $atts['style'];
    
        $mosque_slug = get_option('mosque_prayer_time_mosque_slug');
        $mosque_name = get_option('mosque_prayer_time_mosque_name', 'Your Mosque Name');
        $response = $this->prayer_times_api($mosque_slug);
        $prayer_array = $response ? json_decode($response) : null;
    
        if (!$prayer_array) {
            return '<div>Error fetching prayer times</div>';
        }
        $current_date = date('F j, Y'); // Today's date

        ob_start();
    
        if ($style === 'horizontal') {
            // Horizontal Style (Original Design)
            ?>
            <div class="prayer-time-body-widget horizontal">
                <?php if (!empty($prayer_array->jummahTimes)): ?>
                    <div class="jummah-time-header" style="background-color: #002855; color: white; padding: 10px; text-align: center; font-weight: bold; font-size: 16px;">
                        JUMMAH STARTS AT <?php echo esc_html($prayer_array->jummahTimes[0]->jummahTime); ?>
                    </div>
                <?php endif; ?>
    
                <div class="prayer-time-header" style="background-color: #5C9ABB; color:rgb(255, 255, 255); padding: 10px; text-align: center; font-size: 16px; font-family: Arial, sans-serif;">
                    <p>Today is <?php echo date('F j, Y'); ?> @ <?php echo esc_html($mosque_name); ?></p>
                </div>
    
                <div class="prayer-time-container" style="display: flex; justify-content: space-around; flex-wrap: wrap; margin-top: 10px;">
                    <?php foreach (['fajr', 'dhuhr', 'asr', 'maghrib', 'isha'] as $key): ?>
                        <?php if (isset($prayer_array->$key) && $prayer_array->$key->isShow): ?>
                            <div class="prayer-time-item" style="text-align: center; margin: 10px; flex: 1;">
                                <div class="prayer-time-icon">
                                    <img src="<?php echo plugin_dir_url(__FILE__) . 'assets/icons/' . $key . '.svg'; ?>" alt="<?php echo ucfirst($key); ?>" style="width: 40px; height: 40px;">
                                </div>
                                <div class="prayer-time-info" style="margin-top: 5px;">
                                    <p class="prayer-name" style="font-size: 14px; font-weight: bold;"><?php echo strtoupper($key); ?></p>
                                    <p class="prayer-time" style="font-size: 14px;"><?php echo esc_html($prayer_array->$key->salahTime); ?></p>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php
        } elseif ($style === 'vertical') {
            // Vertical Style
            ?>
            <div class="prayer-time-body-widget vertical">
                <div class="prayer-time-header" style="text-align: center; font-family: Arial, sans-serif; margin-bottom: 10px;">
                    <h3 style="font-weight: bold; margin-bottom: 15px;">PRAYER TIMINGS</h3>
                    <p style="margin: 0;"><?php echo date('l, F j, Y'); ?></p>
                    <p style="margin: 0; color: #373737;"><?php echo esc_html($mosque_name); ?></p>

                </div>
                <table class="prayer-time-table" style="width: 100%; border-collapse: collapse; margin: 10px auto; text-align: center;">
                    <thead>
                        <tr style="background-color: #f5f5f5;">
                            <th style="padding: 8px; border: 1px solid #ddd; font-size: 14px">PRAYER</th>
                            <th style="padding: 8px; border: 1px solid #ddd; font-size: 14px">STARTS</th>
                            <th style="padding: 8px; border: 1px solid #ddd; font-size: 14px">IQAMAH</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (['fajr', 'dhuhr', 'asr', 'maghrib', 'isha'] as $key): ?>
                            <?php if (isset($prayer_array->$key) && $prayer_array->$key->isShow): ?>
                                <tr>
                                    <td style="padding: 8px; border: 1px solid #ddd; font-size: 14px">
                                        <img src="<?php echo plugin_dir_url(__FILE__) . 'assets/icons/' . $key . '.svg'; ?>" alt="<?php echo ucfirst($key); ?>" style="width: 20px; height: 20px; vertical-align: middle; margin-right: 5px;">
                                        <?php echo strtoupper($key); ?>
                                    </td>
                                    <td style="padding: 8px; border: 1px solid #ddd; font-size: 14px"><?php echo esc_html($prayer_array->$key->salahTime); ?></td>
                                    <td style="padding: 8px; border: 1px solid #ddd; font-size: 14px"><?php echo esc_html($prayer_array->$key->salahIqamahTime); ?></td>
                                </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if (!empty($prayer_array->jummahTimes)): ?>
                    <div class="jummah-times" style="text-align: center; margin-top: 10px; font-weight: normal; font-size:14px;">
                        <?php foreach ($prayer_array->jummahTimes as $jummah): ?>
                            <p><?php echo esc_html($jummah->jummahTime); ?> - Jummah</p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            <p style="text-align: center; margin-top: 10px; font-size: 12px; color: #888;">
                Powered by <a href="https://masjidsolutions.net" target="_blank" style="text-decoration: none; color: #002855; font-weight: bold;">MASJID SOLUTIONS</a>
            </p>
            <?php
        }
    
        return ob_get_clean();
    }
    
    
    
    
}




new Mosque_Prayer_Time_Plugin();
add_filter('site_transient_update_plugins', 'mpt_check_github_update');
function mpt_check_github_update($transient) {
    if (empty($transient->checked)) {
        return $transient;
    }

    // Define your plugin information
    $plugin_file = plugin_basename(__FILE__);
    $plugin_slug = 'muslim-prayer-time';
    $github_repo = 'SmAshiqur/prayer-times';
    $github_api_url = "https://api.github.com/repos/{$github_repo}/releases/latest";

    // Make an API call to GitHub
    $response = wp_remote_get($github_api_url, array('timeout' => 10));

    if (is_wp_error($response)) {
        return $transient; // Return unchanged transient on error
    }

    $release_data = json_decode(wp_remote_retrieve_body($response), true);

    if (empty($release_data['tag_name'])) {
        return $transient; // Return unchanged transient if no version tag
    }

    $new_version = $release_data['tag_name'];
    $current_version = '1.2.26'; // Current version of your plugin

    // Check if the new version is greater than the current version
    if (version_compare($current_version, $new_version, '<')) {
        $transient->response[$plugin_file] = (object) array(
            'slug' => $plugin_slug,
            'new_version' => $new_version,
            'package' => $release_data['zipball_url'], // GitHub's zipball URL for the release
            'url' => $release_data['html_url'], // Release page URL
        );
    }

    return $transient;
}

add_filter('plugins_api', 'mpt_github_plugin_details', 20, 3);
function mpt_github_plugin_details($res, $action, $args) {
    if ($action !== 'plugin_information' || $args->slug !== 'muslim-prayer-time') {
        return $res;
    }

    $github_repo = 'SmAshiqur/prayer-times';
    $github_api_url = "https://api.github.com/repos/{$github_repo}/releases/latest";

    // Make an API call to GitHub
    $response = wp_remote_get($github_api_url, array('timeout' => 10));

    if (is_wp_error($response)) {
        return $res; // Return default response on error
    }

    $release_data = json_decode(wp_remote_retrieve_body($response), true);

    if (empty($release_data)) {
        return $res; // Return default response if no data
    }

    // Populate plugin details
    $res = (object) array(
        'name' => 'Mosque Prayer Time Plugin',
        'slug' => 'muslim-prayer-time',
        'version' => $release_data['tag_name'],
        'author' => '<a href="https://masjidsolutions.net">Masjid Solutions</a>',
        'homepage' => 'https://github.com/SmAshiqur/prayer-times',
        'download_link' => $release_data['zipball_url'],
        'sections' => array(
            'description' => 'Accurate prayer timings for all world timezones, ensuring seamless scheduling for users worldwide.',
            'changelog' => !empty($release_data['body']) ? $release_data['body'] : 'No changelog available.',
        ),
    );

    return $res;
}
