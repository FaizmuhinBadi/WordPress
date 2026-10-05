<?php
/**
 * Admin URL change handler class.
 *
 * @package Secure_WP_Guard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Secure_WP_Guard_Login {

	/**
	 * Core plugin instance.
	 *
	 * @var Secure_WP_Guard
	 */
	private $plugin;

	/**
	 * Custom slug for login.
	 *
	 * @var string
	 */
	private $slug;

	/**
	 * Constructor.
	 *
	 * @param Secure_WP_Guard $plugin Core plugin instance.
	 */
	public function __construct( $plugin ) {
		$this->plugin = $plugin;
		$this->slug   = $this->plugin->get_setting( 'admin_slug' );
	}

	/**
	 * Initialize hooks.
	 */
	public function init() {
		if ( empty( $this->slug ) ) {
			return;
		}

		add_action( 'plugins_loaded', array( $this, 'block_hard_login_endpoints' ), 0 );
		add_action( 'init', array( $this, 'intercept_login_request' ), 1 );
		add_action( 'init', array( $this, 'restrict_admin_access' ) );
		add_action( 'template_redirect', array( $this, 'handle_monitored_shortcut_paths' ), 1 );

		add_filter( 'site_url', array( $this, 'filter_site_url' ), 999, 3 );
		add_filter( 'network_site_url', array( $this, 'filter_site_url' ), 999, 3 );
		add_filter( 'login_url', array( $this, 'filter_login_url' ), 999, 3 );
		add_filter( 'wp_redirect', array( $this, 'filter_wp_redirect' ), 999, 2 );
	}

	/**
	 * Get the current request path relative to site root.
	 *
	 * @return string
	 */
	private function get_request_path() {
		if ( ! isset( $_SERVER['REQUEST_URI'] ) ) {
			return '';
		}

		$request_uri = $_SERVER['REQUEST_URI'];
		$parsed_url  = wp_parse_url( $request_uri );
		if ( ! $parsed_url || ! isset( $parsed_url['path'] ) ) {
			return '';
		}
		$path = $parsed_url['path'];

		$site_path = wp_parse_url( site_url(), PHP_URL_PATH );
		if ( ! empty( $site_path ) ) {
			$site_path = rtrim( $site_path, '/' );
			if ( 0 === strpos( $path, $site_path ) ) {
				$path = substr( $path, strlen( $site_path ) );
			}
		}
		return '/' . ltrim( $path, '/' );
	}

	/**
	 * Paths always blocked: wp-login.php and login.php only.
	 *
	 * @return string[]
	 */
	private function get_hard_blocked_paths() {
		return array( '/wp-login.php', '/wp-login.php/', '/login.php', '/login.php/' );
	}

	/**
	 * Script entry points always blocked.
	 *
	 * @return string[]
	 */
	private function get_hard_blocked_scripts() {
		return array( 'wp-login.php', 'login.php' );
	}

	/**
	 * Generic login-like slugs (e.g. /login) when they are not the custom login slug.
	 *
	 * @return string[]
	 */
	private function get_monitored_shortcut_paths() {
		$paths = array( '/login', '/login/' );

		/**
		 * Filter monitored shortcut paths.
		 *
		 * @param string[] $paths Paths relative to site root.
		 */
		return apply_filters( 'secure_wp_guard_monitored_login_shortcuts', $paths );
	}

	/**
	 * Whether the request path is the configured custom login slug.
	 *
	 * @param string|null $request_path Optional path.
	 * @return bool
	 */
	private function is_custom_login_path( $request_path = null ) {
		if ( empty( $this->slug ) ) {
			return false;
		}

		if ( null === $request_path ) {
			$request_path = $this->get_request_path();
		}

		$trimmed_slug = '/' . trim( $this->slug, '/' );

		return $request_path === $trimmed_slug || $request_path === $trimmed_slug . '/';
	}

	/**
	 * Whether the request is a monitored shortcut but not the custom login slug.
	 *
	 * @return bool
	 */
	private function is_monitored_shortcut_request() {
		if ( $this->is_custom_login_path() ) {
			return false;
		}

		return in_array( $this->get_request_path(), $this->get_monitored_shortcut_paths(), true );
	}

	/**
	 * Published WordPress page matching the current request path, if any.
	 *
	 * @param string|null $request_path Optional path.
	 * @return WP_Post|null
	 */
	private function get_page_for_path( $request_path = null ) {
		if ( null === $request_path ) {
			$request_path = $this->get_request_path();
		}

		$slug = trim( $request_path, '/' );
		if ( '' === $slug ) {
			return null;
		}

		$page = get_page_by_path( $slug, OBJECT, 'page' );
		if ( $page && 'publish' === $page->post_status ) {
			return $page;
		}

		return null;
	}

	/**
	 * Whether request hits wp-login.php or login.php directly.
	 *
	 * @return bool
	 */
	private function is_hard_blocked_login_endpoint() {
		if ( defined( 'SECURE_WP_GUARD_CUSTOM_LOGIN' ) ) {
			return false;
		}

		$request_path = $this->get_request_path();

		if ( in_array( $request_path, $this->get_hard_blocked_paths(), true ) ) {
			return true;
		}

		if ( false !== stripos( $request_path, 'wp-login.php' ) ) {
			return true;
		}

		$script = '';
		if ( isset( $_SERVER['SCRIPT_NAME'] ) ) {
			$script = (string) $_SERVER['SCRIPT_NAME'];
		} elseif ( isset( $_SERVER['PHP_SELF'] ) ) {
			$script = (string) $_SERVER['PHP_SELF'];
		}

		return '' !== $script && in_array( basename( $script ), $this->get_hard_blocked_scripts(), true );
	}

	/**
	 * Block wp-login.php and login.php as early as possible.
	 */
	public function block_hard_login_endpoints() {
		if ( empty( $this->slug ) ) {
			return;
		}

		if ( $this->is_hard_blocked_login_endpoint() ) {
			$this->trigger_404();
		}
	}

	/**
	 * Serve custom login slug or block hard endpoints.
	 */
	public function intercept_login_request() {
		if ( empty( $this->slug ) ) {
			return;
		}

		$request_path = $this->get_request_path();
		$trimmed_slug = '/' . trim( $this->slug, '/' );

		if ( $request_path === $trimmed_slug || $request_path === $trimmed_slug . '/' ) {
			if ( ! defined( 'SECURE_WP_GUARD_CUSTOM_LOGIN' ) ) {
				define( 'SECURE_WP_GUARD_CUSTOM_LOGIN', true );
			}

			global $pagenow, $action, $errors, $login_header_title, $login_header_url, $user_login, $user_pass, $error, $message, $redirect_to, $interim_login, $wp_error;
			$pagenow             = 'wp-login.php';
			$_SERVER['PHP_SELF'] = $trimmed_slug;

			require_once ABSPATH . 'wp-login.php';
			exit;
		}

		if ( $this->is_hard_blocked_login_endpoint() ) {
			$this->trigger_404();
		}
	}

	/**
	 * For /login (when not custom slug): show existing WP page or 404.
	 */
	public function handle_monitored_shortcut_paths() {
		if ( empty( $this->slug ) || ! $this->is_monitored_shortcut_request() ) {
			return;
		}

		if ( $this->get_page_for_path() ) {
			return;
		}

		$this->trigger_404();
	}

	/**
	 * Restrict access to /wp-admin for logged-out users.
	 */
	public function restrict_admin_access() {
		if ( empty( $this->slug ) ) {
			return;
		}

		if ( is_admin() && ! is_user_logged_in() ) {
			if ( ! ( defined( 'DOING_AJAX' ) && DOING_AJAX ) && ! ( defined( 'DOING_CRON' ) && DOING_CRON ) ) {
				if ( ! defined( 'SECURE_WP_GUARD_CUSTOM_LOGIN' ) ) {
					$this->trigger_404();
				}
			}
		}
	}

	/**
	 * When a shortcut would expose wp-login: send to existing page or 404.
	 */
	private function block_shortcut_login_redirect() {
		$page = $this->get_page_for_path();
		if ( $page ) {
			wp_safe_redirect( get_permalink( $page ), 302 );
			exit;
		}

		$this->trigger_404();
	}

	/**
	 * Filter site_url to map wp-login.php to the custom slug.
	 */
	public function filter_site_url( $url, $path, $scheme, $blog_id = null ) {
		if ( empty( $this->slug ) || empty( $path ) ) {
			return $url;
		}

		if ( false !== strpos( $path, 'wp-login.php' ) ) {
			$query       = '';
			$parsed_path = wp_parse_url( $path );
			if ( isset( $parsed_path['query'] ) ) {
				$query = '?' . $parsed_path['query'];
			}

			$url = site_url( trim( $this->slug, '/' ) . $query, $scheme );
		}

		return $url;
	}

	/**
	 * Filter login_url to map to the custom slug.
	 */
	public function filter_login_url( $login_url, $redirect, $force_reauth ) {
		if ( empty( $this->slug ) ) {
			return $login_url;
		}

		$login_url = site_url( trim( $this->slug, '/' ) );
		if ( ! empty( $redirect ) ) {
			$login_url = add_query_arg( 'redirect_to', urlencode( $redirect ), $login_url );
		}

		return $login_url;
	}

	/**
	 * Block shortcuts redirecting to wp-login.php; otherwise rewrite to custom slug.
	 */
	public function filter_wp_redirect( $location, $status ) {
		unset( $status );

		if ( empty( $this->slug ) || false === strpos( $location, 'wp-login.php' ) ) {
			return $location;
		}

		if ( $this->is_monitored_shortcut_request() ) {
			$this->block_shortcut_login_redirect();
		}

		$parsed   = wp_parse_url( $location );
		$query    = isset( $parsed['query'] ) ? '?' . $parsed['query'] : '';
		$location = site_url( trim( $this->slug, '/' ) . $query );

		return $location;
	}

	/**
	 * Generate a 404 response.
	 */
	private function trigger_404() {
		status_header( 404 );
		nocache_headers();

		$can_use_theme = did_action( 'wp_loaded' ) && ! is_admin() && function_exists( 'get_query_template' );

		if ( $can_use_theme ) {
			global $wp_query;

			$template = get_query_template( '404' );
			if ( $template && file_exists( $template ) ) {
				if ( is_object( $wp_query ) && method_exists( $wp_query, 'set_404' ) ) {
					$wp_query->set_404();
				}
				include $template;
				exit;
			}
		}

		if ( ! did_action( 'wp_loaded' ) ) {
			header( 'Content-Type: text/html; charset=UTF-8' );
			echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>404 Not Found</title></head><body>';
			echo '<h1>404 Not Found</h1><p>The requested URL was not found on this server.</p>';
			echo '</body></html>';
			exit;
		}

		wp_die(
			'<h1>404 Not Found</h1><p>The requested URL was not found on this server.</p>',
			'Not Found',
			array( 'response' => 404 )
		);
	}
}
