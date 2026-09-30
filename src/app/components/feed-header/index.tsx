/**
 * Feed Header
 *
 * Sits at the top of the stage in boot's full-page mode and carries what our
 * own sidebar used to: the feed description and the actor switcher.
 * Navigation itself is boot's sidebar now.
 */

/**
 * External dependencies
 */
import type { ReactNode } from 'react';

/**
 * WordPress dependencies
 */
import { __experimentalHStack as HStack } from '@wordpress/components';

/**
 * Internal dependencies
 */
import ActorSwitcher from '../actor-switcher';
import FeedDescription from './feed-description';
import './style.scss';

export default function FeedHeader(): ReactNode {
	return (
		<HStack className="feed-header" justify="space-between" alignment="center">
			<p className="feed-header__description">
				<FeedDescription />
			</p>
			<ActorSwitcher />
		</HStack>
	);
}
