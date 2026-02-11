/**
 * External dependencies
 */
import type { ReactNode } from 'react';

/**
 * WordPress dependencies
 */
import { __, _n, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import type { Multiplicator } from '../../types';

interface Props {
	multiplicator: Multiplicator | null | undefined;
}

/**
 * Top Supporter Component.
 *
 * @param {Props} props Component props.
 */
export default function TopSupporter( { multiplicator }: Props ): ReactNode {
	if ( ! multiplicator?.name ) {
		return null;
	}

	return (
		<div className="activitypub-stats-multiplicator">
			<h3>{ __( 'Top Supporter', 'activitypub' ) }</h3>
			<p>
				<a
					href={ multiplicator.url }
					target="_blank"
					rel="noopener noreferrer"
					aria-label={ sprintf(
						/* translators: %s: supporter name */
						__( '%s (opens in a new tab)', 'activitypub' ),
						multiplicator.name
					) }
				>
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
