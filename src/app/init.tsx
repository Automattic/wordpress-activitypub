/**
 * WordPress dependencies
 */
import { dispatch } from '@wordpress/data';
import { store as bootStore } from '@wordpress/boot';
import { Path, SVG } from '@wordpress/primitives';

/*
 * Boot draws its menu icons with `fill: currentColor` on every path, which turns the
 * stroke-based icons of `@wordpress/icons` 17 into solid boxes. This is the fill-based
 * list drawing (`postList` up to icons 16), kept as the app's own icon.
 */
const feedIcon = (
	<SVG xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
		<Path d="M18 5.5H6a.5.5 0 0 0-.5.5v12a.5.5 0 0 0 .5.5h12a.5.5 0 0 0 .5-.5V6a.5.5 0 0 0-.5-.5ZM6 4h12a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Zm1 5h1.5v1.5H7V9Zm1.5 4.5H7V15h1.5v-1.5ZM10 9h7v1.5h-7V9Zm7 4.5h-7V15h7v-1.5Z" />
	</SVG>
);

/**
 * Boot init module: runs after menu items and routes are registered, before
 * the app renders. Icons cannot be passed from PHP, so they are set here.
 */
export async function init(): Promise< void > {
	dispatch( bootStore ).updateMenuItem( 'feed', { icon: feedIcon } );
}
