/**
 * Name field for DataViews.
 */

import { __ } from '@wordpress/i18n';
import type { Field } from '@wordpress/dataviews';
import type { Actor } from '../../../types';

export const nameField: Field< Actor > = {
	id: 'name',
	label: __( 'Name', 'activitypub' ),
	enableHiding: false,
	enableSorting: true,
	getValue: ( { item }: { item: Actor } ) => item.actor_info?.name || '',
	render: ( { item }: { item: Actor } ) => {
		const name = item.actor_info?.name || '';
		const url = item.actor_info?.url || '#';

		return (
			<a href={ url } target="_blank" rel="noopener noreferrer" className="activitypub-name-field__link">
				<strong>{ name }</strong>
			</a>
		);
	},
};
