/**
 * Podigee RSS Importer – Admin JS
 *
 * Handles:
 *  - Loading episode list via AJAX
 *  - Episode selection (all / none / new only)
 *  - Import via AJAX with status display
 */
/* global jQuery, podigeeAjax */
( function ( $ ) {
	'use strict';

	const i18n   = podigeeAjax.i18n;
	const $wrap  = $( '#podigee-episodes-wrapper' );
	const $tbody = $( '#podigee-episodes-tbody' );
	const $result = $( '#podigee-import-result' );
	const $spinner = $( '.podigee-import-spinner' );
	const $loadingIndicator = $( '#podigee-loading-indicator' );

	// Populated after fetch.
	let currentEpisodes = [];

	// =========================================================================
	// Load Episodes
	// =========================================================================

	$( '#podigee-load-episodes' ).on( 'click', function () {
		const feedId = $( '#podigee-feed-select' ).val();
		if ( ! feedId ) {
			return;
		}

		$wrap.hide();
		$result.hide();
		$loadingIndicator.show();
		$tbody.empty();

		$.ajax( {
			url: podigeeAjax.ajaxUrl,
			method: 'POST',
			data: {
				action: 'podigee_fetch_episodes',
				nonce:   podigeeAjax.nonceFetch,
				feed_id: feedId,
			},
			success: function ( response ) {
				$loadingIndicator.hide();
				if ( ! response.success ) {
					showError( response.data || i18n.errorFetch );
					return;
				}

				currentEpisodes = response.data || [];
				renderEpisodes( currentEpisodes );
				$wrap.show();
			},
			error: function () {
				$loadingIndicator.hide();
				showError( i18n.errorFetch );
			},
		} );
	} );

	// =========================================================================
	// Render Episodes Table
	// =========================================================================

	function renderEpisodes( episodes ) {
		$tbody.empty();

		if ( ! episodes || episodes.length === 0 ) {
			$tbody.append( '<tr><td colspan="5">' + escHtml( i18n.noEpisodes ) + '</td></tr>' );
			return;
		}

		episodes.forEach( function ( ep ) {
			const isImported = !! ep.is_imported;
			const statusLabel = isImported ? i18n.imported : i18n.new;
			const statusClass = isImported ? 'imported' : 'new';
			const date        = ep.pub_date
				? new Date( ep.pub_date * 1000 ).toLocaleDateString( document.documentElement.lang || 'de' )
				: '–';
			const rowClass = isImported ? 'is-imported' : '';

			const row = $(
				'<tr class="' + escHtml( rowClass ) + '">' +
					'<td class="check-column">' +
						'<input type="checkbox" class="podigee-ep-check" ' +
							'data-guid="' + escAttr( ep.guid ) + '" ' +
							'data-imported="' + ( isImported ? '1' : '0' ) + '">' +
					'</td>' +
					'<td>' + escHtml( ep.episode_number || '–' ) + '</td>' +
					'<td>' + escHtml( ep.title ) + '</td>' +
					'<td>' + escHtml( date ) + '</td>' +
					'<td>' + renderStatusBadge( isImported, statusClass, statusLabel, ep.existing_post_id ) + '</td>' +
				'</tr>'
			);

			$tbody.append( row );
		} );
	}

	// =========================================================================
	// Bulk Selection Buttons
	// =========================================================================

	$( '#podigee-select-all' ).on( 'click', function () {
		$tbody.find( '.podigee-ep-check' ).prop( 'checked', true );
	} );

	$( '#podigee-select-none' ).on( 'click', function () {
		$tbody.find( '.podigee-ep-check' ).prop( 'checked', false );
	} );

	$( '#podigee-select-new' ).on( 'click', function () {
		$tbody.find( '.podigee-ep-check' ).each( function () {
			$( this ).prop( 'checked', $( this ).data( 'imported' ) === 0 || $( this ).data( 'imported' ) === '0' );
		} );
	} );

	// =========================================================================
	// Import
	// =========================================================================

	$( document ).on( 'click', '.podigee-import-btn', function () {
		const feedId = $( '#podigee-feed-select' ).val();
		if ( ! feedId ) {
			return;
		}

		const guids = [];
		$tbody.find( '.podigee-ep-check:checked' ).each( function () {
			guids.push( $( this ).data( 'guid' ) );
		} );

		if ( guids.length === 0 ) {
			alert( i18n.noSelection );
			return;
		}

		$result.hide();
		$spinner.css( 'visibility', 'visible' );
		$( '.podigee-import-btn' ).prop( 'disabled', true );

		$.ajax( {
			url: podigeeAjax.ajaxUrl,
			method: 'POST',
			data: {
				action:  'podigee_import_episodes',
				nonce:   podigeeAjax.nonceImport,
				feed_id: feedId,
				guids:   guids,
			},
			success: function ( response ) {
				$spinner.css( 'visibility', 'hidden' );
				$( '.podigee-import-btn' ).prop( 'disabled', false );

				if ( ! response.success ) {
					showError( response.data || i18n.errorImport );
					return;
				}

				showImportResult( response.data );

				// Reload episode list to update "already imported" status.
				$( '#podigee-load-episodes' ).trigger( 'click' );
			},
			error: function () {
				$spinner.css( 'visibility', 'hidden' );
				$( '.podigee-import-btn' ).prop( 'disabled', false );
				showError( i18n.errorImport );
			},
		} );
	} );

	// =========================================================================
	// Result display helpers
	// =========================================================================

	function renderStatusBadge( isImported, statusClass, statusLabel, postId ) {
		const badge = '<span class="podigee-badge podigee-badge--' + escAttr( statusClass ) + '">' + escHtml( statusLabel ) + '</span>';
		if ( isImported && postId ) {
			const url = podigeeAjax.editPostUrl + '?post=' + parseInt( postId, 10 ) + '&action=edit';
			return '<a href="' + url + '" target="_blank" rel="noopener" style="text-decoration:none;">' + badge + '</a>';
		}
		return badge;
	}

	function showImportResult( data ) {
		let html = '<div class="notice notice-success"><p>';
		html += '<strong>' + escHtml( wp.i18n ? wp.i18n.__( 'Import abgeschlossen', 'podigee-rss-importer' ) : 'Import abgeschlossen' ) + '</strong>';
		html += '</p><ul>';
		html += '<li>' + data.imported + ' ' + escHtml( 'importiert' ) + '</li>';
		html += '<li>' + data.updated  + ' ' + escHtml( 'aktualisiert' ) + '</li>';
		html += '<li>' + data.skipped  + ' ' + escHtml( 'übersprungen' ) + '</li>';
		html += '</ul>';

		if ( data.errors && data.errors.length > 0 ) {
			html += '<p><strong>' + escHtml( 'Fehler:' ) + '</strong></p><ul>';
			data.errors.forEach( function ( err ) {
				html += '<li>' + escHtml( err ) + '</li>';
			} );
			html += '</ul>';
		}

		html += '</div>';
		$result.html( html ).show();
	}

	function showError( message ) {
		$result
			.html( '<div class="notice notice-error"><p>' + escHtml( message ) + '</p></div>' )
			.show();
	}

	// =========================================================================
	// Security helpers
	// =========================================================================

	function escHtml( str ) {
		return String( str )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' )
			.replace( /'/g, '&#039;' );
	}

	function escAttr( str ) {
		return escHtml( str );
	}

	// =========================================================================
	// Auto-load if feed pre-selected via URL param
	// =========================================================================

	$( function () {
		if ( $( '#podigee-feed-select' ).val() ) {
			$( '#podigee-load-episodes' ).trigger( 'click' );
		}
	} );

} )( jQuery );
