/**
 * Feed Stage
 *
 * Main feed list view with DataViews
 */

import './style.scss';
import { useMemo, useState } from '@wordpress/element';
import { DataViews } from '@wordpress/dataviews';
import type { View, Field } from '@wordpress/dataviews';
import { __ } from '@wordpress/i18n';
import { Page } from '../../components/page';
import { useFeed } from '../../hooks/use-feed';
import {
	createTitleField,
	authorField,
	dateField,
	statusField,
	excerptField,
	metadataField,
	createContentField,
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
	fields: [ 'content' ],
};

const defaultLayouts = {
	table: {
		fields: [ 'title.rendered', 'author', 'date', 'status' ],
	},
	list: {
		primaryField: 'content',
		fields: [ 'content' ],
	},
};

interface FeedStageProps {
	onSelectItem: ( id: number ) => void;
}

export default function FeedStage( { onSelectItem }: FeedStageProps ) {
	// TODO: Switch to useView from @wordpress/views when package is installed
	// const { view, updateView } = useView( {
	// 	kind: 'postType',
	// 	name: 'ap_post',
	// 	slug: 'feed',
	// 	defaultView: DEFAULT_VIEW,
	// } );
	const [ view, setView ] = useState< View >( DEFAULT_VIEW );

	const { feed, isResolving, totalItems, totalPages } = useFeed( {
		perPage: view.perPage || 20,
		page: view.page || 1,
		orderBy: view.sort?.field || 'date',
		order: view.sort?.direction || 'desc',
		search: view.search || '',
	} );

	const fields: Field< FeedPost >[] = useMemo(
		() => [
			createContentField(),
			createTitleField( onSelectItem ),
			metadataField,
			excerptField,
			authorField,
			dateField,
			statusField,
		],
		[ onSelectItem ]
	);

	const actions = useMemo( () => getFeedActions( onSelectItem ), [ onSelectItem ] );

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
				onChangeView={ setView }
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
