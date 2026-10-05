=== Secure WP Guard ===
Tags: security, login, hardening, xmlrpc, brute force, database prefix
Requires at least: 5.8
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Harden WordPress with a custom login URL, login attempt limits, XML-RPC blocking, version hiding, file-edit locks, and safe database prefix migration.

== Description ==

Secure WP Guard is a security plugin that combines several hardening tools in one admin dashboard. Configure protections from **WP Guard** in the WordPress admin menu.

= Features =

* **Custom login URL** — Replace the default `wp-login.php` address with your own slug. Direct requests to `wp-login.php` and logged-out access to `/wp-admin` can return a stealth 404 response.
* **Login attempt limiting** — Limit failed logins per IP (default: 3). Show remaining attempts after a failed login. Block offending IPs and manage a blocked-IP list with one-click unblock.
* **Disable XML-RPC** — Turn off XML-RPC and block direct access to `xmlrpc.php` to reduce brute-force and abuse vectors.
* **Hide WordPress version** — Remove generator tags and strip version query args from enqueued assets where applicable.
* **Disallow file editor** — Write `DISALLOW_FILE_EDIT` to `wp-config.php` to block the theme/plugin file editor in admin.
* **Disallow file modifications** — Write `DISALLOW_FILE_MODS` to `wp-config.php` to block installing or updating plugins and themes from the dashboard.
* **Database prefix migration** — Rename the WordPress table prefix with pre-flight checks, live logging, and rollback on failure. Updates `wp-config.php` and relevant option/meta keys.

= Dashboard =

The plugin includes an overview tab with a security health score and checklist so you can see which protections are active at a glance.

= Important notes =

* **Custom login URL:** Save your new login URL in a safe place. If you lose it, recover access via FTP/SSH (deactivate the plugin or clear the `admin_slug` option).
* **Database prefix change:** Always back up the database first. You will be logged out when the prefix changes.
* **wp-config.php:** Features that modify constants require `wp-config.php` to be writable by the web server.
* **Login limits & IP blocking:** Effectiveness depends on how your host reports client IPs (proxies, CDNs). Clear caches if login pages are cached.

== Installation ==

1. Upload the `secure-wp-guard` folder to `/wp-content/plugins/` or install the zip via **Plugins → Add New → Upload Plugin**.
2. Activate **Secure WP Guard** through the **Plugins** menu.
3. Go to **WP Guard** in the admin sidebar.
4. Configure each tab (Login Protection, Login Limit, Hardening, Database Shield) and click **Save Configuration** where applicable.

== Frequently Asked Questions ==

= I changed the login slug and cannot log in. What do I do? =

Deactivate the plugin via FTP by renaming the plugin folder under `wp-content/plugins/`, or set `admin_slug` to empty in the `secure_wp_guard_settings` option using phpMyAdmin or WP-CLI. Then use the standard `wp-login.php` URL again.

= Does this plugin hide wp-login.php completely? =

When a custom slug is set, direct access to `wp-login.php` is blocked for visitors (404-style response). Use only your custom slug to reach the login screen.

= Will login attempt limiting work behind Cloudflare or a reverse proxy? =

The plugin checks common forwarded IP headers (`CF-Connecting-IP`, `X-Forwarded-For`, `X-Real-IP`, then `REMOTE_ADDR`). Ensure your proxy passes the real client IP if possible.

= Does changing the database prefix break my site? =

The migration renames tables and updates stored prefix references. A backup is strongly recommended. On failure, the plugin attempts to roll back database and `wp-config.php` changes.

= Does deactivation remove settings? =

No. Settings and blocked IPs are kept on deactivation to avoid lockouts and accidental data loss. Remove options manually if you want a full cleanup.

== Screenshots ==

1. Overview dashboard with security health score and checklist
2. Login Protection — custom login slug and live preview
3. Login Limit — attempt limits and blocked IP management
4. Hardening Settings — XML-RPC, version hide, and wp-config constants
5. Database Shield — prefix migration with live console log

== Changelog ==

= 1.0.0 =
* Initial release
* Custom login URL with wp-login.php and wp-admin protection
* Login attempt limiting with IP blocking and unblock UI
* XML-RPC disable, hide WordPress version, DISALLOW_FILE_EDIT / DISALLOW_FILE_MODS
* Database prefix migration with rollback support
* Unified WP Guard admin dashboard

== Upgrade Notice ==

= 1.0.0 =
Initial release of Secure WP Guard.
