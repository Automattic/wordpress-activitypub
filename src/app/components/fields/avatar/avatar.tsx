/**
 * Avatar component that displays an actor's avatar with fallback support.
 */

/**
 * External dependencies
 */
import type { ReactNode, SyntheticEvent } from 'react';

/**
 * Internal dependencies
 */
import { useSettings } from '../../../contexts/settings-context';
import type { Actor } from '../../../types';

interface AvatarProps {
	item: Actor;
}

export default function Avatar( { item }: AvatarProps ): ReactNode {
	const { defaultAvatar } = useSettings();
	const avatarUrl: string = item.actor_info?.icon || defaultAvatar;

	return (
		<img
			alt={ item.actor_info?.username || '' }
			src={ avatarUrl }
			className="activitypub-avatar-field__image"
			onError={ ( e: SyntheticEvent< HTMLImageElement > ): void => {
				e.currentTarget.src = defaultAvatar;
			} }
		/>
	);
}
