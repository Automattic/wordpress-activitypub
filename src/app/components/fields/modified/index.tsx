/**
 * Modified/Last Updated field for DataViews.
 */

/**
 * External dependencies
 */
import type { ReactNode } from 'react';

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { dateI18n } from '@wordpress/date';
import type { Field } from '@wordpress/dataviews';

/**
 * Internal dependencies
 */
import type { Actor } from '../../../types';

export const modifiedField: Field< Actor > = {
	id: 'modified',
	label: __( 'Last Updated', 'activitypub' ),
	enableHiding: true,
	enableSorting: true,
	getValue: ( { item }: { item: Actor } ): string => item.modified_gmt || item.modified,
	render: ( { item }: { item: Actor } ): ReactNode => {
		const date: string = item.modified_gmt || item.modified;
		if ( ! date ) {
			return <span>—</span>;
		}
		return <time dateTime={ date }>{ dateI18n( 'M j, Y', date ) }</time>;
	},
	filterBy: {
		operators: [ 'after', 'before' ],
	},
};
