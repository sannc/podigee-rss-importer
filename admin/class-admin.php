<?php
/**
 * Admin – registers menus, handles form submissions and AJAX.
 *
 * @package Podigee_RSS_Importer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Podigee_Admin {

	private Podigee_Feed_Manager $feed_manager;
	private Podigee_Cron_Manager $cron_manager;

	public function __construct() {
		$this->feed_manager = new Podigee_Feed_Manager();
		$this->cron_manager = new Podigee_Cron_Manager();

		add_action( 'admin_menu', [ $this, 'register_menus' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );

		// Form submissions.
		add_action( 'admin_post_podigee_save_feed', [ $this, 'handle_save_feed' ] );
		add_action( 'admin_post_podigee_delete_feed', [ $this, 'handle_delete_feed' ] );

		// AJAX.
		add_action( 'wp_ajax_podigee_fetch_episodes',    [ $this, 'ajax_fetch_episodes' ] );
		add_action( 'wp_ajax_podigee_import_episodes',   [ $this, 'ajax_import_episodes' ] );
		add_action( 'wp_ajax_podigee_ignore_episodes',   [ $this, 'ajax_ignore_episodes' ] );
		add_action( 'wp_ajax_podigee_unignore_episodes', [ $this, 'ajax_unignore_episodes' ] );
	}

	// =========================================================================
	// Admin Menus
	// =========================================================================

	public function register_menus(): void {
		add_menu_page(
			__( 'Podigee Importer', 'podigee-rss-importer' ),
			__( 'Podigee Importer', 'podigee-rss-importer' ),
			'manage_options',
			'podigee-feeds',
			[ $this, 'page_feeds' ],
			'dashicons-rss',
			80
		);

		add_submenu_page(
			'podigee-feeds',
			__( 'Feeds', 'podigee-rss-importer' ),
			__( 'Feeds', 'podigee-rss-importer' ),
			'manage_options',
			'podigee-feeds',
			[ $this, 'page_feeds' ]
		);

		add_submenu_page(
			'podigee-feeds',
			__( 'Episode importieren', 'podigee-rss-importer' ),
			__( 'Importieren', 'podigee-rss-importer' ),
			'manage_options',
			'podigee-import',
			[ $this, 'page_import' ]
		);
	}

	// =========================================================================
	// Asset Enqueuing
	// =========================================================================

	public function enqueue_assets( string $hook ): void {
		$podigee_pages = [
			'toplevel_page_podigee-feeds',
			'podigee-importer_page_podigee-import',
			'podigee-importer_page_podigee-feeds',
		];

		if ( ! in_array( $hook, $podigee_pages, true )
			&& ! str_contains( $hook, 'podigee' ) ) {
			return;
		}

		wp_enqueue_style(
			'podigee-admin',
			PODIGEE_RSS_PLUGIN_URL . 'admin/assets/admin.css',
			[],
			PODIGEE_RSS_VERSION
		);

		wp_enqueue_script(
			'podigee-admin',
			PODIGEE_RSS_PLUGIN_URL . 'admin/assets/admin.js',
			[ 'jquery' ],
			PODIGEE_RSS_VERSION,
			true
		);

		wp_localize_script( 'podigee-admin', 'podigeeAjax', [
			'ajaxUrl'             => admin_url( 'admin-ajax.php' ),
			'editPostUrl'         => admin_url( 'post.php' ),
			'nonceFetch'          => wp_create_nonce( 'podigee_fetch_episodes' ),
			'nonceImport'         => wp_create_nonce( 'podigee_import_episodes' ),
			'nonceIgnore'         => wp_create_nonce( 'podigee_ignore_episodes' ),
			'nonceUnignore'       => wp_create_nonce( 'podigee_unignore_episodes' ),
			'i18n'                => [
				'loading'         => __( 'Episoden werden geladen…', 'podigee-rss-importer' ),
				'importing'       => __( 'Importiere…', 'podigee-rss-importer' ),
				'selectAll'       => __( 'Alle auswählen', 'podigee-rss-importer' ),
				'selectNone'      => __( 'Keine auswählen', 'podigee-rss-importer' ),
				'selectNew'       => __( 'Nur neue auswählen', 'podigee-rss-importer' ),
				'imported'        => __( 'importiert', 'podigee-rss-importer' ),
				'ignored'         => __( 'ignoriert', 'podigee-rss-importer' ),
				'new'             => __( 'neu', 'podigee-rss-importer' ),
				'ignore'          => __( 'Ignorieren', 'podigee-rss-importer' ),
				'unignore'        => __( 'Reaktivieren', 'podigee-rss-importer' ),
				'importSingle'    => __( 'Importieren', 'podigee-rss-importer' ),
				'noEpisodes'      => __( 'Keine Episoden gefunden.', 'podigee-rss-importer' ),
				'noSelection'     => __( 'Bitte mindestens eine Episode auswählen.', 'podigee-rss-importer' ),
				'errorFetch'      => __( 'Fehler beim Laden der Episoden.', 'podigee-rss-importer' ),
				'errorImport'     => __( 'Fehler beim Importieren.', 'podigee-rss-importer' ),
				'errorIgnore'     => __( 'Fehler beim Ignorieren.', 'podigee-rss-importer' ),
				'resultDone'      => __( 'Import abgeschlossen', 'podigee-rss-importer' ),
				'resultImported'  => __( 'importiert', 'podigee-rss-importer' ),
				'resultUpdated'   => __( 'aktualisiert', 'podigee-rss-importer' ),
				'resultSkipped'   => __( 'übersprungen', 'podigee-rss-importer' ),
				'resultErrors'    => __( 'Fehler:', 'podigee-rss-importer' ),
			],
		] );
	}

	// =========================================================================
	// Page Renderers
	// =========================================================================

	public function page_feeds(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Zugriff verweigert.', 'podigee-rss-importer' ) );
		}

		// Show edit form if ?action=edit or ?action=new.
		$action  = sanitize_key( $_GET['action'] ?? '' );
		$feed_id = sanitize_text_field( $_GET['feed_id'] ?? '' );

		if ( in_array( $action, [ 'edit', 'new' ], true ) ) {
			$feed = $feed_id ? $this->feed_manager->get( $feed_id ) : null;
			include PODIGEE_RSS_PLUGIN_DIR . 'admin/views/feed-edit.php';
		} else {
			$feeds = $this->feed_manager->get_all();
			include PODIGEE_RSS_PLUGIN_DIR . 'admin/views/feeds-list.php';
		}
	}

	public function page_import(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Zugriff verweigert.', 'podigee-rss-importer' ) );
		}

		$feeds = $this->feed_manager->get_all();
		include PODIGEE_RSS_PLUGIN_DIR . 'admin/views/import.php';
	}

	// =========================================================================
	// Form Handlers
	// =========================================================================

	public function handle_save_feed(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Zugriff verweigert.', 'podigee-rss-importer' ) );
		}

		check_admin_referer( 'podigee_save_feed', 'podigee_nonce' );

		$data = [
			'id'               => sanitize_text_field( $_POST['feed_id'] ?? '' ),
			'name'             => sanitize_text_field( $_POST['name'] ?? '' ),
			'url'              => esc_url_raw( $_POST['url'] ?? '' ),
			'post_type'        => sanitize_key( $_POST['post_type'] ?? 'post' ),
			'post_status'      => sanitize_key( $_POST['post_status'] ?? 'draft' ),
			'use_episode_date' => ! empty( $_POST['use_episode_date'] ),
			'update_existing'  => ! empty( $_POST['update_existing'] ),
			'cron_schedule'    => sanitize_key( $_POST['cron_schedule'] ?? 'never' ),
			'category_ids'     => array_map( 'absint', (array) ( $_POST['category_ids'] ?? [] ) ),
			'tag_ids'          => array_map( 'absint', (array) ( $_POST['tag_ids'] ?? [] ) ),
		];

		$saved = $this->feed_manager->save( $data );
		$this->cron_manager->schedule_feed( $saved['id'], $saved['cron_schedule'] );

		wp_safe_redirect( add_query_arg(
			[ 'page' => 'podigee-feeds', 'saved' => '1' ],
			admin_url( 'admin.php' )
		) );
		exit;
	}

	public function handle_delete_feed(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Zugriff verweigert.', 'podigee-rss-importer' ) );
		}

		$feed_id = sanitize_text_field( $_POST['feed_id'] ?? '' );
		check_admin_referer( 'podigee_delete_feed_' . $feed_id, 'podigee_nonce' );

		$this->cron_manager->unschedule_feed( $feed_id );
		$this->feed_manager->delete( $feed_id );

		wp_safe_redirect( add_query_arg(
			[ 'page' => 'podigee-feeds', 'deleted' => '1' ],
			admin_url( 'admin.php' )
		) );
		exit;
	}

	// =========================================================================
	// AJAX Handlers
	// =========================================================================

	public function ajax_fetch_episodes(): void {
		check_ajax_referer( 'podigee_fetch_episodes', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Zugriff verweigert.', 'podigee-rss-importer' ), 403 );
		}

		$feed_id = sanitize_text_field( $_POST['feed_id'] ?? '' );
		if ( empty( $feed_id ) ) {
			wp_send_json_error( __( 'Keine Feed-ID angegeben.', 'podigee-rss-importer' ), 400 );
		}

		$importer = new Podigee_Importer();
		$result   = $importer->get_episodes_with_status( $feed_id );

		if ( ! empty( $result['error'] ) ) {
			wp_send_json_error( $result['error'] );
		}

		wp_send_json_success( $result['episodes'] );
	}

	public function ajax_import_episodes(): void {
		check_ajax_referer( 'podigee_import_episodes', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Zugriff verweigert.', 'podigee-rss-importer' ), 403 );
		}

		$feed_id = sanitize_text_field( $_POST['feed_id'] ?? '' );
		$guids   = array_map( 'sanitize_text_field', (array) ( $_POST['guids'] ?? [] ) );

		if ( empty( $feed_id ) ) {
			wp_send_json_error( __( 'Keine Feed-ID angegeben.', 'podigee-rss-importer' ), 400 );
		}

		$feed = $this->feed_manager->get( $feed_id );
		if ( ! $feed ) {
			wp_send_json_error( __( 'Feed nicht gefunden.', 'podigee-rss-importer' ), 404 );
		}

		$importer = new Podigee_Importer();
		$result   = $importer->import_episodes( $guids, $feed );

		wp_send_json_success( $result );
	}

	public function ajax_ignore_episodes(): void {
		check_ajax_referer( 'podigee_ignore_episodes', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Zugriff verweigert.', 'podigee-rss-importer' ), 403 );
		}

		$feed_id = sanitize_text_field( $_POST['feed_id'] ?? '' );
		$guids   = array_map( 'sanitize_text_field', (array) ( $_POST['guids'] ?? [] ) );

		if ( empty( $feed_id ) || empty( $guids ) ) {
			wp_send_json_error( __( 'Ungültige Parameter.', 'podigee-rss-importer' ), 400 );
		}

		$this->feed_manager->ignore_episodes( $feed_id, $guids );
		wp_send_json_success();
	}

	public function ajax_unignore_episodes(): void {
		check_ajax_referer( 'podigee_unignore_episodes', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Zugriff verweigert.', 'podigee-rss-importer' ), 403 );
		}

		$feed_id = sanitize_text_field( $_POST['feed_id'] ?? '' );
		$guids   = array_map( 'sanitize_text_field', (array) ( $_POST['guids'] ?? [] ) );

		if ( empty( $feed_id ) || empty( $guids ) ) {
			wp_send_json_error( __( 'Ungültige Parameter.', 'podigee-rss-importer' ), 400 );
		}

		$this->feed_manager->unignore_episodes( $feed_id, $guids );
		wp_send_json_success();
	}
}
