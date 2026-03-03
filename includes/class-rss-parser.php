<?php
/**
 * RSS Parser – fetches and parses a Podigee RSS feed via WordPress SimplePie.
 *
 * @package Podigee_RSS_Importer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Podigee_RSS_Parser {

	/**
	 * Fetch and parse a feed URL.
	 *
	 * @param string $url Feed URL.
	 * @return array<int, array> Array of episode data arrays, newest first.
	 * @throws \RuntimeException On fetch/parse failure.
	 */
	public function parse( string $url ): array {
		// fetch_feed() returns WP_Error or SimplePie instance.
		$feed = fetch_feed( esc_url_raw( $url ) );

		if ( is_wp_error( $feed ) ) {
			throw new \RuntimeException(
				sprintf(
					/* translators: %s: error message */
					__( 'Feed konnte nicht geladen werden: %s', 'podigee-rss-importer' ),
					$feed->get_error_message()
				)
			);
		}

		$items    = $feed->get_items();
		$episodes = [];

		foreach ( $items as $item ) {
			$episodes[] = $this->parse_item( $item, $url );
		}

		return $episodes;
	}

	/**
	 * Parse a single SimplePie_Item into a normalised episode array.
	 *
	 * @param SimplePie_Item $item RSS item.
	 * @param string         $feed_url Source feed URL (for context).
	 * @return array Episode data.
	 */
	private function parse_item( SimplePie_Item $item, string $feed_url ): array {
		// --- Core fields ---
		$title = wp_strip_all_tags( $item->get_title() ?? '' );
		$guid  = $item->get_id( true ) ?: $item->get_permalink();

		// description = plain-text summary (<description> tag, itunes:summary).
		$description = wp_strip_all_tags( $item->get_description( true ) ?? '' );

		// Prefer content:encoded for full HTML body, fall back to description.
		$content = $item->get_content( true );
		if ( empty( $content ) ) {
			$content = $item->get_description( true );
		}
		$content = $content ?? '';

		// Publication date as Unix timestamp.
		$pub_date = $item->get_gmdate( 'U' );
		$pub_date = $pub_date ? (int) $pub_date : 0;

		// --- Enclosure (audio file) ---
		$audio_url  = '';
		$audio_type = 'audio/mpeg';
		$enclosure  = $item->get_enclosure();
		if ( $enclosure ) {
			$audio_url  = esc_url_raw( $enclosure->get_link() ?? '' );
			$audio_type = sanitize_text_field( $enclosure->get_type() ?: 'audio/mpeg' );
		}

		// --- iTunes namespace ---
		$itunes_ns      = 'http://www.itunes.com/dtds/podcast-1.0.dtd';
		$episode_number = '';
		$season_number  = '';
		$itunes_image   = '';

		$subtitle_node = $item->get_item_tags( $itunes_ns, 'subtitle' );
		$subtitle      = ! empty( $subtitle_node[0]['data'] )
			? sanitize_text_field( $subtitle_node[0]['data'] )
			: '';

		$ep_node = $item->get_item_tags( $itunes_ns, 'episode' );
		if ( ! empty( $ep_node[0]['data'] ) ) {
			$episode_number = sanitize_text_field( $ep_node[0]['data'] );
		}

		$season_node = $item->get_item_tags( $itunes_ns, 'season' );
		if ( ! empty( $season_node[0]['data'] ) ) {
			$season_number = sanitize_text_field( $season_node[0]['data'] );
		}

		$img_node = $item->get_item_tags( $itunes_ns, 'image' );
		if ( ! empty( $img_node[0]['attribs']['']['href'] ) ) {
			$itunes_image = esc_url_raw( $img_node[0]['attribs']['']['href'] );
		}

		// Fallback: use feed-level image if episode has no image.
		if ( empty( $itunes_image ) ) {
			$feed_image = $item->get_feed()->get_image_url();
			if ( $feed_image ) {
				$itunes_image = esc_url_raw( $feed_image );
			}
		}

		// --- iTunes keywords ---
		$keywords      = [];
		$keywords_node = $item->get_item_tags( $itunes_ns, 'keywords' );
		if ( ! empty( $keywords_node[0]['data'] ) ) {
			$keywords = array_map( 'trim', explode( ',', $keywords_node[0]['data'] ) );
			$keywords = array_filter( $keywords );
		}

		// --- Podigee embed URL ---
		// Podigee puts the embed player URL in <atom:link rel="payment"> or
		// as an <itunes:subtitle> reference. We look for it in atom:link tags.
		$embed_url  = '';
		$atom_ns    = 'http://www.w3.org/2005/Atom';
		$link_nodes = $item->get_item_tags( $atom_ns, 'link' );
		if ( is_array( $link_nodes ) ) {
			foreach ( $link_nodes as $link_node ) {
				$rel  = $link_node['attribs']['']['rel'] ?? '';
				$href = $link_node['attribs']['']['href'] ?? '';
				if ( in_array( $rel, [ 'payment', 'alternate' ], true )
					&& str_contains( $href, 'podigee.io' ) ) {
					$embed_url = esc_url_raw( $href );
					break;
				}
			}
		}

		return [
			'title'          => $title,
			'guid'           => $guid,
			'subtitle'       => $subtitle,
			'description'    => $description,
			'content'        => $content,
			'pub_date'       => $pub_date,
			'audio_url'      => $audio_url,
			'audio_type'     => $audio_type,
			'image_url'      => $itunes_image,
			'episode_number' => $episode_number,
			'season_number'  => $season_number,
			'keywords'       => $keywords,
			'embed_url'      => $embed_url,
			'permalink'      => esc_url_raw( $item->get_permalink() ?? '' ),
		];
	}
}
