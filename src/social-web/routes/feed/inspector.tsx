/**
 * Feed Post Inspector
 *
 * Detail view for a single feed post in the side panel
 */

import { Button, Spinner, Card, CardBody, CardHeader } from '@wordpress/components';
import { useEntityRecord, useEntityRecords } from '@wordpress/core-data';
import { __ } from '@wordpress/i18n';
import { decodeEntities } from '@wordpress/html-entities';
import { Page } from '../../components/page';
import type { Comment, FeedPost } from '../../types';

interface FeedInspectorProps {
	id: number;
	onClose: () => void;
}

// Helper to render HTML content with proper entity decoding and unescape
const RenderHTML = ( { html }: { html: string } ) => {
	// Remove backslash escapes (e.g., \! becomes !)
	const unescaped = html.replace( /\\(.)/g, '$1' );
	const decoded = decodeEntities( unescaped );
	return <div dangerouslySetInnerHTML={ { __html: decoded } } />;
};

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
			<div className="activitypub-inspector-loading">
				<Spinner />
			</div>
		);
	}

	if ( ! post ) {
		return <div className="activitypub-inspector-loading">{ __( 'Post not found', 'activitypub' ) }</div>;
	}

	const actor = post.actor_info;
	const author = decodeEntities( actor?.name || __( 'Unknown author', 'activitypub' ) );
	const postDate = post.date ? new Date( post.date ).toLocaleString() : '';
	const avatarUrl = actor?.icon || '';

	return (
		<Page
			hasPadding={ true }
			actions={
				<Button variant="tertiary" size="small" onClick={ onClose }>
					{ __( 'Close', 'activitypub' ) }
				</Button>
			}
		>
			<Card className="activitypub-inspector-card">
				<CardHeader>
					<div className="activitypub-inspector-header">
						<img src={ avatarUrl } alt={ author } className="activitypub-inspector-avatar" />
						<div className="activitypub-inspector-author">
							<strong>{ author }</strong>
							{ postDate && <span className="activitypub-inspector-date">{ postDate }</span> }
						</div>
					</div>
				</CardHeader>
				<CardBody>
					{ post.title?.rendered && (
						<h2>
							<RenderHTML html={ post.title.rendered } />
						</h2>
					) }
					{ ( post.content?.rendered || post.excerpt?.rendered ) && (
						<RenderHTML html={ post.content?.rendered || post.excerpt?.rendered || '' } />
					) }
					{ post.link && (
						<Button
							variant="secondary"
							href={ post.link }
							target="_blank"
							rel="noopener noreferrer"
							className="activitypub-inspector-link"
						>
							{ __( 'View Original Post', 'activitypub' ) }
						</Button>
					) }
				</CardBody>
			</Card>

			{ ( isLoadingComments || ( comments && comments.length > 0 ) ) && (
				<Card className="activitypub-inspector-card activitypub-inspector-comments-card">
					<CardHeader>
						{ __( 'Comments', 'activitypub' ) }
						{ comments && comments.length > 0 && ` (${ comments.length })` }
					</CardHeader>
					<CardBody>
						{ isLoadingComments && <Spinner /> }
						{ ! isLoadingComments && comments && comments.length > 0 && (
							<div>
								{ comments.map( ( comment ) => (
									<div key={ comment.id } className="activitypub-inspector-comment">
										<div className="activitypub-inspector-comment-meta">
											<strong>{ decodeEntities( comment.author_name ) }</strong>
											<span className="activitypub-inspector-comment-date">
												{ new Date( comment.date ).toLocaleString() }
											</span>
										</div>
										<RenderHTML html={ comment.content.rendered } />
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
