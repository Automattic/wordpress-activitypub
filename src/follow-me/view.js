import { render } from '@wordpress/element';
import domReady from '@wordpress/dom-ready';
import FollowMe from './follow-me';

domReady( () => {
	// iterate over a nodelist
	[].forEach.call( document.querySelectorAll( '.activitypub-follow-me-block-wrapper' ), ( element ) => {
		const attrs = JSON.parse( element.dataset.attrs );
		render( <FollowMe { ...attrs } />, element );
	} );
} );