# Migration URL Fixer

A WordPress plugin that fixes broken URLs and media paths after a site migration — safely and thoroughly.

Unlike generic search-and-replace tools, Migration URL Fixer understands:

- **Gutenberg block attributes** (JSON inside `<!-- wp:... -->` comments).
- **Serialized PHP arrays / objects** (length-safe replacement — no corrupted data).
- **JSON strings** stored in options / post meta (including Elementor-style slash-escaped JSON).
- **URL-encoded variants** (`https%3A%2F%2F...`).

## Features

- Dry-run preview with per-area match counts before any write.
- Automatic per-run DB snapshot of every table touched.
- One-click rollback from the Backups panel.
- AJAX-based batch processing so large sites don't time out.
- No external services, no account, no telemetry.

## Usage

1. Install & activate the plugin.
2. Go to **Tools → Migration URL Fixer**.
3. Enter the old URL and the new URL.
4. Click **Scan (no changes)** to preview match counts.
5. Click **Run replacement** to execute. A backup is taken automatically.
6. If anything looks wrong, use the **Backups / Rollback** panel to restore.

## Requirements

- WordPress 5.8+
- PHP 7.4+

## License

GPL-2.0-or-later.
