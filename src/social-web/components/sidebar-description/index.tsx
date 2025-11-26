/**
 * Sidebar Description Component
 *
 * Displays a contextual description based on the active actor
 */

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { useSelect } from '@wordpress/data';
import { SelectFunction } from '@wordpress/data/build-types/types';

/**
 * Internal dependencies
 */
import { STORE_NAME } from '../../store';
import type { SocialWebSelectors } from '../../store';
import './style.scss';

export default function SidebarDescription() {
	const activeActorId: number = useSelect(
		( select: SelectFunction ): number => ( select( STORE_NAME ) as SocialWebSelectors ).getActiveActorId(),
		[]
	);

	const isSiteActor: boolean = activeActorId === 0;

	const description: string = isSiteActor
		? __( 'Posts from accounts this site follows.', 'activitypub' )
		: __( 'Posts from accounts you follow.', 'activitypub' );

	return <p className="sidebar-description">{ description }</p>;
}
