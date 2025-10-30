/**
 * Feed Stage
 *
 * Main feed list view with DataViews
 */

import { useState } from '@wordpress/element';
import { useEntityRecords } from '@wordpress/core-data';
import { __ } from '@wordpress/i18n';
import { Page } from '../../components/page';
import type { FeedPost } from '../../types';

// @ts-ignore - DataViews types not fully resolved with /wp path
import { DataViews, type View } from '@wordpress/dataviews';

interface FeedStageProps {
	onSelectItem: ( id: number ) => void;
}

export default function FeedStage( { onSelectItem }: FeedStageProps ) {
	const [ view, setView ] = useState< View >( {
		type: 'table',
		perPage: 20,
		page: 1,
		sort: {
			field: 'date',
			direction: 'desc',
		},
		search: '',
		fields: [ 'title.rendered', 'actor.post_title', 'date', 'status' ],
	} );

	// Use WordPress core-data hook to fetch posts
	const query = {
		per_page: view.perPage,
		page: view.page,
		orderby: view.sort.field === 'date' ? 'date' : 'modified',
		order: view.sort.direction,
		search: view.search,
	};

	const { records: feed, isResolving } = useEntityRecords< FeedPost >( 'postType', 'ap_post', query );

	const isLoading = isResolving;

	const defaultLayouts = {
		table: {},
		list: {},
		grid: {},
	};

	const fields = [
		{
			id: 'title.rendered',
			label: __( 'Title', 'activitypub' ),
			render: ( { item }: { item: FeedPost } ) => (
				<button
					onClick={ () => onSelectItem( item.id ) }
					style={ {
						background: 'none',
						border: 'none',
						color: 'var(--wp-admin-theme-color, #3858e9)',
						cursor: 'pointer',
						textAlign: 'left',
						padding: 0,
						font: 'inherit',
						textDecoration: 'underline',
					} }
				>
					{ item.title?.rendered || __( '(No title)', 'activitypub' ) }
				</button>
			),
			enableSorting: true,
			enableGlobalSearch: true,
		},
		{
			id: 'actor.post_title',
			label: __( 'Author', 'activitypub' ),
			enableSorting: false,
			enableGlobalSearch: true,
		},
		{
			id: 'date',
			label: __( 'Date', 'activitypub' ),
			render: ( { item }: { item: FeedPost } ) => new Date( item.date ).toLocaleDateString(),
			enableSorting: true,
		},
		{
			id: 'status',
			label: __( 'Status', 'activitypub' ),
			enableSorting: true,
		},
	];

	const actions = [
		{
			id: 'view-details',
			label: __( 'View Details', 'activitypub' ),
			isPrimary: true,
			callback: ( items: FeedPost[] ) => {
				if ( items.length === 1 ) {
					onSelectItem( items[ 0 ].id );
				}
			},
		},
	];

	// Filter out items with missing required data
	const validFeed = ( feed || [] ).filter(
		( item ) => item && item.id && item.title?.rendered && item.title.rendered.trim() !== '' && item.date
	);

	if ( ! isLoading && validFeed.length === 0 ) {
		return (
			<Page
				title={ __( 'Feed', 'activitypub' ) }
				subTitle={ __( 'ActivityPub posts from your network', 'activitypub' ) }
				hasPadding={ true }
			>
				<div style={ { padding: '20px', textAlign: 'center' } }>
					<p>{ __( 'No posts found in your feed.', 'activitypub' ) }</p>
					<p style={ { color: '#666', fontSize: '14px' } }>
						{ __( 'Posts from ActivityPub actors you follow will appear here.', 'activitypub' ) }
					</p>
				</div>
			</Page>
		);
	}

	return (
		<Page
			title={ __( 'Feed', 'activitypub' ) }
			subTitle={ __( 'ActivityPub posts from your network', 'activitypub' ) }
			hasPadding={ true }
		>
			<DataViews
				data={ validFeed }
				fields={ fields }
				view={ view }
				onChangeView={ setView }
				defaultLayouts={ defaultLayouts }
				actions={ actions }
				getItemId={ ( item: FeedPost ) => item.id.toString() }
				isLoading={ isLoading }
				paginationInfo={ {
					totalItems: validFeed.length,
					totalPages: Math.ceil( validFeed.length / view.perPage ),
				} }
			/>
		</Page>
	);
}
