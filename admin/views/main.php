<?php
/**
 * Admin page view.
 *
 * @package MigrationUrlFixer
 */

defined( 'ABSPATH' ) || exit;

$muf_areas = array_keys( \MUF\Scanner::area_map() );
$muf_runs  = \MUF\Backup::list_runs();
rsort( $muf_runs );
?>
<div class="wrap muf-wrap">
	<h1><?php esc_html_e( 'Migration URL Fixer', 'migration-url-fixer' ); ?></h1>
	<p class="description">
		<?php esc_html_e( 'Replace hardcoded URLs and media paths across posts, Gutenberg blocks, serialized option values, meta fields, and more. A backup is taken automatically before any write.', 'migration-url-fixer' ); ?>
	</p>

	<h2 class="title"><?php esc_html_e( '1. Configure', 'migration-url-fixer' ); ?></h2>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><label for="muf-from"><?php esc_html_e( 'Old URL', 'migration-url-fixer' ); ?></label></th>
			<td><input type="url" id="muf-from" class="regular-text code" placeholder="https://old-site.com" /></td>
		</tr>
		<tr>
			<th scope="row"><label for="muf-to"><?php esc_html_e( 'New URL', 'migration-url-fixer' ); ?></label></th>
			<td><input type="url" id="muf-to" class="regular-text code" placeholder="<?php echo esc_attr( home_url() ); ?>" /></td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Areas', 'migration-url-fixer' ); ?></th>
			<td>
				<?php foreach ( $muf_areas as $area ) : ?>
					<label style="margin-right:12px;display:inline-block;">
						<input type="checkbox" class="muf-area" value="<?php echo esc_attr( $area ); ?>" checked />
						<code><?php echo esc_html( $area ); ?></code>
					</label>
				<?php endforeach; ?>
			</td>
		</tr>
	</table>

	<h2 class="title"><?php esc_html_e( '2. Dry-run preview', 'migration-url-fixer' ); ?></h2>
	<p>
		<button class="button" id="muf-scan"><?php esc_html_e( 'Scan (no changes)', 'migration-url-fixer' ); ?></button>
	</p>
	<div id="muf-scan-results"></div>

	<h2 class="title"><?php esc_html_e( '3. Replace', 'migration-url-fixer' ); ?></h2>
	<p>
		<button class="button button-primary" id="muf-run"><?php esc_html_e( 'Run replacement', 'migration-url-fixer' ); ?></button>
		<span class="description"><?php esc_html_e( 'A backup table per affected area is created automatically.', 'migration-url-fixer' ); ?></span>
	</p>
	<div id="muf-progress"></div>
	<pre id="muf-log" class="muf-log" aria-live="polite"></pre>

	<h2 class="title"><?php esc_html_e( 'Backups / Rollback', 'migration-url-fixer' ); ?></h2>
	<?php if ( empty( $muf_runs ) ) : ?>
		<p><em><?php esc_html_e( 'No backups yet.', 'migration-url-fixer' ); ?></em></p>
	<?php else : ?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Run ID', 'migration-url-fixer' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'migration-url-fixer' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $muf_runs as $run ) : ?>
					<tr>
						<td><code><?php echo esc_html( $run ); ?></code></td>
						<td>
							<button class="button muf-rollback" data-run="<?php echo esc_attr( $run ); ?>"><?php esc_html_e( 'Restore', 'migration-url-fixer' ); ?></button>
							<button class="button muf-discard" data-run="<?php echo esc_attr( $run ); ?>"><?php esc_html_e( 'Discard', 'migration-url-fixer' ); ?></button>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
