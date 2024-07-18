import { createRoot } from '@wordpress/element';
import domReady from '@wordpress/dom-ready';
import FollowMe from './follow-me';

let id = 1;
function getUniqueId() {
	return `activitypub-follow-me-block-${ id++ }`;
}

domReady( () => {
	// iterate over a nodelist
	[].forEach.call( document.querySelectorAll( '.activitypub-follow-me-block-wrapper' ), ( element ) => {
		const attrs = JSON.parse( element.dataset.attrs );
		createRoot( element ).render( <FollowMe { ...attrs } id={ getUniqueId() } useId={ true } /> );
	} );
} );