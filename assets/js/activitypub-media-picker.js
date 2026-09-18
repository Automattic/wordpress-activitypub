/**
 * Handle the media picker settings (header image and blog avatar).
 *
 * This is based on site-icon.js
 *
 * @see wp-admin/js/site-icon.js
 */

/* global jQuery, wp, activitypubMediaPicker */

( function ( $ ) {
	var pickerData = window.activitypubMediaPicker || {},
		ImageCropperNoCustomizer;

	/**
	 * We register our own handler because the Core one invokes the Customizer, which fails the request unnecessarily
	 * for users who don't have the 'customize' capability.
	 * See https://github.com/Automattic/wordpress-activitypub/issues/846
	 */
	ImageCropperNoCustomizer = wp.media.controller.CustomizeImageCropper.extend( {
		doCrop: function ( attachment ) {
			var cropDetails = attachment.get( 'cropDetails' ),
				control = this.get( 'control' ),
				ratio = cropDetails.width / cropDetails.height;

			// Use crop measurements when flexible in both directions.
			if ( control.params.flex_width && control.params.flex_height ) {
				cropDetails.dst_width = cropDetails.width;
				cropDetails.dst_height = cropDetails.height;

				// Constrain flexible side based on image ratio and size of the fixed side.
			} else {
				cropDetails.dst_width = control.params.flex_width ? control.params.height * ratio : control.params.width;
				cropDetails.dst_height = control.params.flex_height ? control.params.width / ratio : control.params.height;
			}

			return wp.ajax.post( 'crop-image', {
				// where wp_customize: 'on' would be in Core, for no good reason I understand.
				nonce: attachment.get( 'nonces' ).edit,
				id: attachment.get( 'id' ),
				context: control.id,
				cropDetails: cropDetails,
			} );
		},
	} );

	/**
	 * Calculate image selection options based on the attachment dimensions.
	 *
	 * @param {Object} attachment    The attachment object representing the image.
	 * @param {number} targetWidth   The width of the target image.
	 * @param {number} targetHeight  The height of the target image.
	 * @return {Object} The image selection options.
	 */
	function calculateImageSelectOptions( attachment, targetWidth, targetHeight ) {
		var realWidth = attachment.get( 'width' ),
			realHeight = attachment.get( 'height' ),
			xInit = targetWidth,
			yInit = targetHeight,
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
	 * Initializes a single media picker, driven by the data attributes of its button.
	 *
	 * Every picker keeps its own state, so multiple pickers can run on the same page.
	 *
	 * @param {jQuery} $chooseButton The button that opens the media frame.
	 */
	function initPicker( $chooseButton ) {
		var $preview = $( $chooseButton.attr( 'data-preview' ) ),
			$previewWrapper = $( $chooseButton.attr( 'data-preview-wrapper' ) ),
			$hiddenDataField = $( $chooseButton.attr( 'data-input' ) ),
			$removeButton = $( $chooseButton.attr( 'data-remove' ) ),
			targetWidth = parseInt( $chooseButton.attr( 'data-width' ), 10 ),
			targetHeight = parseInt( $chooseButton.attr( 'data-height' ), 10 ),
			context = $chooseButton.attr( 'data-context' ),
			previewLabel = $chooseButton.attr( 'data-preview-label' ) || '',
			fallbackUrl = pickerData.fallbackUrls && pickerData.fallbackUrls[ context ],
			frame;

		/**
		 * Update the UI when an image is selected.
		 *
		 * @param {Object} attributes The attributes for the attachment.
		 */
		function switchToUpdate( attributes ) {
			var alt;

			if ( attributes.alt ) {
				alt = wp.i18n.sprintf(
					/* translators: 1: The type of image, 2: The selected image alt text. */
					wp.i18n.__( '%1$s preview: Current image: %2$s', 'activitypub' ),
					previewLabel,
					attributes.alt
				);
			} else {
				alt = wp.i18n.sprintf(
					/* translators: 1: The type of image, 2: The selected image filename. */
					wp.i18n.__( '%1$s preview: The current image has no alternative text. The file name is: %2$s', 'activitypub' ),
					previewLabel,
					attributes.filename
				);
			}

			// Set the preview src and alt text.
			$preview.attr( {
				src: attributes.url,
				alt: alt,
			} );

			// Show the preview and the remove button.
			$previewWrapper.removeClass( 'hidden' );
			$removeButton.removeClass( 'hidden' );

			// If the choose button is not in the update state, swap the classes.
			// Any non-zero attachment ID means an image is already set.
			if ( ! parseInt( $chooseButton.attr( 'data-state' ), 10 ) ) {
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
		 * Initializes the media frame for selecting or cropping an image.
		 */
		$chooseButton.on( 'click', function () {
			var $el = $( this ),
				userId = $el.data( 'userId' ),
				mediaQuery = { type: 'image' };

			if ( userId ) {
				mediaQuery.author = userId;
			}

			// Create the media frame.
			frame = wp.media( {
				button: {
					// Set the text of the button.
					text: $el.attr( 'data-update' ) || $el.attr( 'data-update-text' ),

					// Don't close, we might need to crop.
					close: false,
				},
				states: [
					new wp.media.controller.Library( {
						title: $el.attr( 'data-choose-text' ),
						library: wp.media.query( mediaQuery ),
						date: false,
						suggestedWidth: targetWidth,
						suggestedHeight: targetHeight,
					} ),
					new ImageCropperNoCustomizer( {
						control: {
							id: context,
							params: {
								width: targetWidth,
								height: targetHeight,
							},
						},
						imgSelectOptions: function ( attachment ) {
							return calculateImageSelectOptions( attachment, targetWidth, targetHeight );
						},
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
					targetRatio = targetWidth / targetHeight,
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
		 * Handles the click event of the remove button.
		 */
		$removeButton.on( 'click', function () {
			$hiddenDataField.val( 'false' );
			$removeButton.addClass( 'hidden' );

			if ( fallbackUrl ) {
				// Keep the preview visible and show the fallback image.
				$previewWrapper.removeClass( 'hidden' );
				$preview.attr( {
					src: fallbackUrl,
					alt: '',
				} );
			} else {
				$previewWrapper.addClass( 'hidden' );
				$preview.attr( {
					src: '',
					alt: '',
				} );
			}

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
	}

	// Initialize every media picker on the page.
	$( '.activitypub-media-picker-button' ).each( function () {
		initPicker( $( this ) );
	} );
} )( jQuery );
