jQuery( function( $ ) {
	// Reply from Comment-edit screen & Dashboard.
    if ( $('body').hasClass('edit-comments-php') || $('body').hasClass('index-php') ) {
        //Insert @mentions into comment content on reply
        $( '.comment-inline.button-link' ).on( 'click', function( event ) {
            var recipients = $(this).attr('data-recipients') ? $(this).attr('data-recipients') + ' ' : '';
            setTimeout(function() {
                if ( recipients ){
                    $('#replycontent').val( recipients )
                }
            }, 100);
        })
        //Clear @mentions from content on cancel
        $('.cancel.button').on('click', function(){
            $('#replycontent').val('');
        });
    }
	// Reply from frontend.
    if ( $('body').hasClass('logged-in') && $('body').hasClass('single') ) {
        //Insert @mentions into comment content on reply
        $( '.comment-reply-link' ).on( 'click', function( event ) {
            var recipients = $(this).attr('data-recipients') ? $(this).attr('data-recipients') + ' ' : '';
			console.log( 'recipients', recipients )
            setTimeout(function() {
                if ( recipients ){
                    $('#respond #comment').val( recipients )
                }
            }, 100);
        })
		//Clear @mentions from content on cancel
        $('#cancel-comment-reply-link').on('click', function(){
			$('#respond #comment').val('');
        });
    }
} );
