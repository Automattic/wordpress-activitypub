/**
 * Action handlers for follower management.
 */

import apiFetch from '@wordpress/api-fetch';
import { dispatch } from '@wordpress/data';
import { store as noticesStore } from '@wordpress/notices';
import { __, _n } from '@wordpress/i18n';
import type { APActor, Action } from '../types';

// Get dispatch functions and namespace once for all action handlers
const { createSuccessNotice, createErrorNotice } = dispatch( noticesStore );
const coreDispatch = dispatch( 'core' ) as any;
const namespace = window.activityPubAdmin?.namespace || 'activitypub/1.0';

/**
 * Delete follower action handler.
 */
export async function deleteFollower( items: APActor[] ): Promise< void > {
	try {
		// Delete each follower relationship.
		const deletePromises = items.map( ( item ) =>
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
 */
export async function blockActor( items: APActor[] ): Promise< void > {
	try {
		const blockPromises = items.map( ( item ) =>
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
 */
export async function follow( items: APActor[] ): Promise< void > {
	try {
		const followPromises = items.map( ( item ) =>
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
export function getFollowerActions(): Action[] {
	const followingEnabled = window.activityPubAdmin?.followingEnabled ?? false;

	const actions: Action[] = [
		{
			id: 'block',
			label: __( 'Block', 'activitypub' ),
			isDestructive: true,
			callback: blockActor,
		},
		{
			id: 'delete',
			label: __( 'Remove', 'activitypub' ),
			isPrimary: true,
			isDestructive: true,
			callback: deleteFollower,
		},
	];

	// Add follow back action if following UI is enabled.
	if ( followingEnabled ) {
		actions.unshift( {
			id: 'follow',
			label: __( 'Follow Back', 'activitypub' ),
			isEligible: ( item: APActor ) => ! item.follow_status?.follows_back,
			callback: follow,
		} );
	}

	return actions;
}
