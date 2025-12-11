/**
 * Avatar field for DataViews.
 */

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import type { Field } from '@wordpress/dataviews';

/**
 * Internal dependencies
 */
import Avatar from '../../avatar';
import type { Actor } from '../../../types';
import './style.scss';

export const avatarField: Field< Actor > = {
	id: 'avatar',
	label: __( 'Avatar', 'activitypub' ),
	type: 'media',
	enableHiding: false,
	enableSorting: false,
	getValue: ( { item }: { item: Actor } ) => item.actor_info?.icon || '',
	render: ( { item }: { item: Actor } ) => <Avatar item={ item } />,
};
