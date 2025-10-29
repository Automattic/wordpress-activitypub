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
	Icon,
} from '@wordpress/components';
import { people, commentContent, group, postList, trendingUp } from '@wordpress/icons';

interface DashboardStats {
	followers: number;
	following: number;
	interactions: number;
	posts: number;
}

interface ContentPanelDashboardProps {
	stats: DashboardStats;
	onNavigate: ( path: string ) => void;
}

export default function ContentPanelDashboard( { stats, onNavigate }: ContentPanelDashboardProps ) {
	return (
		<div className="activitypub-content-panel activitypub-dashboard">
			<VStack spacing={ 4 }>
				<Heading level={ 1 } size={ 30 }>
					{ __( 'Social Web Dashboard', 'activitypub' ) }
				</Heading>
				<Text>
					{ __(
						'Your ActivityPub presence at a glance. Monitor your federated social presence and interactions.',
						'activitypub'
					) }
				</Text>

				<div className="activitypub-stats-grid">
					<Card size="small" className="activitypub-stat-card">
						<CardHeader>
							<HStack alignment="center">
								<Icon icon={ people } size={ 24 } />
								<Text weight={ 600 }>{ __( 'Followers', 'activitypub' ) }</Text>
							</HStack>
						</CardHeader>
						<CardBody>
							<VStack spacing={ 2 }>
								<Text size={ 30 } weight={ 700 }>
									{ stats.followers }
								</Text>
								<Button variant="link" onClick={ () => onNavigate( '/followers' ) }>
									{ __( 'View all followers →', 'activitypub' ) }
								</Button>
							</VStack>
						</CardBody>
					</Card>

					<Card size="small" className="activitypub-stat-card">
						<CardHeader>
							<HStack alignment="center">
								<Icon icon={ group } size={ 24 } />
								<Text weight={ 600 }>{ __( 'Following', 'activitypub' ) }</Text>
							</HStack>
						</CardHeader>
						<CardBody>
							<VStack spacing={ 2 }>
								<Text size={ 30 } weight={ 700 }>
									{ stats.following }
								</Text>
								<Button variant="link" onClick={ () => onNavigate( '/following' ) }>
									{ __( 'Manage following →', 'activitypub' ) }
								</Button>
							</VStack>
						</CardBody>
					</Card>

					<Card size="small" className="activitypub-stat-card">
						<CardHeader>
							<HStack alignment="center">
								<Icon icon={ commentContent } size={ 24 } />
								<Text weight={ 600 }>{ __( 'Interactions', 'activitypub' ) }</Text>
							</HStack>
						</CardHeader>
						<CardBody>
							<VStack spacing={ 2 }>
								<Text size={ 30 } weight={ 700 }>
									{ stats.interactions }
								</Text>
								<Button variant="link" onClick={ () => onNavigate( '/interactions' ) }>
									{ __( 'View interactions →', 'activitypub' ) }
								</Button>
							</VStack>
						</CardBody>
					</Card>

					<Card size="small" className="activitypub-stat-card">
						<CardHeader>
							<HStack alignment="center">
								<Icon icon={ postList } size={ 24 } />
								<Text weight={ 600 }>{ __( 'Federated Posts', 'activitypub' ) }</Text>
							</HStack>
						</CardHeader>
						<CardBody>
							<VStack spacing={ 2 }>
								<Text size={ 30 } weight={ 700 }>
									{ stats.posts }
								</Text>
								<Button variant="link" href="/wp-admin/edit.php">
									{ __( 'View posts →', 'activitypub' ) }
								</Button>
							</VStack>
						</CardBody>
					</Card>
				</div>

				<Card>
					<CardHeader>
						<HStack alignment="center">
							<Icon icon={ trendingUp } size={ 24 } />
							<Heading level={ 2 } size={ 20 }>
								{ __( 'Recent Activity', 'activitypub' ) }
							</Heading>
						</HStack>
					</CardHeader>
					<CardBody>
						<VStack spacing={ 3 }>
							<Text variant="muted">
								{ __( 'Activity feed will be displayed here...', 'activitypub' ) }
							</Text>
						</VStack>
					</CardBody>
				</Card>

				<Card>
					<CardHeader>
						<Heading level={ 2 } size={ 20 }>
							{ __( 'Quick Actions', 'activitypub' ) }
						</Heading>
					</CardHeader>
					<CardBody>
						<HStack spacing={ 3 }>
							<Button variant="secondary">{ __( 'Import Followers', 'activitypub' ) }</Button>
							<Button variant="secondary">{ __( 'Export Data', 'activitypub' ) }</Button>
							<Button variant="link" href="/wp-admin/options-general.php?page=activitypub">
								{ __( 'Settings', 'activitypub' ) }
							</Button>
						</HStack>
					</CardBody>
				</Card>
			</VStack>
		</div>
	);
}
