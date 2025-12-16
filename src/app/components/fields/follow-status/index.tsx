/**
 * Follow Status field for DataViews.
 */

/**
 * External dependencies
 */
import type { ReactNode } from 'react';

/**
 * WordPress dependencies
 */
import { __, _x } from '@wordpress/i18n';
import type { Field } from '@wordpress/dataviews/wp';

/**
 * Internal dependencies
 */
import type { Actor } from '../../../types';
import './style.scss';

export const followStatusField: Field< Actor > = {
	id: 'follow_status',
	label: __( 'Following', 'activitypub' ),
	enableHiding: true,
	getValue: ( { item }: { item: Actor } ): boolean | undefined => item.follow_status?.follows_back,
	render: ( { item }: { item: Actor } ): ReactNode => {
		if ( item.follow_status?.follows_back ) {
			return <span className="activitypub-mutual">{ _x( 'Mutual', 'Follow status', 'activitypub' ) }</span>;
		}
		return <span>—</span>;
	},
};
