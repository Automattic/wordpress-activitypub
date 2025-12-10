/**
 * Current Account Section Component
 */

import SiteIcon from '../site-icon';

type CurrentAccountProps = {
	isSiteActor: boolean;
	userAvatarUrl: string;
	displayName: string;
	roleLabel: string;
};

export default function CurrentAccount( { isSiteActor, userAvatarUrl, displayName, roleLabel }: CurrentAccountProps ) {
	return (
		<div className="actor-switcher__section actor-switcher__current">
			{ isSiteActor ? (
				<SiteIcon className="actor-switcher__dropdown-avatar" />
			) : (
				<img src={ userAvatarUrl } alt={ displayName } className="actor-switcher__dropdown-avatar" />
			) }
			<div className="actor-switcher__account-info">
				<span className="actor-switcher__account-name">{ displayName }</span>
				<span className="actor-switcher__account-role">{ roleLabel }</span>
			</div>
		</div>
	);
}
