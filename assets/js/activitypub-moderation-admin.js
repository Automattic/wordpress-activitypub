/**
 * ActivityPub Moderation Admin JavaScript
 */

(function( $ ) {
	'use strict';

	/**
	 * Initialize moderation functionality
	 */
	function init() {
		// User block management.
		initUserBlocks();

		// Site block management.
		initSiteBlocks();
	}

	/**
	 * Initialize user block management
	 */
	function initUserBlocks() {
		// Function to add user block.
		function addUserBlock( type, userId ) {
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

			wp.ajax.post( 'activitypub_add_user_block', {
				user_id: userId,
				type: type,
				value: value,
				_wpnonce: activitypubModerationL10n.userNonce
			}).done( function() {
				// Clear input and reload page.
				input.val( '' );
				location.reload();
			}).fail( function( response ) {
				var message = response && response.message ? response.message : activitypubModerationL10n.addBlockFailed;
				if ( wp.a11y && wp.a11y.speak ) {
					wp.a11y.speak( message, 'assertive' );
				} else {
					alert( message );
				}
			});
		}

		// Function to remove user block.
		function removeUserBlock( type, value, userId ) {
			wp.ajax.post( 'activitypub_remove_user_block', {
				user_id: userId,
				type: type,
				value: value,
				_wpnonce: activitypubModerationL10n.userNonce
			}).done( function() {
				location.reload();
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
			addUserBlock( type, userId );
		});

		// Add user block functionality (Enter key).
		$( document ).on( 'keypress', '#new_user_actor, #new_user_domain, #new_user_keyword', function( e ) {
			if ( e.which === 13 ) { // Enter key.
				e.preventDefault();
				var inputId = $( this ).attr( 'id' );
				var type = inputId.replace( 'new_user_', '' );
				var userId = $( this ).closest( '.activitypub-user-block-list' ).data( 'user-id' );
				addUserBlock( type, userId );
			}
		});

		// Remove user block functionality.
		$( document ).on( 'click', '.remove-user-block-btn', function( e ) {
			e.preventDefault();
			var type = $( this ).data( 'type' );
			var value = $( this ).data( 'value' );
			var userId = $( this ).closest( '.activitypub-user-block-list' ).data( 'user-id' );
			removeUserBlock( type, value, userId );
		});
	}

	/**
	 * Initialize site block management
	 */
	function initSiteBlocks() {
		// Function to add site block.
		function addSiteBlock( type ) {
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

			wp.ajax.post( 'activitypub_add_site_block', {
				type: type,
				value: value,
				_wpnonce: activitypubModerationL10n.siteNonce
			}).done( function() {
				// Clear input and reload page.
				input.val( '' );
				location.reload();
			}).fail( function( response ) {
				var message = response && response.message ? response.message : activitypubModerationL10n.addBlockFailed;
				if ( wp.a11y && wp.a11y.speak ) {
					wp.a11y.speak( message, 'assertive' );
				} else {
					alert( message );
				}
			});
		}

		// Function to remove site block.
		function removeSiteBlock( type, value ) {
			wp.ajax.post( 'activitypub_remove_site_block', {
				type: type,
				value: value,
				_wpnonce: activitypubModerationL10n.siteNonce
			}).done( function() {
				location.reload();
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
			addSiteBlock( type );
		});

		// Add site block functionality (Enter key).
		$( document ).on( 'keypress', '#new_site_actors, #new_site_domains, #new_site_keywords', function( e ) {
			if ( e.which === 13 ) { // Enter key.
				e.preventDefault();
				var inputId = $( this ).attr( 'id' );
				var type = inputId.replace( 'new_site_', '' );
				addSiteBlock( type );
			}
		});

		// Remove site block functionality.
		$( document ).on( 'click', '.remove-site-block-btn', function( e ) {
			e.preventDefault();
			var type = $( this ).data( 'type' );
			var value = $( this ).data( 'value' );
			removeSiteBlock( type, value );
		});
	}

	// Initialize when document is ready.
	$( document ).ready( init );

})( jQuery );