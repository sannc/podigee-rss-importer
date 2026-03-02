<?php
/**
 * Admin View: Feeds list
 *
 * Available variables:
 *   $feeds – array of feed config arrays
 *
 * @package Podigee_RSS_Importer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$cron_labels = [
	'never'      => __( 'Deaktiviert', 'podigee-rss-importer' ),
	'hourly'     => __( 'Stündlich', 'podigee-rss-importer' ),
	'twicedaily' => __( 'Zweimal täglich', 'podigee-rss-importer' ),
	'daily'      => __( 'Täglich', 'podigee-rss-importer' ),
	'weekly'     => __( 'Wöchentlich', 'podigee-rss-importer' ),
];
?>
<div class="wrap podigee-wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Podigee Feeds', 'podigee-rss-importer' ); ?></h1>
	<a href="<?php echo esc_url( add_query_arg( [ 'page' => 'podigee-feeds', 'action' => 'new' ], admin_url( 'admin.php' ) ) ); ?>"
		class="page-title-action"><?php esc_html_e( 'Feed hinzufügen', 'podigee-rss-importer' ); ?></a>
	<hr class="wp-header-end">

	<?php if ( isset( $_GET['saved'] ) ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'Feed gespeichert.', 'podigee-rss-importer' ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( isset( $_GET['deleted'] ) ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'Feed gelöscht.', 'podigee-rss-importer' ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( empty( $feeds ) ) : ?>
		<p><?php esc_html_e( 'Noch keine Feeds konfiguriert.', 'podigee-rss-importer' ); ?>
			<a href="<?php echo esc_url( add_query_arg( [ 'page' => 'podigee-feeds', 'action' => 'new' ], admin_url( 'admin.php' ) ) ); ?>">
				<?php esc_html_e( 'Jetzt Feed hinzufügen', 'podigee-rss-importer' ); ?>
			</a>
		</p>
	<?php else : ?>
		<table class="wp-list-table widefat fixed striped podigee-feeds-table">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Name', 'podigee-rss-importer' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Feed-URL', 'podigee-rss-importer' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Post-Type', 'podigee-rss-importer' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Status', 'podigee-rss-importer' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Cron', 'podigee-rss-importer' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Letzte Ausführung', 'podigee-rss-importer' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Aktionen', 'podigee-rss-importer' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $feeds as $feed ) : ?>
					<tr>
						<td><strong><?php echo esc_html( $feed['name'] ); ?></strong></td>
						<td>
							<a href="<?php echo esc_url( $feed['url'] ); ?>" target="_blank" rel="noopener noreferrer">
								<?php echo esc_html( $feed['url'] ); ?>
							</a>
						</td>
						<td><?php echo esc_html( $feed['post_type'] ); ?></td>
						<td>
							<span class="podigee-badge podigee-badge--<?php echo esc_attr( $feed['post_status'] ); ?>">
								<?php echo 'publish' === $feed['post_status']
									? esc_html__( 'Veröffentlicht', 'podigee-rss-importer' )
									: esc_html__( 'Entwurf', 'podigee-rss-importer' ); ?>
							</span>
						</td>
						<td>
							<?php
							$schedule = $feed['cron_schedule'] ?? 'never';
							echo esc_html( $cron_labels[ $schedule ] ?? $schedule );
							?>
						</td>
						<td>
							<?php if ( ! empty( $feed['last_run'] ) ) : ?>
								<?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $feed['last_run'] ) ); ?>
							<?php else : ?>
								<em><?php esc_html_e( 'Noch nie', 'podigee-rss-importer' ); ?></em>
							<?php endif; ?>
						</td>
						<td class="podigee-actions">
							<a href="<?php echo esc_url( add_query_arg( [ 'page' => 'podigee-feeds', 'action' => 'edit', 'feed_id' => $feed['id'] ], admin_url( 'admin.php' ) ) ); ?>">
								<?php esc_html_e( 'Bearbeiten', 'podigee-rss-importer' ); ?>
							</a>
							<a href="<?php echo esc_url( add_query_arg( [ 'page' => 'podigee-import', 'feed_id' => $feed['id'] ], admin_url( 'admin.php' ) ) ); ?>">
								<?php esc_html_e( 'Importieren', 'podigee-rss-importer' ); ?>
							</a>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
								<input type="hidden" name="action" value="podigee_delete_feed">
								<input type="hidden" name="feed_id" value="<?php echo esc_attr( $feed['id'] ); ?>">
								<?php wp_nonce_field( 'podigee_delete_feed_' . $feed['id'], 'podigee_nonce' ); ?>
								<button type="submit" class="button-link podigee-delete-btn"
									onclick="return confirm('<?php esc_attr_e( 'Feed wirklich löschen?', 'podigee-rss-importer' ); ?>');">
									<?php esc_html_e( 'Löschen', 'podigee-rss-importer' ); ?>
								</button>
							</form>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
