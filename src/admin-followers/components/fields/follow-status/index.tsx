/**
 * Follow Status field for DataViews.
 */

import React from 'react';
import { __, _x } from '@wordpress/i18n';
import type { Field, APActor } from '../../../types';
import './style.scss';

export const followStatusField: Field = {
	id: 'follow_status',
	label: __( 'Following', 'activitypub' ),
	enableHiding: true,
	getValue: ( { item }: { item: APActor } ) => item.follow_status?.follows_back,
	render: ( { item }: { item: APActor } ) => {
		if ( item.follow_status?.follows_back ) {
			return <span className="activitypub-mutual">{ _x( 'Mutual', 'Follow status', 'activitypub' ) }</span>;
		}
		return <span>—</span>;
	},
};
