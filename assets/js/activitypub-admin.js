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
		const match  = $( this ).attr( 'href' ).match( /#tab-link-([\w-]+)/ ),
			$checklist = $( event.delegateTarget );
		if ( match && match[1] && typeof wp !== 'undefined' && wp.ajax && typeof wp.ajax.post === 'function' ) {
			wp.ajax.post( 'activitypub_help_tab_visited', {
				tab: match[1],
				_wpnonce: $checklist.data( 'nonce' )
			} )
			.done( () => {
				// Find the closest onboarding step.
				const $step = $( this ).closest( '.activitypub-onboarding-step' );
				if ( $step.length ) {
					$step.addClass( 'activitypub-step-completed' );
					$step.find( '.step-icon' ).removeClass( 'dashicons-video-alt3 dashicons-info' ).addClass( 'dashicons-yes' );
				}

				// Update progress label and ring using parsed value.
				const $progressLabel = $checklist.find( '.activitypub-progress-label' );
				const $progressCircle = $checklist.find( '.activitypub-progress-ring-circle' );

				if ( $progressLabel.length && $progressCircle.length ) {
					const labelParts = $progressLabel.text().trim().split( '/' );
					const total      = parseInt( labelParts[1], 10 );
					const completed  = parseInt( labelParts[0], 10 ) + 1;

					$progressLabel.text( completed + ' / ' + total );

					const percent = Math.min( 100, Math.round( ( completed / total ) * 100 ) );
					const offset  = 339.292 - ( 339.292 * percent / 100 );

					$progressCircle.css( 'stroke-dashoffset', offset );
				}
			} );
		}
	} );
} );
