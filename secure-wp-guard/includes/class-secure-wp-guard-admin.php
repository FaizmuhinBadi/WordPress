<?php
/**
 * Admin Settings & Dashboard Manager.
 *
 * @package Secure_WP_Guard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Secure_WP_Guard_Admin {

	/**
	 * Core plugin instance.
	 *
	 * @var Secure_WP_Guard
	 */
	private $plugin;

	/**
	 * Constructor.
	 *
	 * @param Secure_WP_Guard $plugin Core plugin instance.
	 */
	public function __construct( $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * Initialize admin hooks.
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

		// AJAX handlers
		add_action( 'wp_ajax_secure_wp_guard_save_settings', array( $this, 'ajax_save_settings' ) );
		add_action( 'wp_ajax_secure_wp_guard_change_prefix', array( $this, 'ajax_change_prefix' ) );
		add_action( 'wp_ajax_secure_wp_guard_unblock_ip', array( $this, 'ajax_unblock_ip' ) );
	}

	/**
	 * Add administration menu page.
	 */
	public function add_admin_menu() {
		add_menu_page(
			__( 'Secure WP Guard', 'secure-wp-guard' ),
			__( 'WP Guard', 'secure-wp-guard' ),
			'manage_options',
			'secure-wp-guard',
			array( $this, 'render_admin_dashboard' ),
			'dashicons-shield',
			80
		);
	}

	/**
	 * Enqueue assets only on our settings page.
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( 'toplevel_page_secure-wp-guard' !== $hook ) {
			return;
		}

		// Enqueue Google Fonts
		wp_enqueue_style( 'secure-wp-guard-fonts', 'https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap', array(), SECURE_WP_GUARD_VERSION );

		$style_path = SECURE_WP_GUARD_PATH . 'admin/css/admin-style.css';
		$style_ver  = SECURE_WP_GUARD_VERSION;
		if ( file_exists( $style_path ) ) {
			$style_ver .= '.' . filemtime( $style_path );
		}

		// Enqueue Custom Styles
		wp_enqueue_style( 'secure-wp-guard-admin-css', SECURE_WP_GUARD_URL . 'admin/css/admin-style.css', array(), $style_ver );

		$script_path = SECURE_WP_GUARD_PATH . 'admin/js/admin-script.js';
		$script_ver  = SECURE_WP_GUARD_VERSION;
		if ( file_exists( $script_path ) ) {
			$script_ver .= '.' . filemtime( $script_path );
		}

		// Enqueue Custom JS
		wp_enqueue_script( 'secure-wp-guard-admin-js', SECURE_WP_GUARD_URL . 'admin/js/admin-script.js', array( 'jquery' ), $script_ver, true );

		// Localize Script for AJAX requests
		wp_localize_script( 
			'secure-wp-guard-admin-js', 
			'swpg_params', 
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'secure_wp_guard_nonce' ),
				'messages' => array(
					'saving'        => __( 'Saving settings...', 'secure-wp-guard' ),
					'saved'         => __( 'Settings saved successfully!', 'secure-wp-guard' ),
					'save_failed'   => __( 'Failed to save settings.', 'secure-wp-guard' ),
					'prefix_run'    => __( 'Beginning database prefix migration. Do NOT close this window...', 'secure-wp-guard' ),
					'prefix_ok'     => __( 'Migration successful! Redirecting you to log in again...', 'secure-wp-guard' ),
					'prefix_fail'   => __( 'Migration failed!', 'secure-wp-guard' ),
					'confirm_prefix'=> __( 'Are you absolutely sure you want to change the database prefix? You will be logged out and must log back in. Ensure you have a backup.', 'secure-wp-guard' ),
					'unblock_ok'    => __( 'IP address unblocked successfully.', 'secure-wp-guard' ),
					'unblock_fail'  => __( 'Failed to unblock IP address.', 'secure-wp-guard' ),
					'confirm_unblock'=> __( 'Unblock this IP address?', 'secure-wp-guard' ),
				)
			)
		);
	}

	/**
	 * Render the main plugin dashboard.
	 */
	public function render_admin_dashboard() {
		// Include the display partial
		$partials_path = SECURE_WP_GUARD_PATH . 'admin/partials/admin-display.php';
		if ( file_exists( $partials_path ) ) {
			include $partials_path;
		} else {
			echo '<div class="wrap"><h2>Error: Partial display file not found.</h2></div>';
		}
	}

	/**
	 * AJAX handler for saving settings.
	 */
	public function ajax_save_settings() {
		check_ajax_referer( 'secure_wp_guard_nonce', 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'secure-wp-guard' ) ) );
		}

		// Retrieve POST values
		$admin_slug         = isset( $_POST['admin_slug'] ) ? sanitize_title( $_POST['admin_slug'] ) : '';
		$disable_xmlrpc     = isset( $_POST['disable_xmlrpc'] ) ? '1' : '0';
		$hide_wp_version    = isset( $_POST['hide_wp_version'] ) ? '1' : '0';
		$disallow_file_edit   = isset( $_POST['disallow_file_edit'] ) ? '1' : '0';
		$disallow_file_mods   = isset( $_POST['disallow_file_mods'] ) ? '1' : '0';
		$limit_login_enabled  = isset( $_POST['limit_login_enabled'] ) ? '1' : '0';
		$login_max_attempts   = isset( $_POST['login_max_attempts'] ) ? absint( $_POST['login_max_attempts'] ) : 3;
		$login_max_attempts   = max( 1, min( 20, $login_max_attempts ) );

		// Validate custom login slug
		if ( ! empty( $admin_slug ) ) {
			$reserved = array( 'wp-admin', 'wp-login.php', 'wp-login', 'login.php', 'admin', 'dashboard' );
			if ( in_array( $admin_slug, $reserved, true ) ) {
				wp_send_json_error( array( 'message' => __( 'The login slug cannot be a reserved WordPress name (e.g. wp-admin, wp-login).', 'secure-wp-guard' ) ) );
			}
		}

		// Load currently saved settings
		$current_settings = $this->plugin->get_setting();

		// Handle writing DISALLOW_FILE_EDIT to wp-config.php if it changed
		if ( $disallow_file_edit !== $current_settings['disallow_file_edit'] ) {
			$res = Secure_WP_Guard_Security::update_wp_config_constant( 'DISALLOW_FILE_EDIT', ( '1' === $disallow_file_edit ) );
			if ( is_wp_error( $res ) ) {
				wp_send_json_error( array( 'message' => $res->get_error_message() ) );
			}
		}

		// Handle writing DISALLOW_FILE_MODS to wp-config.php if it changed
		if ( $disallow_file_mods !== $current_settings['disallow_file_mods'] ) {
			$res = Secure_WP_Guard_Security::update_wp_config_constant( 'DISALLOW_FILE_MODS', ( '1' === $disallow_file_mods ) );
			if ( is_wp_error( $res ) ) {
				wp_send_json_error( array( 'message' => $res->get_error_message() ) );
			}
		}

		// Update option
		$new_settings = array(
			'admin_slug'          => $admin_slug,
			'disable_xmlrpc'      => $disable_xmlrpc,
			'hide_wp_version'     => $hide_wp_version,
			'disallow_file_edit'  => $disallow_file_edit,
			'disallow_file_mods'  => $disallow_file_mods,
			'limit_login_enabled' => $limit_login_enabled,
			'login_max_attempts'  => $login_max_attempts,
		);

		update_option( Secure_WP_Guard::SETTINGS_OPTION, $new_settings );

		// Build response
		$login_url = empty( $admin_slug ) ? site_url( 'wp-login.php' ) : site_url( $admin_slug );
		wp_send_json_success( 
			array( 
				'message'   => __( 'Settings saved successfully.', 'secure-wp-guard' ),
				'login_url' => esc_url( $login_url )
			) 
		);
	}

	/**
	 * AJAX handler for DB Prefix Changer.
	 */
	public function ajax_change_prefix() {
		check_ajax_referer( 'secure_wp_guard_nonce', 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'secure-wp-guard' ) ) );
		}

		$new_prefix = isset( $_POST['new_prefix'] ) ? sanitize_text_field( $_POST['new_prefix'] ) : '';

		$changer = new Secure_WP_Guard_DB_Prefix();
		$result  = $changer->change_prefix( $new_prefix );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		// Generate redirect login URL
		$admin_slug = $this->plugin->get_setting( 'admin_slug' );
		$login_url  = empty( $admin_slug ) ? site_url( 'wp-login.php' ) : site_url( $admin_slug );

		wp_send_json_success( 
			array( 
				'message'   => __( 'Database prefix updated successfully.', 'secure-wp-guard' ),
				'login_url' => esc_url( $login_url )
			) 
		);
	}

	/**
	 * AJAX handler to unblock an IP address.
	 */
	public function ajax_unblock_ip() {
		check_ajax_referer( 'secure_wp_guard_nonce', 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'secure-wp-guard' ) ) );
		}

		$ip = isset( $_POST['ip'] ) ? sanitize_text_field( wp_unslash( $_POST['ip'] ) ) : '';

		if ( empty( $ip ) || ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid IP address.', 'secure-wp-guard' ) ) );
		}

		$limiter = new Secure_WP_Guard_Login_Limiter( $this->plugin );

		if ( ! $limiter->unblock_ip( $ip ) ) {
			wp_send_json_error( array( 'message' => __( 'IP address is not in the blocked list.', 'secure-wp-guard' ) ) );
		}

		wp_send_json_success( array( 'message' => __( 'IP address unblocked successfully.', 'secure-wp-guard' ) ) );
	}
}
