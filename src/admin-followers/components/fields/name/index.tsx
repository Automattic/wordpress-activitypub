/**
 * Name field for DataViews.
 */

import React from 'react';
import { __ } from '@wordpress/i18n';
import type { Field, APActor } from '../../../types';

export const nameField: Field = {
	id: 'name',
	label: __( 'Name', 'activitypub' ),
	enableHiding: false,
	enableSorting: true,
	getValue: ( { item }: { item: APActor } ) => item.actor_info?.name || '',
	render: ( { item }: { item: APActor } ) => {
		const name = item.actor_info?.name || '';
		const url = item.actor_info?.url || '#';

		return (
			<a href={ url } target="_blank" rel="noopener noreferrer" className="activitypub-name-field__link">
				<strong>{ name }</strong>
			</a>
		);
	},
};
