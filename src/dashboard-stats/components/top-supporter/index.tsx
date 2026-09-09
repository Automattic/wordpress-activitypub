/**
 * External dependencies
 */
import type { ReactNode } from 'react';

/**
 * WordPress dependencies
 */
import { __, _n, sprintf } from '@wordpress/i18n';
import { ExternalLink } from '@wordpress/components';

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
				<ExternalLink href={ multiplicator.url } rel="external noreferrer noopener">
					{ multiplicator.name }
				</ExternalLink>{ ' ' }
				{ sprintf(
					/* translators: %s: number of boosts */
					_n( '(%s boost)', '(%s boosts)', multiplicator.count, 'activitypub' ),
					multiplicator.count.toLocaleString()
				) }
			</p>
		</div>
	);
}
