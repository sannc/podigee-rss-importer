<?php
/**
 * Feed Manager – CRUD for feed configurations stored in wp_options.
 *
 * @package Podigee_RSS_Importer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Podigee_Feed_Manager {

	const OPTION_KEY = 'podigee_rss_feeds';

	/**
	 * Return all configured feeds.
	 *
	 * @return array<int, array>
	 */
	public function get_all(): array {
		$feeds = get_option( self::OPTION_KEY, [] );
		return is_array( $feeds ) ? $feeds : [];
	}

	/**
	 * Return a single feed by ID.
	 *
	 * @param string $id Feed UUID.
	 * @return array|null Feed config or null if not found.
	 */
	public function get( string $id ): ?array {
		foreach ( $this->get_all() as $feed ) {
			if ( isset( $feed['id'] ) && $feed['id'] === $id ) {
				return $feed;
			}
		}
		return null;
	}

	/**
	 * Save (create or update) a feed.
	 *
	 * @param array $data Feed data. If 'id' is absent a new UUID is generated.
	 * @return array The saved feed config (with 'id').
	 */
	public function save( array $data ): array {
		$feeds = $this->get_all();

		// Sanitize and apply defaults.
		$feed = $this->sanitize( $data );

		if ( empty( $feed['id'] ) ) {
			$feed['id'] = $this->generate_uuid();
			$feeds[]    = $feed;
		} else {
			$updated = false;
			foreach ( $feeds as $index => $existing ) {
				if ( $existing['id'] === $feed['id'] ) {
					// Preserve ignored_guids if not explicitly provided in the incoming data.
					if ( ! isset( $data['ignored_guids'] ) && ! empty( $existing['ignored_guids'] ) ) {
						$feed['ignored_guids'] = $existing['ignored_guids'];
					}
					$feeds[ $index ] = $feed;
					$updated         = true;
					break;
				}
			}
			if ( ! $updated ) {
				$feeds[] = $feed;
			}
		}

		update_option( self::OPTION_KEY, $feeds, false );
		return $feed;
	}

	/**
	 * Add one or more GUIDs to the ignore list of a feed.
	 *
	 * @param string   $feed_id Feed UUID.
	 * @param string[] $guids   GUIDs to ignore.
	 */
	public function ignore_episodes( string $feed_id, array $guids ): void {
		$feeds = $this->get_all();
		foreach ( $feeds as &$feed ) {
			if ( $feed['id'] === $feed_id ) {
				$existing              = (array) ( $feed['ignored_guids'] ?? [] );
				$feed['ignored_guids'] = array_values( array_unique( array_merge( $existing, $guids ) ) );
				break;
			}
		}
		unset( $feed );
		update_option( self::OPTION_KEY, $feeds, false );
	}

	/**
	 * Remove one or more GUIDs from the ignore list of a feed.
	 *
	 * @param string   $feed_id Feed UUID.
	 * @param string[] $guids   GUIDs to un-ignore.
	 */
	public function unignore_episodes( string $feed_id, array $guids ): void {
		$feeds = $this->get_all();
		foreach ( $feeds as &$feed ) {
			if ( $feed['id'] === $feed_id ) {
				$existing              = (array) ( $feed['ignored_guids'] ?? [] );
				$feed['ignored_guids'] = array_values( array_diff( $existing, $guids ) );
				break;
			}
		}
		unset( $feed );
		update_option( self::OPTION_KEY, $feeds, false );
	}

	/**
	 * Return the list of ignored GUIDs for a feed.
	 *
	 * @param string $feed_id Feed UUID.
	 * @return string[]
	 */
	public function get_ignored_guids( string $feed_id ): array {
		$feed = $this->get( $feed_id );
		return $feed ? (array) ( $feed['ignored_guids'] ?? [] ) : [];
	}

	/**
	 * Delete a feed by ID.
	 *
	 * @param string $id Feed UUID.
	 * @return bool True on success.
	 */
	public function delete( string $id ): bool {
		$feeds = $this->get_all();
		$new   = array_filter( $feeds, fn( $f ) => $f['id'] !== $id );

		if ( count( $new ) === count( $feeds ) ) {
			return false; // Not found.
		}

		update_option( self::OPTION_KEY, array_values( $new ), false );
		return true;
	}

	/**
	 * Update the last-run timestamp for a feed.
	 *
	 * @param string $id   Feed UUID.
	 * @param int    $time Unix timestamp (default: now).
	 */
	public function set_last_run( string $id, int $time = 0 ): void {
		$feed = $this->get( $id );
		if ( $feed ) {
			$feed['last_run'] = $time ?: time();
			$this->save( $feed );
		}
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Sanitize and apply defaults to a feed config array.
	 */
	private function sanitize( array $data ): array {
		$allowed_schedules  = [ 'never', 'hourly', 'twicedaily', 'daily', 'weekly' ];
		$allowed_post_types = get_post_types( [ 'public' => true ] );
		$allowed_statuses   = [ 'publish', 'draft' ];

		$post_type = sanitize_key( $data['post_type'] ?? 'post' );
		if ( ! in_array( $post_type, $allowed_post_types, true ) ) {
			$post_type = 'post';
		}

		$post_status = sanitize_key( $data['post_status'] ?? 'draft' );
		if ( ! in_array( $post_status, $allowed_statuses, true ) ) {
			$post_status = 'draft';
		}

		$cron_schedule = sanitize_key( $data['cron_schedule'] ?? 'never' );
		if ( ! in_array( $cron_schedule, $allowed_schedules, true ) ) {
			$cron_schedule = 'never';
		}

		$category_ids = [];
		if ( ! empty( $data['category_ids'] ) && is_array( $data['category_ids'] ) ) {
			$category_ids = array_map( 'absint', $data['category_ids'] );
		}

		$tag_ids = [];
		if ( ! empty( $data['tag_ids'] ) && is_array( $data['tag_ids'] ) ) {
			$tag_ids = array_map( 'absint', $data['tag_ids'] );
		}

		$ignored_guids = [];
		if ( ! empty( $data['ignored_guids'] ) && is_array( $data['ignored_guids'] ) ) {
			$ignored_guids = array_values( array_map( 'sanitize_text_field', $data['ignored_guids'] ) );
		}

		return [
			'id'               => sanitize_text_field( $data['id'] ?? '' ),
			'name'             => sanitize_text_field( $data['name'] ?? '' ),
			'url'              => esc_url_raw( $data['url'] ?? '' ),
			'post_type'        => $post_type,
			'post_status'      => $post_status,
			'use_episode_date' => ! empty( $data['use_episode_date'] ),
			'update_existing'  => ! empty( $data['update_existing'] ),
			'cron_schedule'    => $cron_schedule,
			'category_ids'     => $category_ids,
			'tag_ids'          => $tag_ids,
			'last_run'         => absint( $data['last_run'] ?? 0 ),
			'ignored_guids'    => $ignored_guids,
		];
	}

	/**
	 * Generate a v4-like UUID.
	 */
	private function generate_uuid(): string {
		$data    = random_bytes( 16 );
		$data[6] = chr( ( ord( $data[6] ) & 0x0f ) | 0x40 );
		$data[8] = chr( ( ord( $data[8] ) & 0x3f ) | 0x80 );
		return vsprintf( '%s%s-%s-%s-%s-%s%s%s', str_split( bin2hex( $data ), 4 ) );
	}
}
