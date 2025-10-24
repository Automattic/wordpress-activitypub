/**
 * Webfinger/Profile field for DataViews.
 */

import React from 'react';
import { __ } from '@wordpress/i18n';
import type { Field, APActor } from '../../../types';

export const webfingerField: Field = {
	id: 'webfinger',
	label: __( 'Profile', 'activitypub' ),
	enableHiding: true,
	getValue: ( { item }: { item: APActor } ) => item.actor_info?.webfinger || '',
	render: ( { item }: { item: APActor } ) => {
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
