import { createRoot } from '@wordpress/element';
import domReady from '@wordpress/dom-ready';
import { Reactions } from './reactions';

domReady( () => {
	// iterate over a nodelist
	[].forEach.call( document.querySelectorAll( '.activitypub-reactions-block' ), ( element ) => {
		const attrs = JSON.parse( element.dataset.attrs );
		createRoot( element ).render( <Reactions { ...attrs } /> );
	} );
} );