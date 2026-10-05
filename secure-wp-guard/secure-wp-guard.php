<?php
/**
 * Plugin Name:       Secure WP Guard
 * Description:       An ultra-secure, premium security plugin to hide login URL, disable XML-RPC, rename DB prefix with safe rollbacks, hide WP version, and disallow file edits.
 * Version:           1.0.0
 * License:           GPLv2 or later
 * Text Domain:       secure-wp-guard
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define Plugin Constants
define( 'SECURE_WP_GUARD_VERSION', '1.0.0' );
define( 'SECURE_WP_GUARD_PATH', plugin_dir_path( __FILE__ ) );
define( 'SECURE_WP_GUARD_URL', plugin_dir_url( __FILE__ ) );
define( 'SECURE_WP_GUARD_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Autoload classes or manually require them.
 * Since this is a specialized plugin, manually requiring files ensures performance and safety.
 */
require_once SECURE_WP_GUARD_PATH . 'includes/class-secure-wp-guard.php';
require_once SECURE_WP_GUARD_PATH . 'includes/class-secure-wp-guard-login.php';
require_once SECURE_WP_GUARD_PATH . 'includes/class-secure-wp-guard-login-limiter.php';
require_once SECURE_WP_GUARD_PATH . 'includes/class-secure-wp-guard-security.php';
require_once SECURE_WP_GUARD_PATH . 'includes/class-secure-wp-guard-db-prefix.php';
require_once SECURE_WP_GUARD_PATH . 'includes/class-secure-wp-guard-admin.php';

/**
 * Activation & Deactivation hooks
 */
function activate_secure_wp_guard() {
	// Set default settings if not already set
	if ( false === get_option( 'secure_wp_guard_settings' ) ) {
		$defaults = array(
			'admin_slug'           => '', // Empty means disabled/default login
			'disable_xmlrpc'       => '0',
			'hide_wp_version'      => '0',
			'disallow_file_edit'   => '0',
			'disallow_file_mods'   => '0',
			'limit_login_enabled'  => '0',
			'login_max_attempts'   => 3,
		);
		update_option( 'secure_wp_guard_settings', $defaults );
	}
}
register_activation_hook( __FILE__, 'activate_secure_wp_guard' );

function deactivate_secure_wp_guard() {
	// We don't remove DB options or revert db prefix on deactivation to avoid lockouts or data loss,
	// but we could clean up temporary variables.
}
register_deactivation_hook( __FILE__, 'deactivate_secure_wp_guard' );

/**
 * Initialize the core plugin class.
 */
function run_secure_wp_guard() {
	$plugin = new Secure_WP_Guard();
	$plugin->run();
}
run_secure_wp_guard();
