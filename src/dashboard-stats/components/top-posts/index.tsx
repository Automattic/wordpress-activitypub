/**
 * External dependencies
 */
import type { ReactNode } from 'react';

/**
 * WordPress dependencies
 */
import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import type { TopPost } from '../../types';

interface Props {
	posts: TopPost[] | null | undefined;
}

/**
 * Top Posts Component.
 *
 * @param {Props} props Component props.
 */
export default function TopPosts( { posts }: Props ): ReactNode {
	if ( ! posts?.length ) {
		return null;
	}

	return (
		<div className="activitypub-stats-top-posts">
			<h3>{ __( 'Top Posts', 'activitypub' ) }</h3>
			<ul>
				{ posts.map( ( post ) => {
					const title = post.title || __( '(no title)', 'activitypub' );
					const href = post.edit_url || post.url;
					const isExternal = ! post.edit_url;
					const ariaLabel = isExternal
						? /* translators: %s: post title */
						  sprintf( __( '%s (opens in a new tab)', 'activitypub' ), title )
						: /* translators: %s: post title */
						  sprintf( __( 'Edit %s', 'activitypub' ), title );
					return (
						<li key={ post.post_id }>
							<a
								href={ href }
								{ ...( isExternal ? { target: '_blank', rel: 'noopener noreferrer' } : {} ) }
								aria-label={ ariaLabel }
							>
								{ title }
							</a>
							<span className="engagement-count">
								{ sprintf(
									/* translators: %s: engagement count */
									__( '%s engagements', 'activitypub' ),
									post.engagement_count.toLocaleString()
								) }
							</span>
						</li>
					);
				} ) }
			</ul>
		</div>
	);
}
