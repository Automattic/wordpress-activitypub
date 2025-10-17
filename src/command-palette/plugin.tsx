/**
 * ActivityPub Command Palette Integration
 *
 * Registers commands for the WordPress Command Palette (Cmd/Ctrl + K)
 * to provide quick navigation to ActivityPub admin pages.
 */

import React from 'react';
import { __, sprintf } from '@wordpress/i18n';
import { dispatch, useSelect } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';
import type { Post } from '@wordpress/core-data';
import { useMemo } from '@wordpress/element';
import type { CommandConfig, CommandLoaderConfig } from './types';

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

// Get configuration from PHP.
const { actorMode, canManageOptions, followingEnabled } = window.activitypubCommandPalette || {
	followingEnabled: false,
	actorMode: 'actor' as const,
	canManageOptions: false,
};

// Helper function to register a command.
const registerCommand = ( command: CommandConfig ) => {
	try {
		dispatch( 'core/commands' ).registerCommand( command );
	} catch ( error ) {
		console.error( 'Failed to register ActivityPub command:', command.name, error );
	}
};

// Helper function to register a command loader for dynamic commands.
const registerCommandLoader = ( loaderConfig: CommandLoaderConfig ) => {
	try {
		dispatch( 'core/commands' ).registerCommandLoader( loaderConfig );
	} catch ( error ) {
		console.error( 'Failed to register ActivityPub command loader:', loaderConfig.name, error );
	}
};

/**
 * Hook to load user extra fields as dynamic commands.
 */
