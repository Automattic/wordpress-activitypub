import React from 'react';

/**
 * Command configuration interface.
 */
export interface CommandConfig {
	name: string;
	label: string;
	icon?: React.ReactNode;
	callback: ( options: { close: () => void } ) => void;
	context?: string;
}

/**
 * Command loader configuration interface.
 */
export interface CommandLoaderConfig {
	name: string;
	hook: ( params: { search: string } ) => {
		commands: CommandConfig[];
		isLoading: boolean;
	};
}

/**
 * ActivityPub Command Palette configuration passed from PHP.
 */
export interface ActivityPubCommandPaletteConfig {
	followingEnabled: boolean;
	actorMode: 'actor' | 'blog' | 'actor_blog';
	canManageOptions: boolean;
}

/**
 * Commands store actions interface.
 */
export interface CommandsStoreActions {
	registerCommand( command: CommandConfig ): void;
	registerCommandLoader( config: CommandLoaderConfig ): void;
	unregisterCommand( commandName: string ): void;
}

/**
 * Extend the global Window interface.
 */
declare global {
	interface Window {
		activitypubCommandPalette?: ActivityPubCommandPaletteConfig;
	}
}

/**
 * WordPress post/entity record interface.
 */
export interface WPPost {
	id: number;
	title: {
		rendered: string;
		raw?: string;
	};
	content?: {
		rendered: string;
		raw?: string;
	};
	status: string;
	author?: number;
	[ key: string ]: unknown;
}

/**
 * WordPress core-data store interface.
 */
export interface CoreDataStore {
	getCurrentUser(): {
		id: number;
		name: string;
		[ key: string ]: unknown;
	} | null;
	getEntityRecords( kind: string, name: string, query?: Record< string, unknown > ): WPPost[] | null;
	hasFinishedResolution( selectorName: string, args: unknown[] ): boolean;
}

/**
 * Type declarations for @wordpress/data with commands store.
 */
declare module '@wordpress/data' {
	export function dispatch( storeKey: 'core/commands' ): CommandsStoreActions;
}

/**
 * Extend @wordpress/core-data store with missing methods.
 */
declare module '@wordpress/core-data' {
	export interface CoreDataSelectors {
		hasFinishedResolution( selectorName: string, args: unknown[] ): boolean;
	}
}
