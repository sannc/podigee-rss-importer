<?php
/**
 * Plugin Name: Podigee RSS Importer
 * Plugin URI:  https://github.com/podigee/podigee-rss-importer
 * Description: Importiert Podigee-Podcast-RSS-Feeds als WordPress-Posts. Unterstützt mehrere Feeds, konfigurierbar pro Feed.
 * Version:     0.1
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Author:      Podigee
 * License:     GPL-2.0+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: podigee-rss-importer
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PODIGEE_RSS_VERSION', '0.1' );
define( 'PODIGEE_RSS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'PODIGEE_RSS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'PODIGEE_RSS_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Autoloader for plugin classes.
 */
spl_autoload_register( function ( string $class_name ) {
	$map = [
		'Podigee_Feed_Manager'  => PODIGEE_RSS_PLUGIN_DIR . 'includes/class-feed-manager.php',
		'Podigee_RSS_Parser'    => PODIGEE_RSS_PLUGIN_DIR . 'includes/class-rss-parser.php',
		'Podigee_Importer'      => PODIGEE_RSS_PLUGIN_DIR . 'includes/class-importer.php',
		'Podigee_Post_Creator'  => PODIGEE_RSS_PLUGIN_DIR . 'includes/class-post-creator.php',
		'Podigee_Cron_Manager'  => PODIGEE_RSS_PLUGIN_DIR . 'includes/class-cron-manager.php',
		'Podigee_Admin'         => PODIGEE_RSS_PLUGIN_DIR . 'admin/class-admin.php',
	];

	if ( isset( $map[ $class_name ] ) ) {
		require_once $map[ $class_name ];
	}
} );

/**
 * Plugin activation hook.
 */
register_activation_hook( __FILE__, function () {
	// Ensure default option exists.
	if ( false === get_option( 'podigee_rss_feeds' ) ) {
		add_option( 'podigee_rss_feeds', [] );
	}

	// Register cron schedules for existing feeds after activation.
	add_action( 'init', function () {
		$cron = new Podigee_Cron_Manager();
		$cron->reschedule_all();
	} );
} );

/**
 * Plugin deactivation hook.
 */
register_deactivation_hook( __FILE__, function () {
	$cron = new Podigee_Cron_Manager();
	$cron->unschedule_all();
} );

/**
 * Bootstrap the plugin.
 */
add_action( 'plugins_loaded', function () {
	load_plugin_textdomain(
		'podigee-rss-importer',
		false,
		dirname( PODIGEE_RSS_PLUGIN_BASENAME ) . '/languages/'
	);

	// Boot cron manager (registers hooks).
	new Podigee_Cron_Manager();

	// Enqueue frontend player: Plyr.js (bundled) + wrapper styles.
	add_action( 'wp_enqueue_scripts', function () {
		wp_enqueue_style(
			'plyr',
			PODIGEE_RSS_PLUGIN_URL . 'public/assets/plyr/plyr.css',
			[],
			'3.8.4'
		);
		wp_enqueue_script(
			'plyr',
			PODIGEE_RSS_PLUGIN_URL . 'public/assets/plyr/plyr.min.js',
			[],
			'3.8.4',
			true
		);
		wp_enqueue_style(
			'podigee-player',
			PODIGEE_RSS_PLUGIN_URL . 'public/assets/player.css',
			[ 'plyr' ],
			PODIGEE_RSS_VERSION
		);
		// Init Plyr on all episode player elements.
		// Script runs in the footer after the DOM is built – no DOMContentLoaded needed.
		wp_add_inline_script( 'plyr', "
			(function() {
				var els = document.querySelectorAll('.podigee-episode-player audio');
				els.forEach(function(el) {
					new Plyr(el, { controls: ['play','progress','current-time','duration','mute','volume'] });
				});
			})();
		" );
	} );

	// Wrap our core/audio blocks in a minimal, customisable player card.
	add_filter( 'render_block', function ( string $block_content, array $block ): string {
		if ( $block['blockName'] !== 'core/audio' ) {
			return $block_content;
		}
		$class = $block['attrs']['className'] ?? '';
		if ( ! str_contains( $class, 'podigee-episode-player' ) ) {
			return $block_content;
		}

		$post_id   = get_the_ID();
		$ep_number = $post_id ? get_post_meta( $post_id, '_podigee_episode_number', true ) : '';
		$season    = $post_id ? get_post_meta( $post_id, '_podigee_season_number', true )   : '';

		$badge = '';
		if ( $ep_number ) {
			$label = $season
				/* translators: %1$s: season number, %2$s: episode number */
				? sprintf( esc_html__( 'S%1$s E%2$s anhören', 'podigee-rss-importer' ), esc_html( $season ), esc_html( $ep_number ) )
				/* translators: %s: episode number */
				: sprintf( esc_html__( 'Episode %s anhören', 'podigee-rss-importer' ), esc_html( $ep_number ) );
			$badge = '<span class="podigee-player__badge">' . $label . '</span>';
		}

		$thumb = '';
		if ( $post_id && has_post_thumbnail( $post_id ) ) {
			$thumb = '<div class="podigee-player__thumb">'
				. get_the_post_thumbnail( $post_id, 'thumbnail', [ 'loading' => 'lazy' ] )
				. '</div>';
		}

		return sprintf(
			'<div class="podigee-player podigee-episode-player">%s<div class="podigee-player__body">%s<div class="podigee-player__controls">%s</div></div></div>',
			$thumb,
			$badge,
			$block_content
		);
	}, 10, 2 );

	// Boot admin.
	if ( is_admin() ) {
		new Podigee_Admin();
	}
} );
