/**
 * Feed Post Inspector
 *
 * Detail view for a single feed post in the side panel
 */

import { Button, Spinner, Card, CardBody, CardHeader } from '@wordpress/components';
import { useEntityRecord, useEntityRecords } from '@wordpress/core-data';
import { __ } from '@wordpress/i18n';
import { Page } from '../../components/page';
import type { Comment, FeedPost } from '../../types';

interface FeedInspectorProps {
	id: number;
	onClose: () => void;
}

export default function FeedInspector( { id, onClose }: FeedInspectorProps ) {
	const { record: post, isResolving: isLoading } = useEntityRecord< FeedPost >( 'postType', 'ap_post', id );
	const { records: comments, isResolving: isLoadingComments } = useEntityRecords< Comment >( 'root', 'comment', {
		post: id,
		per_page: 100,
		order: 'asc',
		orderby: 'date',
	} );

	if ( isLoading ) {
		return (
			<div style={ { padding: '20px', textAlign: 'center' } }>
				<Spinner />
			</div>
		);
	}

	if ( ! post ) {
		return <div style={ { padding: '20px', textAlign: 'center' } }>{ __( 'Post not found', 'activitypub' ) }</div>;
	}

	const author = post.actor?.post_title || __( 'Unknown author', 'activitypub' );
	const postDate = post.date ? new Date( post.date ).toLocaleString() : '';
	const content = post.content?.rendered || post.excerpt?.rendered || '';

	return (
		<Page
			title={ __( 'Post Details', 'activitypub' ) }
			hasPadding={ true }
			actions={
				<Button variant="tertiary" size="small" onClick={ onClose }>
					{ __( 'Close', 'activitypub' ) }
				</Button>
			}
		>
			<Card>
				<CardHeader>
					<strong>{ author }</strong>
					{ postDate && <span style={ { marginLeft: '8px', color: '#757575' } }>{ postDate }</span> }
				</CardHeader>
				<CardBody>
					{ post.title?.rendered && <h2 dangerouslySetInnerHTML={ { __html: post.title.rendered } } /> }
					{ content && <div dangerouslySetInnerHTML={ { __html: content } } /> }
					{ post.link && (
						<Button
							variant="secondary"
							href={ post.link }
							target="_blank"
							rel="noopener noreferrer"
							style={ { marginTop: '16px' } }
						>
							{ __( 'View Original Post', 'activitypub' ) }
						</Button>
					) }
				</CardBody>
			</Card>

			{ ( isLoadingComments || ( comments && comments.length > 0 ) ) && (
				<Card style={ { marginTop: '16px' } }>
					<CardHeader>
						{ __( 'Comments', 'activitypub' ) }
						{ comments && comments.length > 0 && ` (${ comments.length })` }
					</CardHeader>
					<CardBody>
						{ isLoadingComments && <Spinner /> }
						{ ! isLoadingComments && comments && comments.length > 0 && (
							<div>
								{ comments.map( ( comment ) => (
									<div
										key={ comment.id }
										style={ {
											marginBottom: '16px',
											paddingBottom: '16px',
											borderBottom: '1px solid #ddd',
										} }
									>
										<div style={ { marginBottom: '8px' } }>
											<strong>{ comment.author_name }</strong>
											<span style={ { marginLeft: '8px', color: '#757575', fontSize: '0.9em' } }>
												{ new Date( comment.date ).toLocaleString() }
											</span>
										</div>
										<div dangerouslySetInnerHTML={ { __html: comment.content.rendered } } />
									</div>
								) ) }
							</div>
						) }
						{ ! isLoadingComments && ( ! comments || comments.length === 0 ) && (
							<p>{ __( 'No comments yet.', 'activitypub' ) }</p>
						) }
					</CardBody>
				</Card>
			) }
		</Page>
	);
}
