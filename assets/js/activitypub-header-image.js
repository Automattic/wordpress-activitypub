/**
 * Handle the header image setting in
 *
 * This is based on site-icon.js
 *
 * @see wp-admin/js/site-icon.js
 */

/* global jQuery, wp */

( function ( $ ) {
	var $chooseButton = $( '#activitypub-choose-from-library-button' ),
		$headerImagePreviewWrapper = $( '#activitypub-header-image-preview-wrapper' ),
		$headerImagePreview = $( '#activitypub-header-image-preview' ),
		$hiddenDataField = $( '#activitypub_header_image' ),
		$removeButton = $( '#activitypub-remove-header-image' ),
		frame,
		ImageCropperNoCustomizer;

	/**
	 * We register our own handler because the Core one invokes the Customizer, which fails the request unnecessarily
	 * for users who don't have the 'customize' capability.
	 * See https://github.com/Automattic/wordpress-activitypub/issues/846
	 */
	ImageCropperNoCustomizer = wp.media.controller.CustomizeImageCropper.extend( {
		doCrop: function( attachment ) {
			var cropDetails = attachment.get( 'cropDetails' ),
				control = this.get( 'control' ),
				ratio = cropDetails.width / cropDetails.height;

			// Use crop measurements when flexible in both directions.
			if ( control.params.flex_width && control.params.flex_height ) {
				cropDetails.dst_width  = cropDetails.width;
				cropDetails.dst_height = cropDetails.height;

			// Constrain flexible side based on image ratio and size of the fixed side.
			} else {
				cropDetails.dst_width  = control.params.flex_width  ? control.params.height * ratio : control.params.width;
				cropDetails.dst_height = control.params.flex_height ? control.params.width  / ratio : control.params.height;
			}

			return wp.ajax.post( 'crop-image', {
				// where wp_customize: 'on' would be in Core, for no good reason I understand.
				nonce: attachment.get( 'nonces' ).edit,
				id: attachment.get( 'id' ),
				context: control.id,
				cropDetails: cropDetails
			} );
		}
	} );



	/**
	 * Calculate image selection options based on the attachment dimensions.
	 *
	 * @since 6.5.0
	 *
	 * @param {Object} attachment The attachment object representing the image.
	 * @return {Object} The image selection options.
	 */
	function calculateImageSelectOptions( attachment ) {
		var realWidth = attachment.get( 'width' ),
			realHeight = attachment.get( 'height' ),
			xInit = 1500,
			yInit = 500,
			ratio = xInit / yInit,
			xImg = xInit,
			yImg = yInit,
			x1,
			y1,
			imgSelectOptions;

		if ( realWidth / realHeight > ratio ) {
			yInit = realHeight;
			xInit = yInit * ratio;
		} else {
			xInit = realWidth;
			yInit = xInit / ratio;
		}

		x1 = ( realWidth - xInit ) / 2;
		y1 = ( realHeight - yInit ) / 2;

		imgSelectOptions = {
			aspectRatio: xInit + ':' + yInit,
			handles: true,
			keys: true,
			instance: true,
			persistent: true,
			imageWidth: realWidth,
			imageHeight: realHeight,
			minWidth: xImg > xInit ? xInit : xImg,
			minHeight: yImg > yInit ? yInit : yImg,
			x1: x1,
			y1: y1,
			x2: xInit + x1,
			y2: yInit + y1,
		};

		return imgSelectOptions;
	}

	/**
	 * Initializes the media frame for selecting or cropping an image.
	 *
	 * @since 6.5.0
	 */
	$chooseButton.on( 'click', function () {
		var $el = $( this );
		var userId = $el.data( 'userId' );
		var mediaQuery = { type: 'image' };
		if ( userId ) {
			mediaQuery.author = userId;
		}

		// Create the media frame.
		frame = wp.media( {
			button: {
				// Set the text of the button.
				text: $el.data( 'update' ),

				// Don't close, we might need to crop.
				close: false,
			},
			states: [
				new wp.media.controller.Library( {
					title: $el.data( 'choose-text' ),
					library: wp.media.query( mediaQuery ),
					date: false,
					suggestedWidth: $el.data( 'width' ),
					suggestedHeight: $el.data( 'height' ),
				} ),
				new ImageCropperNoCustomizer( {
					control: {
						id: 'activitypub-header-image',
						params: {
							width: $el.data( 'width' ),
							height: $el.data( 'height' ),
						},
					},
					imgSelectOptions: calculateImageSelectOptions,
				} ),
			],
		} );

		frame.on( 'cropped', function ( attachment ) {
			$hiddenDataField.val( attachment.id );
			switchToUpdate( attachment );
			frame.close();

			// Start over with a frame that is so fresh and so clean clean.
			frame = null;
		} );

		// When an image is selected, run a callback.
		frame.on( 'select', function () {
			// Grab the selected attachment.
			var attachment = frame.state().get( 'selection' ).first(),
				targetRatio = $el.data( 'width' ) / $el.data( 'height' ),
				currentRatio = attachment.attributes.width / attachment.attributes.height,
				alreadyCropped = false;

			// Check if the image already has the correct aspect ratio (with a small tolerance).
			if ( Math.abs( currentRatio - targetRatio ) < 0.01 ) {
				// Check if this is the same image that was already selected.
				if ( attachment.id !== parseInt( $hiddenDataField.val(), 10 ) ) {
					// This is a new image with the correct aspect ratio.
					$hiddenDataField.val( attachment.id );
				}

				alreadyCropped = true;
			}

			if ( alreadyCropped ) {
				// Skip cropping for already cropped images.
				switchToUpdate( attachment.attributes );
				frame.close();
			} else {
				frame.setState( 'cropper' );
			}
		} );

		frame.open();
	} );

	/**
	 * Update the UI when a header is selected.
	 *
	 * @since 6.5.0
	 *
	 * @param {array} attributes The attributes for the attachment.
	 */
	function switchToUpdate( attributes ) {
		var i18nAppAlternativeString, i18nBrowserAlternativeString;

		if ( attributes.alt ) {
			i18nBrowserAlternativeString = wp.i18n.sprintf(
				/* translators: %s: The selected image alt text. */
				wp.i18n.__( 'Header Image preview: Current image: %s' ),
				attributes.alt
			);
		} else {
			i18nAppAlternativeString = wp.i18n.sprintf(
				/* translators: %s: The selected image filename. */
				wp.i18n.__(
					'Header Image preview: The current image has no alternative text. The file name is: %s'
				),
				attributes.filename
			);
			i18nBrowserAlternativeString = wp.i18n.sprintf(
				/* translators: %s: The selected image filename. */
				wp.i18n.__(
					'Header Image preview: The current image has no alternative text. The file name is: %s'
				),
				attributes.filename
			);
		}

		// Set activitypub-header-image-preview src.
		$headerImagePreview.attr( {
			src: attributes.url,
			alt: i18nAppAlternativeString,
		} );

		// Remove hidden class from header image preview div and remove button.
		$headerImagePreviewWrapper.removeClass( 'hidden' );
		$removeButton.removeClass( 'hidden' );

		// If the choose button is not in the update state, swap the classes.
		if ( $chooseButton.attr( 'data-state' ) !== '1' ) {
			$chooseButton.attr( {
				class: $chooseButton.attr( 'data-alt-classes' ),
				'data-alt-classes': $chooseButton.attr( 'class' ),
				'data-state': '1',
			} );
		}

		// Swap the text of the choose button.
		$chooseButton.text( $chooseButton.attr( 'data-update-text' ) );
	}

	/**
	 * Handles the click event of the remove button.
	 *
	 * @since 6.5.0
	 */
	$removeButton.on( 'click', function () {
		$hiddenDataField.val( 'false' );
		$( this ).toggleClass( 'hidden' );
		$headerImagePreviewWrapper.toggleClass( 'hidden' );
		$headerImagePreview.attr( {
			src: '',
			alt: '',
		} );

		/**
		 * Resets state to the button, for correct visual style and state.
		 * Updates the text of the button.
		 * Sets focus state to the button.
		 */
		$chooseButton
			.attr( {
				class: $chooseButton.attr( 'data-alt-classes' ),
				'data-alt-classes': $chooseButton.attr( 'class' ),
				'data-state': '',
			} )
			.text( $chooseButton.attr( 'data-choose-text' ) )
			.trigger( 'focus' );
	} );
} )( jQuery );
