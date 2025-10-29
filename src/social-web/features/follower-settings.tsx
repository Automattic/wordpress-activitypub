/**
 * WordPress dependencies
 */
import React from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
	__experimentalVStack as VStack,
	__experimentalText as Text,
	ToggleControl,
	SelectControl,
	Button,
} from '@wordpress/components';

/**
 * Internal dependencies
 */
import type { Follower } from '../types';

interface FollowerSettingsProps {
	item: Follower;
}

export default function FollowerSettings( { item }: FollowerSettingsProps ) {
	return (
		<VStack spacing={ 4 } className="activitypub-follower-settings">
			<Text>
				{ __( 'Settings for', 'activitypub' ) } { item.name }
			</Text>

			<ToggleControl
				label={ __( 'Send notifications', 'activitypub' ) }
				help={ __( 'Receive notifications when this follower interacts with your content', 'activitypub' ) }
				checked={ true }
				onChange={ () => {} }
			/>

			<ToggleControl
				label={ __( 'Allow mentions', 'activitypub' ) }
				help={ __( 'Allow this follower to mention you in their posts', 'activitypub' ) }
				checked={ true }
				onChange={ () => {} }
			/>

			<SelectControl
				label={ __( 'Visibility', 'activitypub' ) }
				value="public"
				options={ [
					{ label: __( 'Public', 'activitypub' ), value: 'public' },
					{ label: __( 'Followers only', 'activitypub' ), value: 'followers' },
					{ label: __( 'Private', 'activitypub' ), value: 'private' },
				] }
				onChange={ () => {} }
			/>

			<Button variant="primary">{ __( 'Save Settings', 'activitypub' ) }</Button>
		</VStack>
	);
}
