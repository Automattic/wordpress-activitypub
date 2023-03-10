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

	//Reply Comment-edit screen
    if ( $('body').hasClass('edit-comments-php') || $('body').hasClass('index-php') ) {
        //Insert Mentions into comment content on reply
        $( '.comment-inline.button-link' ).on( 'click', function( event ) {
            var recipients = $(this).attr('data-recipients') ? $(this).attr('data-recipients') + ' ' : '';
            setTimeout(function() {
                if ( recipients ){
                    $('#replycontent').val( recipients )
                }
            }, 100);
        })
        //Clear Mentions from content on cancel
        $('.cancel.button').on('click', function(){
            $('#replycontent').val('');
        });
    }
} );
