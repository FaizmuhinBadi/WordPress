<?php
/**
 * Core plugin class.
 *
 * @package Secure_WP_Guard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Secure_WP_Guard {

	/**
	 * Settings option name.
	 */
	const SETTINGS_OPTION = 'secure_wp_guard_settings';

	/**
	 * Settings cache array.
	 *
	 * @var array
	 */
	private $settings = array();

	/**
	 * Construct the class.
	 */
	public function __construct() {
		$this->load_settings();
	}

	/**
	 * Load settings and merge with defaults.
	 */
	private function load_settings() {
		$saved = get_option( self::SETTINGS_OPTION, array() );
		$defaults = array(
			'admin_slug'          => '',
			'disable_xmlrpc'      => '0',
			'hide_wp_version'     => '0',
			'disallow_file_edit'  => '0',
			'disallow_file_mods'  => '0',
			'limit_login_enabled' => '0',
			'login_max_attempts'  => 3,
		);

		$this->settings = wp_parse_args( $saved, $defaults );
	}

	/**
	 * Get all settings or a single setting.
	 *
	 * @param string|null $key Setting key.
	 * @return mixed
	 */
	public function get_setting( $key = null ) {
		if ( null === $key ) {
			return $this->settings;
		}
		return isset( $this->settings[ $key ] ) ? $this->settings[ $key ] : null;
	}

	/**
	 * Run the plugin by initializing components.
	 */
	public function run() {
		// Initialize general security functions (XML-RPC, version hide, constants check)
		$security = new Secure_WP_Guard_Security( $this );
		$security->init();

		// Initialize login URL custom redirect rules
		$login = new Secure_WP_Guard_Login( $this );
		$login->init();

		// Login attempt limiting and IP blocking
		$login_limiter = new Secure_WP_Guard_Login_Limiter( $this );
		$login_limiter->init();

		// Load admin configurations and pages
		if ( is_admin() || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
			$admin = new Secure_WP_Guard_Admin( $this );
			$admin->init();
		}
	}
}
