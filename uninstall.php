<?php
/**
 * Uninstall – clean up all plugin data when the plugin is deleted via WP admin.
 *
 * @package Podigee_RSS_Importer
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// --- Remove plugin option ---
delete_option( 'podigee_rss_feeds' );

// --- Remove all Podigee post meta ---
global $wpdb;
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- uninstall cleanup, no cache needed.
$wpdb->query(
	"DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_podigee\_%'"
);

// --- Clear all Podigee cron events ---
$crons = _get_cron_array();
if ( is_array( $crons ) ) {
	foreach ( $crons as $timestamp => $hooks ) {
		foreach ( $hooks as $hook => $events ) {
			if ( str_starts_with( $hook, 'podigee_cron_import_' ) ) {
				foreach ( $events as $key => $event ) {
					wp_unschedule_event( $timestamp, $hook, $event['args'] );
				}
			}
		}
	}
}