const useExtraFieldsCommandLoader = ( { search }: { search: string } ) => {
	// Retrieving the extra fields for the "search" term.
	const { records, isLoading } = useSelect(
		( select ) => {
			const store = select( coreStore );
			const currentUser = store.getCurrentUser();
			const query = {
				search: !! search ? search : undefined,
				per_page: 10,
				orderby: search ? 'relevance' : 'date',
				status: 'any',
				author: currentUser?.id,
			};

			return {
				records: store.getEntityRecords< Post >( 'postType', 'ap_extrafield', query ),
				isLoading: ! ( store as any ).hasFinishedResolution( 'getEntityRecords', [
					'postType',
					'ap_extrafield',
					query,
				] ),
			};
		},
		[ search ]
	);

	// Creating the commands.
	const commands = useMemo( () => {
		return ( records ?? [] ).slice( 0, 10 ).map( ( record ) => {
			const title = record.title?.rendered || __( '(no title)', 'activitypub' );
			// Remove all quotes and special characters that could break CSS selectors.
			const sanitizedTitle = title.replace( /["'`]/g, '' );
			return {
				// Use ID in the name to ensure uniqueness even with duplicate titles.
				name: `activitypub/edit-extra-field/${ record.id }`,
				label: sprintf(
					/* translators: %s: Extra field title */
					__( 'ActivityPub: Edit - %s', 'activitypub' ),
					sanitizedTitle
				),
				icon: activityPubIcon,
				callback: ( { close }: { close: () => void } ) => {
					document.location = `post.php?post=${ record.id }&action=edit`;
					close();
				},
			};
		} );
	}, [ records ] );

	return {
		commands,
		isLoading,
	};
};

/**
 * Hook to load blog extra fields as dynamic commands.
 */
const useBlogExtraFieldsCommandLoader = ( { search }: { search: string } ) => {
	// Retrieving the blog extra fields for the "search" term.
	const { records, isLoading } = useSelect(
		( select ) => {
			const store = select( coreStore );
			const query = {
				search: !! search ? search : undefined,
				per_page: 10,
				orderby: search ? 'relevance' : 'date',
				status: 'any',
			};

			return {
				records: store.getEntityRecords< Post >( 'postType', 'ap_extrafield_blog', query ),
				isLoading: ! ( store as any ).hasFinishedResolution( 'getEntityRecords', [
					'postType',
					'ap_extrafield_blog',
					query,
				] ),
			};
		},
		[ search ]
	);

	// Creating the commands.
	const commands = useMemo( () => {
		return ( records ?? [] ).slice( 0, 10 ).map( ( record ) => {
			const title = record.title?.rendered || __( '(no title)', 'activitypub' );
			// Remove all quotes and special characters that could break CSS selectors.
			const sanitizedTitle = title.replace( /["'`]/g, '' );
			return {
				// Use ID in the name to ensure uniqueness even with duplicate titles.
				name: `activitypub/edit-blog-extra-field/${ record.id }`,
				label: sprintf(
					/* translators: %s: Blog extra field title */
					__( 'ActivityPub: Edit Blog - %s', 'activitypub' ),
					sanitizedTitle
				),
				icon: activityPubIcon,
				callback: ( { close }: { close: () => void } ) => {
					document.location = `post.php?post=${ record.id }&action=edit`;
					close();
				},
			};
		} );
	}, [ records ] );

	return {
		commands,
		isLoading,
	};
};

// User-specific commands (for actor and actor_blog modes).
if ( actorMode === 'actor' || actorMode === 'actor_blog' ) {
	// User Followers command.
	registerCommand( {
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
		registerCommand( {
			name: 'activitypub/navigate-user-following',
			label: __( 'ActivityPub: View Who You Follow', 'activitypub' ),
			icon: activityPubIcon,
			callback: ( { close } ) => {
				document.location.href = 'users.php?page=activitypub-following-list';
				close();
			},
		} );
	}

	// User Extra Fields commands.
	registerCommand( {
		name: 'activitypub/navigate-extra-fields',
		label: __( 'ActivityPub: View Extra Fields', 'activitypub' ),
		icon: activityPubIcon,
		callback: ( { close } ) => {
			document.location.href = 'edit.php?post_type=ap_extrafield';
			close();
		},
	} );

	registerCommand( {
		name: 'activitypub/add-extra-field',
		label: __( 'ActivityPub: Add New Extra Field', 'activitypub' ),
		icon: activityPubIcon,
		callback: ( { close } ) => {
			document.location.href = 'post-new.php?post_type=ap_extrafield';
			close();
		},
	} );

	// Dynamic command loader: Edit existing extra fields.
	registerCommandLoader( {
		name: 'activitypub/extra-fields-search',
		hook: useExtraFieldsCommandLoader,
	} );

	// Blocked Actors command (user-specific).
	registerCommand( {
		name: 'activitypub/navigate-blocked-actors',
		label: __( 'ActivityPub: View Blocked Actors', 'activitypub' ),
		icon: activityPubIcon,
		callback: ( { close } ) => {
			document.location.href = 'users.php?page=activitypub-blocked-actors-list';
			close();
		},
	} );
}

// Blog-related commands (for blog and actor_blog modes with manage_options capability).
if ( canManageOptions && ( actorMode === 'blog' || actorMode === 'actor_blog' ) ) {
	// Blog Followers command.
	registerCommand( {
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
		registerCommand( {
			name: 'activitypub/navigate-blog-following',
			label: __( 'ActivityPub: View Blog Following', 'activitypub' ),
			icon: activityPubIcon,
			callback: ( { close } ) => {
				document.location.href = 'options-general.php?page=activitypub&tab=following';
				close();
			},
		} );
	}

	// Settings command (blog-related, requires manage_options).
	registerCommand( {
		name: 'activitypub/navigate-settings',
		label: __( 'ActivityPub: View Settings', 'activitypub' ),
		icon: activityPubIcon,
		callback: ( { close } ) => {
			document.location.href = 'options-general.php?page=activitypub&tab=settings';
			close();
		},
	} );

	// Blog Extra Fields commands.
	registerCommand( {
		name: 'activitypub/navigate-blog-extra-fields',
		label: __( 'ActivityPub: View Blog Extra Fields', 'activitypub' ),
		icon: activityPubIcon,
		callback: ( { close } ) => {
			document.location.href = 'edit.php?post_type=ap_extrafield_blog';
			close();
		},
	} );

	registerCommand( {
		name: 'activitypub/add-blog-extra-field',
		label: __( 'ActivityPub: Add New Blog Extra Field', 'activitypub' ),
		icon: activityPubIcon,
		callback: ( { close } ) => {
			document.location.href = 'post-new.php?post_type=ap_extrafield_blog';
			close();
		},
	} );

	// Dynamic command loader: Edit existing blog extra fields.
	registerCommandLoader( {
		name: 'activitypub/blog-extra-fields-search',
		hook: useBlogExtraFieldsCommandLoader,
	} );
}
