/**
 * Feed Post Inspector
 *
 * Detail view for a single feed post in the side panel
 */

import { Button, Spinner, Card, CardBody, CardHeader } from '@wordpress/components';
import { useEntityRecord, useEntityRecords } from '@wordpress/core-data';
import type { Term } from '@wordpress/core-data';
import { useView } from '@wordpress/views';
import { useSelect, useDispatch } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { decodeEntities } from '@wordpress/html-entities';
import { close } from '@wordpress/icons';
import { useSettings } from '../../contexts/settings-context';
import type { Comment, FeedPost } from '../../types';
import { getRelativeTime } from '../../utils';
import { STORE_NAME } from '../../store';

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
	const { defaultAvatar } = useSettings();
	const { record: post, isResolving: isLoading } = useEntityRecord< FeedPost >( 'postType', 'ap_post', id );
	const { records: comments, isResolving: isLoadingComments } = useEntityRecords< Comment >( 'root', 'comment', {
		post: id,
		order: 'asc',
		orderby: 'date',
	} );

	// Fetch tag terms if the post has tags
	const tagIds = post?.ap_tag || [];
	const { records: terms } = useEntityRecords< Term[] >( 'taxonomy', 'ap_tag', {
		include: tagIds,
	} );

	// Get the view to update filters
	const { view, updateView } = useView( {
		kind: 'postType',
		name: 'ap_post',
		slug: 'feed',
	} );

	const selectedTagId = useSelect( ( select ) => select( STORE_NAME ).getSelectedTagId(), [] );
	const { setSelectedTag } = useDispatch( STORE_NAME );

	const handleTagClick = ( tagId: number ) => {
		const currentFilters = view.filters || [];
		const tagFilterIndex = currentFilters.findIndex( ( f ) => f.field === 'ap_tag' );

		let newFilters;
		let shouldOpenFilters = false;

		if ( tagFilterIndex !== -1 ) {
			// Tag filter exists - toggle it
			const currentValue = currentFilters[ tagFilterIndex ].value as number[];
			if ( currentValue.includes( tagId ) ) {
				// Remove the tag filter if it's the same tag
				newFilters = currentFilters.filter( ( f ) => f.field !== 'ap_tag' );
			} else {
				// Replace with new tag
				newFilters = [
					...currentFilters.slice( 0, tagFilterIndex ),
					{ field: 'ap_tag', operator: 'isAny', value: [ tagId ] },
					...currentFilters.slice( tagFilterIndex + 1 ),
				];
				shouldOpenFilters = true;
			}
		} else {
			// No tag filter exists - add one
			newFilters = [ ...currentFilters, { field: 'ap_tag', operator: 'isAny', value: [ tagId ] } ];
			shouldOpenFilters = true;
		}

		// Update the view with new filters
		updateView( {
			...view,
			filters: newFilters,
			page: 1, // Reset to first page
			openFilters: shouldOpenFilters ? true : view.openFilters,
		} );

		// Also update the store for synchronization
		setSelectedTag( selectedTagId === tagId ? null : tagId );

		// Close the inspector
		onClose();
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
