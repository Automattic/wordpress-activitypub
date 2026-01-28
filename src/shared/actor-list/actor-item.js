import { useOptions } from '../use-options';

/**
 * Component to display a single actor (follower/following).
 *
 * @param {Object} props           The component props.
 * @param {string} props.name      The name of the actor.
 * @param {Object} props.icon      The icon of the actor.
 * @param {string} props.url       The URL of the actor.
 * @param {string} props.webfinger The webfinger of the actor.
 * @return {JSX.Element} The actor component.
 */
export function ActorItem( { name, icon, url, webfinger } ) {
	const handle = `@${ webfinger }`;
	const { defaultAvatarUrl, showAvatars } = useOptions();
	const avatar = icon?.url || defaultAvatarUrl;

	return (
		<a
			className="activitypub-actor-link"
			href={ url }
			title={ handle }
			onClick={ ( event ) => event.preventDefault() }
		>
			{ showAvatars && (
				<img
					width="48"
					height="48"
					src={ avatar }
					className="activitypub-actor-avatar"
					alt={ name }
					onError={ ( event ) => {
						event.target.src = defaultAvatarUrl;
					} }
				/>
			) }
			<div className="activitypub-actor-info">
				<span className="activitypub-actor-name">{ name }</span>
				<span className="activitypub-actor-handle">{ handle }</span>
			</div>
			<svg
				xmlns="http://www.w3.org/2000/svg"
				viewBox="0 0 24 24"
				width="24"
				height="24"
				className="external-link-icon"
				aria-hidden="true"
				focusable="false"
				fill="currentColor"
			>
				<path d="M18.2 17c0 .7-.6 1.2-1.2 1.2H7c-.7 0-1.2-.6-1.2-1.2V7c0-.7.6-1.2 1.2-1.2h3.2V4.2H7C5.5 4.2 4.2 5.5 4.2 7v10c0 1.5 1.2 2.8 2.8 2.8h10c1.5 0 2.8-1.2 2.8-2.8v-3.6h-1.5V17zM14.9 3v1.5h3.7l-6.4 6.4 1.1 1.1 6.4-6.4v3.7h1.5V3h-6.3z"></path>
			</svg>
		</a>
	);
}
