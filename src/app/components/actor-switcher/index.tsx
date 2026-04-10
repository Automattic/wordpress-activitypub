/**
 * Actor Switcher Component
 *
 * Displays current actor (user or site) and allows switching between user and blog actors
 * based on user capabilities and actor mode settings.
 */

/**
 * External dependencies
 */
import type { ReactNode, SyntheticEvent } from 'react';
import { UseNavigateResult } from '@tanstack/react-router';

/**
 * WordPress dependencies
 */
import { Button, __experimentalHStack as HStack } from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';
import { useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { addQueryArgs } from '@wordpress/url';

/**
 * Internal dependencies
 */
import { STORE_NAME } from '../../store';
import type { AppSelectors, AppActions } from '../../store';
import { useNavigate } from '../../router';
import SiteIcon from '../site-icon';
import { DEFAULT_AVATAR } from '../avatar';
import './style.scss';

// Actor mode constants matching PHP definitions.
// Hopefully temporary—there's just no good way to query these currently.
const ACTOR_MODE = 'actor';
const BLOG_MODE = 'blog';
const ACTOR_AND_BLOG_MODE = 'actor_blog';

export default function ActorSwitcher(): ReactNode {
	const navigate: UseNavigateResult< string > = useNavigate();
	const { setActiveActor } = useDispatch( STORE_NAME ) as AppActions;

	const { currentUser, activeActorId, actorMode, hasUserCap, hasBlogCap } = useSelect(
		( select ) => ( {
			currentUser: select( coreStore ).getCurrentUser(),
			activeActorId: ( select( STORE_NAME ) as AppSelectors ).getActiveActorId(),
			actorMode:
				(
					select( coreStore ).getEntityRecord( 'root', 'site' ) as
						| { activitypub_actor_mode?: string }
						| undefined
				 )?.activitypub_actor_mode ?? ACTOR_AND_BLOG_MODE,
			// Check if user has the activitypub capability (can create user extra fields).
			hasUserCap: select( coreStore ).canUser( 'create', {
				kind: 'postType',
				name: 'ap_extrafield',
			} ),
			// Check if user can manage options (can create blog extra fields).
			hasBlogCap: select( coreStore ).canUser( 'create', {
				kind: 'postType',
				name: 'ap_extrafield_blog',
			} ),
		} ),
		[]
	);

	// User can use their actor if user mode is enabled AND they have the capability.
	const userModeEnabled: boolean = actorMode === ACTOR_MODE || actorMode === ACTOR_AND_BLOG_MODE;
	const canUseUserActor: boolean = userModeEnabled && hasUserCap;

	// User can use the blog actor if blog mode is enabled AND they have the capability.
	const blogModeEnabled: boolean = actorMode === BLOG_MODE || actorMode === ACTOR_AND_BLOG_MODE;
	const canUseBlogActor: boolean = blogModeEnabled && hasBlogCap;

	const currentUserId: number = currentUser?.id;
	const canSwitchActors: boolean = canUseUserActor && canUseBlogActor;

	// Correct the active actor if it's not valid for the current mode.
	const isSiteActor: boolean = activeActorId === 0;
	useEffect( (): void => {
		if ( isSiteActor && ! canUseBlogActor && canUseUserActor && currentUserId ) {
			// Blog actor is selected but not available, switch to user actor.
			setActiveActor( currentUserId );
		} else if ( ! isSiteActor && ! canUseUserActor && canUseBlogActor ) {
			// User actor is selected but not available, switch to blog actor.
			setActiveActor( 0 );
		}
	}, [ isSiteActor, canUseUserActor, canUseBlogActor, currentUserId, setActiveActor ] );

	const userAvatarUrl: string = currentUser?.avatar_urls?.[ 48 ] || DEFAULT_AVATAR;
	const displayName: string = isSiteActor ? __( 'Site', 'activitypub' ) : currentUser?.name || '';

	// Toggle between user and site actor.
	const onClick = (): void => {
		if ( canSwitchActors && currentUserId ) {
			setActiveActor( activeActorId === 0 ? currentUserId : 0 );

			// Close inspector.
			void navigate( {
				search: ( ( prev: Record< string, unknown > ): Record< string, unknown > => {
					const { postId: _, ...rest } = prev as { postId?: number };
					return rest;
				} ) as never,
			} );
		}
	};

	// Determine the appropriate settings link based on available actor.
	const href: string =
		canUseBlogActor && ! canUseUserActor
			? addQueryArgs( 'options-general.php', { page: 'activitypub', tab: 'blog-profile' } )
			: 'profile.php#activitypub';

	return (
		<Button
			{ ...( canSwitchActors ? { onClick } : { href } ) }
			className="actor-switcher"
			label={ canSwitchActors ? __( 'Switch Actor', 'activitypub' ) : __( 'Profile', 'activitypub' ) }
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
							e.currentTarget.src = DEFAULT_AVATAR;
						} }
					/>
				) }
				<span className="actor-switcher__name">{ displayName }</span>
			</HStack>
		</Button>
	);
}
