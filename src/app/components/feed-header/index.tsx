/**
 * Feed Header
 *
 * Sits at the top of the stage in boot's full-page mode and carries what our
 * own sidebar used to: the feed description, the actor switcher and the
 * settings link. Navigation itself is boot's sidebar now.
 */

/**
 * External dependencies
 */
import type { ReactNode } from 'react';

/**
 * WordPress dependencies
 */
import { Button, __experimentalHStack as HStack } from '@wordpress/components';
import { cog } from '@wordpress/icons';
import { __ } from '@wordpress/i18n';
import { addQueryArgs } from '@wordpress/url';

/**
 * Internal dependencies
 */
import ActorSwitcher from '../actor-switcher';
import FeedDescription from '../sidebar/feed-description';
import './style.scss';

export default function FeedHeader(): ReactNode {
	return (
		<HStack className="feed-header" justify="space-between" alignment="center">
			<p className="feed-header__description">
				<FeedDescription />
			</p>
			<HStack justify="flex-end" alignment="center" spacing={ 2 } expanded={ false }>
				<ActorSwitcher />
				<Button
					icon={ cog }
					iconSize={ 20 }
					size="compact"
					href={ addQueryArgs( 'admin.php', { page: 'activitypub' } ) }
					target="_blank"
					label={ __( 'Settings', 'activitypub' ) }
				/>
			</HStack>
		</HStack>
	);
}
