/**
 * Type declarations for WordPress packages without official types.
 */

declare module '@wordpress/commands' {
	export function useCommand( command: {
		name: string;
		label: string;
		icon?: React.ReactNode;
		callback: ( options: { close: () => void } ) => void;
	} ): void;

	export function useCommandLoader( options: {
		name: string;
		hook: ( params: { search: string } ) => {
			commands: Array< {
				name: string;
				label: string;
				icon?: React.ReactNode;
				callback: ( options: { close: () => void } ) => void;
			} >;
			isLoading: boolean;
		};
	} ): void;
}
