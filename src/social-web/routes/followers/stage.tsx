/**
 * Followers Stage
 *
 * Main followers list view with DataViews
 */

/**
 * WordPress dependencies
 */
import { useMemo } from '@wordpress/element';
import { DataViews } from '@wordpress/dataviews';
import { useView } from '@wordpress/views';
import type { View, Field } from '@wordpress/dataviews';
import { __ } from '@wordpress/i18n';
import { useSelect } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';

/**
 * Internal dependencies
 */
import { Page } from '../../components/page';
import { useFollowers } from '../../hooks/use-followers';
import { avatarField, nameField, webfingerField, modifiedField, followStatusField } from '../../components/fields';
import { getFollowerActions } from './FollowerActions';
import type { Actor } from '../../types';
import './style.scss';

// Default view configuration
const DEFAULT_VIEW: View = {
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

export default function FollowersStage() {
	// Use the views hook to persist user preferences.
	const { view, updateView } = useView( {
		kind: 'postType',
		name: 'ap_actor',
		slug: 'followers',
		defaultView: DEFAULT_VIEW,
	} );

	// Get current user ID from WordPress core
	const currentUserId = useSelect( ( select ) => {
		const { getCurrentUser } = select( coreStore );
		const currentUser = getCurrentUser();
		return currentUser?.id;
	}, [] );

	// Fetch followers using entity records
	const { followers, isResolving, totalItems, totalPages } = useFollowers( {
		perPage: view.perPage || 20,
		page: view.page || 1,
		orderBy: view.sort?.field || 'modified',
		order: view.sort?.direction || 'desc',
		search: view.search || '',
		userId: currentUserId,
	} );

	// Define fields configuration
	const fields: Field< Actor >[] = useMemo(
		() => [ avatarField, nameField, webfingerField, modifiedField, followStatusField ],
		[]
	);

	// Get actions
	const actions = useMemo( () => getFollowerActions(), [] );

	return (
		<Page title={ __( 'Followers', 'activitypub' ) } hasPadding={ false }>
			<DataViews
				data={ followers }
				fields={ fields }
				view={ view }
				onChangeView={ updateView }
				actions={ actions }
				isLoading={ isResolving }
				getItemId={ ( item ) => item.id.toString() }
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
