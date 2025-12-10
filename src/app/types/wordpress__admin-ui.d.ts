/**
 * Type declarations for @wordpress/admin-ui
 *
 * This package doesn't ship with TypeScript definitions,
 * so we declare the types we need here.
 */

declare module '@wordpress/admin-ui' {
	import { ComponentType, ReactNode } from 'react';

	export interface NavigableRegionProps {
		children: ReactNode;
		className?: string;
		ariaLabel?: string;
	}

	export const NavigableRegion: ComponentType< NavigableRegionProps >;
}
