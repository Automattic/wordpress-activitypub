import './style.scss';
import { Followers } from './followers';
import { render } from '@wordpress/element';
import domReady from '@wordpress/dom-ready';

domReady( () => {
	// iterate over a nodelist
	[].forEach.call( document.querySelectorAll( '.activitypub-follower-block' ), ( element ) => {
		const attrs = JSON.parse( element.dataset.attrs );
		render( <Followers { ...attrs } />, element );
	} );
} );