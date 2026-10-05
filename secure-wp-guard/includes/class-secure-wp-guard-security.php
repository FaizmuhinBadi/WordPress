<?php
/**
 * Security hardening class.
 *
 * @package Secure_WP_Guard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Secure_WP_Guard_Security {

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
	 * Initialize hooks based on settings.
	 */
	public function init() {
		// XML-RPC
		if ( '1' === $this->plugin->get_setting( 'disable_xmlrpc' ) ) {
			add_filter( 'xmlrpc_enabled', '__return_false' );
			add_filter( 'wp_headers', array( $this, 'remove_xmlrpc_pingback_header' ) );
			add_action( 'plugins_loaded', array( $this, 'block_xmlrpc_requests' ), 1 );
		}

		// Hide WP Version
		if ( '1' === $this->plugin->get_setting( 'hide_wp_version' ) ) {
			// Remove version from header
			remove_action( 'wp_head', 'wp_generator' );
			// Remove version from feeds
			add_filter( 'the_generator', '__return_empty_string' );
			// Remove version from script and style urls
			add_filter( 'script_loader_src', array( $this, 'strip_wp_version' ), 999 );
			add_filter( 'style_loader_src', array( $this, 'strip_wp_version' ), 999 );
		}

		// Disallow File Edit (Programmatic Fallback)
		if ( '1' === $this->plugin->get_setting( 'disallow_file_edit' ) ) {
			if ( ! defined( 'DISALLOW_FILE_EDIT' ) ) {
				define( 'DISALLOW_FILE_EDIT', true );
			}
		}

		// Disallow File Mods (Programmatic Fallback)
		if ( '1' === $this->plugin->get_setting( 'disallow_file_mods' ) ) {
			if ( ! defined( 'DISALLOW_FILE_MODS' ) ) {
				define( 'DISALLOW_FILE_MODS', true );
			}
		}
	}

	/**
	 * Remove X-Pingback header.
	 */
	public function remove_xmlrpc_pingback_header( $headers ) {
		unset( $headers['X-Pingback'] );
		return $headers;
	}

	/**
	 * Block requests to xmlrpc.php directly at early initialization.
	 */
	public function block_xmlrpc_requests() {
		if ( ! isset( $_SERVER['SCRIPT_NAME'] ) ) {
			return;
		}

		if ( 'xmlrpc.php' === basename( $_SERVER['SCRIPT_NAME'] ) ) {
			status_header( 403 );
			header( 'Content-Type: text/plain' );
			wp_die( 
				'XML-RPC services are disabled on this site.', 
				'XML-RPC Disabled', 
				array( 'response' => 403 ) 
			);
		}
	}

	/**
	 * Strip WordPress version query arguments from scripts/styles.
	 */
	public function strip_wp_version( $src ) {
		global $wp_version;

		$parsed = wp_parse_url( $src );
		if ( ! $parsed || ! isset( $parsed['query'] ) ) {
			return $src;
		}

		parse_str( $parsed['query'], $query_args );

		if ( isset( $query_args['ver'] ) && $query_args['ver'] === $wp_version ) {
			$src = remove_query_arg( 'ver', $src );
		}

		return $src;
	}

	/**
	 * Safely update constants in wp-config.php.
	 *
	 * @param string $constant Name of the constant (e.g. 'DISALLOW_FILE_EDIT').
	 * @param bool   $enable   Whether to enable (true) or disable (false) the constant.
	 * @return bool|WP_Error   True on success, WP_Error on failure.
	 */
	public static function update_wp_config_constant( $constant, $enable ) {
		$config_path = self::get_wp_config_path();

		if ( ! $config_path || ! file_exists( $config_path ) ) {
			return new WP_Error( 'config_not_found', __( 'wp-config.php file not found.', 'secure-wp-guard' ) );
		}
		
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable
		if ( ! is_writable( $config_path ) ) {
			return new WP_Error( 'config_not_writable', __( 'wp-config.php is not writable. Please check permissions.', 'secure-wp-guard' ) );
		}

		// Read contents
		$content = file_get_contents( $config_path );
		if ( false === $content ) {
			return new WP_Error( 'config_read_failed', __( 'Failed to read wp-config.php.', 'secure-wp-guard' ) );
		}

		// Match pattern for define('CONSTANT', value); or define("CONSTANT", value);
		// Allows for spaces, single/double quotes, and boolean true/false.
		$pattern = "/define\(\s*['\"]" . preg_quote( $constant, '/' ) . "['\"]\s*,\s*(true|false)\s*\);/i";
		$value_str = $enable ? 'true' : 'false';
		$new_definition = "define( '" . $constant . "', " . $value_str . " );";

		if ( preg_match( $pattern, $content ) ) {
			// Replace existing
			$new_content = preg_replace( $pattern, $new_definition, $content );
		} else {
			// Insert new before stop-editing comment or settings load
			$insert_pattern = "/(\/\*\s*That's\s+all,\s+stop\s+editing!.*\*\/|require_once\s+ABSPATH\s*\.\s*['\"]wp-settings\.php['\"]\s*;)/i";
			
			if ( preg_match( $insert_pattern, $content ) ) {
				$replacement = $new_definition . "\n\n$1";
				$new_content = preg_replace( $insert_pattern, $replacement, $content, 1 );
			} else {
				// Fallback: insert after opening <?php
				$new_content = preg_replace( "/^(<\?php)/i", "$1\n\n" . $new_definition, $content, 1 );
			}
		}

		// Try writing back
		$result = file_put_contents( $config_path, $new_content );
		if ( false === $result ) {
			return new WP_Error( 'config_write_failed', __( 'Failed to write to wp-config.php.', 'secure-wp-guard' ) );
		}

		return true;
	}

	/**
	 * Find the absolute path to wp-config.php.
	 *
	 * @return string|false
	 */
	public static function get_wp_config_path() {
		// Standard location
		$path = ABSPATH . 'wp-config.php';
		if ( file_exists( $path ) ) {
			return $path;
		}

		// One directory up (WordPress support for nested root install)
		$parent_path = dirname( ABSPATH ) . DIRECTORY_SEPARATOR . 'wp-config.php';
		if ( file_exists( $parent_path ) ) {
			return $parent_path;
		}

		return false;
	}
}
