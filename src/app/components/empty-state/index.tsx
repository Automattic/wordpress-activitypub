/**
 * Empty State component.
 *
 * Displays contextual messages when the feed has no posts.
 */

/**
 * External dependencies
 */
import type { ReactNode } from 'react';

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { createInterpolateElement } from '@wordpress/element';
import { addQueryArgs } from '@wordpress/url';
import { useSelect } from '@wordpress/data';
import { useView } from '@wordpress/views';

/**
 * Internal dependencies
 */
import { STORE_NAME } from '../../store';
import type { AppSelectors } from '../../store';

// Minimal default view for consistency with other components using the same view state
const DEFAULT_VIEW = {
	type: 'list' as const,
	filters: [],
	search: '',
};

export default function EmptyState(): ReactNode {
	const activeActorId = useSelect(
		( select ): number | null => ( select( STORE_NAME ) as AppSelectors ).getActiveActorId(),
		[]
	);

	const { view } = useView( {
		kind: 'postType',
		name: 'ap_post',
		slug: 'feed',
		defaultView: DEFAULT_VIEW,
	} );

	// If search or filters are active, show simple "no results" message.
	if ( view?.search || ( view?.filters && view.filters.length > 0 ) ) {
		return <p>{ __( 'No posts found.', 'activitypub' ) }</p>;
	}

	// Show prompt to follow more people with link to following page.
	const followingUrl =
		activeActorId === 0
			? addQueryArgs( 'options-general.php', { page: 'activitypub', tab: 'following' } )
			: addQueryArgs( 'users.php', { page: 'activitypub-following-list' } );

	return (
		<p>
			{ createInterpolateElement(
				__(
					'Your feed is waiting to come alive. <a>Follow more people on the Fediverse</a> to see their posts here.',
					'activitypub'
				),
				{
					a: <a href={ followingUrl } />,
				}
			) }
		</p>
	);
}
