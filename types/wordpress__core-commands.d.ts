/**
 * Type declarations for @wordpress/core-commands
 *
 * This package doesn't ship with TypeScript definitions,
 * so we declare the types we need here.
 */

declare module '@wordpress/core-commands' {
	export interface PrivateApis {
		useCommands: () => void;
	}

	export const privateApis: PrivateApis;
}
