<?php
/**
 * Cron Manager – handles WP-Cron scheduling per feed.
 *
 * @package Podigee_RSS_Importer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Podigee_Cron_Manager {

	const HOOK_PREFIX = 'podigee_cron_import_';

	public function __construct() {
		// Register custom 'weekly' schedule (not available in WP Core).
		add_filter( 'cron_schedules', [ $this, 'add_weekly_schedule' ] );

		// Register the dynamic cron hook for any feed (wildcard via action parsing).
		add_action( 'init', [ $this, 'register_cron_hooks' ] );
	}

	/**
	 * Add a 'weekly' schedule to WP Cron.
	 *
	 * @param array $schedules Existing cron schedules.
	 * @return array Modified schedules.
	 */
	public function add_weekly_schedule( array $schedules ): array {
		if ( ! isset( $schedules['weekly'] ) ) {
			$schedules['weekly'] = [
				'interval' => WEEK_IN_SECONDS,
				'display'  => __( 'Einmal pro Woche', 'podigee-rss-importer' ),
			];
		}
		return $schedules;
	}

	/**
	 * Register cron action callbacks for all configured feeds.
	 * Called on 'init' so WP-Cron can trigger them correctly.
	 */
	public function register_cron_hooks(): void {
		$feed_manager = new Podigee_Feed_Manager();
		foreach ( $feed_manager->get_all() as $feed ) {
			if ( empty( $feed['id'] ) ) {
				continue;
			}
			$hook = self::HOOK_PREFIX . $feed['id'];
			add_action( $hook, function () use ( $feed ) {
				$importer = new Podigee_Importer();
				$importer->import_all_new( $feed );
			} );
		}
	}

	/**
	 * Schedule (or reschedule) a cron event for a feed.
	 *
	 * @param string $feed_id  Feed UUID.
	 * @param string $schedule WP cron schedule name (or 'never').
	 */
	public function schedule_feed( string $feed_id, string $schedule ): void {
		$hook = self::HOOK_PREFIX . $feed_id;

		// Always clear existing schedule first.
		$this->unschedule_feed( $feed_id );

		if ( 'never' === $schedule ) {
			return;
		}

		if ( ! wp_next_scheduled( $hook ) ) {
			wp_schedule_event( time(), $schedule, $hook );
		}
	}

	/**
	 * Remove a cron event for a feed.
	 *
	 * @param string $feed_id Feed UUID.
	 */
	public function unschedule_feed( string $feed_id ): void {
		$hook      = self::HOOK_PREFIX . $feed_id;
		$timestamp = wp_next_scheduled( $hook );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, $hook );
		}
	}

	/**
	 * Reschedule all feeds (used after activation).
	 */
	public function reschedule_all(): void {
		$feed_manager = new Podigee_Feed_Manager();
		foreach ( $feed_manager->get_all() as $feed ) {
			if ( empty( $feed['id'] ) ) {
				continue;
			}
			$schedule = $feed['cron_schedule'] ?? 'never';
			$this->schedule_feed( $feed['id'], $schedule );
		}
	}

	/**
	 * Unschedule all feeds (used on deactivation).
	 */
	public function unschedule_all(): void {
		$feed_manager = new Podigee_Feed_Manager();
		foreach ( $feed_manager->get_all() as $feed ) {
			if ( ! empty( $feed['id'] ) ) {
				$this->unschedule_feed( $feed['id'] );
			}
		}
	}
}
