/**
 * Webfinger/Profile field for DataViews.
 */

/**
 * External dependencies
 */
import type { ReactNode } from 'react';

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import type { Field } from '@wordpress/dataviews/wp';

/**
 * Internal dependencies
 */
import type { Actor } from '../../../types';
import { safeUrl } from '../../../utils';

export const webfingerField: Field< Actor > = {
	id: 'webfinger',
	label: __( 'Profile', 'activitypub' ),
	enableHiding: true,
	getValue: ( { item }: { item: Actor } ): string => item.actor_info?.webfinger || '',
	render: ( { item }: { item: Actor } ): ReactNode => {
		const webfinger: string = item.actor_info?.webfinger || '';
		const url: string = safeUrl( item.actor_info?.url || '' );

		if ( ! webfinger ) {
			return <span>—</span>;
		}

		return (
			<a href={ url } target="_blank" rel="noopener noreferrer" title={ webfinger }>
				@{ webfinger }
			</a>
		);
	},
};
