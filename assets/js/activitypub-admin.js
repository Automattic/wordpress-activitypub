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

	$( '#activitypub-welcome-checklist' ).on( 'click', 'a[href*="#tab-link-"]', function( event ) {
		const match  = $( this ).attr( 'href' ).match( /#tab-link-([\w-]+)/ );
		if ( match && match[1] && typeof wp !== 'undefined' && wp.ajax && typeof wp.ajax.post === 'function' ) {
			wp.ajax.post( 'activitypub_help_tab_visited', {
				tab: match[1],
				_wpnonce: $( event.delegateTarget ).data( 'nonce' )
			} )
			.done( () => {
				// Find the closest onboarding step
				const $step = $( this ).closest( '.activitypub-onboarding-step' );
				if ( $step.length ) {
					$step.addClass( 'activitypub-step-completed' );
					$step.find( '.step-icon' ).removeClass( 'dashicons-video-alt3 dashicons-info' ).addClass( 'dashicons-yes' );
				}
			} );
		}
	} );
} );
