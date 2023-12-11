import { createRoot } from '@wordpress/element';
import domReady from '@wordpress/dom-ready';
import ProfileEditor from './profile-editor.js';

domReady( () => {
	const container = document.getElementById( 'blog-profile-editor' );
	if ( container ) {
		createRoot( container ).render( <ProfileEditor /> );
	}
} );