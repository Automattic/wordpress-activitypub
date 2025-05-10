import { createRoot } from '@wordpress/element';
import domReady from '@wordpress/dom-ready';
import FollowMe from './follow-me';

let id = 1;

/**
 * Generate a unique ID for a follow-me block instance.
 *
 * @returns {string} The unique block ID.
 */
function getUniqueId() {
	return `activitypub-follow-me-block-${ id++ }`;
}

/**
 * Initialize and render all follow-me block instances on DOM ready.
 */
domReady( () => {
	[].forEach.call( document.querySelectorAll( '.activitypub-follow-me-block-wrapper' ), ( element ) => {
		const attrs = JSON.parse( element.dataset.attrs );
		createRoot( element ).render( <FollowMe { ...attrs } id={ getUniqueId() } useId={ true } /> );
	} );
} );
