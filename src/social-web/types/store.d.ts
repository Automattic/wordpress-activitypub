/**
 * Type declarations for the social-web store
 */

import '@wordpress/data';

declare module '@wordpress/data' {
	export function select( key: 'activitypub/social-web' ): {};
	export function dispatch( key: 'activitypub/social-web' ): {};
}
