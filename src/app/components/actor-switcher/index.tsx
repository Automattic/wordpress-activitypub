/**
 * Actor Switcher Component
 *
 * Displays current actor (user or site) and allows admins to toggle between them
 */

/**
 * External dependencies
 */
import type { ReactNode, SyntheticEvent } from 'react';

/**
 * WordPress dependencies
 */
import { Button, __experimentalHStack as HStack } from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';
import type { User } from '@wordpress/core-data';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { useSettings } from '../../contexts/settings-context';
import { STORE_NAME } from '../../store';
import type { AppSelectors, AppActions } from '../../store';
import SiteIcon from '../site-icon';
import './style.scss';

interface ActorSwitcherData {
	currentUser: User | undefined;
	activeActorId: number | null;
	canManageSite: boolean | undefined;
}

export default function ActorSwitcher(): ReactNode {
	const { defaultAvatar, adminUrl } = useSettings();
	const { setActiveActor } = useDispatch( STORE_NAME ) as AppActions;

	const { currentUser, activeActorId, canManageSite }: ActorSwitcherData = useSelect(
		( select ): ActorSwitcherData => ( {
			currentUser: select( coreStore ).getCurrentUser() as User | undefined,
			activeActorId: ( select( STORE_NAME ) as AppSelectors ).getActiveActorId(),
			canManageSite: select( coreStore ).canUser( 'read', {
				kind: 'root',
				name: 'site',
			} ),
		} ),
		[]
	);

	const currentUserId: number | undefined = currentUser?.id;

	// Determine which actor info to display
	const isSiteActor: boolean = activeActorId === 0;
	const userAvatarUrl: string = currentUser?.avatar_urls?.[ 48 ] || defaultAvatar;
	const displayName: string = isSiteActor ? __( 'Site', 'activitypub' ) : currentUser?.name || '';

	// Toggle between user and site actor
	const toggleActor = (): void => {
		if ( canManageSite && currentUserId ) {
			const newActorId: number = activeActorId === 0 ? currentUserId : 0;
			setActiveActor( newActorId );
		}
	};

	return (
		<Button
			{ ...( canManageSite ? { onClick: toggleActor } : { href: `${ adminUrl }profile.php` } ) }
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
						onError={ ( e: SyntheticEvent< HTMLImageElement > ): void => {
							e.currentTarget.src = defaultAvatar;
						} }
					/>
				) }
				<span className="actor-switcher__name">{ displayName }</span>
			</HStack>
		</Button>
	);
}
