/**
 * Actor Switcher Toggle Component
 */

import { Button, __experimentalHStack as HStack } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import SiteIcon from '../site-icon';
import { DEFAULT_AVATAR } from '../avatar';

type ToggleProps = {
	isOpen: boolean;
	onToggle: () => void;
	isSiteActor: boolean;
	userAvatarUrl: string;
	displayName: string;
};

export default function Toggle( {
	isOpen,
	onToggle,
	isSiteActor,
	userAvatarUrl,
	displayName,
}: ToggleProps ) {
	return (
		<Button
			onClick={ onToggle }
			className="actor-switcher"
			aria-expanded={ isOpen }
			label={ __( 'Account menu', 'activitypub' ) }
		>
			<HStack spacing={ 2 } alignment="center">
				{ isSiteActor ? (
					<SiteIcon className="actor-switcher__avatar" />
				) : (
					<img
						src={ userAvatarUrl }
						alt={ displayName }
						className="actor-switcher__avatar"
						onError={ ( e ): void => {
							( e.target as HTMLImageElement ).src = DEFAULT_AVATAR;
						} }
					/>
				) }
				<span className="actor-switcher__name">{ displayName }</span>
			</HStack>
		</Button>
	);
}
