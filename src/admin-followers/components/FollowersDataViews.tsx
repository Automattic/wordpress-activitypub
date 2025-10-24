/**
 * Followers DataViews component for managing ActivityPub followers.
 */

import React from 'react';
import { DataViews } from '@wordpress/dataviews';
import { useMemo } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { useView } from '@wordpress/views';
import { useFollowers } from '../hooks/useFollowers';
import { getFollowerActions } from './FollowerActions';
import { avatarField, nameField, webfingerField, modifiedField, followStatusField } from './fields';
import type { FollowersDataViewsProps, Field } from '../types';

const DEFAULT_VIEW = {
	type: 'table' as const,
	perPage: 20,
	page: 1,
	sort: {
		field: 'modified',
		direction: 'desc' as const,
	},
	search: '',
	filters: [],
	fields: [ 'webfinger', 'modified', 'follow_status' ],
	layout: {},
	titleField: 'name',
	mediaField: 'avatar',
};

/**
 * Followers DataViews component.
 */
export function FollowersDataViews( { userId }: FollowersDataViewsProps ) {
	// Use the views hook to persist user preferences.
	const { view, updateView } = useView( {
		kind: 'postType',
		name: 'ap_actor',
		slug: 'followers',
		defaultView: DEFAULT_VIEW,
	} );

	const sortField = view.sort?.field || 'modified';

	// Fetch followers.
	const { followers, totalItems, totalPages } = useFollowers( {
		userId,
		perPage: view.perPage || 20,
		page: view.page || 1,
		orderBy: [ 'modified', 'date' ].includes( sortField ) ? sortField : 'modified',
		order: view.sort?.direction || 'desc',
		search: view.search || '',
	} );

	const fields: Field[] = useMemo(
		() => [ avatarField, nameField, webfingerField, modifiedField, followStatusField ],
		[]
	);

	const actions = useMemo( () => getFollowerActions(), [] );

	const defaultLayouts = {
		table: {
			fields: [ 'webfinger', 'modified', 'follow_status' ],
		},
		grid: {
			fields: [ 'webfinger' ],
		},
	};

	return (
		<DataViews
			data={ followers }
			fields={ fields }
			view={ view }
			onChangeView={ updateView }
			actions={ actions }
			defaultLayouts={ defaultLayouts }
			getItemId={ ( item ) => item.id.toString() }
			empty={
				<p>
					{ view.search ? __( 'No followers found.', 'activitypub' ) : __( 'No followers.', 'activitypub' ) }
				</p>
			}
			paginationInfo={ {
				totalItems,
				totalPages,
			} }
		/>
	);
}
