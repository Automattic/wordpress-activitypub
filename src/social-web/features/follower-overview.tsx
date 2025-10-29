/**
 * WordPress dependencies
 */
import React from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
	__experimentalVStack as VStack,
	__experimentalHStack as HStack,
	__experimentalText as Text,
	ExternalLink,
	Button,
} from '@wordpress/components';

/**
 * Internal dependencies
 */
import type { Follower } from '../types';

interface FollowerOverviewProps {
	item: Follower;
}

export default function FollowerOverview( { item }: FollowerOverviewProps ) {
	if ( ! item ) {
		return null;
	}

	return (
		<VStack spacing={ 4 } className="activitypub-follower-overview">
			{ /* Profile Header */ }
			<HStack alignment="top" spacing={ 4 }>
				<img
					src={ item.avatar }
					alt={ item.name }
					width={ 80 }
					height={ 80 }
					style={ { borderRadius: '50%' } }
				/>
				<VStack spacing={ 2 }>
					<Text size={ 24 } weight={ 600 }>
						{ item.name }
					</Text>
					<Text variant="muted">@{ item.username }</Text>
					<ExternalLink href={ item.url }>{ __( 'View profile', 'activitypub' ) }</ExternalLink>
				</VStack>
			</HStack>

			{ /* Stats */ }
			<VStack spacing={ 3 }>
				<div>
					<Text weight={ 600 } as="div" marginBottom={ 1 }>
						{ __( 'Following since', 'activitypub' ) }
					</Text>
					<Text variant="muted">{ new Date( item.created ).toLocaleDateString() }</Text>
				</div>

				<div>
					<Text weight={ 600 } as="div" marginBottom={ 1 }>
						{ __( 'Last activity', 'activitypub' ) }
					</Text>
					<Text variant="muted">{ new Date( item.modified ).toLocaleDateString() }</Text>
				</div>

				{ item.errors && item.errors > 0 && (
					<div>
						<Text weight={ 600 } as="div" marginBottom={ 1 }>
							{ __( 'Delivery errors', 'activitypub' ) }
						</Text>
						<Text variant="muted" className="has-warning">
							{ item.errors } { __( 'errors', 'activitypub' ) }
						</Text>
					</div>
				) }
			</VStack>

			{ /* Technical Details */ }
			<VStack spacing={ 3 }>
				<Text weight={ 600 }>{ __( 'Technical Details', 'activitypub' ) }</Text>
				<div>
					<Text variant="muted" size="small">
						{ __( 'Actor ID', 'activitypub' ) }
					</Text>
					<code style={ { wordBreak: 'break-all', fontSize: '11px' } }>{ item.actor }</code>
				</div>
				{ item.inbox && (
					<div>
						<Text variant="muted" size="small">
							{ __( 'Inbox', 'activitypub' ) }
						</Text>
						<code style={ { wordBreak: 'break-all', fontSize: '11px' } }>{ item.inbox }</code>
					</div>
				) }
			</VStack>

			{ /* Actions */ }
			<HStack spacing={ 2 }>
				<Button variant="secondary" isDestructive>
					{ __( 'Block Follower', 'activitypub' ) }
				</Button>
				<Button variant="tertiary">{ __( 'Remove Follower', 'activitypub' ) }</Button>
			</HStack>
		</VStack>
	);
}
