=== Migration URL Fixer ===
Contributors: mrshahbazdev
Tags: migration, search replace, urls, gutenberg, serialized
Requires at least: 5.8
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 0.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Fix broken URLs and media paths after a WordPress migration. Gutenberg-block aware, serialized-safe, with dry-run preview, automatic backup, WP-CLI, regex, exclusions, and one-click rollback.

== Description ==

After migrating a WordPress site to a new domain, old URLs stay hardcoded in posts, Gutenberg block attributes, Elementor JSON, widget configurations, and serialized option/meta values. Generic search-replace tools can corrupt serialized data or miss block attributes entirely.

**Migration URL Fixer** scans every area where URLs live and replaces them safely:

* Posts, pages, and every custom post type (including Gutenberg block attributes and inner HTML).
* Post meta, user meta, comment meta, term meta.
* Options table.
* Comment content and author URLs.
* Serialized PHP arrays and objects (length-safe).
* JSON strings, including Elementor-style slash-escaped JSON.
* URL-encoded variants (`https%3A%2F%2F...`).

= Why this plugin? =

* **Gutenberg-aware** – walks `parse_blocks()` trees and replaces inside `attrs`, `innerHTML`, `innerContent` and nested `innerBlocks`, then rebuilds with `serialize_block()`.
* **Serialized-safe** – decodes arrays, objects and nested JSON before replacement so byte lengths stay valid.
* **Dry-run preview** – see match counts per area before committing.
* **Automatic backup** – every replace run snapshots the touched tables into `muf_backup_{run_id}_*` and is one click away from rollback.
* **Batch via AJAX** – large sites don't hit PHP timeouts.
* **No external services, no accounts, no telemetry.**

= Power-user features =

* **WP-CLI**: `wp muf scan`, `wp muf replace`, `wp muf list-runs`, `wp muf rollback`, `wp muf discard`.
* **Regex mode** with full PCRE and optional case-insensitive flag.
* **Exclusions**: skip specific post types and/or option-name globs (`_transient_*`, `*_session_*`).
* **Safety filters**: critical options (`siteurl`, `home`, `template`, `stylesheet`, `active_plugins`, `upload_path`, `upload_url_path`) are protected by default – opt-in via the **Allow critical options** toggle or `--allow-critical`.
* **Multisite**: network-admin page lists every subsite; use `wp muf replace --network` to run across the whole network.

= WP-CLI quick start =

`wp muf scan --from=https://old.example.com`

`wp muf replace --from=https://old.example.com --to=https://new.example.com --dry-run`

`wp muf replace --from=https://old.example.com --to=https://new.example.com --exclude-post-types=revision --yes`

`wp muf replace --from=https://old.example.com --to=https://new.example.com --network --yes`

`wp muf list-runs`

`wp muf rollback 1712345678`

== Installation ==

1. Upload the `migration-url-fixer` folder to `/wp-content/plugins/` or install via the Plugins screen.
2. Activate the plugin.
3. Go to **Tools → Migration URL Fixer** (or **Network Admin → Tools → Migration URL Fixer** on multisite).
4. Before doing anything else, run a **Scan (no changes)** and confirm the numbers look sane.
5. Keep a full database backup before a real replace run, even though the plugin creates its own snapshot.

== Frequently Asked Questions ==

= Is this different from Better Search Replace? =

Yes. BSR does not walk Gutenberg block trees, and its handling of nested JSON (e.g. Elementor data) is naive. Migration URL Fixer parses blocks, decodes nested JSON, and re-encodes without corrupting lengths.

= Is it safe to run on a live site? =

Use the dry-run first. Every replace run creates per-table backups that you can roll back in one click. Still, keep an independent database backup. The plugin refuses to touch critical options (`siteurl`, `home`, etc.) unless you explicitly allow it.

= Does it support multisite? =

Yes. Each subsite has its own admin page, and `wp muf replace --network` loops every subsite with its own run ID. The network-admin page lists all subsites with deep links.

= Does it support regex? =

Yes – enable **Regex (PCRE)** in the UI, or pass `--regex` to the CLI. Back-references (`$1`, `$2`) are supported in the replacement string. Combine with **Case-insensitive** if needed.

= What if a replace goes wrong? =

Open the **Backups** panel (or run `wp muf list-runs` + `wp muf rollback <run_id>`). Every touched table is restored byte-for-byte.

= Can I change `siteurl` with this plugin? =

You can, by enabling **Allow critical options** / `--allow-critical`, but we strongly recommend using `wp option update siteurl …` directly. Critical options are protected by default to prevent accidental lockouts.

= Does the plugin send data anywhere? =

No. No external calls, no analytics, no account required.

== Screenshots ==

1. Dry-run scan with per-area match counts.
2. Replace run in progress (AJAX batch, live log).
3. Backups panel with one-click rollback.
4. WP-CLI `wp muf replace --dry-run` output.

== Changelog ==

= 0.2.0 =
* **New:** WP-CLI command suite (`wp muf scan | replace | rollback | discard | list-runs`).
* **New:** Regex (PCRE) + case-insensitive matching.
* **New:** Exclusions for post types and option-name globs.
* **New:** Safety filter for critical options (`siteurl`, `home`, `template`, `stylesheet`, `active_plugins`, `upload_path`, `upload_url_path`).
* **New:** Multisite network-admin page + `--network` flag for running across the whole network.
* **New:** PHPUnit test suite and GitHub Actions CI (PHPUnit on PHP 7.4–8.3, PHPCS with WordPress Coding Standards).
* **New:** Matching-mode and exclusion controls in the admin UI.

= 0.1.0 =
* Initial release.

== Upgrade Notice ==

= 0.2.0 =
Adds WP-CLI support, regex matching, exclusions, multisite and critical-options protection. No data migration required.
