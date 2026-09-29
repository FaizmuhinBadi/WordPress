<?php
/**
 * Plugin Name: CF7 Entries Manager
 * Plugin URI:  https://wordpress.org/plugins/cf7-entries-manager/
 * Description: View, edit, delete, and manage Contact Form 7 entries directly from WordPress dashboard with custom column selection, pagination, and CSV export.
 * Version:     1.0.0
 * Author:      Faizmuhin Badi
 * Author URI:  https://github.com/google-deepmind
 * License:     GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Plugin constants
define('CF7EM_VERSION', '1.0.0');
define('CF7EM_FILE', __FILE__);
define('CF7EM_PATH', plugin_dir_path(__FILE__));
define('CF7EM_URL', plugin_dir_url(__FILE__));
define('CF7EM_DB_VERSION', '1.0.0');

/**
 * Main CF7 Entries Manager Class
 */
final class CF7_Entries_Manager {

    /**
     * Single instance of the class
     * @var CF7_Entries_Manager
     */
    private static $instance = null;

    /**
     * Get single instance of the class
     */
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->includes();
        $this->init_hooks();
    }

    /**
     * Include required core files
     */
    private function includes() {
        require_once CF7EM_PATH . 'includes/class-cf7em-db.php';
        require_once CF7EM_PATH . 'includes/class-cf7em-submission.php';
        require_once CF7EM_PATH . 'includes/class-cf7em-list-table.php';
        require_once CF7EM_PATH . 'includes/class-cf7em-admin.php';
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        register_activation_hook(CF7EM_FILE, array($this, 'activate'));
        register_deactivation_hook(CF7EM_FILE, array($this, 'deactivate'));
        add_action('plugins_loaded', array($this, 'init_components'));
    }

    /**
     * Activate the plugin
     */
    public function activate() {
        CF7EM_DB::create_tables();
        update_option('cf7em_db_version', CF7EM_DB_VERSION);
    }

    /**
     * Deactivate the plugin
     */
    public function deactivate() {
        // Cleanup transient or flush rewrite rules if needed
    }

    /**
     * Initialize components
     */
    public function init_components() {
        CF7EM_DB::check_update();
        CF7EM_Submission::init();
        
        if (is_admin()) {
            CF7EM_Admin::init();
        }
    }
}

/**
 * Main function to instantiate plugin
 */
function CF7_Entries_Manager() {
    return CF7_Entries_Manager::instance();
}

// Global initialization
CF7_Entries_Manager();
