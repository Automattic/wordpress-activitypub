jQuery( function( $ ) {
	const { __ } = wp.i18n;
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
	
	$( '.activitypub-settings-action-buttons' ).on( 'click', '.button', function() {
		var button = $ (this );
		var actionValue = button.data('action');
		window.console.log( actionValue );
		$.ajax({
            type: 'POST',
            url: actionValue,
            success: function ( response ) {
				var statusText = button.closest( 'td' ).siblings( '.column-status' ).children( 'span' ).first();
				if ( 'deleted' === response ) {
					button.closest( 'tr' ).remove();
				}
				if ( 'approved' === response ) {
					button.parent().find( '[data-action*="follow_action=reject"] ').attr( 'type', 'button' );
					button.parent().find( '[data-action*="follow_action=delete"]' ).attr( 'type', 'hidden' );
					statusText.text( __( 'Approved', 'activitypub' ) );
					statusText.removeClass( 'activitypub-settings-label-danger' );
					statusText.removeClass( 'activitypub-settings-label-warning' );
					statusText.addClass( 'activitypub-settings-label-success' );
				}
				if ( 'rejected' === response ) {
					// TODO: clarify this behavior together with Mobilizon and others.
					button.closest( 'tr' ).remove();
					// statusText.text( __( 'Rejected', 'activitypub' ) );
					// statusText.removeClass( 'activitypub-settings-label-success' );
					// statusText.removeClass( 'activitypub-settings-label-warning' );
					// statusText.addClass( 'activitypub-settings-label-danger' );
					// button.parent().find( '[data-action*="follow_action=approve"]' ).attr( 'type', 'button' );
					// button.parent().find( '[data-action*="follow_action=delete"]' ).attr( 'type', 'button' );
				}
				button.attr( 'type', 'hidden' );
				// Check if table is completely empty.
				var tbody = button.closest( 'tbody' );
				if ( 0 == tbody.find( 'tr' ).length ) {
					var text = __( 'No items found.', 'core' );
					var newRow = $('<tr>').append($('<td>', { class: 'colspanchange', colspan: 7, text: text }));
					tbody.append(newRow);
					tbody.append("Some appended text.");
				}
            },
            error: function ( error ) {
                // TODO: Handle the error
            }
        });
	} );

} );
