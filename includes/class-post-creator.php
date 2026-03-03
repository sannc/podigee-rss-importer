<?php
/**
 * Post Creator – creates or updates WordPress posts from episode data.
 *
 * @package Podigee_RSS_Importer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Podigee_Post_Creator {

	/**
	 * Create or update a post for a given episode.
	 *
	 * @param array $episode     Parsed episode data from Podigee_RSS_Parser.
	 * @param array $feed        Feed configuration from Podigee_Feed_Manager.
	 * @param int   $existing_id Existing post ID (0 = create new).
	 * @return int|\WP_Error New/updated post ID or WP_Error on failure.
	 */
	public function create_or_update( array $episode, array $feed, int $existing_id = 0 ) {
		$post_date = '';
		if ( ! empty( $feed['use_episode_date'] ) && $episode['pub_date'] > 0 ) {
			$post_date = gmdate( 'Y-m-d H:i:s', $episode['pub_date'] );
		}

		$post_data = [
			'post_title'   => sanitize_text_field( $episode['title'] ),
			'post_content' => $this->build_content( $episode ),
			'post_excerpt' => sanitize_textarea_field( $episode['description'] ?? '' ),
			'post_status'  => sanitize_key( $feed['post_status'] ),
			'post_type'    => sanitize_key( $feed['post_type'] ),
		];

		if ( $post_date ) {
			$post_data['post_date']     = $post_date;
			$post_data['post_date_gmt'] = $post_date;
		}

		if ( $existing_id > 0 ) {
			$post_data['ID'] = $existing_id;
			$post_id         = wp_update_post( $post_data, true );
		} else {
			$post_id = wp_insert_post( $post_data, true );
		}

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		// --- Post meta ---
		update_post_meta( $post_id, '_podigee_episode_guid', sanitize_text_field( $episode['guid'] ) );
		update_post_meta( $post_id, '_podigee_feed_id', sanitize_text_field( $feed['id'] ) );
		update_post_meta( $post_id, '_podigee_audio_url', esc_url_raw( $episode['audio_url'] ) );

		if ( ! empty( $episode['episode_number'] ) ) {
			update_post_meta( $post_id, '_podigee_episode_number', sanitize_text_field( $episode['episode_number'] ) );
		}

		if ( ! empty( $episode['season_number'] ) ) {
			update_post_meta( $post_id, '_podigee_season_number', sanitize_text_field( $episode['season_number'] ) );
		}

		if ( ! empty( $episode['embed_url'] ) ) {
			update_post_meta( $post_id, '_podigee_embed_url', esc_url_raw( $episode['embed_url'] ) );
		}

		// --- Taxonomies ---
		if ( ! empty( $feed['category_ids'] ) ) {
			wp_set_post_categories( $post_id, array_map( 'absint', $feed['category_ids'] ) );
		}

		if ( ! empty( $feed['tag_ids'] ) ) {
			wp_set_post_tags( $post_id, array_map( 'absint', $feed['tag_ids'] ) );
		}

		// --- Featured Image ---
		if ( ! empty( $episode['image_url'] ) && ! has_post_thumbnail( $post_id ) ) {
			$this->maybe_set_thumbnail( $post_id, $episode['image_url'], $episode['title'] );
		}

		return $post_id;
	}

	// =========================================================================
	// Content builder
	// =========================================================================

	/**
	 * Build serialized Gutenberg block content for an episode.
	 *
	 * Block order:
	 *   1. Subtitle    (itunes:subtitle, if present)
	 *   2. Player      (styled audio card or Podigee embed)
	 *   3. Description (plain-text summary)
	 *   4. Shownotes   (full HTML body, parsed into semantic blocks)
	 */
	private function build_content( array $episode ): string {
		$blocks = [];

		// --- 1. Subtitle ---
		$subtitle = trim( $episode['subtitle'] ?? '' );
		if ( $subtitle !== '' ) {
			$html     = '<p class="podigee-subtitle">' . esc_html( $subtitle ) . '</p>';
			$blocks[] = $this->make_block( 'core/paragraph', [ 'className' => 'podigee-subtitle' ], $html );
		}

		// --- 2. Player ---
		if ( ! empty( $episode['embed_url'] ) ) {
			// Podigee iframe embed.
			$embed_url = esc_url( $episode['embed_url'] );
			$html      = sprintf(
				'<div class="podigee-player podigee-player--embed"><iframe src="%s" frameborder="0" scrolling="no" allow="autoplay" loading="lazy"></iframe></div>',
				$embed_url
			);
			$blocks[] = $this->make_block( 'core/html', [], $html );
		} elseif ( ! empty( $episode['audio_url'] ) ) {
			$host       = wp_parse_url( home_url(), PHP_URL_HOST ) ?? '';
			$audio_url  = esc_url( add_query_arg( 'source', $host, $episode['audio_url'] ) );
			$inner_html = sprintf(
				'<figure class="wp-block-audio"><audio controls src="%s"></audio></figure>',
				$audio_url
			);
			// className 'podigee-episode-player' marks the block for the
			// render_block filter which wraps it in the styled card on the frontend.
			$blocks[] = $this->make_block(
				'core/audio',
				[ 'src' => $audio_url, 'className' => 'podigee-episode-player' ],
				$inner_html
			);
		}

		// --- 3. Description ---
		$description = trim( $episode['description'] ?? '' );
		if ( $description !== '' ) {
			$html     = '<p class="podigee-description">' . esc_html( $description ) . '</p>';
			$blocks[] = $this->make_block( 'core/paragraph', [ 'className' => 'podigee-description' ], $html );
		}

		// --- 4. Shownotes ---
		$body = wp_kses_post( $episode['content'] );
		// Remove leading subtitle from shownotes to avoid duplication.
		// The subtitle may appear as bare text or wrapped in a <p> tag.
		if ( $subtitle !== '' ) {
			$quoted = preg_quote( $subtitle, '/' );
			$body   = preg_replace(
				'/^\s*(?:<p[^>]*>\s*)?' . $quoted . '\s*(?:<\/p>)?\s*/iu',
				'',
				$body,
				1
			);
		}
		if ( ! empty( trim( $body ) ) ) {
			$heading_html = '<h2>' . esc_html__( 'Shownotes', 'podigee-rss-importer' ) . '</h2>';
			$blocks[]     = $this->make_block( 'core/heading', [ 'level' => 2 ], $heading_html );
			$blocks       = array_merge( $blocks, $this->html_to_blocks( $body ) );
		}

		return serialize_blocks( $blocks );
	}

	// =========================================================================
	// HTML → Gutenberg blocks
	// =========================================================================

	/**
	 * Parse an HTML string into an array of Gutenberg block arrays.
	 *
	 * Top-level elements are mapped to semantic core blocks:
	 *   <p>          → core/paragraph
	 *   <h1>–<h6>   → core/heading   (with "level" attribute)
	 *   <ul>         → core/list
	 *   <ol>         → core/list      (with "ordered" attribute)
	 *   <blockquote> → core/quote
	 *   <figure>     → core/html      (images/media already wrapped)
	 *   everything else → core/html   (Custom HTML block, no "Convert" prompt)
	 *
	 * @param string $html Raw HTML from the RSS feed (already wp_kses_post'd).
	 * @return array<int, array> Block arrays ready for serialize_blocks().
	 */
	private function html_to_blocks( string $html ): array {
		$blocks = [];

		libxml_use_internal_errors( true );
		$dom = new DOMDocument( '1.0', 'UTF-8' );
		// The charset meta ensures DOMDocument handles UTF-8 characters correctly.
		$dom->loadHTML(
			'<html><head><meta charset="UTF-8"></head><body>' . $html . '</body></html>',
			LIBXML_HTML_NODEFDTD
		);
		libxml_clear_errors();

		$body = $dom->getElementsByTagName( 'body' )->item( 0 );
		if ( ! $body ) {
			return [ $this->make_block( 'core/html', [], $html ) ];
		}

		foreach ( $body->childNodes as $node ) {
			// Skip whitespace-only text nodes between block elements.
			if ( $node->nodeType === XML_TEXT_NODE ) {
				if ( trim( $node->textContent ) === '' ) {
					continue;
				}
				// Bare text → wrap in paragraph.
				$text     = esc_html( trim( $node->textContent ) );
				$blocks[] = $this->make_block( 'core/paragraph', [], '<p>' . $text . '</p>' );
				continue;
			}

			if ( $node->nodeType !== XML_ELEMENT_NODE ) {
				continue;
			}

			$tag   = strtolower( $node->nodeName );
			$outer = $dom->saveHTML( $node );

			switch ( $tag ) {
				case 'p':
					// Skip empty paragraphs.
					if ( trim( $node->textContent ) === '' && $node->childNodes->length === 0 ) {
						break;
					}
					$blocks[] = $this->make_block( 'core/paragraph', [], $outer );
					break;

				case 'h1':
				case 'h2':
				case 'h3':
				case 'h4':
				case 'h5':
				case 'h6':
					$level    = (int) substr( $tag, 1 );
					$blocks[] = $this->make_block( 'core/heading', [ 'level' => $level ], $outer );
					break;

				case 'ul':
					$blocks[] = $this->make_block( 'core/list', [], $outer );
					break;

				case 'ol':
					$blocks[] = $this->make_block( 'core/list', [ 'ordered' => true ], $outer );
					break;

				case 'blockquote':
					$inner    = $this->dom_inner_html( $node, $dom );
					$blocks[] = $this->make_block( 'core/quote', [], '<blockquote class="wp-block-quote">' . $inner . '</blockquote>' );
					break;

				default:
					// Anything else (div, figure, table, pre, …) → Custom HTML block.
					$blocks[] = $this->make_block( 'core/html', [], $outer );
					break;
			}
		}

		return $blocks;
	}

	// =========================================================================
	// Block / DOM helpers
	// =========================================================================

	/**
	 * Build a minimal block array for serialize_block().
	 */
	private function make_block( string $name, array $attrs, string $html ): array {
		return [
			'blockName'    => $name,
			'attrs'        => $attrs,
			'innerBlocks'  => [],
			'innerHTML'    => $html,
			'innerContent' => [ $html ],
		];
	}

	/**
	 * Return the serialised inner HTML of a DOMNode (children only, not the node itself).
	 */
	private function dom_inner_html( DOMNode $node, DOMDocument $dom ): string {
		$html = '';
		foreach ( $node->childNodes as $child ) {
			$html .= $dom->saveHTML( $child );
		}
		return $html;
	}

	/**
	 * Download and set a featured image for a post.
	 */
	private function maybe_set_thumbnail( int $post_id, string $image_url, string $title ): void {
		if ( ! function_exists( 'media_sideload_image' ) ) {
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		$attachment_id = media_sideload_image( $image_url, $post_id, sanitize_text_field( $title ), 'id' );

		if ( ! is_wp_error( $attachment_id ) && $attachment_id > 0 ) {
			set_post_thumbnail( $post_id, $attachment_id );
		}
	}
}
