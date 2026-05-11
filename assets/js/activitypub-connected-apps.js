/**
 * ActivityPub Connected Applications JavaScript.
 *
 * Handles registering OAuth clients, deleting clients, and revoking
 * OAuth tokens from the user profile, following the WordPress core
 * Application Passwords UI pattern.
 */

/* global activitypubConnectedApps, jQuery, ClipboardJS */

( function( $ ) {
	var $section         = $( '#activitypub-connected-apps-section' ),
		$newAppForm      = $section.find( '.create-application-password' ),
		$newAppFields    = $newAppForm.find( '.input' ),
		$newAppButton    = $newAppForm.find( '.button' ),
		$appsWrapper     = $section.find( '#activitypub-registered-apps-wrapper' ),
		$appsTbody       = $section.find( '#activitypub-registered-apps-tbody' ),
		$tokensWrapper   = $section.find( '.activitypub-connected-apps-list-table-wrapper' ),
		$tokensTbody     = $section.find( '#activitypub-connected-apps-tbody' ),
		$revokeAll       = $section.find( '#activitypub-revoke-all-tokens' ),
		$deleteAll       = $section.find( '#activitypub-delete-all-clients' );

	// Register a new application.
	$newAppButton.on( 'click', function( e ) {
		e.preventDefault();

		if ( $newAppButton.prop( 'aria-disabled' ) ) {
			return;
		}

		var $name        = $( '#activitypub-new-app-name' );
		var $redirectUri = $( '#activitypub-new-app-redirect-uri' );

		if ( 0 === $name.val().trim().length ) {
			$name.trigger( 'focus' );
			return;
		}

		if ( 0 === $redirectUri.val().trim().length ) {
			$redirectUri.trigger( 'focus' );
			return;
		}

		clearNotices();
		$newAppButton.prop( 'aria-disabled', true ).addClass( 'disabled' );

		$.ajax( {
			url: activitypubConnectedApps.ajaxUrl,
			method: 'POST',
			data: {
				action: 'activitypub_register_oauth_client',
				name: $name.val().trim(),
				redirect_uri: $redirectUri.val().trim(),
				_wpnonce: activitypubConnectedApps.nonce
			}
		} ).always( function() {
			$newAppButton.removeProp( 'aria-disabled' ).removeClass( 'disabled' );
		} ).done( function( response ) {
			if ( ! response.success ) {
				addNotice(
					response.data && response.data.message ? response.data.message : activitypubConnectedApps.registerError,
					'error'
				);
				return;
			}

			// Build credential notice (matches core's tmpl-new-application-password).
			var $notice = $( '<div></div>' )
				.attr( 'role', 'alert' )
				.attr( 'tabindex', '-1' )
				.addClass( 'notice notice-success is-dismissible new-application-password-notice' );

			// Client ID row.
			var $clientIdRow = $( '<p></p>' ).addClass( 'application-password-display' )
				.append( $( '<label></label>' ).text( activitypubConnectedApps.clientIdLabel ) )
				.append( $( '<input>' ).attr( { type: 'text', readonly: 'readonly' } ).addClass( 'code' ).val( response.data.client_id ) )
				.append(
					$( '<button>' ).attr( 'type', 'button' ).addClass( 'button copy-button' )
						.attr( 'data-clipboard-text', response.data.client_id )
						.text( activitypubConnectedApps.copy )
				)
				.append( $( '<span>' ).addClass( 'success hidden' ).attr( 'aria-hidden', 'true' ).text( activitypubConnectedApps.copied ) );

			$notice.append( $clientIdRow );

			// Client Secret row (if present).
			if ( response.data.client_secret ) {
				var $secretRow = $( '<p></p>' ).addClass( 'application-password-display' )
					.append( $( '<label></label>' ).text( activitypubConnectedApps.clientSecretLabel ) )
					.append( $( '<input>' ).attr( { type: 'text', readonly: 'readonly' } ).addClass( 'code' ).val( response.data.client_secret ) )
					.append(
						$( '<button>' ).attr( 'type', 'button' ).addClass( 'button copy-button' )
							.attr( 'data-clipboard-text', response.data.client_secret )
							.text( activitypubConnectedApps.copy )
					)
					.append( $( '<span>' ).addClass( 'success hidden' ).attr( 'aria-hidden', 'true' ).text( activitypubConnectedApps.copied ) );

				$notice.append( $secretRow );
			}

			$notice.append( $( '<p></p>' ).text( activitypubConnectedApps.saveWarning ) );

			// Dismiss button (matches core's tmpl-new-application-password).
			$notice.append(
				$( '<button>' ).attr( 'type', 'button' ).addClass( 'notice-dismiss' )
					.append( $( '<span>' ).addClass( 'screen-reader-text' ).text( activitypubConnectedApps.dismiss ) )
			);

			// Insert after the form (not inside it), same as core.
			$newAppForm.after( $notice );
			$notice.trigger( 'focus' );

			// Initialize ClipboardJS for the new notice.
			if ( 'undefined' !== typeof ClipboardJS ) {
				new ClipboardJS( '.new-application-password-notice .copy-button' )
					.on( 'success', function( clipEvent ) {
						var $btn = $( clipEvent.trigger );
						$btn.siblings( '.success' ).removeClass( 'hidden' );
						setTimeout( function() {
							$btn.siblings( '.success' ).addClass( 'hidden' );
						}, 3000 );
					} );
			}

			// Add the new client to the registered apps table.
			var $row = $( '<tr>' )
				.attr( 'data-client-id', response.data.client_id )
				.append( $( '<td>' ).text( $name.val().trim() ) )
				.append( $( '<td>' ).text( $redirectUri.val().trim() ) )
				.append( $( '<td>' ).text( response.data.created ) )
				.append(
					$( '<td>' ).append(
						$( '<button>' )
							.addClass( 'button delete' )
							.text( activitypubConnectedApps.deleteLabel )
					)
				);

			$appsTbody.prepend( $row );
			$appsWrapper.show();

			// Clear the form fields.
			$name.val( '' );
			$redirectUri.val( '' );
		} ).fail( function( xhr, textStatus, errorThrown ) {
			var errorMessage = errorThrown;

			if ( xhr.responseJSON && xhr.responseJSON.message ) {
				errorMessage = xhr.responseJSON.message;
			}

			addNotice( errorMessage || activitypubConnectedApps.registerError, 'error' );
		} );
	} );

	// Delete a registered client.
	$appsTbody.on( 'click', '.delete', function( e ) {
		e.preventDefault();

		if ( ! window.confirm( activitypubConnectedApps.confirmDelete ) ) {
			return;
		}

		var $button  = $( this ),
			$tr      = $button.closest( 'tr' ),
			clientId = $tr.data( 'client-id' );

		clearNotices();
		$button.prop( 'disabled', true );

		$.ajax( {
			url: activitypubConnectedApps.ajaxUrl,
			method: 'POST',
			data: {
				action: 'activitypub_delete_oauth_client',
				client_id: clientId,
				_wpnonce: activitypubConnectedApps.nonce
			}
		} ).always( function() {
			$button.prop( 'disabled', false );
		} ).done( function( response ) {
			if ( response.success && response.data.deleted ) {
				if ( 0 === $tr.siblings().length ) {
					$appsWrapper.hide();
				}
				$tr.remove();

				addNotice( activitypubConnectedApps.appDeleted, 'success' ).trigger( 'focus' );
			}
		} ).fail( handleErrorResponse );
	} );

	// Delete all registered clients.
	$deleteAll.on( 'click', function( e ) {
		e.preventDefault();

		if ( ! window.confirm( activitypubConnectedApps.confirmDeleteAll ) ) {
			return;
		}

		var $button = $( this );

		clearNotices();
		$button.prop( 'disabled', true );

		$.ajax( {
			url: activitypubConnectedApps.ajaxUrl,
			method: 'POST',
			data: {
				action: 'activitypub_delete_all_oauth_clients',
				_wpnonce: activitypubConnectedApps.nonce
			}
		} ).always( function() {
			$button.prop( 'disabled', false );
		} ).done( function( response ) {
			if ( response.success && response.data.deleted ) {
				$appsTbody.children().remove();
				$appsWrapper.hide();

				addNotice( activitypubConnectedApps.allAppsDeleted, 'success' ).trigger( 'focus' );
			}
		} ).fail( handleErrorResponse );
	} );

	// Revoke a single token.
	$tokensTbody.on( 'click', '.delete', function( e ) {
		e.preventDefault();

		if ( ! window.confirm( activitypubConnectedApps.confirm ) ) {
			return;
		}

		var $button = $( this ),
			$tr     = $button.closest( 'tr' ),
			metaKey = $tr.data( 'meta-key' );

		clearNotices();
		$button.prop( 'disabled', true );

		$.ajax( {
			url: activitypubConnectedApps.ajaxUrl,
			method: 'POST',
			data: {
				action: 'activitypub_revoke_oauth_token',
				meta_key: metaKey,
				_wpnonce: activitypubConnectedApps.nonce
			}
		} ).always( function() {
			$button.prop( 'disabled', false );
		} ).done( function( response ) {
			if ( response.success && response.data.deleted ) {
				if ( 0 === $tr.siblings().length ) {
					$tokensWrapper.hide();
				}
				$tr.remove();

				addNotice( activitypubConnectedApps.appRevoked, 'success' ).trigger( 'focus' );
			}
		} ).fail( handleErrorResponse );
	} );

	// Revoke all tokens.
	$revokeAll.on( 'click', function( e ) {
		e.preventDefault();

		if ( ! window.confirm( activitypubConnectedApps.confirmAll ) ) {
			return;
		}

		var $button = $( this );

		clearNotices();
		$button.prop( 'disabled', true );

		$.ajax( {
			url: activitypubConnectedApps.ajaxUrl,
			method: 'POST',
			data: {
				action: 'activitypub_revoke_all_oauth_tokens',
				_wpnonce: activitypubConnectedApps.nonce
			}
		} ).always( function() {
			$button.prop( 'disabled', false );
		} ).done( function( response ) {
			if ( response.success && response.data.deleted ) {
				$tokensTbody.children().remove();
				$section.children( '.new-application-password-notice' ).remove();
				$tokensWrapper.hide();

				addNotice( activitypubConnectedApps.allAppsRevoked, 'success' ).trigger( 'focus' );
			}
		} ).fail( handleErrorResponse );
	} );

	// Dismiss notices via event delegation on the section (same as core).
	$section.on( 'click', '.notice-dismiss', function( e ) {
		e.preventDefault();
		var $el = $( this ).parent();
		$el.removeAttr( 'role' );
		$el.fadeTo( 100, 0, function() {
			$el.slideUp( 100, function() {
				$el.remove();
				$newAppFields.first().trigger( 'focus' );
			} );
		} );
	} );

	// Submit form on Enter key in input fields (same as core).
	$newAppFields.on( 'keypress', function( e ) {
		if ( 13 === e.which ) {
			e.preventDefault();
			$newAppButton.trigger( 'click' );
		}
	} );

	/**
	 * Handles an error response from the AJAX call.
	 *
	 * @param {jqXHR}  xhr         The XHR object from the ajax call.
	 * @param {string} textStatus  The string categorizing the ajax request's status.
	 * @param {string} errorThrown The HTTP status error text.
	 */
	function handleErrorResponse( xhr, textStatus, errorThrown ) {
		var errorMessage = errorThrown;

		if ( xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message ) {
			errorMessage = xhr.responseJSON.data.message;
		}

		addNotice( errorMessage, 'error' );
	}

	/**
	 * Displays a notice message in the Connected Applications section.
	 *
	 * @param {string} message The message to display.
	 * @param {string} type    The notice type. Either 'success' or 'error'.
	 * @returns {jQuery} The notice element.
	 */
	function addNotice( message, type ) {
		var $notice = $( '<div></div>' )
			.attr( 'role', 'alert' )
			.attr( 'tabindex', '-1' )
			.addClass( 'is-dismissible notice notice-' + type )
			.append( $( '<p></p>' ).text( message ) )
			.append(
				$( '<button></button>' )
					.attr( 'type', 'button' )
					.addClass( 'notice-dismiss' )
					.append( $( '<span></span>' ).addClass( 'screen-reader-text' ).text( activitypubConnectedApps.dismiss ) )
			);

		$newAppForm.after( $notice );

		return $notice;
	}

	/**
	 * Clears notice messages from the Connected Applications section.
	 */
	function clearNotices() {
		$( '.notice', $section ).remove();
	}
}( jQuery ) );
