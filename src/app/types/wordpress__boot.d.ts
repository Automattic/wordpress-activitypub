/**
 * Type declarations for @wordpress/boot
 *
 * This package does not ship with TypeScript definitions yet,
 * so we declare the boot API used by the app loader.
 */

import type { Route } from '../router/types';

declare module '@wordpress/boot' {
	export interface SinglePageBootConfig {
		mountId: string;
		routes: Route[];
	}

	export function initSinglePage( config: SinglePageBootConfig ): void;
}
