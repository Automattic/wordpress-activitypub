import { registerPlugin } from '@wordpress/plugins';
import { createBlock } from '@wordpress/blocks';
import { dispatch } from '@wordpress/data';
import { store as blockEditorStore } from '@wordpress/block-editor';
import { useEffect, useState } from '@wordpress/element';

// We use a kind of global state to ensure only a single embed is rendered across component re-renders.
let didHandleEmbed = false;

const HandleReplyIntent = () => {
	useEffect( () => {
		if ( didHandleEmbed ) {
			return;
		}
		// Get the GET['in_reply_to'] value from the URL
		const urlParams = new URLSearchParams( window.location.search );
		const inReplyTo = urlParams.get( 'in_reply_to' );
		if ( inReplyTo && ! didHandleEmbed ) {
			// prepend an activitypub/reply block to the editor
			// it appears to need a slight delay
			setTimeout( () => {
				const block = createBlock( 'activitypub/reply', { url: inReplyTo, embedPost: true } );
				const store = dispatch( blockEditorStore );
				store.insertBlock( block );
				// add a new block after it so the user can just type
				store.insertAfterBlock( block.clientId );
			}, 200 );
		}
		didHandleEmbed = true;
	} );

	return null;
};

registerPlugin( 'activitypub-reply-intent', { render: HandleReplyIntent } );