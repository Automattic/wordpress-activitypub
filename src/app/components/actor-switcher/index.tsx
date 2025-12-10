/**
 * Actor Switcher Component
 *
 * Displays current actor (user or site) and allows switching between user and blog actors
 * based on user capabilities and actor mode settings.
 */

import { Button, Dropdown, Icon, __experimentalHStack as HStack } from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';
import { useEffect } from '@wordpress/element';
import { people } from '@wordpress/icons';
import { Path, SVG } from '@wordpress/primitives';
import { __ } from '@wordpress/i18n';
import { decodeEntities } from '@wordpress/html-entities';
import { addQueryArgs, filterURLForDisplay } from '@wordpress/url';
import type { UnstableBase } from '@wordpress/core-data';
import { STORE_NAME } from '../../store';
import type { AppSelectors, AppActions } from '../../store';
import SiteIcon from '../site-icon';
import { DEFAULT_AVATAR } from '../avatar';
import './style.scss';

// Actor mode constants matching PHP definitions.
// Hopefully temporary—there's just no good way to query these currently.
const ACTOR_MODE = 'actor';
const BLOG_MODE = 'blog';
const ACTOR_AND_BLOG_MODE = 'actor_blog';

// Logout icon (arrow pointing right, out of the box)
const logout = (
	<SVG xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
		<Path d="M7.2 5h6c1.1 0 2 .9 2 2v1.5h-1.5V7c0-.3-.2-.5-.5-.5h-6c-.3 0-.5.2-.5.5v10c0 .3.2.5.5.5h6c.3 0 .5-.2.5-.5v-1.5h1.5V17c0 1.1-.9 2-2 2h-6c-1.1 0-2-.9-2-2V7c0-1.1.9-2 2-2z" />
		<Path d="M10 11.25h8.2l-1.7-1.7 1.1-1.1 3.5 3.5-3.5 3.5-1.1-1.1 1.7-1.7H10z" />
	</SVG>
);

export default function ActorSwitcher() {
	const { setActiveActor } = useDispatch( STORE_NAME ) as AppActions;

	const { currentUser, activeActorId, actorMode, hasUserCap, hasBlogCap, siteTitle } = useSelect( ( select ) => {
		const { getEntityRecord, getCurrentUser, canUser } = select( coreStore );
		const base = getEntityRecord< UnstableBase >( 'root', '__unstableBase' );
		return {
			currentUser: getCurrentUser(),
			activeActorId: ( select( STORE_NAME ) as AppSelectors ).getActiveActorId(),
			actorMode:
				( getEntityRecord( 'root', 'site' ) as { activitypub_actor_mode?: string } | undefined )
					?.activitypub_actor_mode ?? ACTOR_AND_BLOG_MODE,
			// Check if user has the activitypub capability (can create user extra fields).
			hasUserCap: canUser( 'create', {
				kind: 'postType',
				name: 'ap_extrafield',
			} ),
			// Check if user can manage options (can create blog extra fields).
			hasBlogCap: canUser( 'create', {
				kind: 'postType',
				name: 'ap_extrafield_blog',
			} ),
			siteTitle: ! base?.name && !! base?.url ? filterURLForDisplay( base?.url ) : base?.name,
		};
	}, [] );

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
	const userName: string = currentUser?.name || '';
	const decodedSiteTitle: string = decodeEntities( siteTitle || '' );
	const displayName: string = isSiteActor ? decodedSiteTitle : userName;

	// Get user's role display name based on capabilities
	const getUserRole = (): string => {
		if ( hasBlogCap ) {
			return __( 'Administrator', 'activitypub' );
		}
		if ( hasUserCap ) {
			return __( 'Author', 'activitypub' );
		}
		return __( 'User', 'activitypub' );
	};

	// Determine the appropriate settings link based on available actor.
	const profileHref: string =
		canUseBlogActor && ! canUseUserActor
			? addQueryArgs( 'options-general.php', { page: 'activitypub', tab: 'blog-profile' } )
			: 'profile.php#activitypub';

	return (
		<Dropdown
			className="actor-switcher-container"
			contentClassName="actor-switcher__dropdown"
			popoverProps={ { placement: 'top-start' } }
			focusOnMount={ true }
			renderToggle={ ( { isOpen, onToggle } ) => (
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
			) }
			renderContent={ ( { onClose } ) => (
				<>
					{ /* Current account section */ }
					<div className="actor-switcher__section actor-switcher__current">
						{ isSiteActor ? (
							<SiteIcon className="actor-switcher__dropdown-avatar" />
						) : (
							<img
								src={ userAvatarUrl }
								alt={ displayName }
								className="actor-switcher__dropdown-avatar"
							/>
						) }
						<div className="actor-switcher__account-info">
							<span className="actor-switcher__account-name">{ displayName }</span>
							<span className="actor-switcher__account-role">
								{ isSiteActor ? __( 'Blog', 'activitypub' ) : getUserRole() }
							</span>
						</div>
					</div>

					{ canSwitchActors && (
						<>
							<div className="actor-switcher__divider" />

							{ /* Switch account section */ }
							<div className="actor-switcher__section">
								<span className="actor-switcher__section-header">
									{ __( 'Switch account', 'activitypub' ) }
								</span>
								<button
									type="button"
									className="actor-switcher__menu-item"
									onClick={ (): void => {
										if ( currentUserId ) {
											setActiveActor( isSiteActor ? currentUserId : 0 );
										}
										onClose();
									} }
								>
									{ isSiteActor ? (
										<img
											src={ userAvatarUrl }
											alt={ userName }
											className="actor-switcher__menu-avatar"
										/>
									) : (
										<SiteIcon className="actor-switcher__menu-avatar" />
									) }
									<span className="actor-switcher__menu-label">
										{ isSiteActor ? userName : decodedSiteTitle }
									</span>
									<span className="actor-switcher__menu-badge">
										{ isSiteActor ? getUserRole() : __( 'Blog', 'activitypub' ) }
									</span>
								</button>
							</div>
						</>
					) }

					<div className="actor-switcher__divider" />

					{ /* Actions section */ }
					<div className="actor-switcher__section actor-switcher__actions">
						<a href={ profileHref } className="actor-switcher__menu-item">
							<Icon icon={ people } className="actor-switcher__menu-icon" />
							<span className="actor-switcher__menu-label">
								{ __( 'Manage Profile', 'activitypub' ) }
							</span>
						</a>
						<a
							href="/wp-login.php?action=logout"
							className="actor-switcher__menu-item actor-switcher__menu-item--danger"
						>
							<Icon icon={ logout } className="actor-switcher__menu-icon" />
							<span className="actor-switcher__menu-label">{ __( 'Log out', 'activitypub' ) }</span>
						</a>
					</div>
				</>
			) }
		/>
	);
}
