import { __, _n, sprintf } from '@wordpress/i18n';
import type { Multiplicator } from '../../types';

interface Props {
	multiplicator: Multiplicator | null | undefined;
}

/**
 * Top Supporter Component.
 */
export default function TopSupporter( { multiplicator }: Props ) {
	if ( ! multiplicator?.name ) {
		return null;
	}

	return (
		<div className="activitypub-stats-multiplicator">
			<h4>{ __( 'Top Supporter', 'activitypub' ) }</h4>
			<p>
				<a href={ multiplicator.url } target="_blank" rel="noopener noreferrer">
					{ multiplicator.name }
				</a>{ ' ' }
				{ sprintf(
					/* translators: %s: number of boosts */
					_n( '(%s boost)', '(%s boosts)', multiplicator.count, 'activitypub' ),
					multiplicator.count.toLocaleString()
				) }
			</p>
		</div>
	);
}
