import { __, sprintf } from '@wordpress/i18n';
import type { TopPost } from '../../types';

interface Props {
	posts: TopPost[] | null | undefined;
}

/**
 * Top Posts Component.
 * @param root0
 * @param root0.posts
 */
export default function TopPosts( { posts }: Props ) {
	if ( ! posts?.length ) {
		return null;
	}

	return (
		<div className="activitypub-stats-top-posts">
			<h3>{ __( 'Top Posts', 'activitypub' ) }</h3>
			<ul>
				{ posts.map( ( post ) => {
					const title = post.title || __( '(no title)', 'activitypub' );
					return (
						<li key={ post.post_id }>
							<a
								href={ post.url }
								target="_blank"
								rel="noopener noreferrer"
								aria-label={ sprintf(
									/* translators: %s: post title */
									__( '%s (opens in a new tab)', 'activitypub' ),
									title
								) }
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
