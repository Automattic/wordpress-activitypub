/**
 * Feed Description Component
 *
 * Shows context-aware description based on active actor.
 */

/**
 * External dependencies
 */
import type { ReactNode } from 'react';

/**
 * WordPress dependencies
 */
import { useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { STORE_NAME } from '../../store';
import type { AppSelectors } from '../../store';

export default function FeedDescription(): ReactNode {
	const activeActorId: number | null = useSelect(
		( select ): number | null => ( select( STORE_NAME ) as AppSelectors ).getActiveActorId(),
		[]
	);

	const text: string =
		activeActorId === 0
			? __( 'Posts from accounts this site follows.', 'activitypub' )
			: __( 'Posts from accounts you follow.', 'activitypub' );

	return <>{ text }</>;
}
