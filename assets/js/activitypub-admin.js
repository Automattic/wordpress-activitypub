jQuery( function( $ ) {
	// Accordion handling in various areas.
	$( '.activitypub-settings-accordion' ).on( 'click', '.activitypub-settings-accordion-trigger', function() {
		var isExpanded = ( 'true' === $( this ).attr( 'aria-expanded' ) );

		if ( isExpanded ) {
			$( this ).attr( 'aria-expanded', 'false' );
			$( '#' + $( this ).attr( 'aria-controls' ) ).attr( 'hidden', true );
		} else {
			$( this ).attr( 'aria-expanded', 'true' );
			$( '#' + $( this ).attr( 'aria-controls' ) ).attr( 'hidden', false );
		}
	} );

	$(document).on( 'wp-plugin-install-success', function( event, response ) {
		setTimeout( function() {
			$( '.activate-now' ).removeClass( 'thickbox open-plugin-details-modal' );
		}, 1200 );
	} );

	/*
	 * Lazy-load embedded iframes (e.g. the Fediverse intro video) only once their
	 * container becomes visible. Help tab panels are rendered hidden, and players
	 * like PeerTube abort with an "invalid width" error when initialized inside a
	 * zero-width container. Deferring the src until the panel is shown avoids that.
	 */
	var lazyIframes = document.querySelectorAll( 'iframe[data-src]' );

	if ( lazyIframes.length && 'IntersectionObserver' in window ) {
		var iframeObserver = new IntersectionObserver( function( entries, observer ) {
			entries.forEach( function( entry ) {
				if ( entry.isIntersecting ) {
					// Set once, then stop observing so it never reloads.
					entry.target.src = entry.target.dataset.src;
					observer.unobserve( entry.target );
				}
			} );
		} );

		lazyIframes.forEach( function( iframe ) {
			iframeObserver.observe( iframe );
		} );
	} else {
		// IntersectionObserver unsupported: load immediately as a fallback.
		lazyIframes.forEach( function( iframe ) {
			iframe.src = iframe.dataset.src;
		} );
	}
} );
