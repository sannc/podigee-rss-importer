<?php
/**
 * Admin View: Episode import
 *
 * Available variables:
 *   $feeds – array of all feed configs
 *
 * @package Podigee_RSS_Importer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$selected_feed_id = sanitize_text_field( $_GET['feed_id'] ?? '' );
?>
<div class="wrap podigee-wrap">
	<h1><?php esc_html_e( 'Episoden importieren', 'podigee-rss-importer' ); ?></h1>
	<hr class="wp-header-end">

	<?php if ( empty( $feeds ) ) : ?>
		<p>
			<?php esc_html_e( 'Noch kein Feed konfiguriert.', 'podigee-rss-importer' ); ?>
			<a href="<?php echo esc_url( add_query_arg( [ 'page' => 'podigee-feeds', 'action' => 'new' ], admin_url( 'admin.php' ) ) ); ?>">
				<?php esc_html_e( 'Feed hinzufügen', 'podigee-rss-importer' ); ?>
			</a>
		</p>
	<?php else : ?>
		<div class="podigee-import-controls">
			<label for="podigee-feed-select"><strong><?php esc_html_e( 'Feed wählen:', 'podigee-rss-importer' ); ?></strong></label>
			<select id="podigee-feed-select">
				<option value=""><?php esc_html_e( '– Feed auswählen –', 'podigee-rss-importer' ); ?></option>
				<?php foreach ( $feeds as $feed ) : ?>
					<option value="<?php echo esc_attr( $feed['id'] ); ?>"
						<?php selected( $selected_feed_id, $feed['id'] ); ?>>
						<?php echo esc_html( $feed['name'] ); ?> (<?php echo esc_html( $feed['url'] ); ?>)
					</option>
				<?php endforeach; ?>
			</select>
			<button type="button" id="podigee-load-episodes" class="button button-secondary">
				<?php esc_html_e( 'Episoden laden', 'podigee-rss-importer' ); ?>
			</button>
		</div>

		<div id="podigee-episodes-wrapper" style="display:none; margin-top:20px;">
			<div class="podigee-bulk-actions">
				<button type="button" id="podigee-select-all" class="button button-secondary"><?php esc_html_e( 'Alle auswählen', 'podigee-rss-importer' ); ?></button>
				<button type="button" id="podigee-select-none" class="button button-secondary"><?php esc_html_e( 'Keine auswählen', 'podigee-rss-importer' ); ?></button>
				<button type="button" id="podigee-select-new" class="button button-secondary"><?php esc_html_e( 'Nur neue auswählen', 'podigee-rss-importer' ); ?></button>
				<button type="button" id="podigee-ignore-selected" class="button button-secondary">
					<?php esc_html_e( 'Ausgewählte ignorieren', 'podigee-rss-importer' ); ?>
				</button>
				<button type="button" class="podigee-import-btn button button-primary">
					<?php esc_html_e( 'Ausgewählte importieren', 'podigee-rss-importer' ); ?>
				</button>
				<span class="podigee-import-spinner spinner" style="float:none; visibility:hidden;"></span>
			</div>

			<div class="podigee-ignored-toggle" style="margin-top:10px;">
				<label>
					<input type="checkbox" id="podigee-show-ignored">
					<?php esc_html_e( 'Ignorierte anzeigen', 'podigee-rss-importer' ); ?>
				</label>
			</div>

			<table class="wp-list-table widefat fixed striped podigee-episodes-table" style="margin-top:10px;">
				<thead>
					<tr>
						<th scope="col" class="check-column"></th>
						<th scope="col" style="width:60px;"><?php esc_html_e( 'Nr.', 'podigee-rss-importer' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Titel', 'podigee-rss-importer' ); ?></th>
						<th scope="col" style="width:130px;"><?php esc_html_e( 'Datum', 'podigee-rss-importer' ); ?></th>
						<th scope="col" style="width:130px;"><?php esc_html_e( 'Status', 'podigee-rss-importer' ); ?></th>
						<th scope="col" style="width:120px;"><?php esc_html_e( 'Aktionen', 'podigee-rss-importer' ); ?></th>
					</tr>
				</thead>
				<tbody id="podigee-episodes-tbody">
					<!-- Populated by JS -->
				</tbody>
			</table>

			<div style="margin-top:15px;">
				<button type="button" class="podigee-import-btn button button-primary">
					<?php esc_html_e( 'Ausgewählte importieren', 'podigee-rss-importer' ); ?>
				</button>
				<span class="podigee-import-spinner spinner" style="float:none; visibility:hidden;"></span>
			</div>

			<div id="podigee-import-result" style="margin-top:15px; display:none;"></div>
		</div>

		<div id="podigee-loading-indicator" style="display:none; margin-top:20px;">
			<span class="spinner is-active" style="float:none;"></span>
			<?php esc_html_e( 'Episoden werden geladen…', 'podigee-rss-importer' ); ?>
		</div>
	<?php endif; ?>
</div>
