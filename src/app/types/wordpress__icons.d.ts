/**
 * Type declarations for @wordpress/icons
 *
 * This package doesn't ship with TypeScript definitions,
 * so we declare the types we need here.
 */

declare module '@wordpress/icons' {
	import type { ReactElement } from 'react';

	// Icons used in src/app/components/object-types/index.tsx
	export const audio: ReactElement;
	export const calendar: ReactElement;
	export const file: ReactElement;
	export const image: ReactElement;
	export const page: ReactElement;
	export const pin: ReactElement;
	export const postContent: ReactElement;
	export const video: ReactElement;

	// Icons used in src/app/components/sidebar/index.tsx
	export const chevronLeft: ReactElement;
	export const chevronRight: ReactElement;
	export const cog: ReactElement;
	export const postList: ReactElement;

	// Icons used in src/app/components/site-hub/index.tsx
	export const search: ReactElement;

	// Icons used in src/app/components/site-icon/index.tsx
	export const wordpress: ReactElement;

	// Icons used in src/app/routes/feed/inspector.tsx
	export const close: ReactElement;

	// Icon used in src/app/components/object-types/index.tsx (also declared above)
	export const comment: ReactElement;
}
