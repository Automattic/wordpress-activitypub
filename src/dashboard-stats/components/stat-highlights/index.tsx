import { __ } from '@wordpress/i18n';
import type { Comparison, CommentType } from '../../types';

interface Props {
	comparison: Comparison | null;
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
 * @param {Props} props                 Component props.
 * @param {Props} props.comparison      Comparison data with current vs previous values.
 * @param {Props} props.commentTypes    Available comment types configuration.
 * @param {Props} props.canUseUserActor Whether user actor is available.
 * @param {Props} props.canUseBlogActor Whether blog actor is available.
 */
export default function StatHighlights( { comparison, commentTypes, canUseUserActor, canUseBlogActor }: Props ) {
	if ( ! comparison ) {
		return null;
	}

	// Build stats array dynamically.
	// isChangeOnly: true means the value IS the change (for followers).
	const stats: Array< { key: string; label: string; value: number; change: number; isChangeOnly?: boolean } > = [];

	// Add user followers change if available.
	if ( canUseUserActor && comparison.followers ) {
		const change = comparison.followers.change ?? 0;
		stats.push( {
			key: 'followers-user',
			label: __( 'Followers', 'activitypub' ),
			value: change,
			change,
			isChangeOnly: true,
		} );
	}

	// Add blog followers change if available.
	if ( canUseBlogActor && comparison.followers ) {
		// TODO: Track blog followers separately when we have blog-specific comparison.
		const change = comparison.followers.change ?? 0;
		stats.push( {
			key: 'followers-blog',
			label: __( 'Followers (Blog)', 'activitypub' ),
			value: change,
			change,
			isChangeOnly: true,
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
					// For change-only stats (followers), show with +/- prefix.
					const displayValue = stat.isChangeOnly
						? `${ stat.change >= 0 ? '+' : '' }${ stat.change.toLocaleString() }`
						: stat.value.toLocaleString();
					const content = (
						<>
							{ displayValue } { stat.label }
						</>
					);
					// For change-only stats, apply color class to the link/span.
					const changeClass =
						stat.isChangeOnly && stat.change !== 0 ? ` ${ stat.change > 0 ? 'positive' : 'negative' }` : '';
					return (
						<li
							key={ stat.key }
							className={ `activitypub-${ stat.key
								.replace( '-user', '' )
								.replace( '-blog', '' ) }-count` }
						>
							{ url ? (
								<a href={ url } className={ changeClass.trim() }>
									{ content }
								</a>
							) : (
								<span className={ changeClass.trim() }>{ content }</span>
							) }
							{ ! stat.isChangeOnly && stat.change !== 0 && ' ' }
							{ ! stat.isChangeOnly && stat.change !== 0 && (
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
