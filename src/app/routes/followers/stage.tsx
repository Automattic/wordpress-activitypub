/**
 * Followers Stage
 *
 * Main followers list view with DataViews
 */

/**
 * External dependencies
 */
import type { ReactNode } from 'react';

/**
 * WordPress dependencies
 */
import { useMemo } from '@wordpress/element';
import { Action, DataViews } from '@wordpress/dataviews/wp';
import type { Field, View as DataViewsView } from '@wordpress/dataviews/wp';
import { useView } from '../../hooks/use-view';
import { __ } from '@wordpress/i18n';
import { useSelect } from '@wordpress/data';

/**
 * Internal dependencies
 */
import { Page } from '../../components/page';
import { useFollowers } from '../../hooks/use-followers';
import { avatarField, nameField, webfingerField, modifiedField, followStatusField } from '../../components/fields';
import { getFollowerActions } from './follower-actions';
import type { Actor } from '../../types';
import { STORE_NAME } from '../../store';
import type { AppSelectors } from '../../store';
import './style.scss';

// Using ReturnType to get the View type from useView to avoid version conflicts
// between @wordpress/views and @wordpress/dataviews
type ViewType = ReturnType< typeof useView >[ 'view' ];

// Default view configuration
const DEFAULT_VIEW: ViewType = {
	type: 'table',
	perPage: 20,
	page: 1,
	sort: {
		field: 'modified',
		direction: 'desc',
	},
	search: '',
	filters: [],
	fields: [ 'webfinger', 'modified', 'follow_status' ],
	layout: {},
	titleField: 'name',
	mediaField: 'avatar',
};

// Default layouts for different view types
const defaultLayouts = {
	table: {
		fields: [ 'webfinger', 'modified', 'follow_status' ],
	},
	grid: {
		fields: [ 'webfinger' ],
		mediaField: 'avatar',
		primaryField: 'name',
	},
};

export default function FollowersStage(): ReactNode {
	// Use the views hook to persist user preferences.
	const { view, updateView } = useView( {
		kind: 'postType',
		name: 'ap_actor',
		slug: 'followers',
		defaultView: DEFAULT_VIEW,
	} );

	// Get active actor ID from store
	const activeActorId: number | null = useSelect(
		( select ): number | null => ( select( STORE_NAME ) as AppSelectors ).getActiveActorId(),
		[]
	);

	// Fetch followers using entity records
	const { followers, isResolving, totalItems, totalPages } = useFollowers( {
		perPage: view.perPage || 20,
		page: view.page || 1,
		orderBy: view.sort?.field || 'modified',
		order: view.sort?.direction || 'desc',
		search: view.search || '',
		userId: activeActorId,
	} );

	// Define fields configuration
	const fields: Field< Actor >[] = useMemo(
		(): Field< Actor >[] => [ avatarField, nameField, webfingerField, modifiedField, followStatusField ],
		[]
	);

	// Get actions
	const actions: Action< Actor >[] = useMemo( (): Action< Actor >[] => getFollowerActions(), [] );

	return (
		<Page title={ __( 'Followers', 'activitypub' ) } hasPadding={ false }>
			<DataViews
				data={ followers }
				fields={ fields }
				view={ view as DataViewsView }
				onChangeView={ updateView as ( view: DataViewsView ) => void }
				actions={ actions }
				isLoading={ isResolving }
				getItemId={ ( item: Actor ): string => item.id.toString() }
				empty={
					<p>
						{ view.search
							? __( 'No followers found.', 'activitypub' )
							: __( 'No followers.', 'activitypub' ) }
					</p>
				}
				paginationInfo={ {
					totalItems,
					totalPages,
				} }
				defaultLayouts={ defaultLayouts }
			/>
		</Page>
	);
}
