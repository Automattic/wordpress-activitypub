/**
 * Feed Stage
 *
 * Main feed list view with data table
 */

import { Button } from '@wordpress/components';
import { useDispatch } from '@wordpress/data';
import { Page } from '../../components/page';
import { useSocialWebData } from '../../hooks/use-social-web-data';
import { STORE_NAME } from '../../store';

interface FeedStageProps {
	onSelectItem: ( id: number ) => void;
}

export default function FeedStage( { onSelectItem }: FeedStageProps ) {
	const { items: feed, isLoading } = useSocialWebData( 'feed' );
	const { fetchFeed } = useDispatch( STORE_NAME ) as any;

	const handleRefresh = () => {
		fetchFeed();
	};

	if ( ! isLoading && ( ! feed || feed.length === 0 ) ) {
		return (
			<Page
				title="Feed"
				subTitle="ActivityPub posts from your network"
				hasPadding={ true }
				actions={
					<Button variant="primary" onClick={ handleRefresh }>
						Refresh
					</Button>
				}
			>
				<div style={ { padding: '20px', textAlign: 'center' } }>
					<p>No posts found in your feed.</p>
					<p style={ { color: '#666', fontSize: '14px' } }>
						Posts from ActivityPub actors you follow will appear here.
					</p>
				</div>
			</Page>
		);
	}

	return (
		<Page
			title="Feed"
			subTitle="ActivityPub posts from your network"
			hasPadding={ true }
			actions={
				<Button variant="primary" onClick={ handleRefresh }>
					Refresh
				</Button>
			}
		>
			<table style={ { width: '100%', borderCollapse: 'collapse' } }>
				<thead>
					<tr style={ { borderBottom: '1px solid #ddd' } }>
						<th style={ { textAlign: 'left', padding: '12px' } }>Title</th>
						<th style={ { textAlign: 'left', padding: '12px' } }>Author</th>
						<th style={ { textAlign: 'left', padding: '12px' } }>Date</th>
						<th style={ { textAlign: 'left', padding: '12px' } }>Status</th>
					</tr>
				</thead>
				<tbody>
					{ feed?.map( ( post ) => (
						<tr
							key={ post.id }
							style={ {
								borderBottom: '1px solid #f0f0f0',
								cursor: 'pointer',
							} }
							onClick={ () => onSelectItem( post.id ) }
						>
							<td style={ { padding: '12px' } }>
								<button
									onClick={ ( e ) => {
										e.stopPropagation();
										onSelectItem( post.id );
									} }
									style={ {
										background: 'none',
										border: 'none',
										color: 'var(--wpds-color-bg-interactive-brand, #3858e9)',
										cursor: 'pointer',
										textAlign: 'left',
										padding: 0,
										font: 'inherit',
									} }
								>
									{ post.title?.rendered || '(No title)' }
								</button>
							</td>
							<td style={ { padding: '12px' } }>
								{ post.actor ? (
									<div style={ { display: 'flex', alignItems: 'center', gap: '8px' } }>
										{ post.actor.post_title }
									</div>
								) : (
									'Unknown'
								) }
							</td>
							<td style={ { padding: '12px' } }>{ new Date( post.date ).toLocaleDateString() }</td>
							<td style={ { padding: '12px' } }>{ post.status }</td>
						</tr>
					) ) }
				</tbody>
			</table>
		</Page>
	);
}
