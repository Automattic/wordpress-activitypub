/**
 * WordPress dependencies
 */
import { dispatch } from '@wordpress/data';
import { store as bootStore } from '@wordpress/boot';
import { postList } from '@wordpress/icons';

/**
 * Boot init module: runs after menu items and routes are registered, before
 * the app renders. Icons cannot be passed from PHP, so they are set here.
 */
export async function init(): Promise< void > {
	dispatch( bootStore ).updateMenuItem( 'feed', { icon: postList } );
}
