/**
 * Name field for DataViews.
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

export const nameField: Field< Actor > = {
	id: 'name',
	label: __( 'Name', 'activitypub' ),
	enableHiding: false,
	enableSorting: true,
	getValue: ( { item }: { item: Actor } ): string => item.actor_info?.name || '',
	render: ( { item }: { item: Actor } ): ReactNode => {
		const name: string = item.actor_info?.name || '';
		const url: string = safeUrl( item.actor_info?.url || '' );

		return (
			<a href={ url } target="_blank" rel="noopener noreferrer" className="activitypub-name-field__link">
				<strong>{ name }</strong>
			</a>
		);
	},
};
