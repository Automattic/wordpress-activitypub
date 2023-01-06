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

	$(document).on( 'click', '#activitypub_post_content_type_title_link_details', function() {
		var content = $( '#activitypub_post_content_type_title_link_details_content');
		var isExpanded = ( 'block' === content.css( 'display' ) );

		if ( isExpanded ) {
			content.css( 'display', 'none' );
		} else {
			content.css( 'display', 'block' );
		}
	} );

	$(document).on( 'click', '#activitypub_post_content_type_excerpt_link_details', function() {
		var content = $( '#activitypub_post_content_type_excerpt_link_details_content');
		var isExpanded = ( 'block' === content.css( 'display' ) );

		if ( isExpanded ) {
			content.css( 'display', 'none' );
		} else {
			content.css( 'display', 'block' );
		}
	} );

	$(document).on( 'click', '#activitypub_post_content_type_content_link_details', function() {
		var content = $( '#activitypub_post_content_type_content_link_details_content');
		var isExpanded = ( 'block' === content.css( 'display' ) );

		if ( isExpanded ) {
			content.css( 'display', 'none' );
		} else {
			content.css( 'display', 'block' );
		}
	} );

	$(document).on( 'wp-plugin-install-success', function( event, response ) {
		setTimeout( function() {
			$( '.activate-now' ).removeClass( 'thickbox open-plugin-details-modal' );
		}, 1200 );
	} );
} );
