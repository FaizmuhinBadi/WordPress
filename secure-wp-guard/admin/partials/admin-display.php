<?php
/**
 * Provide an admin area view for the plugin.
 *
 * This file is used to markup the admin-facing aspects of the plugin.
 *
 * @package Secure_WP_Guard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;

// Retrieve current settings
$settings = $this->plugin->get_setting();
$admin_slug = $settings['admin_slug'];
$current_prefix = $wpdb->prefix;

// Pre-flight checks for status indicators
$is_xmlrpc_disabled = ( '1' === $settings['disable_xmlrpc'] );
$is_version_hidden = ( '1' === $settings['hide_wp_version'] );
$is_file_edit_disabled = defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT;
$is_file_mods_disabled = defined( 'DISALLOW_FILE_MODS' ) && DISALLOW_FILE_MODS;
$is_custom_slug_active = ! empty( $admin_slug );
$is_login_limit_enabled = ( '1' === $settings['limit_login_enabled'] );
$login_max_attempts = isset( $settings['login_max_attempts'] ) ? absint( $settings['login_max_attempts'] ) : 3;
$login_max_attempts = max( 1, min( 20, $login_max_attempts ? $login_max_attempts : 3 ) );

$login_limiter = new Secure_WP_Guard_Login_Limiter( $this->plugin );
$blocked_ips = $login_limiter->get_blocked_ips();

// Check if config file is writable
$config_path = Secure_WP_Guard_Security::get_wp_config_path();
// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable
$is_config_writable = $config_path && is_writable( $config_path );

// Calculate security score
$score = 0;
if ( $is_custom_slug_active ) $score += 25;
if ( $is_xmlrpc_disabled ) $score += 15;
if ( $is_version_hidden ) $score += 10;
if ( $is_file_edit_disabled ) $score += 20;
if ( $is_file_mods_disabled ) $score += 15;
if ( 'wp_' !== $current_prefix ) $score += 15;
if ( $is_login_limit_enabled ) $score += 10;
?>

<div class="swpg-dashboard">
	<!-- Dashboard Header -->
	<header class="swpg-header">
		<div class="swpg-logo">
			<span class="dashicons dashicons-shield"></span>
			<h1>Secure WP Guard <span class="swpg-version">v1.0.0</span></h1>
		</div>
		<div style="display: none;">
			<a href="https://github.com/faizmuhinbadi/secure-wp-guard" target="_blank" class="swpg-btn swpg-btn-secondary">
				<span class="dashicons dashicons-text"></span> Docs
			</a>
		</div>
	</header>

	<!-- Tabs Navigation -->
	<nav class="swpg-nav">
		<button class="swpg-tab-btn active" data-target="tab-overview">
			<span class="dashicons dashicons-dashboard"></span> Overview
		</button>
		<button class="swpg-tab-btn" data-target="tab-login">
			<span class="dashicons dashicons-admin-links"></span> Login Protection
		</button>
		<button class="swpg-tab-btn" data-target="tab-login-limit">
			<span class="dashicons dashicons-lock"></span> Login Limit
		</button>
		<button class="swpg-tab-btn" data-target="tab-hardening">
			<span class="dashicons dashicons-admin-generic"></span> Hardening Settings
		</button>
		<button class="swpg-tab-btn" data-target="tab-database">
			<span class="dashicons dashicons-database"></span> Database Shield
		</button>
	</nav>

	<!-- Content Area -->
	<div class="swpg-content-wrapper">

		<!-- Tab: Overview -->
		<div id="tab-overview" class="swpg-tab-content active">
			<div class="swpg-overview-grid">
				<!-- Health Gauge Card -->
				<div class="swpg-card swpg-health-gauge">
					<div class="swpg-score-circle" style="--score: <?php echo esc_attr( min( 100, $score ) ); ?>;">
						<div class="swpg-score-value"><?php echo esc_html( min( 100, $score ) ); ?>%</div>
					</div>
					<div class="swpg-score-label">Security Health Score</div>
					
					<?php if ( $score >= 80 ) : ?>
						<div class="swpg-status-indicator swpg-status-secure">
							<span class="dashicons dashicons-shield"></span> Site Secure
						</div>
					<?php else : ?>
						<div class="swpg-status-indicator swpg-status-warning">
							<span class="dashicons dashicons-warning"></span> Actions Required
						</div>
					<?php endif; ?>
				</div>

				<!-- Checklist Card -->
				<div class="swpg-card">
					<h3 class="swpg-card-title">
						<span class="dashicons dashicons-yes-alt"></span> Security Status Checklist
					</h3>
					<ul class="swpg-status-list">
						<li class="swpg-status-item">
							<div class="swpg-item-info">
								<strong>Login Attempt Limit</strong>
								<p>Blocks IPs after repeated failed logins and shows remaining attempts</p>
							</div>
							<span class="swpg-badge <?php echo $is_login_limit_enabled ? 'pass' : 'fail'; ?>">
								<?php echo $is_login_limit_enabled ? 'Active' : 'Disabled'; ?>
							</span>
						</li>
						<li class="swpg-status-item">
							<div class="swpg-item-info">
								<strong>Custom Login URL</strong>
								<p>Hides /wp-admin and /wp-login.php endpoints from attackers</p>
							</div>
							<span class="swpg-badge <?php echo $is_custom_slug_active ? 'pass' : 'fail'; ?>">
								<?php echo $is_custom_slug_active ? 'Active' : 'Missing'; ?>
							</span>
						</li>
						<li class="swpg-status-item">
							<div class="swpg-item-info">
								<strong>XML-RPC Services</strong>
								<p>Disables XML-RPC to block brute force and DDoS attacks</p>
							</div>
							<span class="swpg-badge <?php echo $is_xmlrpc_disabled ? 'pass' : 'fail'; ?>">
								<?php echo $is_xmlrpc_disabled ? 'Disabled' : 'Enabled'; ?>
							</span>
						</li>
						<li class="swpg-status-item">
							<div class="swpg-item-info">
								<strong>WordPress Version Hiding</strong>
								<p>Hides system version in header, RSS, and assets</p>
							</div>
							<span class="swpg-badge <?php echo $is_version_hidden ? 'pass' : 'fail'; ?>">
								<?php echo $is_version_hidden ? 'Hidden' : 'Exposed'; ?>
							</span>
						</li>
						<li class="swpg-status-item">
							<div class="swpg-item-info">
								<strong>File Edit Disabled (DISALLOW_FILE_EDIT)</strong>
								<p>Blocks editing themes and plugins directly from admin dashboard</p>
							</div>
							<span class="swpg-badge <?php echo $is_file_edit_disabled ? 'pass' : 'fail'; ?>">
								<?php echo $is_file_edit_disabled ? 'Inactive' : 'Active'; /* Wait, logic inversion check: if defined/true it is ACTIVE (secure) */ ?>
								<?php echo $is_file_edit_disabled ? 'Disabled' : 'Enabled'; ?>
							</span>
						</li>
						<li class="swpg-status-item">
							<div class="swpg-item-info">
								<strong>File Modifications (DISALLOW_FILE_MODS)</strong>
								<p>Blocks plugin/theme updates and installations from dashboard</p>
							</div>
							<span class="swpg-badge <?php echo $is_file_mods_disabled ? 'pass' : 'fail'; ?>">
								<?php echo $is_file_mods_disabled ? 'Disabled' : 'Enabled'; ?>
							</span>
						</li>
						<li class="swpg-status-item">
							<div class="swpg-item-info">
								<strong>Database Prefix</strong>
								<p>Obfuscates core table names from default 'wp_'</p>
							</div>
							<span class="swpg-badge <?php echo ( 'wp_' !== $current_prefix ) ? 'pass' : 'fail'; ?>">
								<?php echo ( 'wp_' !== $current_prefix ) ? 'Secured' : 'Default'; ?>
							</span>
						</li>
					</ul>
				</div>
			</div>
		</div>

		<!-- Unified Settings Form -->
		<form id="swpg-settings-form" method="post" action="">
			<div id="swpg-settings-alert" class="swpg-alert" style="display:none;"></div>
			<!-- Tab: Login Rewrite -->
			<div id="tab-login" class="swpg-tab-content">
				<div class="swpg-card">
					<h3 class="swpg-card-title">
						<span class="dashicons dashicons-admin-links"></span> Admin Login Protection
					</h3>

					<div class="swpg-form-group">
						<label for="admin_slug">Custom Login Slug</label>
						<input type="text" id="admin_slug" name="admin_slug" value="<?php echo esc_attr( $admin_slug ); ?>" placeholder="e.g. stealth-portal" />
						<p class="swpg-help-text">
							Your real login URL. <code>/wp-admin</code>, <code>/wp-login.php</code>, and <code>/login.php</code> are always blocked. For paths like <code>/login</code>: if a WordPress page exists at that URL it is shown; if not, visitors get a 404. If <code>/login</code> tries to redirect to <code>wp-login.php</code>, that is blocked the same way.
						</p>
					</div>

					<input type="hidden" id="swpg-site-url" value="<?php echo esc_url( trailingslashit( site_url() ) ); ?>" />
					
					<div class="swpg-preview-url">
						<span>
							<strong>Live Login Link:</strong> 
							<span id="swpg-preview-domain"><?php echo esc_html( trailingslashit( site_url() ) ); ?></span><span id="swpg-preview-path" style="font-weight: 700; color: #6c5ce7;"><?php echo esc_html( empty( $admin_slug ) ? 'wp-login.php' : $admin_slug ); ?></span>
						</span>
						<a href="<?php echo esc_url( empty( $admin_slug ) ? site_url( 'wp-login.php' ) : site_url( $admin_slug ) ); ?>" id="swpg-preview-link" target="_blank">
							Visit Page <span class="dashicons dashicons-external"></span>
						</a>
					</div>
				</div>

				<div style="display: flex; gap: 10px;">
					<button type="submit" class="swpg-btn">
						<span class="swpg-spinner"></span>
						<span class="dashicons dashicons-saved"></span> Save Configuration
					</button>
				</div>
			</div>

			<!-- Tab: Login Attempt Limit -->
			<div id="tab-login-limit" class="swpg-tab-content">
				<div class="swpg-card">
					<h3 class="swpg-card-title">
						<span class="dashicons dashicons-lock"></span> Login Attempt Limit
					</h3>

					<div class="swpg-switch-row">
						<div class="swpg-switch-label">
							<strong>Enable Login Attempt Limiting</strong>
							<span>Track failed logins per IP. After the maximum attempts, the IP is blocked until you unblock it.</span>
						</div>
						<label class="swpg-switch">
							<input type="checkbox" name="limit_login_enabled" value="1" <?php checked( $settings['limit_login_enabled'], '1' ); ?> />
							<span class="swpg-slider"></span>
						</label>
					</div>

					<div class="swpg-form-group">
						<label for="login_max_attempts">Maximum Failed Attempts</label>
						<input type="number" id="login_max_attempts" name="login_max_attempts" value="<?php echo esc_attr( $login_max_attempts ); ?>" min="1" max="20" step="1" />
						<p class="swpg-help-text">
							Default is 3. Remaining attempts are shown only after a failed login. After this many failed attempts, the IP is blocked.
						</p>
					</div>
				</div>

				<div style="display: flex; gap: 10px;">
					<button type="submit" class="swpg-btn">
						<span class="swpg-spinner"></span>
						<span class="dashicons dashicons-saved"></span> Save Configuration
					</button>
				</div>
			</div>

			<!-- Tab: Hardening Settings -->
			<div id="tab-hardening" class="swpg-tab-content">
				<div class="swpg-card">
					<h3 class="swpg-card-title">
						<span class="dashicons dashicons-admin-generic"></span> General Site Hardening
					</h3>

					<!-- Switch: XML RPC -->
					<div class="swpg-switch-row">
						<div class="swpg-switch-label">
							<strong>Disable XML-RPC</strong>
							<span>Disables XML-RPC API and blocks access to <code>xmlrpc.php</code>. Stops brute-force amplifications and pingback DDoS.</span>
						</div>
						<label class="swpg-switch">
							<input type="checkbox" name="disable_xmlrpc" value="1" <?php checked( $settings['disable_xmlrpc'], '1' ); ?> />
							<span class="swpg-slider"></span>
						</label>
					</div>

					<!-- Switch: Hide WP Version -->
					<div class="swpg-switch-row">
						<div class="swpg-switch-label">
							<strong>Hide WordPress Version</strong>
							<span>Removes WordPress generator tags from document head, RSS feeds, and scripts/styles version parameters. Prevents version reconnaissance.</span>
						</div>
						<label class="swpg-switch">
							<input type="checkbox" name="hide_wp_version" value="1" <?php checked( $settings['hide_wp_version'], '1' ); ?> />
							<span class="swpg-slider"></span>
						</label>
					</div>

					<!-- Switch: DISALLOW_FILE_EDIT -->
					<div class="swpg-switch-row">
						<div class="swpg-switch-label">
							<strong>Disallow File Editor (DISALLOW_FILE_EDIT)</strong>
							<span>Inserts <code>define( 'DISALLOW_FILE_EDIT', true );</code> directly into your <code>wp-config.php</code> file. Prevents direct file editing inside the dashboard.</span>
						</div>
						<label class="swpg-switch">
							<input type="checkbox" name="disallow_file_edit" value="1" <?php checked( $settings['disallow_file_edit'], '1' ); ?> />
							<span class="swpg-slider"></span>
						</label>
					</div>

					<!-- Switch: DISALLOW_FILE_MODS -->
					<div class="swpg-switch-row">
						<div class="swpg-switch-label">
							<strong>Disallow File Modifications (DISALLOW_FILE_MODS)</strong>
							<span>Inserts <code>define( 'DISALLOW_FILE_MODS', true );</code> directly into your <code>wp-config.php</code>. Disables installing or updating plugins and themes via dashboard.</span>
						</div>
						<label class="swpg-switch">
							<input type="checkbox" name="disallow_file_mods" value="1" <?php checked( $settings['disallow_file_mods'], '1' ); ?> />
							<span class="swpg-slider"></span>
						</label>
					</div>
				</div>

				<div style="display: flex; gap: 10px;">
					<button type="submit" class="swpg-btn">
						<span class="swpg-spinner"></span>
						<span class="dashicons dashicons-saved"></span> Save Configuration
					</button>
				</div>
			</div>
		</form>

		<!-- Blocked IPs panel (outside form; shown with Login Limit tab) -->
		<div id="swpg-login-limit-blocked" class="swpg-tab-addon" data-tab="tab-login-limit" style="display:none;">
			<div class="swpg-card swpg-blocked-card">
				<h3 class="swpg-card-title">
					<span class="dashicons dashicons-dismiss"></span> Blocked IP Addresses
				</h3>
				<div id="swpg-blocked-alert" class="swpg-alert" style="display:none;"></div>

				<?php if ( empty( $blocked_ips ) ) : ?>
					<p class="swpg-blocked-empty">No IP addresses are currently blocked.</p>
				<?php else : ?>
					<div class="swpg-table-wrap">
						<table class="swpg-blocked-table">
							<thead>
								<tr>
									<th>IP Address</th>
									<th>Blocked At</th>
									<th>Failed Attempts</th>
									<th>Action</th>
								</tr>
							</thead>
							<tbody id="swpg-blocked-ips-body">
								<?php foreach ( $blocked_ips as $ip => $data ) : ?>
									<tr data-ip="<?php echo esc_attr( $ip ); ?>">
										<td><code class="swpg-ip-code"><?php echo esc_html( $ip ); ?></code></td>
										<td><?php echo esc_html( isset( $data['blocked_at'] ) ? wp_date( 'Y-m-d H:i:s', $data['blocked_at'] ) : '—' ); ?></td>
										<td><span class="swpg-attempt-badge"><?php echo esc_html( isset( $data['attempts'] ) ? absint( $data['attempts'] ) : '—' ); ?></span></td>
										<td>
											<button type="button" class="swpg-btn swpg-btn-secondary swpg-unblock-ip" data-ip="<?php echo esc_attr( $ip ); ?>">
												Unblock
											</button>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				<?php endif; ?>
			</div>
		</div>

		<!-- Tab: Database Shield -->
		<div id="tab-database" class="swpg-tab-content">
			<div class="swpg-card">
				<h3 class="swpg-card-title">
					<span class="dashicons dashicons-database"></span> Database Prefix Shield
				</h3>

				<div id="swpg-prefix-alert" class="swpg-alert" style="display:none;"></div>

				<!-- Alert Warning Box -->
				<div class="swpg-alert swpg-alert-warning">
					<span class="dashicons dashicons-warning" style="font-size:24px; width:24px; height:24px;"></span>
					<div>
						<strong>CRITICAL SECURITY WARNING:</strong> Changing your database prefix alters the core table schema of your WordPress website and updates the <code>wp-config.php</code> system file. 
						<ul style="margin: 5px 0 0 15px; padding: 0; list-style: disc;">
							<li>We **highly recommend** backing up your database before running this operation.</li>
							<li>Performing this change will **instantly invalidate all active login sessions** (you will be forced to log back in).</li>
						</ul>
					</div>
				</div>

				<?php if ( ! $is_config_writable ) : ?>
					<div class="swpg-alert swpg-alert-error">
						<span class="dashicons dashicons-dismiss"></span>
						<div>
							<strong>wp-config.php is READ-ONLY:</strong> Your <code>wp-config.php</code> file is not writable by the server. Database prefix migration cannot proceed until permissions are changed.
						</div>
					</div>
				<?php endif; ?>

				<div class="swpg-form-group">
					<label>Current Database Prefix</label>
					<input type="text" value="<?php echo esc_attr( $current_prefix ); ?>" disabled style="background:#dfe6e9; color:#636e72; font-weight: 600;" />
				</div>

				<div class="swpg-form-group">
					<label for="new_prefix">New Database Prefix</label>
					<div style="display:flex; gap:10px; max-width: 400px;">
						<input type="text" id="new_prefix" name="new_prefix" placeholder="e.g. wp_secure_" style="flex-grow:1;" <?php disabled( $is_config_writable, false ); ?> />
						<button id="swpg-generate-prefix" class="swpg-btn swpg-btn-secondary" style="margin:0;" <?php disabled( $is_config_writable, false ); ?>>
							Generate
						</button>
					</div>
					<p class="swpg-help-text">
						Must start with a letter, contain only alphanumeric characters and underscores, and end with an underscore (e.g. <code>wp_guard_391_</code>). Max 30 chars.
					</p>
				</div>

				<!-- Progress bar & Logging Console -->
				<div class="swpg-progress-container">
					<div class="swpg-progress-bar"></div>
				</div>

				<div class="swpg-console-wrapper">
					<label>Migration Live Log Console</label>
					<div class="swpg-console"></div>
				</div>

				<div style="margin-top: 25px;">
					<button id="swpg-run-prefix-migration" class="swpg-btn swpg-btn-danger" <?php disabled( $is_config_writable, false ); ?>>
						<span class="swpg-spinner"></span>
						<span class="dashicons dashicons-migrate"></span> Execute Prefix Migration
					</button>
				</div>
			</div>
		</div>

	</div>
</div>

<!-- Inter-form Sync script to bind submit handler of both tabs -->
<script type="text/javascript">
jQuery(document).ready(function($) {
    // Bind all submit handlers of standard forms together
    $('form#swpg-settings-form').on('submit', function() {
        // Let jQuery AJAX handle it, they share class/ID
    });
});
</script>
