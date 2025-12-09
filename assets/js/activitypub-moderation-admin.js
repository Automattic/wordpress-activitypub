/**
 * ActivityPub Moderation Admin JavaScript
 */

/* global activitypubModerationL10n, jQuery */

/**
 * @param {Object} $  - jQuery
 * @param {Object} wp - WordPress global object
 * @param {Object} wp.i18n - Internationalization functions
 * @param {Object} wp.a11y - Accessibility functions
 * @param {Object} wp.ajax - AJAX functions
 */
(function( $, wp ) {
	'use strict';

	var __ = wp.i18n.__;
	var _n = wp.i18n._n;
	var sprintf = wp.i18n.sprintf;

	/**
	 * Helper function to show a message using wp.a11y and alert
	 *
	 * @param {string} message - The message to display
	 */
	function showMessage( message ) {
		if ( wp.a11y && wp.a11y.speak ) {
			wp.a11y.speak( message, 'assertive' );
		}
		alert( message );
	}

	/**
	 * Helper function to validate domain format
	 *
	 * @param {string} domain - The domain to validate
	 * @return {boolean} Whether the domain is valid
	 */
	function isValidDomain( domain ) {
		// Basic domain validation - must contain at least one dot and valid characters
		var domainRegex = /^[a-zA-Z0-9]([a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(\.[a-zA-Z0-9]([a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)*$/;
		return domainRegex.test( domain ) && domain.includes( '.' ) && domain.length > 3;
	}

	/**
	 * Helper function to check if a term already exists in the UI
	 *
	 * @param {string}      type    - The type of block (domain or keyword)
	 * @param {string}      value   - The value to check
	 * @param {string}      context - The context (user or site)
	 * @param {number|null} userId  - The user ID (for user context)
	 * @return {boolean} Whether the term is already blocked
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
	 * Validate a blocked term value
	 *
	 * @param {string}      type    - The type of block (domain or keyword)
	 * @param {string}      value   - The value to validate
	 * @param {string}      context - The context (user or site)
	 * @param {number|null} userId  - The user ID (for user context)
	 * @return {boolean} Whether the value is valid
	 */
	function validateBlockedTerm( type, value, context, userId ) {
		if ( ! value ) {
			showMessage( __( 'Please enter a value to block.', 'activitypub' ) );
			return false;
		}

		if ( type === 'domain' && ! isValidDomain( value ) ) {
			showMessage( __( 'Please enter a valid domain (e.g., example.com).', 'activitypub' ) );
			return false;
		}

		if ( isTermAlreadyBlocked( type, value, context, userId ) ) {
			showMessage( __( 'This term is already blocked.', 'activitypub' ) );
			return false;
		}

		return true;
	}

	/**
	 * Create a table row for a blocked term.
	 *
	 * @param {string} type    - The type of block (domain or keyword)
	 * @param {string} value   - The blocked value
	 * @param {string} context - The context (user or site)
	 * @return {jQuery} The constructed table row
	 */
	function createBlockedTermRow( type, value, context ) {
		var $button = $( '<button>', {
			type: 'button',
			'class': 'button button-small remove-' + context + '-block-btn',
			'data-type': type,
			'data-value': value,
			text: __( 'Remove', 'activitypub' )
		} );

		return $( '<tr>' ).append( $( '<td>' ).text( value ), $( '<td>' ).append( $button ) );
	}

	/**
	 * Helper function to add a blocked term to the UI
	 */
	function addBlockedTermToUI( type, value, context, userId ) {
		var table;

		if ( context === 'user' ) {
			// For user moderation, add to the appropriate table
			var container = $( '.activitypub-user-block-list[data-user-id="' + userId + '"]' );

			table = container.find( '.activitypub-blocked-' + type );
			if ( table.length === 0 ) {
				table = $( '<table class="widefat striped activitypub-blocked-' + type + '" role="presentation" style="max-width: 500px; margin: 15px 0;"><tbody></tbody></table>' );
				container.find( '#new_user_' + type ).closest( '.add-user-block-form' ).before( table );
			}
			table.find( 'tbody' ).append( createBlockedTermRow( type, value, context ) );
		} else if ( context === 'site' ) {
			// For site moderation, add to the table inside the details element
			var details = $( '.activitypub-site-block-details[data-type="' + type + '"]' );
			table = details.find( '.activitypub-site-blocked-' + type );

			if ( table.length === 0 ) {
				// Create table inside the details element (after summary)
				table = $( '<table class="widefat striped activitypub-site-blocked-' + type + '" role="presentation"><tbody></tbody></table>' );
				details.find( 'summary' ).after( table );
			}

			table.find( 'tbody' ).append( createBlockedTermRow( type, value, context ) );

			updateSiteBlockSummary( type );
		}
	}

	/**
	 * Helper function to update the site block summary count
	 */
	function updateSiteBlockSummary( type ) {
		var details = $( '.activitypub-site-block-details[data-type="' + type + '"]' );
		var table = details.find( '.activitypub-site-blocked-' + type );
		var count = table.find( 'tbody tr' ).length || table.find( 'tr' ).length;
		var summary = details.find( 'summary' );

		if ( count === 0 ) {
			// Empty state
			var emptyText = type === 'domain'
				? __( 'No blocked domains', 'activitypub' )
				: __( 'No blocked keywords', 'activitypub' );
			summary.text( emptyText );
			details.attr( 'open', '' );
			table.remove();
		} else {
			// Has items - use _n for proper pluralization
			var text = type === 'domain'
				? _n( '%s blocked domain', '%s blocked domains', count, 'activitypub' )
				: _n( '%s blocked keyword', '%s blocked keywords', count, 'activitypub' );
			summary.text( sprintf( text, count ) );
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
			button.closest( 'tr' ).remove();

			// Update the summary count for site blocks
			if ( context === 'site' ) {
				updateSiteBlockSummary( type );
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

		// Blocklist subscriptions management.
		initBlocklistSubscriptions();
	}

	/**
	 * Initialize user moderation management
	 */
	function initUserModeration() {
		// Function to add user blocked term.
		function addUserBlockedTerm( type, userId ) {
			var input = $( '#new_user_' + type );
			var value = input.val().trim();

			if ( ! validateBlockedTerm( type, value, 'user', userId ) ) {
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
				var message = response && response.message ? response.message : __( 'Failed to add block.', 'activitypub' );
				showMessage( message );
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
				var message = response && response.message ? response.message : __( 'Failed to remove block.', 'activitypub' );
				showMessage( message );
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

			if ( ! validateBlockedTerm( type, value, 'site', null ) ) {
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
				var message = response && response.message ? response.message : __( 'Failed to add block.', 'activitypub' );
				showMessage( message );
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
				var message = response && response.message ? response.message : __( 'Failed to remove block.', 'activitypub' );
				showMessage( message );
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

	/**
	 * Initialize blocklist subscriptions management
	 */
	function initBlocklistSubscriptions() {
		// Function to add a blocklist subscription.
		function addBlocklistSubscription( url ) {
			if ( ! url ) {
				var message = activitypubModerationL10n.enterUrl || 'Please enter a URL.';
				if ( wp.a11y && wp.a11y.speak ) {
					wp.a11y.speak( message, 'assertive' );
				}
				alert( message );
				return;
			}

			// Disable the button while processing.
			var button = $( '.add-blocklist-subscription-btn' );
			button.prop( 'disabled', true );

			wp.ajax.post( 'activitypub_blocklist_subscription', {
				operation: 'add',
				url: url,
				_wpnonce: activitypubModerationL10n.nonce
			}).done( function() {
				// Reload the page to show the updated list.
				window.location.reload();
			}).fail( function( response ) {
				var message = response && response.message ? response.message : activitypubModerationL10n.subscriptionFailed || 'Failed to add subscription.';
				if ( wp.a11y && wp.a11y.speak ) {
					wp.a11y.speak( message, 'assertive' );
				}
				alert( message );
				button.prop( 'disabled', false );
			});
		}

		// Function to remove a blocklist subscription.
		function removeBlocklistSubscription( url ) {
			wp.ajax.post( 'activitypub_blocklist_subscription', {
				operation: 'remove',
				url: url,
				_wpnonce: activitypubModerationL10n.nonce
			}).done( function() {
				// Remove the row from the UI.
				$( '.remove-blocklist-subscription-btn' ).filter( function() {
					return $( this ).data( 'url' ) === url;
				}).closest( 'tr' ).remove();

				// If no more subscriptions, remove the table.
				var table = $( '.activitypub-blocklist-subscriptions table' );
				if ( table.find( 'tbody tr' ).length === 0 ) {
					table.remove();
				}
			}).fail( function( response ) {
				var message = response && response.message ? response.message : activitypubModerationL10n.removeSubscriptionFailed || 'Failed to remove subscription.';
				if ( wp.a11y && wp.a11y.speak ) {
					wp.a11y.speak( message, 'assertive' );
				}
				alert( message );
			});
		}

		// Add subscription functionality (button click).
		$( document ).on( 'click', '.add-blocklist-subscription-btn', function( e ) {
			e.preventDefault();
			var url = $( this ).data( 'url' ) || $( '#new_blocklist_subscription_url' ).val().trim();
			addBlocklistSubscription( url );
		});

		// Add subscription functionality (Enter key).
		$( document ).on( 'keypress', '#new_blocklist_subscription_url', function( e ) {
			if ( e.which === 13 ) { // Enter key.
				e.preventDefault();
				var url = $( this ).val().trim();
				addBlocklistSubscription( url );
			}
		});

		// Remove subscription functionality.
		$( document ).on( 'click', '.remove-blocklist-subscription-btn', function( e ) {
			e.preventDefault();
			var url = $( this ).data( 'url' );
			removeBlocklistSubscription( url );
		});
	}

	// Initialize when document is ready.
	$( document ).ready( init );

})( jQuery, wp );
