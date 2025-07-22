/**
 * ActivityPub Following List Table Polling.
 *
 * Adds polling functionality to the Following list table to check for status updates
 * of pending follow requests without requiring manual page refresh.
 *
 * @package Activitypub
 */

( function ( $ ) {
	'use strict';

	/**
	 * Following List Table Polling.
	 */
	var ActivityPubFollowing = {
		/**
		 * Initialize the polling functionality.
		 */
		init: function () {
			this.setupHeartbeatListeners();

			// Check every 5 seconds. It'll automatically slow down after 2 mins 30 secs.
			window.wp.heartbeat.interval( 'fast' );
		},

		/**
		 * Set up WordPress Heartbeat API listeners.
		 */
		setupHeartbeatListeners: function () {
			// Add our data to the Heartbeat API request.
			$( document ).on( 'heartbeat-send.activitypub_following', function ( e, data ) {
				data.activitypub_following_check = {
					user_id: ActivityPubFollowingSettings.user_id,
					pending_ids: ActivityPubFollowing.getPendingIds(),
				};
			} );

			// Process the Heartbeat API response.
			$( document ).on( 'heartbeat-tick.activitypub_following', function ( e, data ) {
				if ( data.activitypub_following ) {
					ActivityPubFollowing.processUpdates( data.activitypub_following );
				}
			} );
		},

		/**
		 * Get IDs of all pending follow requests currently displayed in the table.
		 *
		 * @return {Array} Array of pending follow request IDs.
		 */
		getPendingIds: function () {
			var pendingIds = [];

			// Find all rows with pending status.
			$( '.wp-list-table tr.status-pending' ).each( function () {
				var id = $( this ).attr( 'id' );

				if ( id ) {
					// Extract the numeric ID from the row ID (e.g., "following-123" -> "123").
					pendingIds.push( id.replace( /^following-(\d+)$/, '$1' ) );
				}
			} );

			return pendingIds;
		},

		/**
		 * Process updates received from the server.
		 *
		 * @param {Object} response Response data from the server.
		 */
		processUpdates: function ( response ) {
			if ( response.counts ) {
				// Update the counts in the views navigation.
				if ( Object.hasOwn( response.counts, 'all' ) ) {
					$( '.subsubsub .all .count' ).text( '(' + response.counts.all + ')' );
				}
				if ( Object.hasOwn( response.counts, 'accepted' ) ) {
					$( '.subsubsub .accepted .count' ).text( '(' + response.counts.accepted + ')' );
				}
				if ( Object.hasOwn( response.counts, 'pending' ) ) {
					$( '.subsubsub .pending .count' ).text( '(' + response.counts.pending + ')' );

					// Remove heartbeat listeners when there are no more pending follows.
					if ( 0 === response.counts.pending ) {
						$( document ).off( 'heartbeat-send.activitypub_following' );
						$( document ).off( 'heartbeat-tick.activitypub_following' );
						window.wp.heartbeat.interval( 60 );
					}
				}
			}

			if ( ! response.updated_items || ! response.updated_items.length ) {
				return;
			}

			// Remove any existing notices.
			$( 'div.notice' ).remove();

			var $listTable = $( '#the-list' );

			// Process each updated item.
			$.each( response.updated_items, function ( index, item ) {
				var $row = $( '#following-' + item.id );

				if ( $row.length && item.status === 'accepted' ) {
					// Remove the row when we're in the "Pending" view.
					if ( 'pending' === new URLSearchParams( window.location.search ).get( 'status' ) ) {
						$row.remove();
					} else {
						$row.find( 'strong.pending' ).remove();
					}

					if ( 0 === $listTable.children().length ) {
						$listTable.append(
							'<tr class="no-items"><td class="colspanchange" colspan="5">' + response.no_items + '</td></tr>'
						);
					}
				}
			} );
		},
	};

	// Initialize on document ready.
	$( document ).ready( function () {
		ActivityPubFollowing.init();
	} );
} )( jQuery );
