import { createRoot } from '@wordpress/element';
import domReady from '@wordpress/dom-ready';
import ProfileEditor from './profile-editor.js';

domReady( () => {
	document.querySelectorAll( '.activitypub-profile-editor' ).forEach( ( element ) => {
		const id = parseInt( element.dataset.id, 10 );
		createRoot( element ).render( <ProfileEditor id={ id } /> );
	} );
} );