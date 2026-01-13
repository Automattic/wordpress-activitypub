/**
 * Avatar field for DataViews.
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
import Avatar from '../../avatar';
import type { Actor } from '../../../types';
import './style.scss';

export const avatarField: Field< Actor > = {
	id: 'avatar',
	label: __( 'Avatar', 'activitypub' ),
	type: 'media',
	enableHiding: false,
	enableSorting: false,
	getValue: ( { item }: { item: Actor } ): string => item.actor_info?.icon || '',
	render: ( { item }: { item: Actor } ): ReactNode => <Avatar item={ item } />,
};
