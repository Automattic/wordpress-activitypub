/**
 * WordPress dependencies
 */
import React, { useState } from 'react';
import { __ } from '@wordpress/i18n';
import {
	SearchControl,
	SelectControl,
	__experimentalVStack as VStack,
	__experimentalItem as Item,
	__experimentalItemGroup as ItemGroup,
	__experimentalHStack as HStack,
	FlexBlock,
	__experimentalText as Text,
} from '@wordpress/components';

/**
 * Internal dependencies
 */
import SidebarNavigationScreen from './sidebar-navigation-screen';
import { useNavigation } from './navigation-context';
import type { Follower } from '../types';

interface SidebarNavigationScreenFollowersProps {
	followers: Follower[];
	selectedId?: string;
	onSelectFollower: ( id: string ) => void;
}

export default function SidebarNavigationScreenFollowers( {
	followers = [],
	selectedId,
	onSelectFollower,
}: SidebarNavigationScreenFollowersProps ) {
	const [ search, setSearch ] = useState( '' );
	const [ sortBy, setSortBy ] = useState( 'recent' );
	const { navigate } = useNavigation();

	// Filter followers based on search
	const filteredFollowers = followers.filter( ( follower ) => {
		if ( ! search ) return true;
		const searchLower = search.toLowerCase();
		return (
			follower.name.toLowerCase().includes( searchLower ) ||
			follower.username.toLowerCase().includes( searchLower ) ||
			follower.actor.toLowerCase().includes( searchLower )
		);
	} );

	// Sort followers
	const sortedFollowers = [ ...filteredFollowers ].sort( ( a, b ) => {
		switch ( sortBy ) {
			case 'name':
				return a.name.localeCompare( b.name );
			case 'username':
				return a.username.localeCompare( b.username );
			case 'recent':
			default:
				return new Date( b.created ).getTime() - new Date( a.created ).getTime();
		}
	} );

	const handleSelectFollower = ( follower: Follower ) => {
		onSelectFollower( follower.id );
		navigate( `/followers/${ follower.id }`, 'forward' );
	};

	const content = (
		<VStack spacing={ 4 }>
			<SearchControl
				__nextHasNoMarginBottom
				label={ __( 'Search followers', 'activitypub' ) }
				placeholder={ __( 'Search by name or handle...', 'activitypub' ) }
				value={ search }
				onChange={ setSearch }
			/>
			<SelectControl
				__nextHasNoMarginBottom
				label={ __( 'Sort by', 'activitypub' ) }
				value={ sortBy }
				options={ [
					{ label: __( 'Recently followed', 'activitypub' ), value: 'recent' },
					{ label: __( 'Name', 'activitypub' ), value: 'name' },
					{ label: __( 'Username', 'activitypub' ), value: 'username' },
				] }
				onChange={ setSortBy }
			/>
			<ItemGroup className="activitypub-followers-list">
				{ sortedFollowers.length === 0 ? (
					<Item>
						<Text>
							{ search
								? __( 'No followers match your search.', 'activitypub' )
								: __( 'No followers yet.', 'activitypub' ) }
						</Text>
					</Item>
				) : (
					sortedFollowers.map( ( follower ) => (
						<Item
							key={ follower.id }
							onClick={ () => handleSelectFollower( follower ) }
							className={ selectedId === follower.id ? 'is-selected' : '' }
						>
							<HStack alignment="center" spacing={ 3 }>
								<img
									src={ follower.avatar }
									alt={ follower.name }
									width={ 32 }
									height={ 32 }
									style={ { borderRadius: '50%' } }
								/>
								<VStack spacing={ 0 }>
									<Text weight={ 600 }>{ follower.name }</Text>
									<Text variant="muted" size="small" lineHeight={ 1.2 }>
										@{ follower.username }
									</Text>
								</VStack>
							</HStack>
						</Item>
					) )
				) }
			</ItemGroup>
		</VStack>
	);

	const footer = (
		<Text variant="muted" size="small">
			{ sortedFollowers.length } { __( 'followers', 'activitypub' ) }
		</Text>
	);

	return (
		<SidebarNavigationScreen
			title={ __( 'Followers', 'activitypub' ) }
			description={ __( 'People who follow your ActivityPub profile.', 'activitypub' ) }
			content={ content }
			footer={ footer }
			backPath="/"
			backLabel={ __( 'Back to Social Web', 'activitypub' ) }
		/>
	);
}
