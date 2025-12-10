/**
 * Type declarations for @wordpress/commands
 *
 * This package doesn't ship with TypeScript definitions,
 * so we declare the types we need here.
 */

declare module '@wordpress/commands' {
	import { ComponentType, ReactNode } from 'react';
	import { StoreDescriptor } from '@wordpress/data';

	export interface Command {
		name: string;
		label: string;
		icon?: ReactNode;
		callback?: ( { close }: { close: () => void } ) => void;
		context?: string;
	}

	export interface CommandsState {
		commands: Command[];
		isOpen: boolean;
	}

	export interface CommandsActions {
		registerCommand: ( command: Command ) => void;
		unregisterCommand: ( commandName: string ) => void;
		open: () => void;
		close: () => void;
		toggle: () => void;
	}

	export interface CommandsSelectors {
		getCommands: () => Command[];
		isOpen: () => boolean;
	}

	export const store: StoreDescriptor< CommandsState, CommandsActions, CommandsSelectors >;

	export const CommandMenu: ComponentType< {} >;
}
