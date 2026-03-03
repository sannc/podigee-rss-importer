/**
 * Podigee RSS Importer – Frontend Player
 *
 * - Initialises Plyr on all episode audio elements.
 * - Wires up a click on the post's featured image (.podigee-featured-image)
 *   to toggle play / pause on the associated player.
 * - Reflects playback state as an .is-playing class on the figure so CSS
 *   can show a play / pause overlay icon.
 */
/* global Plyr */
( function () {
	'use strict';

	// Map from <audio> element → Plyr instance so we can look up players later.
	var playerMap = new Map();

	document.querySelectorAll( '.podigee-episode-player audio' ).forEach( function ( el ) {
		var player = new Plyr( el, {
			controls: [ 'play', 'progress', 'current-time', 'duration', 'mute', 'volume' ],
		} );
		playerMap.set( el, player );
	} );

	// Wire featured image click → play / pause on the associated player.
	document.querySelectorAll( '.podigee-featured-image' ).forEach( function ( fig ) {
		// Prefer a player inside the same article / post container; fall back to
		// the first player on the page (covers the common single-post layout).
		var scope   = fig.closest( 'article, .hentry, .post' ) || document.body;
		var audioEl = scope.querySelector( '.podigee-episode-player audio' )
		           || document.querySelector( '.podigee-episode-player audio' );

		if ( ! audioEl ) return;

		var player = playerMap.get( audioEl );
		if ( ! player ) return;

		fig.addEventListener( 'click', function ( e ) {
			// Let regular links inside the figure (e.g. permalink) work normally.
			if ( e.target.closest( 'a' ) ) return;
			player.playing ? player.pause() : player.play();
		} );

		// Keep CSS in sync with playback state.
		player.on( 'play',  function () { fig.classList.add( 'is-playing' ); } );
		player.on( 'pause', function () { fig.classList.remove( 'is-playing' ); } );
		player.on( 'ended', function () { fig.classList.remove( 'is-playing' ); } );
	} );
} )();
