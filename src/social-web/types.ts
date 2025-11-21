/**
 * Type definitions for Social Web
 */

export interface SocialWebSettings {
	adminUrl: string;
	defaultAvatar: string;
	nonce: string;
	restUrl: string;
	siteTitle: string;
	siteUrl: string;
}

export interface Follower {
	id: string;
	actor: string;
	name: string;
	username: string;
	avatar: string;
	url: string;
	created: string;
	modified: string;
	errors?: number;
	inbox?: string;
	shared_inbox?: string;
}

// New types for entity records implementation
export interface ActorInfo {
	username: string;
	name: string;
	icon: string;
	url: string;
	webfinger: string;
	identifier: string;
}

export interface FollowStatus {
	follows_back: boolean;
}

export interface Actor {
	id: number;
	date: string;
	date_gmt: string;
	guid: { rendered: string };
	modified: string;
	modified_gmt: string;
	slug: string;
	status: string;
	type: string;
	link: string;
	title: { rendered: string };
	content: { rendered: string; protected: boolean };
	meta: {
		_activitypub_following: string[];
		[ key: string ]: any;
	};
	actor_info?: ActorInfo;
	follow_status?: FollowStatus;
	activitypub_json?: string;
}

export interface FeedPost {
	id: number;
	title: {
		rendered: string;
	};
	content: {
		rendered: string;
	};
	excerpt: {
		rendered: string;
	};
	author: number;
	date: string;
	date_gmt: string;
	modified: string;
	modified_gmt: string;
	slug: string;
	status: string;
	type: string;
	link: string;
	guid: {
		rendered: string;
	};
	comment_status: string;
	ping_status: string;
	featured_image?: string;
	ap_object_type?: number[];
	actor_info?: ActorInfo;
}

export interface Comment {
	id: number;
	post: number;
	parent: number;
	author: number;
	author_name: string;
	author_url: string;
	author_avatar_urls: {
		[ size: string ]: string;
	};
	date: string;
	date_gmt: string;
	content: {
		rendered: string;
	};
	link: string;
	status: string;
	type: string;
}
