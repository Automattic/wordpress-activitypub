import { __ } from '@wordpress/i18n';
import type { Comparison, CommentType } from '../../types';

interface Props {
	comparison: Comparison | null;
	userComparison: Comparison | null;
	blogComparison: Comparison | null;
	commentTypes: Record< string, CommentType > | null;
	canUseUserActor: boolean;
	canUseBlogActor: boolean;
}

/**
 * Get the admin URL for a stat type.
 *
 * @param {string} type The stat type (followers, posts, etc.).
 * @return {string|null} The admin URL or null if no link.
 */
function getStatUrl( type: string ): string | null {
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
 * Shows follower change and engagement stats for available actors.
 *
 * @param {Props} props Component props.
 */
export default function StatHighlights( {
	comparison,
	userComparison,
	blogComparison,
	commentTypes,
	canUseUserActor,
	canUseBlogActor,
}: Props ) {
	if ( ! comparison ) {
		return null;
	}

	// Build stats array dynamically.
	const stats: Array< { key: string; label: string; value: number; change: number } > = [];

	// Add user followers if available (from user-specific stats).
	// Note: This shows new followers gained this month, not total followers.
	if ( canUseUserActor && userComparison?.followers ) {
		stats.push( {
			key: 'followers-user',
			label: __( 'New Followers', 'activitypub' ),
			value: userComparison.followers.current ?? 0,
			change: userComparison.followers.change ?? 0,
		} );
	}

	// Add blog followers if available (from blog-specific stats).
	// Note: This shows new followers gained this month, not total followers.
	if ( canUseBlogActor && blogComparison?.followers ) {
		stats.push( {
			key: 'followers-blog',
			label: __( 'New Followers (Blog)', 'activitypub' ),
			value: blogComparison.followers.current ?? 0,
			change: blogComparison.followers.change ?? 0,
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
			<h3>{ __( 'This month vs. last month', 'activitypub' ) }</h3>
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
