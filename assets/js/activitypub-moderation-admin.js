/**
 * ActivityPub Moderation Admin JavaScript
 */

(function( $ ) {
	'use strict';

	/**
	 * Helper function to validate domain format
	 */
	function isValidDomain( domain ) {
		// Basic domain validation - must contain at least one dot and valid characters
		var domainRegex = /^[a-zA-Z0-9]([a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(\.[a-zA-Z0-9]([a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)*$/;
		return domainRegex.test( domain ) && domain.includes( '.' ) && domain.length > 3;
	}

	/**
	 * Helper function to check if a term already exists in the UI
	 */
	function isTermAlreadyBlocked( type, value, context, userId ) {
		var selector;
		if ( context === 'user' ) {
			selector = '.activitypub-user-block-list[data-user-id="' + userId + '"] .remove-user-block-btn[data-type="' + type + '"][data-value="' + value + '"]';
		} else if ( context === 'site' ) {
			selector = '.remove-site-block-btn[data-type="' + type + '"][data-value="' + value + '"]';
		}
		return $( selector ).length > 0;
	}

	/**
	 * Helper function to add a blocked term to the UI
	 */
	function addBlockedTermToUI( type, value, context, userId ) {
		if ( context === 'user' ) {
			// For user moderation, add to the appropriate table
			var container = $( '.activitypub-user-block-list[data-user-id="' + userId + '"]' );

			var table = container.find( '.activitypub-blocked-' + type );
			if ( table.length === 0 ) {
				table = $( '<table class="widefat striped activitypub-blocked-' + type + '" role="presentation" style="max-width: 500px; margin: 15px 0;"><tbody></tbody></table>' );
				container.find( '#new_user_' + type ).closest( '.add-user-block-form' ).before( table );
			}
			table.append( '<tr><td>' + value + '</td><td style="width: 80px;"><button type="button" class="button button-small remove-user-block-btn" data-type="' + type + '" data-value="' + value + '">Remove</button></td></tr>' );
		} else if ( context === 'site' ) {
			// For site moderation, add to the appropriate table
			var container = $( '#new_site_' + type ).closest( '.activitypub-site-block-list' );
			var table = container.find( '.activitypub-site-blocked-' + type );
			if ( table.length === 0 ) {
				table = $( '<table class="widefat striped activitypub-site-blocked-' + type + '" role="presentation" style="max-width: 500px; margin: 15px 0;"><tbody></tbody></table>' );
				container.find( '.add-site-block-form' ).before( table );
			}
			table.append( '<tr><td>' + value + '</td><td style="width: 80px;"><button type="button" class="button button-small remove-site-block-btn" data-type="' + type + '" data-value="' + value + '">Remove</button></td></tr>' );
		}
	}

	/**
	 * Helper function to remove a blocked term from the UI
	 */
	function removeBlockedTermFromUI( type, value, context ) {
		// Find and remove the specific blocked term element
		var selector = '.remove-' + context + '-block-btn[data-type="' + type + '"][data-value="' + value + '"]';
		var button = $( selector );

		if ( button.length > 0 ) {
			// Remove the parent table row
			var parent = button.closest( 'tr' );
			var container = parent.closest( 'table' );
			parent.remove();

			// If the container is now empty, remove it
			if ( container.find( 'tr' ).length === 0 ) {
				container.remove();
			}
		}
	}

	/**
	 * Initialize moderation functionality
	 */
	function init() {
		// User moderation management.
		initUserModeration();

		// Site moderation management.
		initSiteModeration();
	}

	/**
	 * Initialize user moderation management
	 */
	function initUserModeration() {
		// Function to add user blocked term.
		function addUserBlockedTerm( type, userId ) {
			var input = $( '#new_user_' + type );
			var value = input.val().trim();

			if ( ! value ) {
				// Use wp.a11y.speak for better accessibility.
				if ( wp.a11y && wp.a11y.speak ) {
					wp.a11y.speak( activitypubModerationL10n.enterValue, 'assertive' );
				} else {
					alert( activitypubModerationL10n.enterValue );
				}
				return;
			}

			// Validate domain format if this is a domain block
			if ( type === 'domain' && ! isValidDomain( value ) ) {
				var message = activitypubModerationL10n.invalidDomain || 'Please enter a valid domain (e.g., example.com).';
				if ( wp.a11y && wp.a11y.speak ) {
					wp.a11y.speak( message, 'assertive' );
				}
				alert( message );
				return;
			}

			// Check if the term is already blocked
			if ( isTermAlreadyBlocked( type, value, 'user', userId ) ) {
				var message = activitypubModerationL10n.alreadyBlocked || 'This term is already blocked.';
				if ( wp.a11y && wp.a11y.speak ) {
					wp.a11y.speak( message, 'assertive' );
				}
				alert( message );
				return;
			}

			wp.ajax.post( 'activitypub_moderation_settings', {
				context: 'user',
				operation: 'add',
				user_id: userId,
				type: type,
				value: value,
				_wpnonce: activitypubModerationL10n.nonce
			}).done( function() {
				// Clear input and add item to the UI.
				input.val( '' );
				addBlockedTermToUI( type, value, 'user', userId );
			}).fail( function( response ) {
				var message = response && response.message ? response.message : activitypubModerationL10n.addBlockFailed;
				if ( wp.a11y && wp.a11y.speak ) {
					wp.a11y.speak( message, 'assertive' );
				} else {
					alert( message );
				}
			});
		}

		// Function to remove user blocked term.
		function removeUserBlockedTerm( type, value, userId ) {
			wp.ajax.post( 'activitypub_moderation_settings', {
				context: 'user',
				operation: 'remove',
				user_id: userId,
				type: type,
				value: value,
				_wpnonce: activitypubModerationL10n.nonce
			}).done( function() {
				removeBlockedTermFromUI( type, value, 'user' );
			}).fail( function( response ) {
				var message = response && response.message ? response.message : activitypubModerationL10n.removeBlockFailed;
				if ( wp.a11y && wp.a11y.speak ) {
					wp.a11y.speak( message, 'assertive' );
				} else {
					alert( message );
				}
			});
		}

		// Add user block functionality (button click).
		$( document ).on( 'click', '.add-user-block-btn', function( e ) {
			e.preventDefault();
			var type = $( this ).data( 'type' );
			var userId = $( this ).closest( '.activitypub-user-block-list' ).data( 'user-id' );
			addUserBlockedTerm( type, userId );
		});

		// Add user block functionality (Enter key).
		$( document ).on( 'keypress', '#new_user_domain, #new_user_keyword', function( e ) {
			if ( e.which === 13 ) { // Enter key.
				e.preventDefault();
				var inputId = $( this ).attr( 'id' );
				var type = inputId.replace( 'new_user_', '' );
				var userId = $( this ).closest( '.activitypub-user-block-list' ).data( 'user-id' );
				addUserBlockedTerm( type, userId );
			}
		});

		// Remove user block functionality.
		$( document ).on( 'click', '.remove-user-block-btn', function( e ) {
			e.preventDefault();
			var type = $( this ).data( 'type' );
			var value = $( this ).data( 'value' );
			var userId = $( this ).closest( '.activitypub-user-block-list' ).data( 'user-id' );
			removeUserBlockedTerm( type, value, userId );
		});
	}

	/**
	 * Initialize site moderation management
	 */
	function initSiteModeration() {
		// Function to add site blocked term.
		function addSiteBlockedTerm( type ) {
			var input = $( '#new_site_' + type );
			var value = input.val().trim();

			if ( ! value ) {
				if ( wp.a11y && wp.a11y.speak ) {
					wp.a11y.speak( activitypubModerationL10n.enterValue, 'assertive' );
				} else {
					alert( activitypubModerationL10n.enterValue );
				}
				return;
			}

			// Validate domain format if this is a domain block
			if ( type === 'domain' && ! isValidDomain( value ) ) {
				var message = activitypubModerationL10n.invalidDomain || 'Please enter a valid domain (e.g., example.com).';
				if ( wp.a11y && wp.a11y.speak ) {
					wp.a11y.speak( message, 'assertive' );
				}
				alert( message );
				return;
			}

			// Check if the term is already blocked
			if ( isTermAlreadyBlocked( type, value, 'site' ) ) {
				var message = activitypubModerationL10n.alreadyBlocked || 'This term is already blocked.';
				if ( wp.a11y && wp.a11y.speak ) {
					wp.a11y.speak( message, 'assertive' );
				}
				alert( message );
				return;
			}

			wp.ajax.post( 'activitypub_moderation_settings', {
				context: 'site',
				operation: 'add',
				type: type,
				value: value,
				_wpnonce: activitypubModerationL10n.nonce
			}).done( function() {
				// Clear input and add item to the UI.
				input.val( '' );
				addBlockedTermToUI( type, value, 'site' );
			}).fail( function( response ) {
				var message = response && response.message ? response.message : activitypubModerationL10n.addBlockFailed;
				if ( wp.a11y && wp.a11y.speak ) {
					wp.a11y.speak( message, 'assertive' );
				} else {
					alert( message );
				}
			});
		}

		// Function to remove site blocked term.
		function removeSiteBlockedTerm( type, value ) {
			wp.ajax.post( 'activitypub_moderation_settings', {
				context: 'site',
				operation: 'remove',
				type: type,
				value: value,
				_wpnonce: activitypubModerationL10n.nonce
			}).done( function() {
				removeBlockedTermFromUI( type, value, 'site' );
			}).fail( function( response ) {
				var message = response && response.message ? response.message : activitypubModerationL10n.removeBlockFailed;
				if ( wp.a11y && wp.a11y.speak ) {
					wp.a11y.speak( message, 'assertive' );
				} else {
					alert( message );
				}
			});
		}

		// Add site block functionality (button click).
		$( document ).on( 'click', '.add-site-block-btn', function( e ) {
			e.preventDefault();
			var type = $( this ).data( 'type' );
			addSiteBlockedTerm( type );
		});

		// Add site block functionality (Enter key).
		$( document ).on( 'keypress', '#new_site_domain, #new_site_keyword', function( e ) {
			if ( e.which === 13 ) { // Enter key.
				e.preventDefault();
				var inputId = $( this ).attr( 'id' );
				var type = inputId.replace( 'new_site_', '' );
				addSiteBlockedTerm( type );
			}
		});

		// Remove site block functionality.
		$( document ).on( 'click', '.remove-site-block-btn', function( e ) {
			e.preventDefault();
			var type = $( this ).data( 'type' );
			var value = $( this ).data( 'value' );
			removeSiteBlockedTerm( type, value );
		});
	}

	// Initialize when document is ready.
	$( document ).ready( init );

})( jQuery );
