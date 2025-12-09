/**
 * Feed Post Inspector
 *
 * Detail view for a single feed post in the side panel
 */

import { Button, Spinner, Card, CardBody, CardHeader } from '@wordpress/components';
import { useEntityRecord, useEntityRecords } from '@wordpress/core-data';
import type { Term } from '@wordpress/core-data';
import { sprintf, __ } from '@wordpress/i18n';
import { decodeEntities } from '@wordpress/html-entities';
import { close } from '@wordpress/icons';
import { useSettings } from '../../contexts/settings-context';
import type { Comment, FeedPost } from '../../types';
import { getRelativeTime } from '../../utils';
import { useTagFilter } from '../../hooks/use-tag-filter';
import { useSearch, useNavigate } from '../../router';

// Helper to render HTML content with proper entity decoding and unescape
const RenderHTML = ( { html }: { html: string } ) => {
	// Remove backslash escapes (e.g., \! becomes !)
	const unescaped = html.replace( /\\(.)/g, '$1' );
	const decoded = decodeEntities( unescaped );
	return <div dangerouslySetInnerHTML={ { __html: decoded } } />;
};

export default function FeedInspector() {
	const search = useSearch( { strict: false } ) as { postId?: number };
	const navigate = useNavigate();
	const id = search.postId;

	// Close inspector by removing postId from search params
	const onClose = () => {
		navigate( {
			search: ( prev: Record< string, unknown > ) => {
				const { postId: _, ...rest } = prev as { postId?: number };
				return rest;
			},
		} );
	};

	const { defaultAvatar } = useSettings();
	const { record: post, isResolving: isLoading } = useEntityRecord< FeedPost >( 'postType', 'ap_post', id ?? 0 );
	const { records: comments, isResolving: isLoadingComments } = useEntityRecords< Comment >( 'root', 'comment', {
		post: id ?? 0,
		order: 'asc',
		orderby: 'date',
	} );

	// Early return if no id (shouldn't happen due to route config, but handle gracefully)
	if ( ! id ) {
		return null;
	}

	// Fetch tag terms if the post has tags
	const tagIds = post?.ap_tag || [];
	const { records: terms } = useEntityRecords< Term >( 'taxonomy', 'ap_tag', {
		include: tagIds,
	} );

	// Use the shared tag filter hook
	const { selectedTagId, updateTagFilter } = useTagFilter();

	const handleTagClick = ( tagId: number ) => {
		// Apply filter and close inspector
		updateTagFilter( tagId, { onComplete: onClose } );
	};

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
	const webfinger = actor?.webfinger || '';
	const profileUrl = actor?.url || '';
	const avatarUrl = actor?.icon || '';
	const postLink = post.link || '';
	const relativeTime = post.date ? getRelativeTime( post.date ) : '';

	return (
		<div className="activitypub-inspector">
			<Card className="activitypub-inspector-card">
				<CardHeader>
					<div className="activitypub-inspector-header">
						<img
							src={ avatarUrl }
							alt={ author }
							className="activitypub-inspector-avatar"
							onError={ ( e ) => {
								( e.target as HTMLImageElement ).src = defaultAvatar;
							} }
						/>
						<div className="activitypub-inspector-author">
							<a
								href={ profileUrl }
								target="_blank"
								rel="noopener noreferrer"
								className="activitypub-inspector-author-name"
							>
								{ author }
							</a>
							<div className="activitypub-inspector-meta">
								{ webfinger && <span className="activitypub-inspector-webfinger">{ webfinger }</span> }
								{ relativeTime && postLink && (
									<>
										<span className="activitypub-inspector-separator">·</span>
										<a
											href={ postLink }
											target="_blank"
											rel="noopener noreferrer"
											className="activitypub-inspector-timestamp"
										>
											{ relativeTime }
										</a>
									</>
								) }
							</div>
						</div>
						<Button
							icon={ close }
							label={ __( 'Close', 'activitypub' ) }
							onClick={ onClose }
							className="activitypub-inspector-close"
						/>
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
					{ terms && terms.length > 0 && (
						<div className="activitypub-inspector-tags">
							{ terms.map( ( term: Term ) => (
								<Button
									key={ term.id }
									size="small"
									variant="secondary"
									onClick={ () => handleTagClick( term.id ) }
									aria-pressed={ selectedTagId === term.id }
									aria-label={
										/* translators: %s: tag name */
										sprintf( __( 'Filter by tag: %s', 'activitypub' ) as string, term.name as any )
									}
								>
									#{ term.name }
								</Button>
							) ) }
						</div>
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
								{ comments.map( ( comment ) => {
									// Use date_gmt for reliable UTC parsing
									const commentDate = comment.date_gmt ? getRelativeTime( comment.date_gmt ) : '';
									return (
										<div key={ comment.id } className="activitypub-inspector-comment">
											<div className="activitypub-inspector-comment-meta">
												<strong>{ decodeEntities( comment.author_name ) }</strong>
												{ commentDate && (
													<span className="activitypub-inspector-comment-date">
														{ commentDate }
													</span>
												) }
											</div>
											<RenderHTML html={ comment.content.rendered } />
										</div>
									);
								} ) }
							</div>
						) }
						{ ! isLoadingComments && ( ! comments || comments.length === 0 ) && (
							<p>{ __( 'No comments yet.', 'activitypub' ) }</p>
						) }
					</CardBody>
				</Card>
			) }
		</div>
	);
}
