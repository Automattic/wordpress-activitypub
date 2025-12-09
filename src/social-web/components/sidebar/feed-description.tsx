/**
 * Feed Description Component
 *
 * Shows context-aware description based on active actor.
 */

/**
 * External dependencies
 */
import { ReactNode } from 'react';

/**
 * WordPress dependencies
 */
import { useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { STORE_NAME } from '../../store';
import type { SocialWebSelectors } from '../../store';

export default function FeedDescription(): ReactNode {
	const activeActorId = useSelect(
		( select ) => ( select( STORE_NAME ) as SocialWebSelectors ).getActiveActorId(),
		[]
	);

	const text =
		activeActorId === 0
			? __( 'Posts from accounts this site follows.', 'activitypub' )
			: __( 'Posts from accounts you follow.', 'activitypub' );

	return <>{ text }</>;
}
