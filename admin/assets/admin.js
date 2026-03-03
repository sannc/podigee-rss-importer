/**
 * Podigee RSS Importer – Admin JS
 *
 * Handles:
 *  - Loading episode list via AJAX
 *  - Episode selection (all / none / new only)
 *  - Import via AJAX with status display
 *  - Ignore / un-ignore episodes (single and bulk)
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
			$tbody.append( '<tr><td colspan="6">' + escHtml( i18n.noEpisodes ) + '</td></tr>' );
			return;
		}

		const showIgnored = $( '#podigee-show-ignored' ).is( ':checked' );

		episodes.forEach( function ( ep ) {
			const isImported = !! ep.is_imported;
			const isIgnored  = !! ep.is_ignored;

			let statusLabel, statusClass;
			if ( isIgnored ) {
				statusLabel = i18n.ignored;
				statusClass = 'ignored';
			} else if ( isImported ) {
				statusLabel = i18n.imported;
				statusClass = 'imported';
			} else {
				statusLabel = i18n.new;
				statusClass = 'new';
			}

			const date = ep.pub_date
				? new Date( ep.pub_date * 1000 ).toLocaleDateString( document.documentElement.lang || 'de' )
				: '–';

			let rowClass = '';
			if ( isIgnored ) {
				rowClass = 'is-ignored' + ( showIgnored ? '' : ' hidden' );
			} else if ( isImported ) {
				rowClass = 'is-imported';
			}

			let actionBtn = '';
			if ( isIgnored ) {
				actionBtn = '<button type="button" class="button-link podigee-unignore-btn" data-guid="' + escAttr( ep.guid ) + '">' + escHtml( i18n.unignore ) + '</button>';
			} else if ( isImported ) {
				actionBtn = '';
			} else {
				actionBtn =
					'<button type="button" class="button button-primary button-small podigee-import-single-btn" data-guid="' + escAttr( ep.guid ) + '">' + escHtml( i18n.importSingle || 'Importieren' ) + '</button>' +
					' <button type="button" class="button-link podigee-ignore-btn" data-guid="' + escAttr( ep.guid ) + '">' + escHtml( i18n.ignore ) + '</button>';
			}

			const row = $(
				'<tr class="' + escHtml( rowClass ) + '" data-guid="' + escAttr( ep.guid ) + '">' +
					'<td class="check-column">' +
						'<input type="checkbox" class="podigee-ep-check" ' +
							'data-guid="' + escAttr( ep.guid ) + '" ' +
							'data-imported="' + ( isImported ? '1' : '0' ) + '">' +
					'</td>' +
					'<td>' + escHtml( ep.episode_number || '–' ) + '</td>' +
					'<td>' + escHtml( ep.title ) + '</td>' +
					'<td>' + escHtml( date ) + '</td>' +
					'<td>' + renderStatusBadge( isImported, isIgnored, statusClass, statusLabel, ep.existing_post_id ) + '</td>' +
					'<td>' + actionBtn + '</td>' +
				'</tr>'
			);

			$tbody.append( row );
		} );
	}

	// =========================================================================
	// Toggle: show/hide ignored rows
	// =========================================================================

	$( document ).on( 'change', '#podigee-show-ignored', function () {
		const show = $( this ).is( ':checked' );
		$tbody.find( 'tr.is-ignored' ).toggleClass( 'hidden', ! show );
	} );

	// =========================================================================
	// Ignore / Un-ignore (single row)
	// =========================================================================

	$( document ).on( 'click', '.podigee-ignore-btn', function () {
		const guid   = $( this ).data( 'guid' );
		const feedId = $( '#podigee-feed-select' ).val();
		ignoreEpisodes( feedId, [ guid ] );
	} );

	$( document ).on( 'click', '.podigee-unignore-btn', function () {
		const guid   = $( this ).data( 'guid' );
		const feedId = $( '#podigee-feed-select' ).val();
		unignoreEpisodes( feedId, [ guid ] );
	} );

	// =========================================================================
	// Import single episode (row button)
	// =========================================================================

	$( document ).on( 'click', '.podigee-import-single-btn', function () {
		const $btn   = $( this );
		const guid   = $btn.data( 'guid' );
		const feedId = $( '#podigee-feed-select' ).val();
		if ( ! feedId ) {
			return;
		}

		$btn.prop( 'disabled', true ).text( i18n.importing );

		$.ajax( {
			url: podigeeAjax.ajaxUrl,
			method: 'POST',
			data: {
				action:  'podigee_import_episodes',
				nonce:   podigeeAjax.nonceImport,
				feed_id: feedId,
				guids:   [ guid ],
			},
			success: function ( response ) {
				if ( ! response.success ) {
					$btn.prop( 'disabled', false ).text( i18n.importSingle );
					showError( response.data || i18n.errorImport );
					return;
				}

				showImportResult( response.data );

				// Update local cache and re-render.
				const ep = currentEpisodes.find( function ( e ) { return e.guid === guid; } );
				if ( ep ) {
					ep.is_imported = true;
					ep.existing_post_id = response.data.post_ids ? response.data.post_ids[ guid ] : null;
				}
				renderEpisodes( currentEpisodes );
			},
			error: function () {
				$btn.prop( 'disabled', false ).text( i18n.importSingle );
				showError( i18n.errorImport );
			},
		} );
	} );

	// =========================================================================
	// Bulk: Ignore selected
	// =========================================================================

	$( document ).on( 'click', '#podigee-ignore-selected', function () {
		const feedId = $( '#podigee-feed-select' ).val();
		if ( ! feedId ) {
			return;
		}

		const guids = [];
		$tbody.find( '.podigee-ep-check:checked' ).each( function () {
			guids.push( String( $( this ).data( 'guid' ) ) );
		} );

		if ( guids.length === 0 ) {
			alert( i18n.noSelection );
			return;
		}

		ignoreEpisodes( feedId, guids );
	} );

	// =========================================================================
	// AJAX helpers: ignore / unignore
	// =========================================================================

	function ignoreEpisodes( feedId, guids ) {
		$.ajax( {
			url: podigeeAjax.ajaxUrl,
			method: 'POST',
			data: {
				action:  'podigee_ignore_episodes',
				nonce:   podigeeAjax.nonceIgnore,
				feed_id: feedId,
				guids:   guids,
			},
			success: function ( response ) {
				if ( ! response.success ) {
					showError( response.data || i18n.errorIgnore );
					return;
				}
				// Mark episodes as ignored in local cache and re-render.
				guids.forEach( function ( guid ) {
					const ep = currentEpisodes.find( function ( e ) { return e.guid === guid; } );
					if ( ep ) {
						ep.is_ignored = true;
					}
				} );
				renderEpisodes( currentEpisodes );
			},
			error: function () {
				showError( i18n.errorIgnore );
			},
		} );
	}

	function unignoreEpisodes( feedId, guids ) {
		$.ajax( {
			url: podigeeAjax.ajaxUrl,
			method: 'POST',
			data: {
				action:  'podigee_unignore_episodes',
				nonce:   podigeeAjax.nonceUnignore,
				feed_id: feedId,
				guids:   guids,
			},
			success: function ( response ) {
				if ( ! response.success ) {
					showError( response.data || i18n.errorIgnore );
					return;
				}
				// Remove ignored flag from local cache and re-render.
				guids.forEach( function ( guid ) {
					const ep = currentEpisodes.find( function ( e ) { return e.guid === guid; } );
					if ( ep ) {
						ep.is_ignored = false;
					}
				} );
				renderEpisodes( currentEpisodes );
			},
			error: function () {
				showError( i18n.errorIgnore );
			},
		} );
	}

	// =========================================================================
	// Bulk Selection Buttons
	// =========================================================================

	$( '#podigee-select-all' ).on( 'click', function () {
		$tbody.find( 'tr:not(.hidden) .podigee-ep-check' ).prop( 'checked', true );
	} );

	$( '#podigee-select-none' ).on( 'click', function () {
		$tbody.find( '.podigee-ep-check' ).prop( 'checked', false );
	} );

	$( '#podigee-select-new' ).on( 'click', function () {
		$tbody.find( 'tr:not(.hidden) .podigee-ep-check' ).each( function () {
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

	function renderStatusBadge( isImported, isIgnored, statusClass, statusLabel, postId ) {
		const badge = '<span class="podigee-badge podigee-badge--' + escAttr( statusClass ) + '">' + escHtml( statusLabel ) + '</span>';
		if ( isImported && ! isIgnored && postId ) {
			const url = podigeeAjax.editPostUrl + '?post=' + parseInt( postId, 10 ) + '&action=edit';
			return '<a href="' + url + '" target="_blank" rel="noopener" style="text-decoration:none;">' + badge + '</a>';
		}
		return badge;
	}

	function showImportResult( data ) {
		let html = '<div class="notice notice-success"><p>';
		html += '<strong>' + escHtml( i18n.resultDone ) + '</strong>';
		html += '</p><ul>';
		html += '<li>' + data.imported + ' ' + escHtml( i18n.resultImported ) + '</li>';
		html += '<li>' + data.updated  + ' ' + escHtml( i18n.resultUpdated ) + '</li>';
		html += '<li>' + data.skipped  + ' ' + escHtml( i18n.resultSkipped ) + '</li>';
		html += '</ul>';

		if ( data.errors && data.errors.length > 0 ) {
			html += '<p><strong>' + escHtml( i18n.resultErrors ) + '</strong></p><ul>';
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
	// Content order: Sortable + image_mode dependency
	// =========================================================================

	$( function () {
		$( '#podigee-content-order' ).sortable( {
			handle: '.podigee-drag-handle',
			axis: 'y',
			containment: 'parent',
		} );

		$( '#podigee-image-mode' ).on( 'change', function () {
			var $imgItem = $( '#podigee-content-order li[data-key="image"]' );
			var $cb      = $imgItem.find( 'input[type="checkbox"]' );
			if ( $( this ).val() !== 'inline' ) {
				$cb.prop( 'checked', false ).prop( 'disabled', true );
				$imgItem.addClass( 'podigee-sortable-disabled' );
			} else {
				$cb.prop( 'disabled', false );
				$imgItem.removeClass( 'podigee-sortable-disabled' );
			}
		} ).trigger( 'change' );

		// Ensure disabled checkboxes are submitted as unchecked (re-enable before submit).
		$( 'form' ).on( 'submit', function () {
			$( '#podigee-content-order input:disabled' ).prop( 'disabled', false ).prop( 'checked', false );
		} );
	} );

	// Toggle disabled styling when checkbox is changed.
	$( document ).on( 'change', '#podigee-content-order input[type="checkbox"]', function () {
		$( this ).closest( 'li' ).toggleClass( 'podigee-sortable-disabled', ! this.checked );
	} );

	// =========================================================================
	// Auto-load if feed pre-selected via URL param
	// =========================================================================

	$( function () {
		if ( $( '#podigee-feed-select' ).val() ) {
			$( '#podigee-load-episodes' ).trigger( 'click' );
		}
	} );

} )( jQuery );
