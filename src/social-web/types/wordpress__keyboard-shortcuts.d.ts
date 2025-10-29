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

	export interface ShortcutConfig {
		name: string;
		category: string;
		description: string;
		keyCombination: {
			modifier?: string;
			character: string;
		};
		aliases?: Array< {
			modifier?: string;
			character: string;
		} >;
	}

	export function useShortcut(
		name: string,
		callback: ( event: KeyboardEvent ) => void,
		options?: {
			bindGlobal?: boolean;
			eventName?: 'keydown' | 'keypress' | 'keyup';
			isDisabled?: boolean;
		}
	): void;
}
