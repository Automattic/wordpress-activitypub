/**
 * Action handlers for follower management.
 */

import apiFetch from '@wordpress/api-fetch';
import { dispatch } from '@wordpress/data';
import { store as noticesStore } from '@wordpress/notices';
import { __ } from '@wordpress/i18n';
import type { APActor, Action } from '../types';

/**
 * Delete follower action handler.
 */
export async function deleteFollower( items: APActor[] ): Promise< void > {
	const { createSuccessNotice, createErrorNotice } = dispatch( noticesStore );

	try {
		// Delete each follower relationship
		const deletePromises = items.map( ( item ) =>
			apiFetch( {
				path: `/activitypub/1.0/admin/actors/${ item.id }/unfollow`,
				method: 'DELETE',
			} )
		);

		await Promise.all( deletePromises );

		// Refresh the entity records
		( dispatch( 'core' ) as any ).invalidateResolution( 'getEntityRecords', [ 'postType', 'ap_actor' ] );

		const message =
			items.length === 1 ? __( 'Follower removed.', 'activitypub' ) : __( 'Followers removed.', 'activitypub' );

		await createSuccessNotice( message, {
			type: 'snackbar',
		} );
	} catch ( error ) {
		await createErrorNotice( __( 'Failed to remove followers.', 'activitypub' ), {
			type: 'snackbar',
		} );
	}
}

/**
 * Block actor action handler.
 */
export async function blockActor( items: APActor[] ): Promise< void > {
	const { createSuccessNotice, createErrorNotice } = dispatch( noticesStore );

	try {
		const blockPromises = items.map( ( item ) =>
			apiFetch( {
				path: `/activitypub/1.0/admin/actors/${ item.id }/block`,
				method: 'POST',
				data: {
					site_wide: false, // User-specific block by default
				},
			} )
		);

		await Promise.all( blockPromises );

		// Refresh the entity records
		( dispatch( 'core' ) as any ).invalidateResolution( 'getEntityRecords', [ 'postType', 'ap_actor' ] );

		const message =
			items.length === 1 ? __( 'Account blocked.', 'activitypub' ) : __( 'Accounts blocked.', 'activitypub' );

		await createSuccessNotice( message, {
			type: 'snackbar',
		} );
	} catch ( error ) {
		await createErrorNotice( __( 'Failed to block accounts.', 'activitypub' ), {
			type: 'snackbar',
		} );
	}
}

/**
 * Follow back action handler
 */
export async function follow( items: APActor[] ): Promise< void > {
	const { createSuccessNotice, createErrorNotice } = dispatch( noticesStore );

	try {
		const followPromises = items.map( ( item ) =>
			apiFetch( {
				path: `/activitypub/1.0/admin/actors/${ item.id }/follow`,
				method: 'POST',
			} )
		);

		await Promise.all( followPromises );

		// Refresh the entity records to update follow status
		( dispatch( 'core' ) as any ).invalidateResolution( 'getEntityRecords', [ 'postType', 'ap_actor' ] );

		const message =
			items.length === 1 ? __( 'Account followed.', 'activitypub' ) : __( 'Accounts followed.', 'activitypub' );

		await createSuccessNotice( message, {
			type: 'snackbar',
		} );
	} catch ( error ) {
		await createErrorNotice( __( 'Failed to follow accounts.', 'activitypub' ), {
			type: 'snackbar',
		} );
	}
}

/**
 * Get all available actions for the DataViews component
 */
export function getFollowerActions(): Action[] {
	const followingEnabled = window.activityPubAdmin?.followingEnabled ?? false;

	const actions: Action[] = [
		{
			id: 'delete',
			label: __( 'Remove', 'activitypub' ),
			isPrimary: true,
			isDestructive: true,
			callback: deleteFollower,
		},
		{
			id: 'block',
			label: __( 'Block', 'activitypub' ),
			isDestructive: true,
			callback: blockActor,
		},
	];

	// Add follow back action if following UI is enabled
	if ( followingEnabled ) {
		actions.push( {
			id: 'follow',
			label: __( 'Follow Back', 'activitypub' ),
			isEligible: ( item: APActor ) => ! item.follow_status?.follows_back,
			callback: follow,
		} );
	}

	return actions;
}
