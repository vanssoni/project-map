<?php
/**
 * Plugin Name: Project Map Plugin
 * Plugin URI: https://github.com/vanssoni
 * Description: A comprehensive plugin to display projects on an interactive world map with GPS pins, filtering, and detailed project reports.
 * Version: 1.0.0
 * Author: Abhishek
 * License: GPL v2 or later
 * Text Domain: project-map-plugin
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('PMP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('PMP_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('PMP_VERSION', '1.0.3');

class ProjectMapPlugin
{

    private static $instance = null;

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct()
    {
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        add_action('admin_menu', array($this, 'add_admin_menu'));

        // Register shortcodes
        add_shortcode('project_map', array($this, 'project_map_shortcode'));
        add_shortcode('project_detail', array($this, 'project_detail_shortcode'));

        // Activation/Deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));

        // AJAX handlers
        add_action('wp_ajax_pmp_get_projects', array($this, 'ajax_get_projects'));
        add_action('wp_ajax_nopriv_pmp_get_projects', array($this, 'ajax_get_projects'));
        add_action('wp_ajax_pmp_import_csv', array($this, 'ajax_import_csv'));
        add_action('wp_ajax_pmp_export_csv', array($this, 'ajax_export_csv'));
        add_action('wp_ajax_pmp_delete_project', array($this, 'ajax_delete_project'));
        add_action('wp_ajax_pmp_import_project_types', array($this, 'ajax_import_project_types'));
        add_action('wp_ajax_pmp_export_project_types', array($this, 'ajax_export_project_types'));
        add_action('wp_ajax_pmp_import_solution_types', array($this, 'ajax_import_solution_types'));
        add_action('wp_ajax_pmp_export_solution_types', array($this, 'ajax_export_solution_types'));

        // Template redirect for single project
        add_action('template_redirect', array($this, 'handle_single_project'));
        add_filter('query_vars', array($this, 'add_query_vars'));
        add_action('init', array($this, 'add_rewrite_rules'));
    }

    public function init()
    {
        load_plugin_textdomain('project-map-plugin', false, dirname(plugin_basename(__FILE__)) . '/languages');

        // Flush rewrite rules if version changed
        $stored_version = get_option('pmp_version', '0');
        if (version_compare($stored_version, PMP_VERSION, '<')) {
            flush_rewrite_rules();
            update_option('pmp_version', PMP_VERSION);
        }
    }

    public function activate()
    {
        $this->create_database_tables();
        $this->insert_default_project_types();
        $this->insert_default_solution_types();
        $this->insert_default_countries();
        $this->set_default_options();
        flush_rewrite_rules();
    }

    public function deactivate()
    {
        flush_rewrite_rules();
    }

    public function create_database_tables()
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Project Types table (e.g., Water Project, Orphan Home)
        $project_types_table = $wpdb->prefix . 'pmp_project_types';
        $sql_project_types = "CREATE TABLE $project_types_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            description text,
            icon varchar(50),
            color varchar(20) DEFAULT '#ffc220',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY name (name)
        ) $charset_collate;";

        // Solution types table (linked to project types)
        $solution_types_table = $wpdb->prefix . 'pmp_solution_types';
        $sql_solution_types = "CREATE TABLE $solution_types_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            project_type_id mediumint(9),
            name varchar(255) NOT NULL,
            description text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY name (name),
            KEY project_type_id (project_type_id)
        ) $charset_collate;";

        // Projects table
        $projects_table = $wpdb->prefix . 'pmp_projects';
        $sql_projects = "CREATE TABLE $projects_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            village_name varchar(255) NOT NULL,
            project_number varchar(100),
            project_type_id mediumint(9),
            country varchar(100) NOT NULL,
            gps_latitude decimal(10,8) NOT NULL,
            gps_longitude decimal(11,8) NOT NULL,
            completion_month tinyint(2),
            completion_year smallint(4),
            beneficiaries int(11) DEFAULT 0,
            in_honour_of text,
            solution_type_id mediumint(9),
            featured_image_id bigint(20),
            gallery_images text,
            video_urls text,
            description longtext,
            status varchar(20) DEFAULT 'publish',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY country (country),
            KEY project_type_id (project_type_id),
            KEY solution_type_id (solution_type_id),
            KEY status (status)
        ) $charset_collate;";

        // Countries table for reference
        $countries_table = $wpdb->prefix . 'pmp_countries';
        $sql_countries = "CREATE TABLE $countries_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(100) NOT NULL,
            code varchar(3) NOT NULL,
            flag varchar(10),
            PRIMARY KEY (id),
            UNIQUE KEY code (code)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_project_types);
        dbDelta($sql_solution_types);
        dbDelta($sql_projects);
        dbDelta($sql_countries);
    }

    private function insert_default_project_types()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'pmp_project_types';

        $count = $wpdb->get_var("SELECT COUNT(*) FROM $table");
        if ($count > 0)
            return;

        $types = array(
            array('Water Project', 'Clean water initiatives providing safe drinking water', ''),
            array('Orphan Home', 'Care facilities for orphaned children', ''),
            array('Education', 'Schools and educational programs', ''),
            array('Healthcare', 'Medical facilities and health programs', ''),
            array('Agriculture', 'Farming and food security projects', '')
        );

        foreach ($types as $type) {
            $wpdb->insert($table, array(
                'name' => $type[0],
                'description' => $type[1],
                'icon' => $type[2]
            ), array('%s', '%s', '%s'));
        }
    }

    private function insert_default_solution_types()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'pmp_solution_types';
        $project_types_table = $wpdb->prefix . 'pmp_project_types';

        $count = $wpdb->get_var("SELECT COUNT(*) FROM $table");
        if ($count > 0)
            return;

        // Get Water Project ID
        $water_project_id = $wpdb->get_var("SELECT id FROM $project_types_table WHERE name = 'Water Project'");

        $types = array(
            array($water_project_id, 'Piped System Tap Stand'),
            array($water_project_id, 'Well With Hand Pump'),
            array($water_project_id, 'Spring Protection'),
            array($water_project_id, 'Rainwater Catchment System'),
            array($water_project_id, 'Borehole'),
            array($water_project_id, 'Water Kiosk'),
            array($water_project_id, 'Solar-Powered Pump')
        );

        foreach ($types as $type) {
            $wpdb->insert($table, array(
                'project_type_id' => $type[0],
                'name' => $type[1]
            ), array('%d', '%s'));
        }
    }

    private function insert_default_countries()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'pmp_countries';

        $countries = array(
            array('Afghanistan', 'AF', '🇦🇫'),
            array('Albania', 'AL', '🇦🇱'),
            array('Algeria', 'DZ', '🇩🇿'),
            array('Andorra', 'AD', '🇦🇩'),
            array('Angola', 'AO', '🇦🇴'),
            array('Antigua and Barbuda', 'AG', '🇦🇬'),
            array('Argentina', 'AR', '🇦🇷'),
            array('Armenia', 'AM', '🇦🇲'),
            array('Australia', 'AU', '🇦🇺'),
            array('Austria', 'AT', '🇦🇹'),
            array('Azerbaijan', 'AZ', '🇦🇿'),
            array('Bahamas', 'BS', '🇧🇸'),
            array('Bahrain', 'BH', '🇧🇭'),
            array('Bangladesh', 'BD', '🇧🇩'),
            array('Barbados', 'BB', '🇧🇧'),
            array('Belarus', 'BY', '🇧🇾'),
            array('Belgium', 'BE', '🇧🇪'),
            array('Belize', 'BZ', '🇧🇿'),
            array('Benin', 'BJ', '🇧🇯'),
            array('Bhutan', 'BT', '🇧🇹'),
            array('Bolivia', 'BO', '🇧🇴'),
            array('Bosnia and Herzegovina', 'BA', '🇧🇦'),
            array('Botswana', 'BW', '🇧🇼'),
            array('Brazil', 'BR', '🇧🇷'),
            array('Brunei', 'BN', '🇧🇳'),
            array('Bulgaria', 'BG', '🇧🇬'),
            array('Burkina Faso', 'BF', '🇧🇫'),
            array('Burundi', 'BI', '🇧🇮'),
            array('Cambodia', 'KH', '🇰🇭'),
            array('Cameroon', 'CM', '🇨🇲'),
            array('Canada', 'CA', '🇨🇦'),
            array('Cape Verde', 'CV', '🇨🇻'),
            array('Central African Republic', 'CF', '🇨🇫'),
            array('Chad', 'TD', '🇹🇩'),
            array('Chile', 'CL', '🇨🇱'),
            array('China', 'CN', '🇨🇳'),
            array('Colombia', 'CO', '🇨🇴'),
            array('Comoros', 'KM', '🇰🇲'),
            array('Congo', 'CG', '🇨🇬'),
            array('Costa Rica', 'CR', '🇨🇷'),
            array('Croatia', 'HR', '🇭🇷'),
            array('Cuba', 'CU', '🇨🇺'),
            array('Cyprus', 'CY', '🇨🇾'),
            array('Czech Republic', 'CZ', '🇨🇿'),
            array('Democratic Republic of the Congo', 'CD', '🇨🇩'),
            array('Denmark', 'DK', '🇩🇰'),
            array('Djibouti', 'DJ', '🇩🇯'),
            array('Dominica', 'DM', '🇩🇲'),
            array('Dominican Republic', 'DO', '🇩🇴'),
            array('Ecuador', 'EC', '🇪🇨'),
            array('Egypt', 'EG', '🇪🇬'),
            array('El Salvador', 'SV', '🇸🇻'),
            array('Equatorial Guinea', 'GQ', '🇬🇶'),
            array('Eritrea', 'ER', '🇪🇷'),
            array('Estonia', 'EE', '🇪🇪'),
            array('Eswatini', 'SZ', '🇸🇿'),
            array('Ethiopia', 'ET', '🇪🇹'),
            array('Fiji', 'FJ', '🇫🇯'),
            array('Finland', 'FI', '🇫🇮'),
            array('France', 'FR', '🇫🇷'),
            array('Gabon', 'GA', '🇬🇦'),
            array('Gambia', 'GM', '🇬🇲'),
            array('Georgia', 'GE', '🇬🇪'),
            array('Germany', 'DE', '🇩🇪'),
            array('Ghana', 'GH', '🇬🇭'),
            array('Greece', 'GR', '🇬🇷'),
            array('Grenada', 'GD', '🇬🇩'),
            array('Guatemala', 'GT', '🇬🇹'),
            array('Guinea', 'GN', '🇬🇳'),
            array('Guinea-Bissau', 'GW', '🇬🇼'),
            array('Guyana', 'GY', '🇬🇾'),
            array('Haiti', 'HT', '🇭🇹'),
            array('Honduras', 'HN', '🇭🇳'),
            array('Hungary', 'HU', '🇭🇺'),
            array('Iceland', 'IS', '🇮🇸'),
            array('India', 'IN', '🇮🇳'),
            array('Indonesia', 'ID', '🇮🇩'),
            array('Iran', 'IR', '🇮🇷'),
            array('Iraq', 'IQ', '🇮🇶'),
            array('Ireland', 'IE', '🇮🇪'),
            array('Israel', 'IL', '🇮🇱'),
            array('Italy', 'IT', '🇮🇹'),
            array('Ivory Coast', 'CI', '🇨🇮'),
            array('Jamaica', 'JM', '🇯🇲'),
            array('Japan', 'JP', '🇯🇵'),
            array('Jordan', 'JO', '🇯🇴'),
            array('Kazakhstan', 'KZ', '🇰🇿'),
            array('Kenya', 'KE', '🇰🇪'),
            array('Kiribati', 'KI', '🇰🇮'),
            array('Kuwait', 'KW', '🇰🇼'),
            array('Kyrgyzstan', 'KG', '🇰🇬'),
            array('Laos', 'LA', '🇱🇦'),
            array('Latvia', 'LV', '🇱🇻'),
            array('Lebanon', 'LB', '🇱🇧'),
            array('Lesotho', 'LS', '🇱🇸'),
            array('Liberia', 'LR', '🇱🇷'),
            array('Libya', 'LY', '🇱🇾'),
            array('Liechtenstein', 'LI', '🇱🇮'),
            array('Lithuania', 'LT', '🇱🇹'),
            array('Luxembourg', 'LU', '🇱🇺'),
            array('Madagascar', 'MG', '🇲🇬'),
            array('Malawi', 'MW', '🇲🇼'),
            array('Malaysia', 'MY', '🇲🇾'),
            array('Maldives', 'MV', '🇲🇻'),
            array('Mali', 'ML', '🇲🇱'),
            array('Malta', 'MT', '🇲🇹'),
            array('Marshall Islands', 'MH', '🇲🇭'),
            array('Mauritania', 'MR', '🇲🇷'),
            array('Mauritius', 'MU', '🇲🇺'),
            array('Mexico', 'MX', '🇲🇽'),
            array('Micronesia', 'FM', '🇫🇲'),
            array('Moldova', 'MD', '🇲🇩'),
            array('Monaco', 'MC', '🇲🇨'),
            array('Mongolia', 'MN', '🇲🇳'),
            array('Montenegro', 'ME', '🇲🇪'),
            array('Morocco', 'MA', '🇲🇦'),
            array('Mozambique', 'MZ', '🇲🇿'),
            array('Myanmar', 'MM', '🇲🇲'),
            array('Namibia', 'NA', '🇳🇦'),
            array('Nauru', 'NR', '🇳🇷'),
            array('Nepal', 'NP', '🇳🇵'),
            array('Netherlands', 'NL', '🇳🇱'),
            array('New Zealand', 'NZ', '🇳🇿'),
            array('Nicaragua', 'NI', '🇳🇮'),
            array('Niger', 'NE', '🇳🇪'),
            array('Nigeria', 'NG', '🇳🇬'),
            array('North Korea', 'KP', '🇰🇵'),
            array('North Macedonia', 'MK', '🇲🇰'),
            array('Norway', 'NO', '🇳🇴'),
            array('Oman', 'OM', '🇴🇲'),
            array('Pakistan', 'PK', '🇵🇰'),
            array('Palau', 'PW', '🇵🇼'),
            array('Palestine', 'PS', '🇵🇸'),
            array('Panama', 'PA', '🇵🇦'),
            array('Papua New Guinea', 'PG', '🇵🇬'),
            array('Paraguay', 'PY', '🇵🇾'),
            array('Peru', 'PE', '🇵🇪'),
            array('Philippines', 'PH', '🇵🇭'),
            array('Poland', 'PL', '🇵🇱'),
            array('Portugal', 'PT', '🇵🇹'),
            array('Qatar', 'QA', '🇶🇦'),
            array('Romania', 'RO', '🇷🇴'),
            array('Russia', 'RU', '🇷🇺'),
            array('Rwanda', 'RW', '🇷🇼'),
            array('Saint Kitts and Nevis', 'KN', '🇰🇳'),
            array('Saint Lucia', 'LC', '🇱🇨'),
            array('Saint Vincent and the Grenadines', 'VC', '🇻🇨'),
            array('Samoa', 'WS', '🇼🇸'),
            array('San Marino', 'SM', '🇸🇲'),
            array('Sao Tome and Principe', 'ST', '🇸🇹'),
            array('Saudi Arabia', 'SA', '🇸🇦'),
            array('Senegal', 'SN', '🇸🇳'),
            array('Serbia', 'RS', '🇷🇸'),
            array('Seychelles', 'SC', '🇸🇨'),
            array('Sierra Leone', 'SL', '🇸🇱'),
            array('Singapore', 'SG', '🇸🇬'),
            array('Slovakia', 'SK', '🇸🇰'),
            array('Slovenia', 'SI', '🇸🇮'),
            array('Solomon Islands', 'SB', '🇸🇧'),
            array('Somalia', 'SO', '🇸🇴'),
            array('South Africa', 'ZA', '🇿🇦'),
            array('South Korea', 'KR', '🇰🇷'),
            array('South Sudan', 'SS', '🇸🇸'),
            array('Spain', 'ES', '🇪🇸'),
            array('Sri Lanka', 'LK', '🇱🇰'),
            array('Sudan', 'SD', '🇸🇩'),
            array('Suriname', 'SR', '🇸🇷'),
            array('Sweden', 'SE', '🇸🇪'),
            array('Switzerland', 'CH', '🇨🇭'),
            array('Syria', 'SY', '🇸🇾'),
            array('Taiwan', 'TW', '🇹🇼'),
            array('Tajikistan', 'TJ', '🇹🇯'),
            array('Tanzania', 'TZ', '🇹🇿'),
            array('Thailand', 'TH', '🇹🇭'),
            array('Timor-Leste', 'TL', '🇹🇱'),
            array('Togo', 'TG', '🇹🇬'),
            array('Tonga', 'TO', '🇹🇴'),
            array('Trinidad and Tobago', 'TT', '🇹🇹'),
            array('Tunisia', 'TN', '🇹🇳'),
            array('Turkey', 'TR', '🇹🇷'),
            array('Turkmenistan', 'TM', '🇹🇲'),
            array('Tuvalu', 'TV', '🇹🇻'),
            array('Uganda', 'UG', '🇺🇬'),
            array('Ukraine', 'UA', '🇺🇦'),
            array('United Arab Emirates', 'AE', '🇦🇪'),
            array('United Kingdom', 'GB', '🇬🇧'),
            array('United States', 'US', '🇺🇸'),
            array('Uruguay', 'UY', '🇺🇾'),
            array('Uzbekistan', 'UZ', '🇺🇿'),
            array('Vanuatu', 'VU', '🇻🇺'),
            array('Vatican City', 'VA', '🇻🇦'),
            array('Venezuela', 'VE', '🇻🇪'),
            array('Vietnam', 'VN', '🇻🇳'),
            array('Yemen', 'YE', '🇾🇪'),
            array('Zambia', 'ZM', '🇿🇲'),
            array('Zimbabwe', 'ZW', '🇿🇼')
        );

        foreach ($countries as $country) {
            // Check if country already exists
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $table WHERE code = %s",
                $country[1]
            ));

            if (!$exists) {
                $wpdb->insert(
                    $table,
                    array(
                        'name' => $country[0],
                        'code' => $country[1],
                        'flag' => $country[2]
                    ),
                    array('%s', '%s', '%s')
                );
            }
        }
    }

    private function set_default_options()
    {
        if (!get_option('pmp_mapbox_token')) {
            update_option('pmp_mapbox_token', '');
        }
        if (!get_option('pmp_map_style')) {
            update_option('pmp_map_style', 'dark-v11');
        }
        if (!get_option('pmp_enable_mapbox')) {
            update_option('pmp_enable_mapbox', '0');
        }
    }

    public function enqueue_frontend_scripts()
    {
        global $post;
        if (is_a($post, 'WP_Post') && (has_shortcode($post->post_content, 'project_map') || has_shortcode($post->post_content, 'project_detail'))) {
            $this->load_frontend_assets();
        }
    }

    public function load_frontend_assets()
    {
        // Check if Mapbox is enabled
        $enable_mapbox = get_option('pmp_enable_mapbox', '0');
        $mapbox_token = get_option('pmp_mapbox_token', '');
        $use_mapbox = ($enable_mapbox == '1' && !empty($mapbox_token));

        // Load appropriate map library
        if ($use_mapbox) {
            // Mapbox GL JS
            wp_enqueue_style('mapbox-gl-css', 'https://api.mapbox.com/mapbox-gl-js/v2.15.0/mapbox-gl.css', array(), '2.15.0');
            wp_enqueue_script('mapbox-gl-js', 'https://api.mapbox.com/mapbox-gl-js/v2.15.0/mapbox-gl.js', array(), '2.15.0', true);
        } else {
            // Leaflet (OpenStreetMap)
            wp_enqueue_style('leaflet-css', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css', array(), '1.9.4');
            wp_enqueue_script('leaflet-js', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', array(), '1.9.4', true);
        }

        // Plugin frontend assets
        wp_enqueue_style('pmp-frontend-css', PMP_PLUGIN_URL . 'assets/frontend.css', array(), PMP_VERSION);
        wp_enqueue_script('pmp-frontend-js', PMP_PLUGIN_URL . 'assets/frontend.js', array('jquery'), PMP_VERSION, true);

        // Single project page assets
        if (get_query_var('pmp_project_id')) {
            wp_enqueue_style('pmp-single-project-css', PMP_PLUGIN_URL . 'assets/single-project.css', array(), PMP_VERSION);
        }

        // Check if Mapbox is enabled
        $enable_mapbox = get_option('pmp_enable_mapbox', '0');
        $mapbox_token = get_option('pmp_mapbox_token', '');
        $use_mapbox = ($enable_mapbox == '1' && !empty($mapbox_token));

        // Load appropriate map library
        if ($use_mapbox) {
            // Mapbox GL JS
            wp_enqueue_style('mapbox-gl-css', 'https://api.mapbox.com/mapbox-gl-js/v2.15.0/mapbox-gl.css', array(), '2.15.0');
            wp_enqueue_script('mapbox-gl-js', 'https://api.mapbox.com/mapbox-gl-js/v2.15.0/mapbox-gl.js', array(), '2.15.0', true);
        } else {
            // Leaflet (OpenStreetMap)
            wp_enqueue_style('leaflet-css', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css', array(), '1.9.4');
            wp_enqueue_script('leaflet-js', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', array(), '1.9.4', true);
        }

        // Localize script
        wp_localize_script('pmp-frontend-js', 'pmp_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('pmp_nonce'),
            'use_mapbox' => $use_mapbox,
            'mapbox_token' => $mapbox_token,
            'map_style' => get_option('pmp_map_style', 'dark-v11'),
            'osm_style' => get_option('pmp_osm_style', 'standard'),
            'project_base_url' => home_url('/project-report/'),
            'placeholder_image' => PMP_PLUGIN_URL . 'assets/images/placeholder.svg',
            'cluster_radius' => get_option('pmp_cluster_radius', '50'),
            // Color customization
            'header_bg_color' => get_option('pmp_header_bg_color', '#1d1d1d'),
            'header_text_color' => get_option('pmp_header_text_color', '#ffffff'),
            'accent_color' => get_option('pmp_accent_color', '#ffc220'),
            'button_text_color' => get_option('pmp_button_text_color', '#2d2d2d')
        ));
    }

    public function admin_enqueue_scripts($hook)
    {
        // Only load on plugin pages
        $plugin_pages = array(
            'toplevel_page_pmp-projects',
            'project-map_page_pmp-add-project',
            'project-map_page_pmp-project-types',
            'project-map_page_pmp-solution-types',
            'project-map_page_pmp-import-export',
            'project-map_page_pmp-settings'
        );

        if (!in_array($hook, $plugin_pages) && strpos($hook, 'pmp-') === false) {
            return;
        }

        // Ensure dashicons are loaded
        wp_enqueue_style('dashicons');

        // Load media uploader
        wp_enqueue_media();

        // Load Select2 for searchable dropdowns
        wp_enqueue_style('select2-css', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css', array(), '4.1.0');
        wp_enqueue_script('select2-js', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', array('jquery'), '4.1.0', true);

        wp_enqueue_style('pmp-admin-css', PMP_PLUGIN_URL . 'assets/admin.css', array('dashicons', 'select2-css'), PMP_VERSION);
        wp_enqueue_script('pmp-admin-js', PMP_PLUGIN_URL . 'assets/admin.js', array('jquery', 'select2-js'), PMP_VERSION, true);

        wp_localize_script('pmp-admin-js', 'pmp_admin_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('pmp_admin_nonce')
        ));
    }

    public function add_admin_menu()
    {
        add_menu_page(
            __('Project Map', 'project-map-plugin'),
            __('Project Map', 'project-map-plugin'),
            'manage_options',
            'pmp-projects',
            array($this, 'admin_projects_page'),
            'dashicons-location-alt',
            30
        );

        add_submenu_page(
            'pmp-projects',
            __('All Projects', 'project-map-plugin'),
            __('All Projects', 'project-map-plugin'),
            'manage_options',
            'pmp-projects',
            array($this, 'admin_projects_page')
        );

        add_submenu_page(
            'pmp-projects',
            __('Add New Project', 'project-map-plugin'),
            __('Add New', 'project-map-plugin'),
            'manage_options',
            'pmp-add-project',
            array($this, 'admin_add_project_page')
        );

        add_submenu_page(
            'pmp-projects',
            __('Project Types', 'project-map-plugin'),
            __('Project Types', 'project-map-plugin'),
            'manage_options',
            'pmp-project-types',
            array($this, 'admin_project_types_page')
        );

        add_submenu_page(
            'pmp-projects',
            __('Solution Types', 'project-map-plugin'),
            __('Solution Types', 'project-map-plugin'),
            'manage_options',
            'pmp-solution-types',
            array($this, 'admin_solution_types_page')
        );

        add_submenu_page(
            'pmp-projects',
            __('Import/Export', 'project-map-plugin'),
            __('Import/Export', 'project-map-plugin'),
            'manage_options',
            'pmp-import-export',
            array($this, 'admin_import_export_page')
        );

        add_submenu_page(
            'pmp-projects',
            __('Settings', 'project-map-plugin'),
            __('Settings', 'project-map-plugin'),
            'manage_options',
            'pmp-settings',
            array($this, 'admin_settings_page')
        );
    }

    public function admin_projects_page()
    {
        include PMP_PLUGIN_PATH . 'admin/projects-list.php';
    }

    public function admin_add_project_page()
    {
        include PMP_PLUGIN_PATH . 'admin/add-project.php';
    }

    public function admin_project_types_page()
    {
        include PMP_PLUGIN_PATH . 'admin/project-types.php';
    }

    public function admin_solution_types_page()
    {
        include PMP_PLUGIN_PATH . 'admin/solution-types.php';
    }

    public function admin_import_export_page()
    {
        include PMP_PLUGIN_PATH . 'admin/import-export.php';
    }

    public function admin_settings_page()
    {
        include PMP_PLUGIN_PATH . 'admin/settings.php';
    }

    // Shortcode: Project Map
    public function project_map_shortcode($atts)
    {
        $this->load_frontend_assets();

        $atts = shortcode_atts(array(
            'country' => '',
            'project_type' => '',
            'solution_type' => '',
            'show_filters' => 'true',
            'show_stats' => 'true',
            'show_search' => 'true',
            'height' => '800px'
        ), $atts);

        ob_start();
        include PMP_PLUGIN_PATH . 'templates/map-shortcode.php';
        return ob_get_clean();
    }

    // Shortcode: Project Detail
    public function project_detail_shortcode($atts)
    {
        $this->load_frontend_assets();

        $atts = shortcode_atts(array(
            'id' => ''
        ), $atts);

        ob_start();
        include PMP_PLUGIN_PATH . 'templates/project-detail-shortcode.php';
        return ob_get_clean();
    }

    // Query vars for single project
    public function add_query_vars($vars)
    {
        $vars[] = 'pmp_project_id';
        return $vars;
    }

    // Rewrite rules for single project
    public function add_rewrite_rules()
    {
        add_rewrite_rule(
            '^project-report/([0-9]+)/?$',
            'index.php?pmp_project_id=$matches[1]',
            'top'
        );
        add_rewrite_tag('%pmp_project_id%', '([0-9]+)');
    }

    // Handle single project template
    public function handle_single_project()
    {
        $project_id = get_query_var('pmp_project_id');
        if ($project_id) {
            $project = self::get_project($project_id);
            if (!$project || $project->status !== 'publish') {
                wp_redirect(home_url());
                exit;
            }

            // Set global project variable for template
            global $pmp_project;
            $pmp_project = $project;

            // Load assets for single project page
            $this->load_single_project_assets();
            add_filter('template_include', array($this, 'single_project_template'));
        }
    }

    // Load assets specifically for single project page
    public function load_single_project_assets()
    {
        // Check if Mapbox is enabled
        $enable_mapbox = get_option('pmp_enable_mapbox', '0');
        $mapbox_token = get_option('pmp_mapbox_token', '');
        $use_mapbox = ($enable_mapbox == '1' && !empty($mapbox_token));

        // Load appropriate map library
        if ($use_mapbox) {
            wp_enqueue_style('mapbox-gl-css', 'https://api.mapbox.com/mapbox-gl-js/v2.15.0/mapbox-gl.css', array(), '2.15.0');
            wp_enqueue_script('mapbox-gl-js', 'https://api.mapbox.com/mapbox-gl-js/v2.15.0/mapbox-gl.js', array(), '2.15.0', true);
        } else {
            wp_enqueue_style('leaflet-css', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css', array(), '1.9.4');
            wp_enqueue_script('leaflet-js', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', array(), '1.9.4', true);
        }

        // Load single project CSS
        wp_enqueue_style('pmp-single-project-css', PMP_PLUGIN_URL . 'assets/single-project.css', array(), PMP_VERSION);
    }

    // Override template for single project
    public function single_project_template($template)
    {
        $project_id = get_query_var('pmp_project_id');
        if ($project_id) {
            $single_template = PMP_PLUGIN_PATH . 'templates/single-project.php';
            if (file_exists($single_template)) {
                return $single_template;
            }
        }
        return $template;
    }

    // AJAX: Get projects for map
    public function ajax_get_projects()
    {
        check_ajax_referer('pmp_nonce', 'nonce');

        global $wpdb;
        $table = $wpdb->prefix . 'pmp_projects';
        $types_table = $wpdb->prefix . 'pmp_solution_types';
        $project_types_table = $wpdb->prefix . 'pmp_project_types';
        $countries_table = $wpdb->prefix . 'pmp_countries';

        $country = isset($_POST['country']) ? sanitize_text_field($_POST['country']) : '';
        $project_type = isset($_POST['project_type']) ? intval($_POST['project_type']) : 0;
        $solution_type = isset($_POST['solution_type']) ? intval($_POST['solution_type']) : 0;
        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';

        $where = "WHERE p.status = 'publish'";
        $params = array();

        if ($country) {
            $where .= " AND p.country = %s";
            $params[] = $country;
        }

        if ($project_type) {
            $where .= " AND p.project_type_id = %d";
            $params[] = $project_type;
        }

        if ($solution_type) {
            $where .= " AND p.solution_type_id = %d";
            $params[] = $solution_type;
        }

        if ($search) {
            $where .= " AND (p.village_name LIKE %s OR p.country LIKE %s OR p.description LIKE %s)";
            $search_term = '%' . $wpdb->esc_like($search) . '%';
            $params[] = $search_term;
            $params[] = $search_term;
            $params[] = $search_term;
        }

        $sql = "SELECT p.*, st.name as solution_type_name, pt.name as project_type_name, pt.icon as project_type_icon, c.flag as country_flag
                FROM $table p
                LEFT JOIN $types_table st ON p.solution_type_id = st.id
                LEFT JOIN $project_types_table pt ON p.project_type_id = pt.id
                LEFT JOIN $countries_table c ON p.country = c.name
                $where
                ORDER BY p.created_at DESC";

        if (!empty($params)) {
            $projects = $wpdb->get_results($wpdb->prepare($sql, $params));
        } else {
            $projects = $wpdb->get_results($sql);
        }

        // Format projects for frontend
        $formatted = array();
        foreach ($projects as $project) {
            $featured_image = $project->featured_image_id ? wp_get_attachment_url($project->featured_image_id) : '';

            $formatted[] = array(
                'id' => $project->id,
                'name' => $project->village_name,
                'projectNumber' => $project->project_number,
                'country' => $project->country,
                'countryFlag' => $project->country_flag ?: '',
                'coordinates' => array(floatval($project->gps_longitude), floatval($project->gps_latitude)),
                'peopleServed' => intval($project->beneficiaries),
                'date' => $this->format_completion_date($project->completion_month, $project->completion_year),
                'image' => $featured_image ?: PMP_PLUGIN_URL . 'assets/images/placeholder.jpg',
                'projectType' => $project->project_type_name ?: '',
                'projectTypeIcon' => $project->project_type_icon ?: '📍',
                'solutionType' => $project->solution_type_name ?: '',
                'fundedBy' => $project->in_honour_of ?: 'Anonymous Donors',
                'description' => wp_trim_words($project->description, 30)
            );
        }

        // Get statistics
        $stats = $this->get_statistics($country, $project_type, $solution_type);

        wp_send_json_success(array(
            'projects' => $formatted,
            'stats' => $stats
        ));
    }

    private function format_completion_date($month, $year)
    {
        if (!$year)
            return 'N/A';

        $months = array(
            1 => 'January',
            2 => 'February',
            3 => 'March',
            4 => 'April',
            5 => 'May',
            6 => 'June',
            7 => 'July',
            8 => 'August',
            9 => 'September',
            10 => 'October',
            11 => 'November',
            12 => 'December'
        );

        if ($month && isset($months[$month])) {
            return $months[$month] . ' ' . $year;
        }

        return $year;
    }

    private function get_statistics($country = '', $project_type = 0, $solution_type = 0)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'pmp_projects';

        $where = "WHERE status = 'publish'";
        $params = array();

        if ($country) {
            $where .= " AND country = %s";
            $params[] = $country;
        }

        if ($project_type) {
            $where .= " AND project_type_id = %d";
            $params[] = $project_type;
        }

        if ($solution_type) {
            $where .= " AND solution_type_id = %d";
            $params[] = $solution_type;
        }

        $sql = "SELECT
                    COUNT(*) as total_projects,
                    COALESCE(SUM(beneficiaries), 0) as total_beneficiaries,
                    COUNT(DISTINCT country) as total_countries
                FROM $table $where";

        if (!empty($params)) {
            $stats = $wpdb->get_row($wpdb->prepare($sql, $params));
        } else {
            $stats = $wpdb->get_row($sql);
        }

        return array(
            'totalProjects' => intval($stats->total_projects),
            'totalBeneficiaries' => intval($stats->total_beneficiaries),
            'totalCountries' => intval($stats->total_countries)
        );
    }

    // AJAX: Import CSV
    public function ajax_import_csv()
    {
        check_ajax_referer('pmp_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'project-map-plugin'));
            return;
        }

        if (!isset($_FILES['csv_file'])) {
            wp_send_json_error(__('No file uploaded', 'project-map-plugin'));
            return;
        }

        $file = $_FILES['csv_file'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error(__('File upload error', 'project-map-plugin'));
            return;
        }

        $handle = fopen($file['tmp_name'], 'r');
        if (!$handle) {
            wp_send_json_error(__('Could not open file', 'project-map-plugin'));
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'pmp_projects';

        $header = fgetcsv($handle);
        $imported = 0;
        $errors = array();
        $row_num = 1;

        // Expected columns: Village Name, Project Number, Country, GPS Latitude, GPS Longitude, Project Type, Solution Type, Completion Month, Completion Year, Beneficiaries, In Honour Of, Description, Featured Image URL, Gallery Images (comma-separated URLs), Video URLs (comma-separated), Status
        while (($data = fgetcsv($handle)) !== FALSE) {
            $row_num++;

            if (count($data) < 6) {
                $errors[] = sprintf(__('Row %d: Insufficient data', 'project-map-plugin'), $row_num);
                continue;
            }

            $project_type_id = isset($data[5]) && !empty($data[5]) ? $this->get_or_create_project_type($data[5]) : null;
            $solution_type_id = isset($data[6]) && !empty($data[6]) ? $this->get_or_create_solution_type($data[6], $project_type_id) : null;

            // Handle featured image
            $featured_image_id = null;
            if (isset($data[12]) && !empty($data[12])) {
                $featured_image_id = $this->import_image_from_url($data[12]);
            }

            // Handle gallery images
            $gallery_images = '';
            if (isset($data[13]) && !empty($data[13])) {
                $gallery_urls = array_filter(array_map('trim', explode(',', $data[13])));
                $gallery_ids = array();
                foreach ($gallery_urls as $url) {
                    $img_id = $this->import_image_from_url($url);
                    if ($img_id) {
                        $gallery_ids[] = $img_id;
                    }
                }
                $gallery_images = implode(',', $gallery_ids);
            }

            // Handle video URLs
            $video_urls = '';
            if (isset($data[14]) && !empty($data[14])) {
                $videos = array_filter(array_map('trim', explode(',', $data[14])));
                $video_urls = implode("\n", array_map('esc_url_raw', $videos));
            }

            $status = isset($data[15]) && !empty($data[15]) ? sanitize_text_field($data[15]) : 'publish';
            if (!in_array($status, array('publish', 'draft'))) {
                $status = 'publish';
            }

            $result = $wpdb->insert(
                $table,
                array(
                    'village_name' => sanitize_text_field($data[0]),
                    'project_number' => isset($data[1]) ? sanitize_text_field($data[1]) : '',
                    'country' => sanitize_text_field($data[2]),
                    'gps_latitude' => floatval($data[3]),
                    'gps_longitude' => floatval($data[4]),
                    'project_type_id' => $project_type_id,
                    'solution_type_id' => $solution_type_id,
                    'completion_month' => isset($data[7]) && !empty($data[7]) ? intval($data[7]) : null,
                    'completion_year' => isset($data[8]) && !empty($data[8]) ? intval($data[8]) : null,
                    'beneficiaries' => isset($data[9]) ? intval($data[9]) : 0,
                    'in_honour_of' => isset($data[10]) ? sanitize_text_field($data[10]) : '',
                    'description' => isset($data[11]) ? wp_kses_post($data[11]) : '',
                    'featured_image_id' => $featured_image_id,
                    'gallery_images' => $gallery_images,
                    'video_urls' => $video_urls,
                    'status' => $status
                ),
                array('%s', '%s', '%s', '%f', '%f', '%d', '%d', '%d', '%d', '%d', '%s', '%s', '%d', '%s', '%s', '%s')
            );

            if ($result) {
                $imported++;
            } else {
                $errors[] = sprintf(__('Row %d: Database error', 'project-map-plugin'), $row_num);
            }
        }

        fclose($handle);

        wp_send_json_success(array(
            'imported' => $imported,
            'errors' => $errors
        ));
    }

    private function get_or_create_project_type($name)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'pmp_project_types';

        $name = sanitize_text_field($name);
        if (empty($name))
            return null;

        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE name = %s",
            $name
        ));

        if ($existing)
            return intval($existing);

        $wpdb->insert($table, array('name' => $name), array('%s'));
        return $wpdb->insert_id;
    }

    private function get_or_create_solution_type($name, $project_type_id = null)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'pmp_solution_types';

        $name = sanitize_text_field($name);
        if (empty($name))
            return null;

        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE name = %s",
            $name
        ));

        if ($existing)
            return intval($existing);

        $wpdb->insert($table, array(
            'name' => $name,
            'project_type_id' => $project_type_id
        ), array('%s', '%d'));
        return $wpdb->insert_id;
    }

    /**
     * Import image from URL and attach to media library
     */
    private function import_image_from_url($url)
    {
        if (empty($url))
            return null;

        // Check if image already exists in media library
        global $wpdb;
        $attachment = $wpdb->get_col($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE guid = %s AND post_type = 'attachment'",
            $url
        ));

        if (!empty($attachment)) {
            return intval($attachment[0]);
        }

        // Download and import image
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        $tmp = download_url($url);
        if (is_wp_error($tmp)) {
            return null;
        }

        $file_array = array(
            'name' => basename($url),
            'tmp_name' => $tmp
        );

        $id = media_handle_sideload($file_array, 0);
        if (is_wp_error($id)) {
            @unlink($file_array['tmp_name']);
            return null;
        }

        return $id;
    }

    // AJAX: Import Project Types
    public function ajax_import_project_types()
    {
        check_ajax_referer('pmp_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'project-map-plugin'));
            return;
        }

        if (!isset($_FILES['csv_file'])) {
            wp_send_json_error(__('No file uploaded', 'project-map-plugin'));
            return;
        }

        $file = $_FILES['csv_file'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error(__('File upload error', 'project-map-plugin'));
            return;
        }

        $handle = fopen($file['tmp_name'], 'r');
        if (!$handle) {
            wp_send_json_error(__('Could not open file', 'project-map-plugin'));
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'pmp_project_types';

        $header = fgetcsv($handle);
        $imported = 0;
        $errors = array();
        $row_num = 1;

        while (($data = fgetcsv($handle)) !== FALSE) {
            $row_num++;

            if (count($data) < 1) {
                $errors[] = sprintf(__('Row %d: Insufficient data', 'project-map-plugin'), $row_num);
                continue;
            }

            $name = sanitize_text_field($data[0]);
            $description = isset($data[1]) ? sanitize_text_field($data[1]) : '';

            if (empty($name)) {
                $errors[] = sprintf(__('Row %d: Name is required', 'project-map-plugin'), $row_num);
                continue;
            }

            // Check if exists
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $table WHERE name = %s",
                $name
            ));

            if ($existing) {
                // Update existing
                $wpdb->update(
                    $table,
                    array('description' => $description),
                    array('id' => $existing),
                    array('%s'),
                    array('%d')
                );
                $imported++;
            } else {
                // Insert new
                $result = $wpdb->insert(
                    $table,
                    array('name' => $name, 'description' => $description),
                    array('%s', '%s')
                );
                if ($result) {
                    $imported++;
                } else {
                    $errors[] = sprintf(__('Row %d: Database error', 'project-map-plugin'), $row_num);
                }
            }
        }

        fclose($handle);

        wp_send_json_success(array(
            'imported' => $imported,
            'errors' => $errors
        ));
    }

    // AJAX: Export Project Types
    public function ajax_export_project_types()
    {
        check_ajax_referer('pmp_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'project-map-plugin'));
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'pmp_project_types';

        $types = $wpdb->get_results("SELECT * FROM $table ORDER BY name ASC");

        $csv_data = array();
        $csv_data[] = array('Name', 'Description');

        foreach ($types as $type) {
            $csv_data[] = array(
                $type->name,
                $type->description ?: ''
            );
        }

        wp_send_json_success(array('data' => $csv_data));
    }

    // AJAX: Import Solution Types
    public function ajax_import_solution_types()
    {
        check_ajax_referer('pmp_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'project-map-plugin'));
            return;
        }

        if (!isset($_FILES['csv_file'])) {
            wp_send_json_error(__('No file uploaded', 'project-map-plugin'));
            return;
        }

        $file = $_FILES['csv_file'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error(__('File upload error', 'project-map-plugin'));
            return;
        }

        $handle = fopen($file['tmp_name'], 'r');
        if (!$handle) {
            wp_send_json_error(__('Could not open file', 'project-map-plugin'));
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'pmp_solution_types';

        $header = fgetcsv($handle);
        $imported = 0;
        $errors = array();
        $row_num = 1;

        while (($data = fgetcsv($handle)) !== FALSE) {
            $row_num++;

            if (count($data) < 1) {
                $errors[] = sprintf(__('Row %d: Insufficient data', 'project-map-plugin'), $row_num);
                continue;
            }

            $name = sanitize_text_field($data[0]);
            $description = isset($data[1]) ? sanitize_text_field($data[1]) : '';

            if (empty($name)) {
                $errors[] = sprintf(__('Row %d: Name is required', 'project-map-plugin'), $row_num);
                continue;
            }

            // Check if exists
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $table WHERE name = %s",
                $name
            ));

            if ($existing) {
                // Update existing
                $wpdb->update(
                    $table,
                    array('description' => $description),
                    array('id' => $existing),
                    array('%s'),
                    array('%d')
                );
                $imported++;
            } else {
                // Insert new (no project_type_id in CSV)
                $result = $wpdb->insert(
                    $table,
                    array('name' => $name, 'description' => $description),
                    array('%s', '%s')
                );
                if ($result) {
                    $imported++;
                } else {
                    $errors[] = sprintf(__('Row %d: Database error', 'project-map-plugin'), $row_num);
                }
            }
        }

        fclose($handle);

        wp_send_json_success(array(
            'imported' => $imported,
            'errors' => $errors
        ));
    }

    // AJAX: Export Solution Types
    public function ajax_export_solution_types()
    {
        check_ajax_referer('pmp_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'project-map-plugin'));
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'pmp_solution_types';

        $types = $wpdb->get_results("SELECT * FROM $table ORDER BY name ASC");

        $csv_data = array();
        $csv_data[] = array('Name', 'Description');

        foreach ($types as $type) {
            $csv_data[] = array(
                $type->name,
                $type->description ?: ''
            );
        }

        wp_send_json_success(array('data' => $csv_data));
    }

    // AJAX: Export CSV
    public function ajax_export_csv()
    {
        check_ajax_referer('pmp_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'project-map-plugin'));
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'pmp_projects';
        $types_table = $wpdb->prefix . 'pmp_solution_types';
        $project_types_table = $wpdb->prefix . 'pmp_project_types';

        $projects = $wpdb->get_results(
            "SELECT p.*, st.name as solution_type_name, pt.name as project_type_name
             FROM $table p
             LEFT JOIN $types_table st ON p.solution_type_id = st.id
             LEFT JOIN $project_types_table pt ON p.project_type_id = pt.id
             ORDER BY p.id ASC"
        );

        $csv_data = array();
        $csv_data[] = array(
            'Village Name',
            'Project Number',
            'Country',
            'GPS Latitude',
            'GPS Longitude',
            'Project Type',
            'Solution Type',
            'Completion Month',
            'Completion Year',
            'Beneficiaries',
            'In Honour Of',
            'Description',
            'Featured Image URL',
            'Gallery Images',
            'Video URLs',
            'Status'
        );

        foreach ($projects as $project) {
            // Get featured image URL
            $featured_image_url = '';
            if ($project->featured_image_id) {
                $featured_image_url = wp_get_attachment_url($project->featured_image_id);
            }

            // Get gallery image URLs
            $gallery_urls = array();
            if ($project->gallery_images) {
                $gallery_ids = explode(',', $project->gallery_images);
                foreach ($gallery_ids as $img_id) {
                    $url = wp_get_attachment_url(intval($img_id));
                    if ($url) {
                        $gallery_urls[] = $url;
                    }
                }
            }

            // Get video URLs
            $video_urls = '';
            if ($project->video_urls) {
                $videos = array_filter(explode("\n", $project->video_urls));
                $video_urls = implode(',', $videos);
            }

            $csv_data[] = array(
                $project->village_name,
                $project->project_number,
                $project->country,
                $project->gps_latitude,
                $project->gps_longitude,
                $project->project_type_name ?: '',
                $project->solution_type_name ?: '',
                $project->completion_month ?: '',
                $project->completion_year ?: '',
                $project->beneficiaries,
                $project->in_honour_of,
                $project->description,
                $featured_image_url,
                implode(',', $gallery_urls),
                $video_urls,
                $project->status
            );
        }

        wp_send_json_success(array('data' => $csv_data));
    }

    // AJAX: Delete project
    public function ajax_delete_project()
    {
        check_ajax_referer('pmp_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'project-map-plugin'));
            return;
        }

        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        if (!$id) {
            wp_send_json_error(__('Invalid project ID', 'project-map-plugin'));
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'pmp_projects';

        $result = $wpdb->delete($table, array('id' => $id), array('%d'));

        if ($result) {
            wp_send_json_success(__('Project deleted successfully', 'project-map-plugin'));
        } else {
            wp_send_json_error(__('Failed to delete project', 'project-map-plugin'));
        }
    }

    // Helper: Get project by ID
    public static function get_project($id)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'pmp_projects';
        $types_table = $wpdb->prefix . 'pmp_solution_types';
        $project_types_table = $wpdb->prefix . 'pmp_project_types';
        $countries_table = $wpdb->prefix . 'pmp_countries';

        return $wpdb->get_row($wpdb->prepare(
            "SELECT p.*,
                    st.name as solution_type_name,
                    st.description as solution_type_description,
                    pt.name as project_type_name,
                    pt.description as project_type_description,
                    c.flag as country_flag
             FROM $table p
             LEFT JOIN $types_table st ON p.solution_type_id = st.id
             LEFT JOIN $project_types_table pt ON p.project_type_id = pt.id
             LEFT JOIN $countries_table c ON p.country = c.name
             WHERE p.id = %d",
            $id
        ));
    }

    // Helper: Get all project types
    public static function get_project_types()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'pmp_project_types';
        return $wpdb->get_results("SELECT * FROM $table ORDER BY name ASC");
    }

    // Helper: Get all solution types
    public static function get_solution_types($project_type_id = null)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'pmp_solution_types';

        if ($project_type_id) {
            return $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $table WHERE project_type_id = %d OR project_type_id IS NULL ORDER BY name ASC",
                $project_type_id
            ));
        }

        return $wpdb->get_results("SELECT * FROM $table ORDER BY name ASC");
    }

    // Helper: Get all countries
    public static function get_countries()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'pmp_countries';
        return $wpdb->get_results("SELECT * FROM $table ORDER BY name ASC");
    }

    // Helper: Get countries with projects
    public static function get_countries_with_projects()
    {
        global $wpdb;
        $projects_table = $wpdb->prefix . 'pmp_projects';
        $countries_table = $wpdb->prefix . 'pmp_countries';

        return $wpdb->get_results(
            "SELECT DISTINCT c.*, COUNT(p.id) as project_count
             FROM $countries_table c
             INNER JOIN $projects_table p ON c.name = p.country
             WHERE p.status = 'publish'
             GROUP BY c.id
             ORDER BY c.name ASC"
        );
    }

    // Helper: Get project types with projects
    public static function get_project_types_with_projects()
    {
        global $wpdb;
        $projects_table = $wpdb->prefix . 'pmp_projects';
        $project_types_table = $wpdb->prefix . 'pmp_project_types';

        return $wpdb->get_results(
            "SELECT DISTINCT pt.*, COUNT(p.id) as project_count
             FROM $project_types_table pt
             INNER JOIN $projects_table p ON pt.id = p.project_type_id
             WHERE p.status = 'publish'
             GROUP BY pt.id
             ORDER BY pt.name ASC"
        );
    }
}

// Initialize plugin
ProjectMapPlugin::get_instance();
