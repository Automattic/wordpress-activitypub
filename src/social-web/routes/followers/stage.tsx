/**
 * Followers Stage
 *
 * Main followers list view with data table
 */

import { useState } from '@wordpress/element';
import { Button } from '@wordpress/components';
import { DataViews } from '@wordpress/dataviews';
import { Page } from '../../components/page';
import { useSocialWebData } from '../../hooks/use-social-web-data';

interface FollowersStageProps {
	onSelectItem: ( id: string ) => void;
}

export default function FollowersStage( { onSelectItem }: FollowersStageProps ) {
	const { items: followers, isLoading } = useSocialWebData( 'followers' );
	const [ view, setView ] = useState( { type: 'table', perPage: 20, page: 1 } );

	const fields = [
		{
			id: 'name',
			label: 'Name',
			enableSorting: true,
			render: ( { item }: { item: any } ) => (
				<button
					onClick={ () => onSelectItem( item.id ) }
					style={ {
						background: 'none',
						border: 'none',
						color: 'var(--wpds-color-bg-interactive-brand, #3858e9)',
						cursor: 'pointer',
						textAlign: 'left',
					} }
				>
					{ item.name }
				</button>
			),
		},
		{
			id: 'url',
			label: 'URL',
			enableSorting: false,
		},
		{
			id: 'followers',
			label: 'Followers',
			enableSorting: true,
		},
	];

	return (
		<Page
			title="Followers"
			subTitle="Manage and view your followers"
			hasPadding={ false }
			actions={ <Button variant="primary">Add Follower</Button> }
		>
			<DataViews
				data={ followers || [] }
				fields={ fields }
				view={ view }
				onChangeView={ setView }
				isLoading={ isLoading }
				paginationInfo={ { totalItems: followers?.length || 0, totalPages: 1 } }
			/>
		</Page>
	);
}
