/**
 * Actor Switcher Component
 *
 * Displays current actor (user or site) and allows admins to toggle between them
 */

import { Button, __experimentalHStack as HStack } from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';
import { __ } from '@wordpress/i18n';
import { STORE_NAME } from '../../store';
import type { AppSelectors, AppActions } from '../../store';
import SiteIcon from '../site-icon';
import { DEFAULT_AVATAR } from '../avatar';
import './style.scss';

export default function ActorSwitcher() {
	const { setActiveActor } = useDispatch( STORE_NAME ) as AppActions;

	const { currentUser, activeActorId, canManageSite } = useSelect(
		( select ) => ( {
			currentUser: select( coreStore ).getCurrentUser(),
			activeActorId: ( select( STORE_NAME ) as AppSelectors ).getActiveActorId(),
			canManageSite: select( coreStore ).canUser( 'read', {
				kind: 'root',
				name: 'site',
			} ),
		} ),
		[]
	);

	const currentUserId = currentUser?.id;

	// Determine which actor info to display
	const isSiteActor = activeActorId === 0;
	const userAvatarUrl = currentUser?.avatar_urls?.[ 48 ] || DEFAULT_AVATAR;
	const displayName = isSiteActor ? __( 'Site', 'activitypub' ) : currentUser?.name || '';

	// Toggle between user and site actor
	const toggleActor = () => {
		if ( canManageSite && currentUserId ) {
			const newActorId = activeActorId === 0 ? currentUserId : 0;
			setActiveActor( newActorId );
		}
	};

	return (
		<Button
			{ ...( canManageSite ? { onClick: toggleActor } : { href: 'profile.php' } ) }
			className="actor-switcher"
			label={ canManageSite ? __( 'Switch Actor', 'activitypub' ) : __( 'Profile', 'activitypub' ) }
		>
			<HStack spacing={ 2 } alignment="center">
				{ isSiteActor ? (
					<SiteIcon className="actor-switcher__avatar" />
				) : (
					<img
						src={ userAvatarUrl }
						alt={ displayName }
						className="actor-switcher__avatar"
						onError={ ( e ) => {
							( e.target as HTMLImageElement ).src = DEFAULT_AVATAR;
						} }
					/>
				) }
				<span className="actor-switcher__name">{ displayName }</span>
			</HStack>
		</Button>
	);
}
