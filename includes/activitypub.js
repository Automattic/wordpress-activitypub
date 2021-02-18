(function($) {    
    /**
     * Reply Comment-edit screen
     */
    
    //Insert Mentions into comment content on reply
    $('.comment-inline.button-link').on('click', function( event){
        // Summary/ContentWarning Syntax [CW]
        var summary = $(this).attr('data-summary') ? '[' + $(this).attr('data-summary') + '] ' : '';
        var recipients = $(this).attr('data-recipients') ? $(this).attr('data-recipients') + ' ' : '';
        setTimeout(function() {
            if ( summary || recipients ){
                $('#replycontent').val( summary + recipients )
            }
        }, 100);
    })
    //Clear Mentions from content on cancel
    $('.cancel.button').on('click', function(){
        $('#replycontent').val('');
    });

})( jQuery );