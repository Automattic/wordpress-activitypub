/**
 * Type declarations for the ActivityPub client store
 */

import '@wordpress/data';

declare module '@wordpress/data' {
	export function select( key: 'activitypub/client' ): {};
	export function dispatch( key: 'activitypub/client' ): {};
}
