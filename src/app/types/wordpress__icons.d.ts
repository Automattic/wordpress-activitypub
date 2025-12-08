/**
 * Type declarations for @wordpress/icons
 *
 * This package doesn't ship with TypeScript definitions,
 * so we declare the types we need here.
 */

declare module '@wordpress/icons' {
	import { ReactElement } from 'react';

	// ReactElements used in the project.
	export const chevronLeft: ReactElement;
	export const chevronRight: ReactElement;
	export const close: ReactElement;
	export const comment: ReactElement;
	export const cog: ReactElement;
	export const group: ReactElement;
	export const home: ReactElement;
	export const menu: ReactElement;
	export const people: ReactElement;
	export const postList: ReactElement;
	export const search: ReactElement;
	export const wordpress: ReactElement;
}
