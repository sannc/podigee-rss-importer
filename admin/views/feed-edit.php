<?php
/**
 * Admin View: Feed add / edit form
 *
 * Available variables:
 *   $feed – array|null  (null when creating a new feed)
 *
 * @package Podigee_RSS_Importer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$is_new  = empty( $feed );
$page_title = $is_new
	? __( 'Feed hinzufügen', 'podigee-rss-importer' )
	: __( 'Feed bearbeiten', 'podigee-rss-importer' );

// Defaults.
$feed = wp_parse_args( $feed ?? [], [
	'id'               => '',
	'name'             => '',
	'url'              => '',
	'post_type'        => 'post',
	'post_status'      => 'draft',
	'use_episode_date' => false,
	'update_existing'  => false,
	'cron_schedule'    => 'never',
	'image_mode'       => 'none',
	'category_ids'     => [],
	'tag_ids'          => [],
] );

$post_types = get_post_types( [ 'public' => true ], 'objects' );
$categories = get_categories( [ 'hide_empty' => false ] );
$tags       = get_tags( [ 'hide_empty' => false ] );
?>
<div class="wrap podigee-wrap">
	<h1><?php echo esc_html( $page_title ); ?></h1>
	<a href="<?php echo esc_url( add_query_arg( 'page', 'podigee-feeds', admin_url( 'admin.php' ) ) ); ?>">
		&larr; <?php esc_html_e( 'Zurück zur Übersicht', 'podigee-rss-importer' ); ?>
	</a>
	<hr>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="podigee_save_feed">
		<input type="hidden" name="feed_id" value="<?php echo esc_attr( $feed['id'] ); ?>">
		<?php wp_nonce_field( 'podigee_save_feed', 'podigee_nonce' ); ?>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row">
					<label for="podigee-name"><?php esc_html_e( 'Name', 'podigee-rss-importer' ); ?></label>
				</th>
				<td>
					<input type="text" id="podigee-name" name="name"
						value="<?php echo esc_attr( $feed['name'] ); ?>"
						class="regular-text" required>
					<p class="description"><?php esc_html_e( 'Anzeigename für diesen Feed (nur intern)', 'podigee-rss-importer' ); ?></p>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="podigee-url"><?php esc_html_e( 'Feed-URL', 'podigee-rss-importer' ); ?></label>
				</th>
				<td>
					<input type="url" id="podigee-url" name="url"
						value="<?php echo esc_attr( $feed['url'] ); ?>"
						class="regular-text" required
						placeholder="https://meinpodcast.podigee.io/feed/mp3">
					<p class="description"><?php esc_html_e( 'Vollständige URL des Podigee RSS-Feeds', 'podigee-rss-importer' ); ?></p>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="podigee-post-type"><?php esc_html_e( 'Post-Type', 'podigee-rss-importer' ); ?></label>
				</th>
				<td>
					<select id="podigee-post-type" name="post_type">
						<?php foreach ( $post_types as $pt ) : ?>
							<option value="<?php echo esc_attr( $pt->name ); ?>"
								<?php selected( $feed['post_type'], $pt->name ); ?>>
								<?php echo esc_html( $pt->labels->singular_name . ' (' . $pt->name . ')' ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<?php esc_html_e( 'Post-Status', 'podigee-rss-importer' ); ?>
				</th>
				<td>
					<label>
						<input type="radio" name="post_status" value="draft"
							<?php checked( $feed['post_status'], 'draft' ); ?>>
						<?php esc_html_e( 'Entwurf', 'podigee-rss-importer' ); ?>
					</label>
					&nbsp;&nbsp;
					<label>
						<input type="radio" name="post_status" value="publish"
							<?php checked( $feed['post_status'], 'publish' ); ?>>
						<?php esc_html_e( 'Veröffentlicht', 'podigee-rss-importer' ); ?>
					</label>
				</td>
			</tr>

			<tr>
				<th scope="row"><?php esc_html_e( 'Optionen', 'podigee-rss-importer' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="use_episode_date" value="1"
							<?php checked( $feed['use_episode_date'] ); ?>>
						<?php esc_html_e( 'Episodendatum als Post-Datum verwenden', 'podigee-rss-importer' ); ?>
					</label>
					<br>
					<label>
						<input type="checkbox" name="update_existing" value="1"
							<?php checked( $feed['update_existing'] ); ?>>
						<?php esc_html_e( 'Bereits importierte Posts aktualisieren', 'podigee-rss-importer' ); ?>
					</label>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="podigee-cron"><?php esc_html_e( 'Automatischer Import', 'podigee-rss-importer' ); ?></label>
				</th>
				<td>
					<select id="podigee-cron" name="cron_schedule">
						<option value="never" <?php selected( $feed['cron_schedule'], 'never' ); ?>><?php esc_html_e( 'Deaktiviert', 'podigee-rss-importer' ); ?></option>
						<option value="hourly" <?php selected( $feed['cron_schedule'], 'hourly' ); ?>><?php esc_html_e( 'Stündlich', 'podigee-rss-importer' ); ?></option>
						<option value="twicedaily" <?php selected( $feed['cron_schedule'], 'twicedaily' ); ?>><?php esc_html_e( 'Zweimal täglich', 'podigee-rss-importer' ); ?></option>
						<option value="daily" <?php selected( $feed['cron_schedule'], 'daily' ); ?>><?php esc_html_e( 'Täglich', 'podigee-rss-importer' ); ?></option>
						<option value="weekly" <?php selected( $feed['cron_schedule'], 'weekly' ); ?>><?php esc_html_e( 'Wöchentlich', 'podigee-rss-importer' ); ?></option>
					</select>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="podigee-image-mode"><?php esc_html_e( 'Episodenbild', 'podigee-rss-importer' ); ?></label>
				</th>
				<td>
					<select id="podigee-image-mode" name="image_mode">
						<option value="none" <?php selected( $feed['image_mode'], 'none' ); ?>><?php esc_html_e( 'Nein', 'podigee-rss-importer' ); ?></option>
						<option value="featured" <?php selected( $feed['image_mode'], 'featured' ); ?>><?php esc_html_e( 'Als Featured Image', 'podigee-rss-importer' ); ?></option>
						<option value="inline" <?php selected( $feed['image_mode'], 'inline' ); ?>><?php esc_html_e( 'Als Bild im Artikel', 'podigee-rss-importer' ); ?></option>
						<option value="media_only" <?php selected( $feed['image_mode'], 'media_only' ); ?>><?php esc_html_e( 'Nur in die Mediathek', 'podigee-rss-importer' ); ?></option>
					</select>
					<p class="description"><?php esc_html_e( 'Was soll mit dem Episodenbild geschehen?', 'podigee-rss-importer' ); ?></p>
				</td>
			</tr>

			<?php if ( ! empty( $categories ) ) : ?>
			<tr>
				<th scope="row"><?php esc_html_e( 'Kategorien', 'podigee-rss-importer' ); ?></th>
				<td>
					<div class="podigee-term-list">
						<?php foreach ( $categories as $cat ) : ?>
							<label>
								<input type="checkbox" name="category_ids[]"
									value="<?php echo esc_attr( $cat->term_id ); ?>"
									<?php checked( in_array( $cat->term_id, $feed['category_ids'], true ) ); ?>>
								<?php echo esc_html( $cat->name ); ?>
							</label><br>
						<?php endforeach; ?>
					</div>
				</td>
			</tr>
			<?php endif; ?>

			<?php if ( ! empty( $tags ) ) : ?>
			<tr>
				<th scope="row"><?php esc_html_e( 'Tags', 'podigee-rss-importer' ); ?></th>
				<td>
					<div class="podigee-term-list">
						<?php foreach ( $tags as $tag ) : ?>
							<label>
								<input type="checkbox" name="tag_ids[]"
									value="<?php echo esc_attr( $tag->term_id ); ?>"
									<?php checked( in_array( $tag->term_id, $feed['tag_ids'], true ) ); ?>>
								<?php echo esc_html( $tag->name ); ?>
							</label><br>
						<?php endforeach; ?>
					</div>
				</td>
			</tr>
			<?php endif; ?>
		</table>

		<?php submit_button( $is_new ? __( 'Feed hinzufügen', 'podigee-rss-importer' ) : __( 'Änderungen speichern', 'podigee-rss-importer' ) ); ?>
	</form>
</div>
