<?php
/**
 * Login attempt limiter and IP blocking.
 *
 * @package Secure_WP_Guard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Secure_WP_Guard_Login_Limiter {

	const BLOCKED_IPS_OPTION = 'secure_wp_guard_blocked_ips';
	const ATTEMPT_TRANSIENT_PREFIX = 'swpg_login_attempts_';

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
	 * Initialize hooks.
	 */
	public function init() {
		if ( ! $this->is_enabled() ) {
			return;
		}

		add_action( 'login_init', array( $this, 'block_if_ip_blocked' ), 1 );
		add_filter( 'authenticate', array( $this, 'authenticate_check' ), 30, 3 );
		add_action( 'wp_login_failed', array( $this, 'handle_failed_login' ) );
		add_action( 'wp_login', array( $this, 'handle_successful_login' ), 10, 2 );
		add_filter( 'login_errors', array( $this, 'append_remaining_to_errors' ) );
	}

	/**
	 * Whether login limiting is enabled.
	 *
	 * @return bool
	 */
	public function is_enabled() {
		return '1' === $this->plugin->get_setting( 'limit_login_enabled' );
	}

	/**
	 * Maximum allowed failed attempts before block.
	 *
	 * @return int
	 */
	public function get_max_attempts() {
		$max = absint( $this->plugin->get_setting( 'login_max_attempts' ) );
		return max( 1, min( 20, $max ? $max : 3 ) );
	}

	/**
	 * Get client IP address.
	 *
	 * @return string
	 */
	public function get_client_ip() {
		$keys = array(
			'HTTP_CF_CONNECTING_IP',
			'HTTP_X_FORWARDED_FOR',
			'HTTP_X_REAL_IP',
			'REMOTE_ADDR',
		);

		foreach ( $keys as $key ) {
			if ( empty( $_SERVER[ $key ] ) ) {
				continue;
			}

			$ip = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
			if ( false !== strpos( $ip, ',' ) ) {
				$parts = explode( ',', $ip );
				$ip    = trim( $parts[0] );
			}

			if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
				return $ip;
			}
		}

		return '0.0.0.0';
	}

	/**
	 * Transient key for attempt storage.
	 *
	 * @param string $ip IP address.
	 * @return string
	 */
	private function get_attempt_key( $ip ) {
		return self::ATTEMPT_TRANSIENT_PREFIX . md5( $ip );
	}

	/**
	 * Current failed attempt count for IP.
	 *
	 * @param string|null $ip IP address.
	 * @return int
	 */
	public function get_attempt_count( $ip = null ) {
		if ( null === $ip ) {
			$ip = $this->get_client_ip();
		}

		return absint( get_transient( $this->get_attempt_key( $ip ) ) );
	}

	/**
	 * Remaining attempts before block.
	 *
	 * @param string|null $ip IP address.
	 * @return int
	 */
	public function get_remaining_attempts( $ip = null ) {
		$remaining = $this->get_max_attempts() - $this->get_attempt_count( $ip );
		return max( 0, $remaining );
	}

	/**
	 * Whether IP is blocked.
	 *
	 * @param string|null $ip IP address.
	 * @return bool
	 */
	public function is_ip_blocked( $ip = null ) {
		if ( null === $ip ) {
			$ip = $this->get_client_ip();
		}

		$blocked = $this->get_blocked_ips();
		return isset( $blocked[ $ip ] );
	}

	/**
	 * Get all blocked IPs.
	 *
	 * @return array
	 */
	public function get_blocked_ips() {
		$blocked = get_option( self::BLOCKED_IPS_OPTION, array() );
		return is_array( $blocked ) ? $blocked : array();
	}

	/**
	 * Block an IP address.
	 *
	 * @param string $ip IP address.
	 */
	public function block_ip( $ip ) {
		$blocked = $this->get_blocked_ips();

		$blocked[ $ip ] = array(
			'blocked_at' => time(),
			'attempts'   => $this->get_attempt_count( $ip ),
		);

		update_option( self::BLOCKED_IPS_OPTION, $blocked, false );
		delete_transient( $this->get_attempt_key( $ip ) );
	}

	/**
	 * Unblock an IP address.
	 *
	 * @param string $ip IP address.
	 * @return bool
	 */
	public function unblock_ip( $ip ) {
		$blocked = $this->get_blocked_ips();

		if ( ! isset( $blocked[ $ip ] ) ) {
			return false;
		}

		unset( $blocked[ $ip ] );
		update_option( self::BLOCKED_IPS_OPTION, $blocked, false );
		delete_transient( $this->get_attempt_key( $ip ) );

		return true;
	}

	/**
	 * Increment failed login attempts.
	 *
	 * @param string $ip IP address.
	 */
	private function increment_attempts( $ip ) {
		$count = $this->get_attempt_count( $ip ) + 1;
		set_transient( $this->get_attempt_key( $ip ), $count, DAY_IN_SECONDS );

		if ( $count >= $this->get_max_attempts() ) {
			$this->block_ip( $ip );
		}
	}

	/**
	 * Block login page access for blocked IPs.
	 */
	public function block_if_ip_blocked() {
		if ( ! $this->is_ip_blocked() ) {
			return;
		}

		login_header( __( 'Login Blocked', 'secure-wp-guard' ) );
		echo '<div id="login_error" class="notice notice-error"><p>';
		echo esc_html__( 'Too many failed login attempts. Your IP address has been blocked. Contact site admin to unblock.', 'secure-wp-guard' );
		echo '</p></div>';
		login_footer();
		exit;
	}

	/**
	 * Prevent authentication for blocked IPs.
	 *
	 * @param WP_User|WP_Error|null $user     User or error.
	 * @param string                $username Username.
	 * @param string                $password Password.
	 * @return WP_User|WP_Error|null
	 */
	public function authenticate_check( $user, $username, $password ) {
		if ( $this->is_ip_blocked() ) {
			return new WP_Error(
				'swpg_ip_blocked',
				__( '<strong>Error:</strong> Too many failed login attempts. Your IP address has been blocked. Contact site admin to unblock.', 'secure-wp-guard' )
			);
		}

		return $user;
	}

	/**
	 * Record a failed login attempt.
	 *
	 * @param string $username Username attempted.
	 */
	public function handle_failed_login( $username ) {
		unset( $username );

		if ( $this->is_ip_blocked() ) {
			return;
		}

		$this->increment_attempts( $this->get_client_ip() );
	}

	/**
	 * Clear attempts after successful login.
	 *
	 * @param string  $user_login Username.
	 * @param WP_User $user       User object.
	 */
	public function handle_successful_login( $user_login, $user ) {
		unset( $user_login, $user );
		delete_transient( $this->get_attempt_key( $this->get_client_ip() ) );
	}

	/**
	 * Show remaining attempts on the login form.
	 *
	 * @param string $message Existing message.
	 * @return string
	 */
	public function login_remaining_message( $message ) {
		if ( $this->is_ip_blocked() ) {
			return $message;
		}

		$remaining = $this->get_remaining_attempts();
		$message  .= $this->get_remaining_notice_html( $remaining );

		return $message;
	}

	/**
	 * Append remaining attempts to login error output.
	 *
	 * @param string $errors Error HTML.
	 * @return string
	 */
	public function append_remaining_to_errors( $errors ) {
		if ( $this->is_ip_blocked() ) {
			return $errors;
		}

		$remaining = $this->get_remaining_attempts();
		if ( $remaining > 0 && false === strpos( $errors, 'swpg-remaining-attempts' ) ) {
			$errors .= $this->get_remaining_notice_html( $remaining );
		}

		return $errors;
	}

	/**
	 * HTML notice for remaining attempts.
	 *
	 * @param int $remaining Remaining attempts.
	 * @return string
	 */
	private function get_remaining_notice_html( $remaining ) {
		if ( 0 === $remaining ) {
			$text = __( 'No login attempts remaining. Your IP will be blocked on the next failed attempt.', 'secure-wp-guard' );
		} elseif ( 1 === $remaining ) {
			$text = __( '1 login attempt remaining before your IP is blocked.', 'secure-wp-guard' );
		} else {
			/* translators: %d: number of remaining login attempts */
			$text = sprintf( __( '%d login attempts remaining before your IP is blocked.', 'secure-wp-guard' ), $remaining );
		}

		return '<br /><strong class="swpg-remaining-attempts">' . esc_html( $text ) . '</strong>';
	}
}
