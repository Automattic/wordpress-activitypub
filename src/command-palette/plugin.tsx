/**
 * ActivityPub Command Palette Integration
 *
 * Registers commands for the WordPress Command Palette (Cmd/Ctrl + K)
 * to provide quick navigation to ActivityPub admin pages.
 */

import React from 'react';
import { useCommand } from '@wordpress/commands';
import { __ } from '@wordpress/i18n';
import { registerPlugin } from '@wordpress/plugins';

// TypeScript interface for the configuration passed from PHP.
interface ActivityPubCommandPaletteConfig {
	followingEnabled: boolean;
	actorMode: 'actor' | 'blog' | 'actor_blog';
	canManageOptions: boolean;
}

// Declare global window property.
declare global {
	interface Window {
		activitypubCommandPalette?: ActivityPubCommandPaletteConfig;
	}
}

// Icon for ActivityPub commands - using the official ActivityPub plugin icon.
const activityPubIcon = (
	<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 80 80" width="24" height="24">
		<rect width="80" height="80" fill="#f1027e" />
		<path
			d="M42.9 19.8L72 36.6v6.7L42.9 60.2v-6.7L66.2 40 42.9 26.6v-6.8z"
			fillRule="evenodd"
			clipRule="evenodd"
			fill="white"
		/>
		<path d="M42.9 33.3L54.5 40l-11.6 6.7V33.3z" fillRule="evenodd" clipRule="evenodd" fill="white" />
		<path
			d="M37.1 19.8L8 36.6v6.7l23.3-13.4v26.9l5.8 3.4V19.8zM25.5 40L13.8 46.7l11.6 6.7V40z"
			fillRule="evenodd"
			clipRule="evenodd"
			fill="white"
		/>
	</svg>
);

/**
 * Component that registers all ActivityPub commands.
 *
 * @see https://make.wordpress.org/core/2023/07/17/introducing-the-wordpress-command-palette-api/
 */
const ActivityPubCommands = (): null => {
	const config = window.activitypubCommandPalette || {
		followingEnabled: false,
		actorMode: 'actor' as const,
		canManageOptions: false,
	};
	const { actorMode, canManageOptions, followingEnabled } = config;

	// Register follower commands based on actor mode.
	if ( actorMode === 'actor' || actorMode === 'actor_blog' ) {
		// User Followers command
		useCommand( {
			name: 'activitypub/navigate-user-followers',
			label: __( 'ActivityPub: View Your Followers', 'activitypub' ),
			icon: activityPubIcon,
			callback: ( { close } ) => {
				document.location.href = 'users.php?page=activitypub-followers-list';
				close();
			},
		} );

		// User Following command (only if enabled).
		if ( followingEnabled ) {
			useCommand( {
				name: 'activitypub/navigate-user-following',
				label: __( 'ActivityPub: View Who You Follow', 'activitypub' ),
				icon: activityPubIcon,
				callback: ( { close } ) => {
					document.location.href = 'users.php?page=activitypub-following-list';
					close();
				},
			} );
		}
	}

	// Blog-related commands (require manage_options capability).
	if ( canManageOptions && ( actorMode === 'blog' || actorMode === 'actor_blog' ) ) {
		// Blog Followers command
		useCommand( {
			name: 'activitypub/navigate-blog-followers',
			label: __( 'ActivityPub: View Blog Followers', 'activitypub' ),
			icon: activityPubIcon,
			callback: ( { close } ) => {
				document.location.href = 'options-general.php?page=activitypub&tab=followers';
				close();
			},
		} );

		// Blog Following command (only if enabled).
		if ( followingEnabled ) {
			useCommand( {
				name: 'activitypub/navigate-blog-following',
				label: __( 'ActivityPub: View Blog Following', 'activitypub' ),
				icon: activityPubIcon,
				callback: ( { close } ) => {
					document.location.href = 'options-general.php?page=activitypub&tab=following';
					close();
				},
			} );
		}
	}

	// Command: Navigate to Blocked Actors list.
	useCommand( {
		name: 'activitypub/navigate-blocked-actors',
		label: __( 'ActivityPub: View Blocked Actors', 'activitypub' ),
		icon: activityPubIcon,
		callback: ( { close } ) => {
			document.location.href = 'users.php?page=activitypub-blocked-actors-list';
			close();
		},
	} );

	// Command: Navigate to ActivityPub Settings (requires manage_options capability).
	if ( canManageOptions ) {
		useCommand( {
			name: 'activitypub/navigate-settings',
			label: __( 'ActivityPub: View Settings', 'activitypub' ),
			icon: activityPubIcon,
			callback: ( { close } ) => {
				document.location.href = 'options-general.php?page=activitypub&tab=settings';
				close();
			},
		} );
	}

	// Command: Navigate to Extra Fields.
	useCommand( {
		name: 'activitypub/navigate-extra-fields',
		label: __( 'ActivityPub: View Extra Fields', 'activitypub' ),
		icon: activityPubIcon,
		callback: ( { close } ) => {
			document.location.href = 'edit.php?post_type=ap_extrafield';
			close();
		},
	} );

	// Command: Add New Extra Field.
	useCommand( {
		name: 'activitypub/add-extra-field',
		label: __( 'ActivityPub: Add New Extra Field', 'activitypub' ),
		icon: activityPubIcon,
		callback: ( { close } ) => {
			document.location.href = 'post-new.php?post_type=ap_extrafield';
			close();
		},
	} );

	return null;
};

// Register the plugin that adds our commands.
registerPlugin( 'activitypub-command-palette', {
	render: ActivityPubCommands,
} );
