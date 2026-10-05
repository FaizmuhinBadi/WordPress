<?php
/**
 * Permission Manager.
 *
 * @package Secure_WP_Guard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Secure_WP_Guard_Permission_Manager {

	/**
	 * Predefined target paths for permission management.
	 *
	 * @return array Associative array of path keys to absolute paths.
	 */
	public static function get_target_paths() {
		$paths = array(
			'core-wp-config' => array( 'group' => 'Core', 'name' => 'wp-config.php', 'path' => Secure_WP_Guard_Security::get_wp_config_path() ),
			'core-uploads'   => array( 'group' => 'Core', 'name' => 'uploads', 'path' => wp_upload_dir()['basedir'] ),
			'core-upgrade'   => array( 'group' => 'Core', 'name' => 'upgrade', 'path' => WP_CONTENT_DIR . '/upgrade' ),
		);

		// Scan plugins
		if ( is_dir( WP_PLUGIN_DIR ) ) {
			$plugins = scandir( WP_PLUGIN_DIR );
			foreach ( $plugins as $plugin ) {
				if ( '.' === $plugin || '..' === $plugin ) continue;
				$plugin_path = WP_PLUGIN_DIR . '/' . $plugin;
				if ( is_dir( $plugin_path ) || ( is_file( $plugin_path ) && pathinfo( $plugin_path, PATHINFO_EXTENSION ) === 'php' ) ) {
					$paths[ 'plugin-' . $plugin ] = array( 'group' => 'Plugins', 'name' => $plugin, 'path' => $plugin_path );
				}
			}
		}

		// Scan themes
		$theme_root = get_theme_root();
		if ( is_dir( $theme_root ) ) {
			$themes = scandir( $theme_root );
			foreach ( $themes as $theme ) {
				if ( '.' === $theme || '..' === $theme ) continue;
				$theme_path = $theme_root . '/' . $theme;
				if ( is_dir( $theme_path ) ) {
					$paths[ 'theme-' . $theme ] = array( 'group' => 'Themes', 'name' => $theme, 'path' => $theme_path );
				}
			}
		}

		return $paths;
	}

	/**
	 * Get the current permissions of a file or directory.
	 *
	 * @param string $path Absolute path.
	 * @return string Current permission in octal string format (e.g., '0755') or 'Unknown'.
	 */
	public static function get_permissions( $path ) {
		clearstatcache( true, $path );
		
		if ( ! file_exists( $path ) ) {
			return 'Not Found';
		}

		$perms = fileperms( $path );
		if ( false === $perms ) {
			return 'Unknown';
		}

		return substr( sprintf( '%o', $perms ), -4 );
	}

	/**
	 * Change permissions recursively for a path.
	 *
	 * @param string $path     Absolute path.
	 * @param int    $dir_perm Octal integer for directory permissions (e.g., 0755).
	 * @param int    $file_perm Octal integer for file permissions (e.g., 0644).
	 * @return array Result array with status, logs, and errors.
	 */
	public static function change_permissions_recursively( $path, $dir_perm, $file_perm ) {
		$result = array(
			'success' => true,
			'logs'    => array(),
			'errors'  => array(),
			'changed' => 0,
			'failed'  => 0,
		);

		if ( ! file_exists( $path ) ) {
			$result['success'] = false;
			$result['errors'][] = "Path not found: {$path}";
			return $result;
		}

		// Handle single file (e.g., wp-config.php)
		if ( is_file( $path ) ) {
			$current = fileperms( $path );
			$expected = $file_perm;
			if ( ( $current & 0777 ) !== $expected ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod
				if ( @chmod( $path, $expected ) ) {
					$result['logs'][] = "Changed file: {$path} to " . decoct( $expected );
					$result['changed']++;
				} else {
					$result['success'] = false;
					$result['errors'][] = "Failed to change file: {$path}";
					$result['failed']++;
				}
			}
			return $result;
		}

		// Handle directory recursively
		try {
			$iterator = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator( $path, RecursiveDirectoryIterator::SKIP_DOTS ),
				RecursiveIteratorIterator::SELF_FIRST
			);

			// First, change the root directory itself
			$current_dir = fileperms( $path );
			if ( ( $current_dir & 0777 ) !== $dir_perm ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod
				if ( @chmod( $path, $dir_perm ) ) {
					$result['logs'][] = "Changed directory: {$path} to " . decoct( $dir_perm );
					$result['changed']++;
				} else {
					$result['success'] = false;
					$result['errors'][] = "Failed to change directory: {$path}";
					$result['failed']++;
				}
			}

			foreach ( $iterator as $item ) {
				$item_path = $item->getPathname();
				$is_dir = $item->isDir();
				$perm_to_apply = $is_dir ? $dir_perm : $file_perm;
				$current_item = fileperms( $item_path );

				if ( ( $current_item & 0777 ) !== $perm_to_apply ) {
					// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod
					if ( @chmod( $item_path, $perm_to_apply ) ) {
						$type = $is_dir ? 'directory' : 'file';
						// We don't want to log every single file successfully changed as it might freeze the UI with too much data
						// We will only log a few samples or just keep the count
						if ( $result['changed'] < 50 ) {
							$result['logs'][] = "Changed {$type}: " . basename( $item_path ) . " to " . decoct( $perm_to_apply );
						} elseif ( $result['changed'] === 50 ) {
							$result['logs'][] = "... further successful changes omitted to save space.";
						}
						$result['changed']++;
					} else {
						$result['success'] = false;
						$type = $is_dir ? 'directory' : 'file';
						// Log up to 50 errors
						if ( $result['failed'] < 50 ) {
							$result['errors'][] = "Failed to change {$type}: " . basename( $item_path );
						} elseif ( $result['failed'] === 50 ) {
							$result['errors'][] = "... further failed changes omitted to save space.";
						}
						$result['failed']++;
					}
				}
			}
		} catch ( Exception $e ) {
			$result['success'] = false;
			$result['errors'][] = "Exception occurred: " . $e->getMessage();
		}

		clearstatcache();
		return $result;
	}
}
