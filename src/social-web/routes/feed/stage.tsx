/**
 * Feed Stage
 *
 * Main feed list view with DataViews
 */

import './style.scss';
import './post-card-style.scss';
import { useMemo } from '@wordpress/element';
import { DataViews } from '@wordpress/dataviews';
import { useView } from '@wordpress/views';
import type { View, Field } from '@wordpress/dataviews';
import { __ } from '@wordpress/i18n';
import { Page } from '../../components/page';
import { useFeed } from '../../hooks/use-feed';
import {
	createTitleField,
	dateField,
	statusField,
	excerptField,
	metadataField,
	createContentField,
	createPostCardField,
} from '../../components/fields';
import { getFeedActions } from './FeedActions';
import type { FeedPost } from '../../types';

const DEFAULT_VIEW: View = {
	type: 'list',
	perPage: 20,
	page: 1,
	sort: {
		field: 'date',
		direction: 'desc',
	},
	search: '',
	filters: [],
	fields: [ 'metadata', 'title.rendered', 'excerpt.rendered' ],
};

const defaultLayouts = {
	table: {
		fields: [ 'title.rendered', 'metadata', 'excerpt.rendered' ],
	},
	list: {
		primaryField: 'post_card',
		fields: [ 'post_card' ],
		mediaField: undefined,
	},
};

interface FeedStageProps {
	onSelectItem: ( id: number ) => void;
}

export default function FeedStage( { onSelectItem }: FeedStageProps ) {
	// Use the views hook to persist user preferences
	const { view, updateView } = useView( {
		kind: 'postType',
		name: 'ap_post',
		slug: 'feed',
		defaultView: DEFAULT_VIEW,
	} );

	const { feed, isResolving, totalItems, totalPages } = useFeed( {
		perPage: view.perPage || 20,
		page: view.page || 1,
		orderBy: view.sort?.field || 'date',
		order: view.sort?.direction || 'desc',
		search: view.search || '',
	} );

	const fields: Field< FeedPost >[] = useMemo(
		() => [
			createPostCardField( onSelectItem ),
			createContentField(),
			createTitleField( onSelectItem ),
			metadataField,
			excerptField,
			dateField,
			statusField,
		],
		[ onSelectItem ]
	);

	// Hide actions for now
	const actions = useMemo( () => [], [ onSelectItem ] );

	return (
		<Page
			title={ __( 'Feed', 'activitypub' ) }
			subTitle={ __( 'ActivityPub posts from your network', 'activitypub' ) }
			hasPadding={ false }
		>
			<DataViews
				data={ feed }
				fields={ fields }
				view={ view }
				onChangeView={ updateView }
				actions={ actions }
				isLoading={ isResolving }
				getItemId={ ( item ) => item.id.toString() }
				empty={
					<p>
						{ view.search
							? __( 'No posts found.', 'activitypub' )
							: __(
									'No posts found in your feed. Posts from ActivityPub actors you follow will appear here.',
									'activitypub'
							  ) }
					</p>
				}
				paginationInfo={ { totalItems, totalPages } }
				defaultLayouts={ defaultLayouts }
			/>
		</Page>
	);
}
