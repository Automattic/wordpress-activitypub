/**
 * Feed Stage
 *
 * Main feed list view with DataViews
 */

import './style.scss';
import { useMemo, useCallback, useState, useEffect } from '@wordpress/element';
import { DataViews } from '@wordpress/dataviews';
import { useView } from '@wordpress/views';
import type { View, Field } from '@wordpress/dataviews';
import { __ } from '@wordpress/i18n';
import { Page } from '../../components/page';
import { useFeed } from '../../hooks/use-feed';
import { titleField, dateField, excerptField, metadataField, contentField } from '../../components/fields';
import { getFeedActions } from './feed-actions';
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
	layout: {},
};

const defaultLayouts = {
	list: {
		primaryField: 'metadata',
		fields: [ 'metadata', 'title.rendered', 'excerpt.rendered' ],
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
		() => [ metadataField, titleField, excerptField, contentField, dateField ],
		[]
	);

	// Actions for feed items
	const actions = useMemo( () => getFeedActions( onSelectItem ), [ onSelectItem ] );

	const [ selection, setSelection ] = useState< string[] >( [] );

	useEffect( () => {
		if ( selection.length === 0 ) {
			return;
		}

		const selectedId = selection[ 0 ];
		const exists = feed.some( ( item ) => item.id.toString() === selectedId );
		if ( ! exists ) {
			setSelection( [] );
		}
	}, [ feed, selection ] );

	const handleChangeSelection = useCallback(
		( nextSelection: string[] ) => {
			setSelection( nextSelection );

			if ( nextSelection.length === 0 ) {
				return;
			}

			const selectedId = nextSelection[ 0 ];
			const selectedItem = feed.find( ( item ) => item.id.toString() === selectedId );

			if ( selectedItem ) {
				onSelectItem( selectedItem.id );
			}
		},
		[ feed, onSelectItem ]
	);

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
				onClickItem={ ( item ) => onSelectItem( item.id ) }
				isItemClickable={ () => true }
				getItemId={ ( item ) => item.id.toString() }
				selection={ selection }
				onChangeSelection={ handleChangeSelection }
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
