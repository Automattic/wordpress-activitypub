/**
 * ActivityPub Following List Table Polling
 *
 * Adds polling functionality to the Following list table to check for status updates
 * of pending follow requests without requiring manual page refresh.
 *
 * @package Activitypub
 */

( function ( $ ) {
	'use strict';

	/**
	 * Following List Table Polling
	 */
	var ActivityPubFollowing = {
		/**
		 * Initialize the polling functionality
		 */
		init: function () {
			this.setupHeartbeatListeners();

			// Log that polling is active (for debugging)
			if ( window.console && window.console.log ) {
				console.log(
					'ActivityPub Following polling initialized with ' +
						( wp.heartbeat ? wp.heartbeat.interval() : 'default' ) +
						' second interval'
				);
			}
		},

		/**
		 * Set up WordPress Heartbeat API listeners
		 */
		setupHeartbeatListeners: function () {
			// Add our data to the Heartbeat API request
			$( document ).on( 'heartbeat-send', function ( e, data ) {
				data.activitypub_following_check = {
					screen_id: ActivityPubFollowingSettings.screen_id,
					user_id: ActivityPubFollowingSettings.user_id,
					pending_ids: ActivityPubFollowing.getPendingIds(),
				};
			} );

			// Process the Heartbeat API response
			$( document ).on( 'heartbeat-tick', function ( e, data ) {
				if ( data.activitypub_following_response ) {
					ActivityPubFollowing.processUpdates( data.activitypub_following_response );
				}
			} );
		},

		/**
		 * Get IDs of all pending follow requests currently displayed in the table
		 *
		 * @return {Array} Array of pending follow request IDs
		 */
		getPendingIds: function () {
			var pendingIds = [];

			// Find all rows with pending status.
			$( '.wp-list-table tr.status-pending' ).each( function () {
				var id = $( this ).attr( 'id' );
				if ( id ) {
					// Extract the numeric ID from the row ID (e.g., "following-123" -> "123")
					var numericId = id.replace( /^following-(\d+)$/, '$1' );
					pendingIds.push( numericId );
				}
			} );

			return pendingIds;
		},

		/**
		 * Process updates received from the server
		 *
		 * @param {Object} response Response data from the server
		 */
		processUpdates: function ( response ) {
			if ( ! response.updated_items || ! response.updated_items.length ) {
				return;
			}

			var hasUpdates = false;

			// Process each updated item
			$.each( response.updated_items, function ( index, item ) {
				var $row = $( '#following-' + item.id );

				if ( $row.length ) {
					// Update the row status
					if ( item.status === 'accepted' ) {
						$row.find( 'strong.pending' ).remove();
						hasUpdates = true;
					}
				}
			} );

			// If we have updates, update the counts
			if ( hasUpdates && response.counts ) {
				// Update the counts in the views navigation
				if ( response.counts.all ) {
					$( '.subsubsub .all .count' ).text( '(' + response.counts.all + ')' );
				}
				if ( response.counts.accepted ) {
					$( '.subsubsub .accepted .count' ).text( '(' + response.counts.accepted + ')' );
				}
				if ( response.counts.pending ) {
					$( '.subsubsub .pending .count' ).text( '(' + response.counts.pending + ')' );
				}

				// Show a notification
				ActivityPubFollowing.showNotification(
					response.message || wp.i18n.__( 'Follow requests updated.', 'activitypub' )
				);
			}
		},

		/**
		 * Show a notification message
		 *
		 * @param {string} message The message to display
		 */
		showNotification: function ( message ) {
			var $notice = $( '<div class="notice notice-success is-dismissible"><p>' + message + '</p></div>' );

			// Remove any existing notices
			$( '.activitypub-following-notice' ).remove();

			// Add the new notice
			$notice.addClass( 'activitypub-following-notice' ).insertAfter( '.wp-header-end' );

			// Make it dismissible
			if ( wp.notices && wp.notices.removeDismissNotice ) {
				wp.notices.removeDismissNotice( $notice );
			}
		},
	};

	// Initialize on document ready
	$( document ).ready( function () {
		ActivityPubFollowing.init();
	} );
} )( jQuery );
