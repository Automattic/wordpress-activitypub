/**
 * Type definitions for Social Web
 */

export interface SocialWebSettings {
	siteUrl: string;
	siteTitle: string;
	adminUrl: string;
	restUrl: string;
	nonce: string;
}

export interface Route {
	name: string;
	path: string;
	label: string;
	parent?: string;
}

export interface Location {
	path: string;
	params: {
		section?: string;
		id?: string;
		[ key: string ]: string | undefined;
	};
	query: Record< string, string >;
}

export interface NavigationItem {
	name: string;
	label: string;
	icon: any;
	withChevron?: boolean;
	path: string;
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

export interface Following {
	id: string;
	actor: string;
	name: string;
	username: string;
	avatar: string;
	url: string;
	created: string;
	modified: string;
}

export interface Interaction {
	id: string;
	type: 'mention' | 'reply' | 'like' | 'announce';
	actor: string;
	actorName: string;
	actorAvatar: string;
	content: string;
	url: string;
	created: string;
	object?: string;
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
	actor?: {
		id: string;
		name: string;
		preferredUsername: string;
		url: string;
		icon: string | object | null;
		type: string;
	} | null;
}
