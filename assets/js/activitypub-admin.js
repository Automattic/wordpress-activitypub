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

	/*
	 * Copy buttons, e.g. for the Fediverse handle on the welcome page. The text to copy
	 * comes from `data-clipboard-text`, the success label from `data-copied-label`.
	 */
	$( document ).on( 'click', '.activitypub-copy-to-clipboard', function() {
		var $button = $( this ),
			text = $button.data( 'clipboard-text' ),
			copiedLabel = $button.data( 'copied-label' ),
			originalLabel = $button.data( 'originalLabel' );

		/*
		 * Remember the label from the first click only. Reading it again while the
		 * success label is showing would make "Copied!" the label to restore.
		 */
		if ( undefined === originalLabel ) {
			originalLabel = $.trim( $button.text() );
			$button.data( 'originalLabel', originalLabel );
		}

		function showSuccess() {
			clearTimeout( $button.data( 'resetTimeout' ) );

			$button.text( copiedLabel );

			/*
			 * The button keeps its `aria-label`, which wins over the text content, so
			 * the new label alone would not reach a screen reader.
			 */
			window.wp.a11y.speak( copiedLabel );

			$button.data( 'resetTimeout', setTimeout( function() {
				$button.text( originalLabel );
			}, 1000 ) );
		}

		// The Clipboard API needs a secure context, which self-hosted admins do not always have.
		if ( window.navigator.clipboard && window.navigator.clipboard.writeText ) {
			window.navigator.clipboard.writeText( text ).then( showSuccess, function( error ) {
				// eslint-disable-next-line no-console -- Log error if copying fails.
				console.error( 'Could not copy text: ', error );
			} );

			return;
		}

		// Fall back to selecting the field the button belongs to.
		var field = $button.siblings( 'input' ).get( 0 );

		if ( ! field ) {
			return;
		}

		field.select();

		if ( document.execCommand( 'copy' ) ) {
			showSuccess();
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
