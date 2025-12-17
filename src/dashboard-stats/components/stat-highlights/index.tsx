import { __ } from '@wordpress/i18n';
import type { Comparison } from '../../types';

interface Props {
	comparison: Comparison | null;
}

/**
 * Stat Highlights Component.
 *
 * Displays key statistics with year-over-year comparison.
 */
export default function StatHighlights( { comparison }: Props ) {
	if ( ! comparison ) {
		return null;
	}

	const stats = [
		{
			key: 'followers',
			label: __( 'Followers', 'activitypub' ),
			value: comparison.followers?.current ?? 0,
			change: comparison.followers?.change ?? 0,
		},
		{
			key: 'posts',
			label: __( 'Posts', 'activitypub' ),
			value: comparison.posts?.current ?? 0,
			change: comparison.posts?.change ?? 0,
		},
		{
			key: 'likes',
			label: __( 'Likes', 'activitypub' ),
			value: comparison.like?.current ?? 0,
			change: comparison.like?.change ?? 0,
		},
		{
			key: 'reposts',
			label: __( 'Reposts', 'activitypub' ),
			value: comparison.repost?.current ?? 0,
			change: comparison.repost?.change ?? 0,
		},
	];

	return (
		<div className="activitypub-stats-highlights">
			{ stats.map( ( stat ) => (
				<div key={ stat.key } className="activitypub-stat-item">
					<span className="stat-value">{ stat.value.toLocaleString() }</span>
					<span className="stat-label">{ stat.label }</span>
					{ stat.change !== 0 && (
						<span className={ `stat-change ${ stat.change > 0 ? 'positive' : 'negative' }` }>
							{ stat.change > 0 ? '+' : '' }
							{ stat.change.toLocaleString() }
						</span>
					) }
				</div>
			) ) }
		</div>
	);
}
