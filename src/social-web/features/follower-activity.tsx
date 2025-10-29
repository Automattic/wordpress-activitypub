/**
 * WordPress dependencies
 */
import React from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { __experimentalVStack as VStack, __experimentalText as Text, Card, CardBody } from '@wordpress/components';

/**
 * Internal dependencies
 */
import type { Follower } from '../types';

interface FollowerActivityProps {
	item: Follower;
}

export default function FollowerActivity( { item }: FollowerActivityProps ) {
	return (
		<VStack spacing={ 4 } className="activitypub-follower-activity">
			<Text>
				{ __( 'Activity timeline for', 'activitypub' ) } { item.name }
			</Text>

			<Card>
				<CardBody>
					<Text variant="muted">{ __( 'Activity history will be displayed here...', 'activitypub' ) }</Text>
				</CardBody>
			</Card>

			{ /* This would be populated with real activity data */ }
			<VStack spacing={ 3 }>
				<Card size="small">
					<CardBody>
						<Text size="small" variant="muted">
							2 hours ago
						</Text>
						<Text>Liked your post "Introduction to ActivityPub"</Text>
					</CardBody>
				</Card>

				<Card size="small">
					<CardBody>
						<Text size="small" variant="muted">
							Yesterday
						</Text>
						<Text>Started following you</Text>
					</CardBody>
				</Card>
			</VStack>
		</VStack>
	);
}
