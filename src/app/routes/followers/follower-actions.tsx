/**
 * Action handlers for follower management.
 */

/**
 * WordPress dependencies
 */
import apiFetch from '@wordpress/api-fetch';
import { dispatch } from '@wordpress/data';
import { store as noticesStore } from '@wordpress/notices';
import { __, _n } from '@wordpress/i18n';
import type { Action } from '@wordpress/dataviews/wp';

/**
 * Internal dependencies
 */
import type { Actor } from '../../types';

// Get dispatch functions and namespace once for all action handlers
const { createSuccessNotice, createErrorNotice } = dispatch( noticesStore );
const coreDispatch = dispatch( 'core' ) as any;
const namespace = 'activitypub/1.0'; // Standard ActivityPub REST API namespace

/**
 * Delete follower action handler.
 *
 * @param items Array of actors to remove as followers.
 */
export async function deleteFollower( items: Actor[] ): Promise< void > {
	try {
		// Delete each follower relationship.
		const deletePromises: Promise< unknown >[] = items.map(
			( item: Actor ): Promise< unknown > =>
				apiFetch( {
					path: `/${ namespace }/admin/actors/${ item.id }/unfollow`,
					method: 'DELETE',
				} )
		);

		await Promise.all( deletePromises );

		// Refresh the entity records.
		coreDispatch.invalidateResolutionForStoreSelector( 'getEntityRecords' );

		await createSuccessNotice( _n( 'Follower removed.', 'Followers removed.', items.length, 'activitypub' ), {
			type: 'snackbar',
		} );
	} catch ( error ) {
		await createErrorNotice( __( 'Failed to remove followers.', 'activitypub' ), { type: 'snackbar' } );
	}
}

/**
 * Block actor action handler.
 *
 * @param items Array of actors to block.
 */
export async function blockActor( items: Actor[] ): Promise< void > {
	try {
		const blockPromises: Promise< unknown >[] = items.map(
			( item: Actor ): Promise< unknown > =>
				apiFetch( {
					path: `/${ namespace }/admin/actors/${ item.id }/block`,
					method: 'POST',
					data: {
						site_wide: false, // User-specific block by default
					},
				} )
		);

		await Promise.all( blockPromises );

		// Refresh the entity records.
		coreDispatch.invalidateResolutionForStoreSelector( 'getEntityRecords' );

		await createSuccessNotice( _n( 'Account blocked.', 'Accounts blocked.', items.length, 'activitypub' ), {
			type: 'snackbar',
		} );
	} catch ( error ) {
		await createErrorNotice( __( 'Failed to block accounts.', 'activitypub' ), { type: 'snackbar' } );
	}
}

/**
 * Follow back action handler.
 *
 * @param items Array of actors to follow back.
 */
export async function follow( items: Actor[] ): Promise< void > {
	try {
		const followPromises: Promise< unknown >[] = items.map(
			( item: Actor ): Promise< unknown > =>
				apiFetch( {
					path: `/${ namespace }/admin/actors/${ item.id }/follow`,
					method: 'POST',
				} )
		);

		await Promise.all( followPromises );

		// Refresh the entity records.
		coreDispatch.invalidateResolutionForStoreSelector( 'getEntityRecords' );

		await createSuccessNotice( _n( 'Account followed.', 'Accounts followed.', items.length, 'activitypub' ), {
			type: 'snackbar',
		} );
	} catch ( error ) {
		await createErrorNotice( __( 'Failed to follow accounts.', 'activitypub' ), { type: 'snackbar' } );
	}
}

/**
 * Get all available actions for the DataViews component.
 */
export function getFollowerActions(): Action< Actor >[] {
	const followingEnabled = false; // TODO: Replace with actual setting check.

	const actions: Action< Actor >[] = [
		{
			id: 'block',
			label: __( 'Block', 'activitypub' ),
			callback: blockActor,
		},
		{
			id: 'delete',
			label: __( 'Remove', 'activitypub' ),
			callback: deleteFollower,
		},
	];

	// Add follow back action if following UI is enabled.
	if ( followingEnabled ) {
		actions.unshift( {
			id: 'follow',
			label: __( 'Follow Back', 'activitypub' ),
			isEligible: ( item: Actor ) => ! item.follow_status?.follows_back,
			callback: follow,
		} );
	}

	return actions;
}
