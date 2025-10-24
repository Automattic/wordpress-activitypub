/**
 * Modified/Last Updated field for DataViews.
 */

import React from 'react';
import { __ } from '@wordpress/i18n';
import { dateI18n } from '@wordpress/date';
import type { Field, APActor } from '../../../types';

export const modifiedField: Field = {
	id: 'modified',
	label: __( 'Last Updated', 'activitypub' ),
	enableHiding: true,
	enableSorting: true,
	getValue: ( { item }: { item: APActor } ) => item.modified_gmt || item.modified,
	render: ( { item }: { item: APActor } ) => {
		const date = item.modified_gmt || item.modified;
		if ( ! date ) {
			return <span>—</span>;
		}
		return <time dateTime={ date }>{ dateI18n( 'M j, Y', date ) }</time>;
	},
	filterBy: {
		operators: [ 'after', 'before' ],
	},
};
