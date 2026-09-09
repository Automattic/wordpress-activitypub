/**
 * Handle the blog avatar setting on the blog profile settings page.
 */

/* global jQuery, wp */

( function ( $ ) {
	var fallbackUrl = window.activitypubBlogAvatar && window.activitypubBlogAvatar.fallbackUrl,
		$chooseButton = $( '#activitypub-choose-blog-avatar-button' ),
		$preview = $( '#activitypub-blog-avatar-preview' ),
		$hiddenDataField = $( '#activitypub_blog_icon' ),
		$removeButton = $( '#activitypub-remove-blog-avatar' ),
		frame;

	/**
	 * Initializes the media frame for selecting an avatar.
	 */
	$chooseButton.on( 'click', function () {
		// Create the media frame.
		frame = wp.media( {
			title: $chooseButton.attr( 'data-choose-text' ),
			button: {
				// Set the text of the button.
				text: $chooseButton.attr( 'data-update-text' ),
				close: true,
			},
			library: { type: 'image' },
			multiple: false,
		} );

		// When an image is selected, run a callback.
		frame.on( 'select', function () {
			var attachment = frame.state().get( 'selection' ).first();

			$hiddenDataField.val( attachment.id );
			$preview.attr( 'src', attachment.attributes.url );
			$removeButton.removeClass( 'hidden' );

			$chooseButton
				.attr( {
					class: 'button',
					'data-state': '1',
				} )
				.text( $chooseButton.attr( 'data-update-text' ) );

			frame.close();
		} );

		frame.open();
	} );

	/**
	 * Handles the click event of the remove button.
	 */
	$removeButton.on( 'click', function () {
		$hiddenDataField.val( '' );
		$preview.attr( 'src', fallbackUrl );
		$( this ).addClass( 'hidden' );

		$chooseButton
			.attr( {
				class: 'button upload-button button-add-media button-add-blog-avatar',
				'data-state': '',
			} )
			.text( $chooseButton.attr( 'data-choose-text' ) )
			.trigger( 'focus' );
	} );
} )( jQuery );
