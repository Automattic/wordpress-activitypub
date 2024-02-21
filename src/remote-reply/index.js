import { createRoot } from '@wordpress/element';
import domReady from '@wordpress/dom-ready';
import RemoteReply from './remote-reply';

let id = 1;
function getUniqueId() {
	return `activitypub-remote-reply-link-${ id++ }`;
}

domReady( () => {
	// iterate over a nodelist
	[].forEach.call( document.querySelectorAll( '.activitypub-remote-reply' ), ( element ) => {
		const attrs = JSON.parse( element.dataset.attrs );
		createRoot( element).render( <RemoteReply { ...attrs } id={ getUniqueId() } useId={ true } /> );
	} );
} );
