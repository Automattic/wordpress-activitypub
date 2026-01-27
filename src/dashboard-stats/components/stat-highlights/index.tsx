import { __ } from '@wordpress/i18n';
import type { Comparison, CommentType } from '../../types';

interface FollowerCounts {
	user: number | null;
	blog: number | null;
}

interface Props {
	comparison: Comparison | null;
	commentTypes: Record< string, CommentType > | null;
	followerCounts: FollowerCounts;
	canUseUserActor: boolean;
	canUseBlogActor: boolean;
}

/**
 * Get the admin URL for a stat type.
 *
 * @param {string}  type   The stat type (followers, posts, etc.).
 * @param {boolean} isBlog Whether this is for the blog actor.
 * @return {string|null} The admin URL or null if no link.
 */
function getStatUrl( type: string, isBlog: boolean = false ): string | null {
	switch ( type ) {
		case 'followers':
		case 'followers-user':
			return 'users.php?page=activitypub-followers-list';
		case 'followers-blog':
			return 'options-general.php?page=activitypub&tab=followers';
		case 'posts':
			return 'edit.php';
		default:
			// Likes, reposts, comments filter by comment type.
			return `edit-comments.php?comment_type=${ type }`;
	}
}

/**
 * Stat Highlights Component.
 *
 * Displays key statistics with month-over-month comparison.
 * Shows follower counts for both user and blog actors if available.
 *
 * @param {Props} props                 Component props.
 * @param {Props} props.comparison      Comparison data with current vs previous values.
 * @param {Props} props.commentTypes    Available comment types configuration.
 * @param {Props} props.followerCounts  Follower counts for user and blog.
 * @param {Props} props.canUseUserActor Whether user actor is available.
 * @param {Props} props.canUseBlogActor Whether blog actor is available.
 */
export default function StatHighlights( {
	comparison,
	commentTypes,
	followerCounts,
	canUseUserActor,
	canUseBlogActor,
}: Props ) {
	if ( ! comparison ) {
		return null;
	}

	// Build stats array dynamically.
	const stats: Array< { key: string; label: string; value: number; change: number } > = [];

	// Add user followers if available.
	if ( canUseUserActor && followerCounts.user !== null ) {
		stats.push( {
			key: 'followers-user',
			label: __( 'Followers', 'activitypub' ),
			value: followerCounts.user,
			change: comparison.followers?.change ?? 0,
		} );
	}

	// Add blog followers if available.
	if ( canUseBlogActor && followerCounts.blog !== null ) {
		stats.push( {
			key: 'followers-blog',
			label: __( 'Followers (Blog)', 'activitypub' ),
			value: followerCounts.blog,
			change: 0, // Blog followers change tracked separately.
		} );
	}

	// Add posts.
	stats.push( {
		key: 'posts',
		label: __( 'Posts', 'activitypub' ),
		value: comparison.posts?.current ?? 0,
		change: comparison.posts?.change ?? 0,
	} );

	// Add engagement types dynamically from comment types.
	if ( commentTypes ) {
		Object.entries( commentTypes ).forEach( ( [ slug, type ] ) => {
			const comparisonData = comparison[ slug as keyof Comparison ];
			if ( comparisonData && typeof comparisonData === 'object' && 'current' in comparisonData ) {
				stats.push( {
					key: slug,
					label: type.label,
					value: comparisonData.current ?? 0,
					change: comparisonData.change ?? 0,
				} );
			}
		} );
	}

	return (
		<div className="activitypub-stats-highlights main">
			<h3>{ __( 'This month vs. last year', 'activitypub' ) }</h3>
			<ul>
				{ stats.map( ( stat ) => {
					const url = getStatUrl( stat.key );
					const content = (
						<>
							{ stat.value.toLocaleString() } { stat.label }
						</>
					);
					return (
						<li
							key={ stat.key }
							className={ `activitypub-${ stat.key
								.replace( '-user', '' )
								.replace( '-blog', '' ) }-count` }
						>
							{ url ? <a href={ url }>{ content }</a> : <span>{ content }</span> }
							{ stat.change !== 0 && ' ' }
							{ stat.change !== 0 && (
								<span className={ `stat-change ${ stat.change > 0 ? 'positive' : 'negative' }` }>
									({ stat.change > 0 ? '+' : '' }
									{ stat.change.toLocaleString() })
								</span>
							) }
						</li>
					);
				} ) }
			</ul>
		</div>
	);
}
