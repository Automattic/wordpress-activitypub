/**
 * Avatar component that displays an actor's avatar with fallback support.
 */

import type { Actor, FeedPost } from '../../types';
import './style.scss';

export const DEFAULT_AVATAR =
	"data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 200 200'%3E%3Crect width='200' height='200' fill='%23f0f0f0'/%3E%3Cpath fill='%23c6c6c6' d='M32,201 C22,201 12,201 1,201 C1,134 1,68 1,1 C68,1 134,1 201,1 C201,68 201,134 201,201 C194,201 186,201 178,201 C174,184 165,172 149,166 C145,164 139,163 134,162 C131,161 128,160 126,158 C123,156 122,154 126,151 C147,137 154,112 145,89 C139,70 122,58 104,59 C90,59 79,66 71,77 C54,101 60,135 84,151 C88,154 88,155 84,158 C81,160 78,161 75,162 C53,167 38,179 32,201z'/%3E%3C/svg%3E";

interface AvatarProps {
	item: Actor | FeedPost;
}

export default function Avatar( { item }: AvatarProps ) {
	const avatarUrl: string = item.actor_info?.icon || DEFAULT_AVATAR;

	return (
		<img
			alt={ item.actor_info?.name || item.actor_info?.username || '' }
			src={ avatarUrl }
			className="activitypub-avatar"
			onError={ ( e ): void => {
				( e.target as HTMLImageElement ).src = DEFAULT_AVATAR;
			} }
		/>
	);
}
