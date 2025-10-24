/**
 * Type definitions for the admin followers DataViews component.
 */

import type { Field as DataViewsField, View as DataViewsView, Action as DataViewsAction } from '@wordpress/dataviews';

/**
 * Actor information from REST API
 */
export interface ActorInfo {
	username: string;
	name: string;
	icon: string;
	url: string;
	webfinger: string;
	identifier: string;
}

/**
 * Follow status from REST API
 */
export interface FollowStatus {
	follows_back: boolean;
}

/**
 * AP Actor post type from REST API
 */
export interface APActor {
	id: number;
	date: string;
	date_gmt: string;
	guid: {
		rendered: string;
	};
	modified: string;
	modified_gmt: string;
	slug: string;
	status: string;
	type: string;
	link: string;
	title: {
		rendered: string;
	};
	content: {
		rendered: string;
		protected: boolean;
	};
	meta: {
		_activitypub_following: string[];
		[ key: string ]: any;
	};
	actor_info?: ActorInfo;
	follow_status?: FollowStatus;
	activitypub_json?: string;
}

/**
 * DataViews action (re-export from @wordpress/dataviews)
 */
export type Action = DataViewsAction< APActor >;

/**
 * DataViews field (re-export from @wordpress/dataviews)
 */
export type Field = DataViewsField< APActor >;

/**
 * DataViews view configuration (re-export from @wordpress/dataviews)
 */
export type View = DataViewsView;

/**
 * Component props
 */
export interface FollowersDataViewsProps {
	userId: number;
}

/**
 * WordPress localized script data
 */
export interface ActivityPubAdmin {
	userId: number;
	namespace: string;
	followingEnabled: boolean;
	defaultAvatar: string;
}

declare global {
	interface Window {
		activityPubAdmin: ActivityPubAdmin;
	}
}
