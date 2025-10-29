/**
 * WordPress dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';
import { isRTL } from '@wordpress/i18n';
import {
	__experimentalHStack as HStack,
	__experimentalItem as Item,
	__experimentalItemGroup as ItemGroup,
	FlexBlock,
	Icon,
} from '@wordpress/components';
import { people, commentContent, home, chevronRightSmall, chevronLeftSmall, group } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import SidebarNavigationScreen from './sidebar-navigation-screen';
import { useNavigation } from './navigation-context';
import type { NavigationItem } from '../types';

const NAVIGATION_ITEMS: NavigationItem[] = [
	{
		name: 'dashboard',
		label: __( 'Dashboard', 'activitypub' ),
		icon: home,
		withChevron: false,
		path: '/',
	},
	{
		name: 'followers',
		label: __( 'Followers', 'activitypub' ),
		icon: people,
		withChevron: true,
		path: '/followers',
	},
	{
		name: 'following',
		label: __( 'Following', 'activitypub' ),
		icon: group,
		withChevron: true,
		path: '/following',
	},
	{
		name: 'interactions',
		label: __( 'Interactions', 'activitypub' ),
		icon: commentContent,
		withChevron: true,
		path: '/interactions',
	},
];

export default function SidebarNavigationScreenMain() {
	const { navigate } = useNavigation();

	const handleNavigation = ( path: string ) => {
		navigate( path, 'forward' );
	};

	const content = (
		<ItemGroup className="edit-site-sidebar-navigation-screen-main">
			{ NAVIGATION_ITEMS.map( ( item ) => (
				<Item
					key={ item.name }
					className="edit-site-sidebar-navigation-item"
					onClick={ () => handleNavigation( item.path ) }
				>
					<HStack justify="flex-start">
						{ item.icon && <Icon style={ { fill: 'currentcolor' } } icon={ item.icon } size={ 24 } /> }
						<FlexBlock>{ item.label }</FlexBlock>
						{ item.withChevron && (
							<Icon
								icon={ isRTL() ? chevronLeftSmall : chevronRightSmall }
								className="edit-site-sidebar-navigation-item__drilldown-indicator"
								size={ 24 }
							/>
						) }
					</HStack>
				</Item>
			) ) }
		</ItemGroup>
	);

	return (
		<SidebarNavigationScreen
			isRoot
			title={ __( 'Social Web', 'activitypub' ) }
			description={ __( 'Connect and interact with the fediverse through ActivityPub.', 'activitypub' ) }
			content={ content }
		/>
	);
}
