<?php
/**
 * Plugin Name: EntrySaver Manager
 * Plugin URI:  https://wordpress.org/plugins/entrysaver-manager/
 * Description: View, edit, delete, and manage Contact Form 7 entries directly from WordPress dashboard with custom column selection, pagination, and CSV export.
 * Version:     1.0.0
 * Author:      Faizmuhin Badi
 * Author URI:  https://github.com/FaizmuhinBadi
 * Requires Plugins: contact-form-7
 * License:     GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Plugin constants
define('ENTRMA_VERSION', '1.0.0');
define('ENTRMA_FILE', __FILE__);
define('ENTRMA_PATH', plugin_dir_path(__FILE__));
define('ENTRMA_URL', plugin_dir_url(__FILE__));
define('ENTRMA_DB_VERSION', '1.0.0');

/**
 * Main EntrySaver Manager Class
 */
final class EntrySaver_Manager {

    /**
     * Single instance of the class
     * @var EntrySaver_Manager
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
        require_once ENTRMA_PATH . 'includes/class-entrma-db.php';
        require_once ENTRMA_PATH . 'includes/class-entrma-submission.php';
        require_once ENTRMA_PATH . 'includes/class-entrma-list-table.php';
        require_once ENTRMA_PATH . 'includes/class-entrma-admin.php';
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        register_activation_hook(ENTRMA_FILE, array($this, 'activate'));
        register_deactivation_hook(ENTRMA_FILE, array($this, 'deactivate'));
        add_action('plugins_loaded', array($this, 'init_components'));
    }

    /**
     * Activate the plugin
     */
    public function activate() {
        ENTRMA_DB::create_tables();
        update_option('entrma_db_version', ENTRMA_DB_VERSION);
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
        ENTRMA_DB::check_update();
        ENTRMA_Submission::init();
        
        if (is_admin()) {
            ENTRMA_Admin::init();
        }
    }
}

/**
 * Main function to instantiate plugin
 */
function EntrySaver_Manager() {
    return EntrySaver_Manager::instance();
}

// Global initialization
EntrySaver_Manager();
