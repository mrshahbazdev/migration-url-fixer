=== Migration URL Fixer ===
Contributors: mrshahbazdev
Tags: migration, search replace, urls, gutenberg, serialized
Requires at least: 5.8
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Fix broken URLs and media paths after a WordPress migration. Gutenberg-block aware, serialized-safe, with dry-run preview, automatic backup, and one-click rollback.

== Description ==

After migrating a WordPress site to a new domain, old URLs stay hardcoded in posts, Gutenberg block attributes, Elementor JSON, widget configurations, and serialized option/meta values. Generic search-replace tools can corrupt serialized data or miss block attributes entirely.

Migration URL Fixer scans every area where URLs live and replaces them safely:

* Posts, pages, and custom post types (including Gutenberg block attributes and inner HTML).
* Post meta, user meta, comment meta, term meta.
* Options table.
* Comment content and author URLs.
* Serialized PHP arrays/objects (length-safe).
* JSON strings (including Elementor-style slash-escaped JSON).
* URL-encoded variants.

Features:

* Dry-run preview showing match counts per area before any write.
* Automatic per-run DB snapshot of every table touched.
* One-click rollback from the Backups panel.
* AJAX-based batch processing so large sites don't time out.
* No external services, no account, no telemetry.

== Installation ==

1. Upload the `migration-url-fixer` folder to `/wp-content/plugins/` or install via the Plugins screen.
2. Activate the plugin.
3. Go to **Tools → Migration URL Fixer**.

== Changelog ==

= 0.1.0 =
* Initial release.
