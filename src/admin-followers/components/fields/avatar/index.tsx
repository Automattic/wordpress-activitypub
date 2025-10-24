/**
 * Avatar field for DataViews.
 */

import React from 'react';
import { __ } from '@wordpress/i18n';
import type { Field, APActor } from '../../../types';
import './style.scss';

const DEFAULT_AVATAR = window.activityPubAdmin?.defaultAvatar || '/avatar-default.png';

export const avatarField: Field = {
	id: 'avatar',
	label: __( 'Avatar', 'activitypub' ),
	type: 'media',
	enableHiding: false,
	enableSorting: false,
	getValue: ( { item }: { item: APActor } ) => item.actor_info?.icon || DEFAULT_AVATAR,
	render: ( { item }: { item: APActor } ) => (
		<img
			alt={ item.actor_info?.username || '' }
			src={ item.actor_info?.icon || DEFAULT_AVATAR }
			className="activitypub-avatar-field__image"
			onError={ ( e ) => {
				( e.target as HTMLImageElement ).src = DEFAULT_AVATAR;
			} }
		/>
	),
};
