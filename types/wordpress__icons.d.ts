/**
 * Type declarations for @wordpress/icons
 *
 * This package doesn't ship with TypeScript definitions,
 * so we declare the types we need here.
 */

declare module '@wordpress/icons' {
	import { ReactElement } from 'react';

	// ReactElements used in the project.
	export const search: ReactElement;
	export const wordpress: ReactElement;
	export const people: ReactElement;
	export const commentContent: ReactElement;
	export const home: ReactElement;
	export const chevronRightSmall: ReactElement;
	export const chevronLeftSmall: ReactElement;
	export const group: ReactElement;
	export const close: ReactElement;
	export const postList: ReactElement;
	export const trendingUp: ReactElement;
	export const arrowLeft: ReactElement;
	export const cog: ReactElement;
	export const chartBar: ReactElement;
	export const addCard: ReactElement;
	export const comment: ReactElement;
}
