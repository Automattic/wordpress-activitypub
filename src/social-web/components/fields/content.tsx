import { __ } from '@wordpress/i18n';
import type { Field } from '@wordpress/dataviews';
import type { FeedPost } from '../../types';

/**
 * Content field for reader-style view
 * Displays rich content with HTML formatting
 */
export const createContentField = (): Field< FeedPost > => ( {
	id: 'content',
	label: __( 'Content', 'activitypub' ),
	enableHiding: true,
	enableSorting: false,
	getValue: ( { item }: { item: FeedPost } ) => {
		// Strip HTML tags for plain text value (used for search/sort)
		const text = item.excerpt?.rendered || item.content?.rendered || '';
		return text.replace( /<[^>]*>/g, '' ).replace( /&[^;]+;/g, '' );
	},
	render: ( { item }: { item: FeedPost } ) => {
		// Prefer excerpt, fall back to content
		const content = item.excerpt?.rendered || item.content?.rendered || '';
		const author = ( item as any ).actor?.post_title || __( 'Unknown author', 'activitypub' );
		const date = item.date
			? new Date( item.date ).toLocaleDateString( undefined, {
					year: 'numeric',
					month: 'short',
					day: 'numeric',
			  } )
			: '';

		// Check if content is actually empty (not just whitespace)
		const hasRealContent =
			content
				.trim()
				.replace( /<\/?p>/g, '' )
				.replace( /&nbsp;/g, '' )
				.trim().length > 0;

		if ( ! hasRealContent && ! item.title?.rendered ) {
			return (
				<div style={ { padding: '12px 0', color: '#757575' } }>
					{ __( 'No content available', 'activitypub' ) }
				</div>
			);
		}

		return (
			<div className="activitypub-feed-post">
				{ /* Author and date metadata */ }
				<div className="activitypub-feed-post-meta">
					<span className="author">{ author }</span>
					{ date && (
						<>
							<span className="separator">·</span>
							<span className="date">{ date }</span>
						</>
					) }
				</div>

				{ /* Title if available */ }
				{ item.title?.rendered && (
					<div
						className="activitypub-feed-post-title"
						dangerouslySetInnerHTML={ { __html: item.title.rendered } }
					/>
				) }

				{ /* Content with HTML rendering */ }
				{ hasRealContent ? (
					<div className="activitypub-feed-content" dangerouslySetInnerHTML={ { __html: content } } />
				) : (
					<div className="activitypub-feed-no-content">
						{ __( 'No excerpt or content available', 'activitypub' ) }
					</div>
				) }

				{ /* Link to original post */ }
				{ item.link && (
					<div className="activitypub-feed-view-original">
						<a href={ item.link } target="_blank" rel="noopener noreferrer">
							{ __( 'View original', 'activitypub' ) }
						</a>
					</div>
				) }
			</div>
		);
	},
} );
