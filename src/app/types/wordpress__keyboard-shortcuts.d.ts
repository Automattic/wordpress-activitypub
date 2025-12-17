/**
 * Type declarations for @wordpress/keyboard-shortcuts
 *
 * This package doesn't ship with TypeScript definitions,
 * so we declare the types we need here.
 */

declare module '@wordpress/keyboard-shortcuts' {
	import { ComponentType, ReactNode } from 'react';

	export interface ShortcutProviderProps {
		children: ReactNode;
	}

	export const ShortcutProvider: ComponentType< ShortcutProviderProps >;
}
