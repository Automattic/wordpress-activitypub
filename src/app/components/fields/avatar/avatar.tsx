/**
 * Avatar component that displays an actor's avatar with fallback support.
 */

import { useSettings } from '../../../contexts/settings-context';
import type { Actor } from '../../../types';

interface AvatarProps {
	item: Actor;
}

export default function Avatar( { item }: AvatarProps ) {
	const { defaultAvatar } = useSettings();
	const avatarUrl = item.actor_info?.icon || defaultAvatar;

	return (
		<img
			alt={ item.actor_info?.username || '' }
			src={ avatarUrl }
			className="activitypub-avatar-field__image"
			onError={ ( e ) => {
				( e.target as HTMLImageElement ).src = defaultAvatar;
			} }
		/>
	);
}
