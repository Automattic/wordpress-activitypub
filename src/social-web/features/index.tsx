/**
 * WordPress dependencies
 */
import React from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { home, people, group, commentContent, cog, chartBar } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import type { FeatureTab } from '../components/item-view';
import type { Follower, Following, Interaction } from '../types';

// Import feature components
import FollowerOverview from './follower-overview';
import FollowerActivity from './follower-activity';
import FollowerSettings from './follower-settings';

/**
 * Feature configuration for different sections
 */
export interface FeatureConfig {
	name: string;
	label: string;
	icon: any;
	tabs: FeatureTab[];
	defaultTab?: string;
	enabled?: boolean;
}

/**
 * Register features for the Followers section
 */
export const followerFeatures: FeatureConfig = {
	name: 'followers',
	label: __( 'Followers', 'activitypub' ),
	icon: people,
	defaultTab: 'overview',
	tabs: [
		{
			name: 'overview',
			title: __( 'Overview', 'activitypub' ),
			icon: home,
			component: FollowerOverview,
			enabled: true,
		},
		{
			name: 'activity',
			title: __( 'Activity', 'activitypub' ),
			icon: chartBar,
			component: FollowerActivity,
			enabled: true,
		},
		{
			name: 'settings',
			title: __( 'Settings', 'activitypub' ),
			icon: cog,
			component: FollowerSettings,
			enabled: true,
		},
	],
};

/**
 * Register features for the Following section
 */
export const followingFeatures: FeatureConfig = {
	name: 'following',
	label: __( 'Following', 'activitypub' ),
	icon: group,
	defaultTab: 'overview',
	tabs: [
		{
			name: 'overview',
			title: __( 'Overview', 'activitypub' ),
			component: () => <div>Following Overview</div>, // Placeholder
			enabled: true,
		},
		{
			name: 'activity',
			title: __( 'Activity', 'activitypub' ),
			component: () => <div>Following Activity</div>, // Placeholder
			enabled: true,
		},
	],
};

/**
 * Register features for the Interactions section
 */
export const interactionFeatures: FeatureConfig = {
	name: 'interactions',
	label: __( 'Interactions', 'activitypub' ),
	icon: commentContent,
	defaultTab: 'all',
	tabs: [
		{
			name: 'all',
			title: __( 'All', 'activitypub' ),
			component: () => <div>All Interactions</div>, // Placeholder
			enabled: true,
		},
		{
			name: 'mentions',
			title: __( 'Mentions', 'activitypub' ),
			component: () => <div>Mentions</div>, // Placeholder
			enabled: true,
		},
		{
			name: 'replies',
			title: __( 'Replies', 'activitypub' ),
			component: () => <div>Replies</div>, // Placeholder
			enabled: true,
		},
	],
};

/**
 * Feature registry
 */
export const featureRegistry: Map< string, FeatureConfig > = new Map( [
	[ 'followers', followerFeatures ],
	[ 'following', followingFeatures ],
	[ 'interactions', interactionFeatures ],
] );

/**
 * Get feature configuration by name
 */
export function getFeature( name: string ): FeatureConfig | undefined {
	return featureRegistry.get( name );
}

/**
 * Register a new feature
 */
export function registerFeature( feature: FeatureConfig ): void {
	featureRegistry.set( feature.name, feature );
}

/**
 * Hook to use features in components
 */
export function useFeature( featureName: string ): FeatureConfig | undefined {
	return getFeature( featureName );
}
