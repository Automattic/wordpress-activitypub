/**
 * Webfinger/Profile field for DataViews.
 */

import { __ } from '@wordpress/i18n';
import type { Field } from '@wordpress/dataviews';
import type { Actor } from '../../../types';

export const webfingerField: Field< Actor > = {
	id: 'webfinger',
	label: __( 'Profile', 'activitypub' ),
	enableHiding: true,
	getValue: ( { item }: { item: Actor } ) => item.actor_info?.webfinger || '',
	render: ( { item }: { item: Actor } ) => {
		const webfinger = item.actor_info?.webfinger || '';
		const url = item.actor_info?.url || '#';

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
