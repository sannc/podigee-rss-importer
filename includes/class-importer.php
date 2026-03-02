<?php
/**
 * Importer – core import logic orchestrating parser, deduplication and post creation.
 *
 * @package Podigee_RSS_Importer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Podigee_Importer {

	private Podigee_RSS_Parser  $parser;
	private Podigee_Post_Creator $creator;
	private Podigee_Feed_Manager $feed_manager;

	public function __construct() {
		$this->parser       = new Podigee_RSS_Parser();
		$this->creator      = new Podigee_Post_Creator();
		$this->feed_manager = new Podigee_Feed_Manager();
	}

	/**
	 * Import specific episodes by GUID.
	 *
	 * @param string[] $guids       GUIDs to import.
	 * @param array    $feed_config Feed configuration.
	 * @return array{ imported: int, updated: int, skipped: int, errors: string[] }
	 */
	public function import_episodes( array $guids, array $feed_config ): array {
		$result = [ 'imported' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => [] ];

		try {
			$all_episodes = $this->parser->parse( $feed_config['url'] );
		} catch ( RuntimeException $e ) {
			$result['errors'][] = $e->getMessage();
			return $result;
		}

		// Index episodes by GUID for quick lookup.
		$episode_map = [];
		foreach ( $all_episodes as $ep ) {
			if ( ! empty( $ep['guid'] ) ) {
				$episode_map[ $ep['guid'] ] = $ep;
			}
		}

		foreach ( $guids as $guid ) {
			if ( ! isset( $episode_map[ $guid ] ) ) {
				$result['skipped']++;
				continue;
			}

			$episode     = $episode_map[ $guid ];
			$existing_id = $this->find_existing_post( $guid, $feed_config['id'] );

			if ( $existing_id > 0 && empty( $feed_config['update_existing'] ) ) {
				$result['skipped']++;
				continue;
			}

			$post_id = $this->creator->create_or_update( $episode, $feed_config, $existing_id );

			if ( is_wp_error( $post_id ) ) {
				$result['errors'][] = sprintf(
					/* translators: 1: episode title, 2: error message */
					__( 'Fehler bei "%1$s": %2$s', 'podigee-rss-importer' ),
					$episode['title'],
					$post_id->get_error_message()
				);
			} elseif ( $existing_id > 0 ) {
				$result['updated']++;
			} else {
				$result['imported']++;
			}
		}

		$this->feed_manager->set_last_run( $feed_config['id'] );
		return $result;
	}

	/**
	 * Import all episodes that have not yet been imported (for cron runs).
	 *
	 * @param array $feed_config Feed configuration.
	 * @return array Same result shape as import_episodes().
	 */
	public function import_all_new( array $feed_config ): array {
		$result = [ 'imported' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => [] ];

		try {
			$all_episodes = $this->parser->parse( $feed_config['url'] );
		} catch ( RuntimeException $e ) {
			$result['errors'][] = $e->getMessage();
			return $result;
		}

		foreach ( $all_episodes as $episode ) {
			if ( empty( $episode['guid'] ) ) {
				$result['skipped']++;
				continue;
			}

			$existing_id = $this->find_existing_post( $episode['guid'], $feed_config['id'] );

			if ( $existing_id > 0 && empty( $feed_config['update_existing'] ) ) {
				$result['skipped']++;
				continue;
			}

			$post_id = $this->creator->create_or_update( $episode, $feed_config, $existing_id );

			if ( is_wp_error( $post_id ) ) {
				$result['errors'][] = sprintf(
					/* translators: 1: episode title, 2: error message */
					__( 'Fehler bei "%1$s": %2$s', 'podigee-rss-importer' ),
					$episode['title'],
					$post_id->get_error_message()
				);
			} elseif ( $existing_id > 0 ) {
				$result['updated']++;
			} else {
				$result['imported']++;
			}
		}

		$this->feed_manager->set_last_run( $feed_config['id'] );
		return $result;
	}

	/**
	 * Fetch parsed episodes from a feed URL (for the admin preview list).
	 *
	 * Each episode is enriched with `is_imported` (bool) and `existing_post_id` (int).
	 *
	 * @param string $feed_id  Feed UUID.
	 * @return array{ episodes: array, error: string }
	 */
	public function get_episodes_with_status( string $feed_id ): array {
		$feed = $this->feed_manager->get( $feed_id );
		if ( ! $feed ) {
			return [ 'episodes' => [], 'error' => __( 'Feed nicht gefunden.', 'podigee-rss-importer' ) ];
		}

		try {
			$episodes = $this->parser->parse( $feed['url'] );
		} catch ( RuntimeException $e ) {
			return [ 'episodes' => [], 'error' => $e->getMessage() ];
		}

		foreach ( $episodes as &$ep ) {
			$existing_id         = $this->find_existing_post( $ep['guid'], $feed_id );
			$ep['is_imported']   = $existing_id > 0;
			$ep['existing_post_id'] = $existing_id;
		}
		unset( $ep );

		return [ 'episodes' => $episodes, 'error' => '' ];
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Find an existing post by GUID and feed ID.
	 *
	 * @param string $guid    Episode GUID.
	 * @param string $feed_id Feed UUID.
	 * @return int Post ID or 0 if not found.
	 */
	private function find_existing_post( string $guid, string $feed_id ): int {
		$query = new WP_Query( [
			'post_type'      => 'any',
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'meta_query'     => [
				'relation' => 'AND',
				[
					'key'   => '_podigee_episode_guid',
					'value' => $guid,
				],
				[
					'key'   => '_podigee_feed_id',
					'value' => $feed_id,
				],
			],
		] );

		$ids = $query->posts;
		return ! empty( $ids ) ? (int) $ids[0] : 0;
	}
}
