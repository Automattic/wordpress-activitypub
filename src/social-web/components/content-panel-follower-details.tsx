/**
 * WordPress dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';
import {
	Card,
	CardBody,
	CardHeader,
	__experimentalVStack as VStack,
	__experimentalHStack as HStack,
	__experimentalText as Text,
	__experimentalHeading as Heading,
	Button,
	TabPanel,
	ExternalLink,
} from '@wordpress/components';

/**
 * Internal dependencies
 */
import type { Follower } from '../types';

interface ContentPanelFollowerDetailsProps {
	follower: Follower | null;
	onBlock?: ( id: string ) => void;
	onRemove?: ( id: string ) => void;
}

export default function ContentPanelFollowerDetails( {
	follower,
	onBlock,
	onRemove,
}: ContentPanelFollowerDetailsProps ) {
	if ( ! follower ) {
		return (
			<div className="activitypub-content-panel activitypub-follower-details">
				<Card>
					<CardBody>
						<Text variant="muted">{ __( 'Select a follower to view details.', 'activitypub' ) }</Text>
					</CardBody>
				</Card>
			</div>
		);
	}

	const tabs = [
		{
			name: 'overview',
			title: __( 'Overview', 'activitypub' ),
			className: 'activitypub-tab-overview',
		},
		{
			name: 'activity',
			title: __( 'Activity', 'activitypub' ),
			className: 'activitypub-tab-activity',
		},
		{
			name: 'technical',
			title: __( 'Technical Info', 'activitypub' ),
			className: 'activitypub-tab-technical',
		},
	];

	return (
		<div className="activitypub-content-panel activitypub-follower-details">
			<VStack spacing={ 4 }>
				{ /* Header with avatar and basic info */ }
				<Card>
					<CardBody>
						<HStack alignment="top" spacing={ 4 }>
							<img
								src={ follower.avatar }
								alt={ follower.name }
								width={ 80 }
								height={ 80 }
								style={ { borderRadius: '50%' } }
							/>
							<VStack spacing={ 2 } alignment="left">
								<Heading level={ 2 } size={ 24 }>
									{ follower.name }
								</Heading>
								<Text size={ 16 } variant="muted">
									@{ follower.username }
								</Text>
								<ExternalLink href={ follower.url }>
									{ __( 'View profile', 'activitypub' ) }
								</ExternalLink>
							</VStack>
						</HStack>
					</CardBody>
				</Card>

				{ /* Tabbed content */ }
				<Card>
					<CardBody>
						<TabPanel tabs={ tabs } onSelect={ () => {} }>
							{ ( tab ) => {
								switch ( tab.name ) {
									case 'overview':
										return (
											<VStack spacing={ 4 }>
												<div>
													<Text weight={ 600 } as="div" marginBottom={ 1 }>
														{ __( 'Profile URL', 'activitypub' ) }
													</Text>
													<Text variant="muted">{ follower.url }</Text>
												</div>
												<div>
													<Text weight={ 600 } as="div" marginBottom={ 1 }>
														{ __( 'Following since', 'activitypub' ) }
													</Text>
													<Text variant="muted">
														{ new Date( follower.created ).toLocaleDateString() }
													</Text>
												</div>
												<div>
													<Text weight={ 600 } as="div" marginBottom={ 1 }>
														{ __( 'Last activity', 'activitypub' ) }
													</Text>
													<Text variant="muted">
														{ new Date( follower.modified ).toLocaleDateString() }
													</Text>
												</div>
												{ follower.errors && follower.errors > 0 && (
													<div>
														<Text weight={ 600 } as="div" marginBottom={ 1 }>
															{ __( 'Delivery errors', 'activitypub' ) }
														</Text>
														<Text variant="muted" className="has-warning">
															{ follower.errors } { __( 'errors', 'activitypub' ) }
														</Text>
													</div>
												) }
											</VStack>
										);
									case 'activity':
										return (
											<VStack spacing={ 3 }>
												<Text variant="muted">
													{ __(
														'Activity history will be displayed here...',
														'activitypub'
													) }
												</Text>
											</VStack>
										);
									case 'technical':
										return (
											<VStack spacing={ 4 }>
												<div>
													<Text weight={ 600 } as="div" marginBottom={ 1 }>
														{ __( 'Actor ID', 'activitypub' ) }
													</Text>
													<code style={ { wordBreak: 'break-all' } }>{ follower.actor }</code>
												</div>
												{ follower.inbox && (
													<div>
														<Text weight={ 600 } as="div" marginBottom={ 1 }>
															{ __( 'Inbox', 'activitypub' ) }
														</Text>
														<code style={ { wordBreak: 'break-all' } }>
															{ follower.inbox }
														</code>
													</div>
												) }
												{ follower.shared_inbox && (
													<div>
														<Text weight={ 600 } as="div" marginBottom={ 1 }>
															{ __( 'Shared Inbox', 'activitypub' ) }
														</Text>
														<code style={ { wordBreak: 'break-all' } }>
															{ follower.shared_inbox }
														</code>
													</div>
												) }
											</VStack>
										);
									default:
										return null;
								}
							} }
						</TabPanel>
					</CardBody>
				</Card>

				{ /* Actions */ }
				<Card>
					<CardBody>
						<HStack spacing={ 3 }>
							<Button variant="secondary" isDestructive onClick={ () => onBlock?.( follower.id ) }>
								{ __( 'Block Follower', 'activitypub' ) }
							</Button>
							<Button variant="tertiary" isDestructive onClick={ () => onRemove?.( follower.id ) }>
								{ __( 'Remove Follower', 'activitypub' ) }
							</Button>
						</HStack>
					</CardBody>
				</Card>
			</VStack>
		</div>
	);
}
