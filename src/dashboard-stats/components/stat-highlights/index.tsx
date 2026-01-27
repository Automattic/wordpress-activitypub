import { __ } from '@wordpress/i18n';
import type { Comparison, CommentType } from '../../types';

interface Props {
	comparison: Comparison | null;
	commentTypes: Record< string, CommentType > | null;
}

/**
 * Stat Highlights Component.
 *
 * Displays key statistics with month-over-month comparison.
 *
 * @param {Props} props              Component props.
 * @param {Props} props.comparison   Comparison data with current vs previous values.
 * @param {Props} props.commentTypes Available comment types configuration.
 */
export default function StatHighlights( { comparison, commentTypes }: Props ) {
	if ( ! comparison ) {
		return null;
	}

	// Build stats array dynamically from comparison data and comment types.
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
	];

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
		<div className="activitypub-stats-highlights">
			<h3 className="activitypub-stats-period">{ __( 'This month vs. last year', 'activitypub' ) }</h3>
			<div className="activitypub-stats-grid">
				{ stats.map( ( stat ) => (
					<div key={ stat.key } className="activitypub-stat-item" data-type={ stat.key }>
						<span className="stat-content">
							<span className="stat-value">{ stat.value.toLocaleString() }</span>
							<span className="stat-label">{ stat.label }</span>
							{ stat.change !== 0 && (
								<span className={ `stat-change ${ stat.change > 0 ? 'positive' : 'negative' }` }>
									({ stat.change > 0 ? '+' : '' }
									{ stat.change.toLocaleString() })
								</span>
							) }
						</span>
					</div>
				) ) }
			</div>
		</div>
	);
}
