/**
 * Type declarations for the ActivityPub app store
 */

import '@wordpress/data';

declare module '@wordpress/data' {
	export function select( key: 'activitypub/app' ): {};
	export function dispatch( key: 'activitypub/app' ): {};
}
