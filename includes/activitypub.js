(function($) {    
    /**
     * Reply Comment-edit screen
     */
    if ( $('body').hasClass('edit-comments-php') ) {
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

    /**
     * Tools screen
     */
    if ( $('body').hasClass('tools_page_activitypub_tools') ) {
            
        $('.delete_annouce' ).on('click', function(event) {
            event.preventDefault();
            var row = $(this).parents('tr') ? $(this).parents('tr') : '';
            var nonce = $(this).attr('data-nonce') ? $(this).attr('data-nonce') : '';
            var post_url = $(this).attr('data-post_url') ? $(this).attr('data-post_url') : '';
            var post_author = $(this).attr('data-post_author') ? $(this).attr('data-post_author') : '';
            var data = {
                'action': 'migrate_post',
                'nonce': nonce,
                'post_url': post_url,
                'post_author': post_author
            }
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: data,
                success: function () {
                    row.remove()
                },
                error: function (jqXHR, textStatus, errorThrown) {
                    console.log(textStatus + errorThrown);
                }
            })
        });
    }

})( jQuery );
