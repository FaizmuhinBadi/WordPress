<?php
/**
 * Safe Database Prefix Changer Class.
 *
 * @package Secure_WP_Guard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Secure_WP_Guard_DB_Prefix {

	/**
	 * Run the prefix change operation.
	 *
	 * @param string $new_prefix The target database prefix.
	 * @return true|WP_Error True on success, WP_Error with detailed logs on failure.
	 */
	public function change_prefix( $new_prefix ) {
		global $wpdb;

		// 1. Validation
		$old_prefix = $wpdb->prefix;

		// Trim and sanitize input
		$new_prefix = trim( $new_prefix );

		if ( empty( $new_prefix ) ) {
			return new WP_Error( 'invalid_prefix', __( 'Database prefix cannot be empty.', 'secure-wp-guard' ) );
		}

		if ( $new_prefix === $old_prefix ) {
			return new WP_Error( 'same_prefix', __( 'The new prefix is identical to the current prefix.', 'secure-wp-guard' ) );
		}

		// Ensure it only contains letters, numbers, and underscores, starts with a letter, and ends with an underscore
		if ( ! preg_match( '/^[a-zA-Z][a-zA-Z0-9_]*_$/', $new_prefix ) ) {
			return new WP_Error( 
				'invalid_format', 
				__( 'Invalid prefix format. It must start with a letter, contain only alphanumeric characters and underscores, and end with an underscore (e.g., wp_secure_).', 'secure-wp-guard' ) 
			);
		}

		if ( strlen( $new_prefix ) > 30 ) {
			return new WP_Error( 'prefix_too_long', __( 'Database prefix is too long (maximum 30 characters).', 'secure-wp-guard' ) );
		}

		// 2. Pre-flight Checks
		// Check admin capability
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'forbidden', __( 'You do not have permission to perform this action.', 'secure-wp-guard' ) );
		}

		// Check if wp-config.php path is valid and writable
		$config_path = Secure_WP_Guard_Security::get_wp_config_path();
		if ( ! $config_path || ! file_exists( $config_path ) ) {
			return new WP_Error( 'config_not_found', __( 'wp-config.php file not found.', 'secure-wp-guard' ) );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable
		if ( ! is_writable( $config_path ) ) {
			return new WP_Error( 'config_not_writable', __( 'wp-config.php is not writable. Database prefix cannot be changed safely.', 'secure-wp-guard' ) );
		}

		// Test database user privileges (CREATE, RENAME, DROP)
		$privilege_check = $this->check_db_privileges( $old_prefix );
		if ( is_wp_error( $privilege_check ) ) {
			return $privilege_check;
		}

		// 3. Retrieve all tables starting with old prefix
		$esc_old_prefix = $wpdb->esc_like( $old_prefix );
		$tables = $wpdb->get_col( $wpdb->prepare( "SHOW TABLES LIKE %s", $esc_old_prefix . '%' ) );

		if ( empty( $tables ) ) {
			return new WP_Error( 'no_tables_found', __( 'No tables found with the current prefix.', 'secure-wp-guard' ) );
		}

		// Keep track of renamed tables for rollbacks
		$renamed_tables = array();
		$rename_error   = null;

		// 4. Rename tables one-by-one
		foreach ( $tables as $old_table ) {
			// Double check the table actually starts with the prefix (SHOW TABLES LIKE can be greedy)
			if ( 0 !== strpos( $old_table, $old_prefix ) ) {
				continue;
			}

			// Generate new table name
			$new_table = $new_prefix . substr( $old_table, strlen( $old_prefix ) );

			// Perform rename
			$rename_query = "RENAME TABLE `$old_table` TO `$new_table`";
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$result = $wpdb->query( $rename_query );

			if ( false === $result ) {
				$rename_error = $wpdb->last_error;
				break;
			}

			// Save to renamed log
			$renamed_tables[ $old_table ] = $new_table;
		}

		// If renaming failed, roll back already renamed tables immediately!
		if ( ! empty( $rename_error ) ) {
			$rollback_errors = $this->rollback_renamed_tables( $renamed_tables );
			$err_msg = sprintf(
				/* translators: 1: Database rename error message, 2: Rollback error details. */
				__( 'Database table rename failed: %1$s. All changes successfully rolled back. Rollback details: %2$s', 'secure-wp-guard' ),
				$rename_error,
				$rollback_errors ? implode( '; ', $rollback_errors ) : 'None'
			);
			return new WP_Error( 'rename_failed', $err_msg );
		}

		// At this point, all tables are renamed. Now update database values.
		// 5. Update options table: option_name
		$new_options_table = $new_prefix . 'options';

		// Pre-clean duplicate options to prevent unique key constraint failures (Duplicate entry error)
		$options_to_rename = $wpdb->get_col( $wpdb->prepare(
			"SELECT option_name FROM `$new_options_table` WHERE option_name LIKE %s",
			$esc_old_prefix . '%'
		) );

		if ( ! empty( $options_to_rename ) ) {
			foreach ( $options_to_rename as $old_opt_name ) {
				$target_opt_name = $new_prefix . substr( $old_opt_name, strlen( $old_prefix ) );
				if ( $target_opt_name !== $old_opt_name ) {
					$wpdb->query( $wpdb->prepare(
						"DELETE FROM `$new_options_table` WHERE option_name = %s",
						$target_opt_name
					) );
				}
			}
		}

		$options_query = $wpdb->prepare(
			"UPDATE `$new_options_table` SET option_name = REPLACE(option_name, %s, %s) WHERE option_name LIKE %s",
			$old_prefix,
			$new_prefix,
			$esc_old_prefix . '%'
		);
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$options_result = $wpdb->query( $options_query );

		if ( false === $options_result ) {
			// Options update failed. Rollback database table renames.
			$db_err = $wpdb->last_error;
			$rollback_errors = $this->rollback_renamed_tables( $renamed_tables );
			return new WP_Error(
				'options_update_failed',
				sprintf(
					/* translators: 1: Database error message, 2: Rollback error details. */
					__( 'Failed to update options table keys: %1$s. All changes rolled back. Rollback details: %2$s', 'secure-wp-guard' ),
					$db_err,
					$rollback_errors ? implode( '; ', $rollback_errors ) : 'None'
				)
			);
		}

		// 6. Update usermeta table: meta_key
		$new_usermeta_table = $new_prefix . 'usermeta';
		$usermeta_query = $wpdb->prepare(
			"UPDATE `$new_usermeta_table` SET meta_key = REPLACE(meta_key, %s, %s) WHERE meta_key LIKE %s",
			$old_prefix,
			$new_prefix,
			$esc_old_prefix . '%'
		);
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$usermeta_result = $wpdb->query( $usermeta_query );

		if ( false === $usermeta_result ) {
			// Usermeta update failed. Revert options changes, then rollback tables.
			$db_err = $wpdb->last_error;

			// Revert options keys
			$revert_options_query = $wpdb->prepare(
				"UPDATE `$new_options_table` SET option_name = REPLACE(option_name, %s, %s) WHERE option_name LIKE %s",
				$new_prefix,
				$old_prefix,
				$new_prefix . '%'
			);
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->query( $revert_options_query );

			// Rollback tables
			$rollback_errors = $this->rollback_renamed_tables( $renamed_tables );
			return new WP_Error(
				'usermeta_update_failed',
				sprintf(
					/* translators: 1: Database error message, 2: Rollback error details. */
					__( 'Failed to update usermeta keys: %1$s. All changes rolled back. Rollback details: %2$s', 'secure-wp-guard' ),
					$db_err,
					$rollback_errors ? implode( '; ', $rollback_errors ) : 'None'
				)
			);
		}

		// 7. Update wp-config.php
		$config_update_result = $this->update_wp_config_prefix( $config_path, $new_prefix );

		if ( is_wp_error( $config_update_result ) ) {
			// Config update failed. Rollback usermeta, options, and tables.
			$config_err = $config_update_result->get_error_message();

			// Revert usermeta
			$revert_usermeta_query = $wpdb->prepare(
				"UPDATE `$new_usermeta_table` SET meta_key = REPLACE(meta_key, %s, %s) WHERE meta_key LIKE %s",
				$new_prefix,
				$old_prefix,
				$new_prefix . '%'
			);
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->query( $revert_usermeta_query );

			// Revert options
			$revert_options_query = $wpdb->prepare(
				"UPDATE `$new_options_table` SET option_name = REPLACE(option_name, %s, %s) WHERE option_name LIKE %s",
				$new_prefix,
				$old_prefix,
				$new_prefix . '%'
			);
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->query( $revert_options_query );

			// Rollback tables
			$rollback_errors = $this->rollback_renamed_tables( $renamed_tables );

			return new WP_Error(
				'config_write_failed',
				sprintf(
					/* translators: 1: Configuration write error message, 2: Rollback error details. */
					__( 'Failed to write new prefix to wp-config.php: %1$s. All database changes rolled back. Rollback details: %2$s', 'secure-wp-guard' ),
					$config_err,
					$rollback_errors ? implode( '; ', $rollback_errors ) : 'None'
				)
			);
		}

		// Operation completed successfully!
		return true;
	}

	/**
	 * Verify database permissions by performing a temp table create/rename/drop.
	 */
	private function check_db_privileges( $prefix ) {
		global $wpdb;

		$test_table = $prefix . 'tmp_sec_check';
		$test_table_rename = $prefix . 'tmp_sec_check_renamed';

		// Create table
		$create_res = $wpdb->query( "CREATE TABLE `$test_table` ( id INT NOT NULL )" );
		if ( false === $create_res ) {
			return new WP_Error( 'db_priv_create', __( 'DB privilege check failed: cannot CREATE tables. Please grant CREATE privileges.', 'secure-wp-guard' ) );
		}

		// Rename table
		$rename_res = $wpdb->query( "RENAME TABLE `$test_table` TO `$test_table_rename`" );
		if ( false === $rename_res ) {
			$wpdb->query( "DROP TABLE IF EXISTS `$test_table`" );
			return new WP_Error( 'db_priv_rename', __( 'DB privilege check failed: cannot RENAME tables. Please grant ALTER/RENAME privileges.', 'secure-wp-guard' ) );
		}

		// Drop table
		$drop_res = $wpdb->query( "DROP TABLE `$test_table_rename`" );
		if ( false === $drop_res ) {
			return new WP_Error( 'db_priv_drop', __( 'DB privilege check failed: cannot DROP tables. Please grant DROP privileges.', 'secure-wp-guard' ) );
		}

		return true;
	}

	/**
	 * Rename tables back from new name to old name.
	 *
	 * @param array $renamed_tables Array of renamed tables (old => new).
	 * @return array Rollback error messages, if any.
	 */
	private function rollback_renamed_tables( $renamed_tables ) {
		global $wpdb;
		$errors = array();

		// Rollback in reverse order
		$reversed = array_reverse( $renamed_tables, true );

		foreach ( $reversed as $old_table => $new_table ) {
			$result = $wpdb->query( "RENAME TABLE `$new_table` TO `$old_table`" );
			if ( false === $result ) {
				$errors[] = sprintf( 
					/* translators: 1: New table name, 2: Original table name, 3: Database error message. */
					__( 'Failed to rename %1$s back to %2$s: %3$s', 'secure-wp-guard' ), $new_table, $old_table, $wpdb->last_error );
			}
		}

		return $errors;
	}

	/**
	 * Update the $table_prefix variable in wp-config.php.
	 *
	 * @param string $config_path Path to wp-config.php.
	 * @param string $new_prefix The new prefix.
	 * @return true|WP_Error
	 */
	private function update_wp_config_prefix( $config_path, $new_prefix ) {
		$content = file_get_contents( $config_path );
		if ( false === $content ) {
			return new WP_Error( 'read_failed', __( 'Could not read wp-config.php.', 'secure-wp-guard' ) );
		}

		// Search pattern for $table_prefix = '...';
		$pattern = '/(\$table_prefix\s*=\s*[\'"])(.*?)([\'"]\s*;)/';

		if ( ! preg_match( $pattern, $content ) ) {
			return new WP_Error( 'prefix_variable_not_found', __( 'Could not find the $table_prefix variable in wp-config.php.', 'secure-wp-guard' ) );
		}

		$new_content = preg_replace( $pattern, '${1}' . preg_quote( $new_prefix, '/' ) . '${3}', $content );

		// Perform a backup write first, just in case
		$backup_path = dirname( $config_path ) . DIRECTORY_SEPARATOR . 'wp-config.backup.php';
		if ( ! file_put_contents( $backup_path, $content ) ) {
			return new WP_Error( 'backup_failed', __( 'Could not create a backup copy of wp-config.php. Aborting database prefix changes for safety.', 'secure-wp-guard' ) );
		}

		// Write actual config
		$write_result = file_put_contents( $config_path, $new_content );
		if ( false === $write_result ) {
			// Revert backup
			copy( $backup_path, $config_path );
			// @unlink( $backup_path );
			if ( file_exists( $backup_path ) ) {
				wp_delete_file( $backup_path );
			}
			return new WP_Error( 'write_failed', __( 'Could not write changes to wp-config.php.', 'secure-wp-guard' ) );
		}

		// Success! Clean up backup
		// @unlink( $backup_path );
		if ( file_exists( $backup_path ) ) {
			wp_delete_file( $backup_path );
		}
		return true;
	}
}
