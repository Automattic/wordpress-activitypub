/**
 * Account Menu
 *
 * Registers permitted accounts in core's sidebar and applies account navigation.
 */

/**
 * External dependencies
 */
import type { ReactNode } from 'react';

/**
 * WordPress dependencies
 */
import { useSelect, useDispatch } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';
import { store as bootStore } from '@wordpress/boot';
import { useEffect } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { replace } from '@wordpress/icons';
import { useParams } from '@wordpress/route';
import { useView } from '@wordpress/views';

/**
 * Internal dependencies
 */
import { STORE_NAME } from '../../store';
import type { AppSelectors, AppActions } from '../../store';
import { DEFAULT_VIEW, defaultLayouts } from '../../routes/feed/utils';

// Actor mode constants matching PHP definitions.
// Hopefully temporary—there's just no good way to query these currently.
const ACTOR_MODE = 'actor';
const BLOG_MODE = 'blog';
const ACTOR_AND_BLOG_MODE = 'actor_blog';

export default function AccountMenu(): ReactNode {
	const { actorId } = useParams( { strict: false } ) as { actorId?: string };
	const { setActiveActor } = useDispatch( STORE_NAME ) as AppActions;
	const { registerMenuItem } = useDispatch( bootStore );
	const { view, updateView } = useView( {
		kind: 'postType',
		name: 'ap_post',
		slug: 'feed',
		defaultView: DEFAULT_VIEW,
		defaultLayouts,
	} );

	const { currentUser, activeActorId, actorMode, hasUserCap, hasBlogCap } = useSelect(
		( select ) => ( {
			currentUser: select( coreStore ).getCurrentUser(),
			activeActorId: ( select( STORE_NAME ) as AppSelectors ).getActiveActorId(),
			actorMode: (
				select( coreStore ).getEntityRecord( 'root', 'site' ) as { activitypub_actor_mode?: string } | undefined
			 )?.activitypub_actor_mode,
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
	const isReady = !! currentUser && actorMode !== undefined && hasUserCap !== undefined && hasBlogCap !== undefined;

	// Correct the active actor if it's not valid for the current mode.
	const isSiteActor: boolean = activeActorId === 0;
	useEffect( (): void => {
		if ( ! isReady ) {
			return;
		}
		const switchActor = ( nextActorId: number ): void => {
			// Reset on the committed switch, including when the route remounts the feed.
			updateView( { ...DEFAULT_VIEW, fields: view.fields } );
			setActiveActor( nextActorId );
		};
		if ( actorId !== undefined ) {
			// Commit only after navigation: core also runs route loaders when links are hovered.
			if ( actorId === '0' && canUseBlogActor && activeActorId !== 0 ) {
				switchActor( 0 );
			} else if ( actorId === String( currentUserId ) && canUseUserActor && activeActorId !== currentUserId ) {
				switchActor( currentUserId );
			}
			return;
		}
		if ( isSiteActor && ! canUseBlogActor && canUseUserActor && currentUserId ) {
			// Blog actor is selected but not available, switch to user actor.
			switchActor( currentUserId );
		} else if ( ! isSiteActor && ! canUseUserActor && canUseBlogActor ) {
			// User actor is selected but not available, switch to blog actor.
			switchActor( 0 );
		}
	}, [
		isReady,
		actorId,
		activeActorId,
		isSiteActor,
		canUseUserActor,
		canUseBlogActor,
		currentUserId,
		setActiveActor,
		updateView,
		view.fields,
	] );

	const userName: string = currentUser?.name || __( 'Your account', 'activitypub' );
	const displayName: string = isSiteActor ? __( 'Site', 'activitypub' ) : userName;
	useEffect( () => {
		if ( ! isReady || ! canUseUserActor || ! canUseBlogActor ) {
			return;
		}
		registerMenuItem( 'feed-account', {
			id: 'feed-account',
			parent: 'feed',
			to: `/account/${ isSiteActor ? currentUserId : 0 }`,
			/* translators: %s: current account name. */
			label: sprintf( __( 'Account: %s', 'activitypub' ), displayName ),
			icon: replace,
		} );
	}, [ isReady, canUseUserActor, canUseBlogActor, isSiteActor, currentUserId, displayName, registerMenuItem ] );

	return null;
}
