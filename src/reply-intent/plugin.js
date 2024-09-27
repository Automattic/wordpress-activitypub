import { registerPlugin } from '@wordpress/plugins';
import { createBlock } from '@wordpress/blocks';
import { dispatch } from '@wordpress/data';
import { store as blockEditorStore } from '@wordpress/block-editor';
import { useEffect, useState } from '@wordpress/element';

const HandleReplyIntent = () => {
	const [ didHandle, setDidHandle ] = useState( false );
	useEffect( () => {
		if ( didHandle ) {
			return;
		}
		// Get the GET['in_reply_to'] value from the URL
		const urlParams = new URLSearchParams( window.location.search );
		const inReplyTo = urlParams.get( 'in_reply_to' );
		if ( inReplyTo ) {
			// prepend an activitypub/reply block to the editor
			// it appears to need a slight delay
			setTimeout( () => {
				const block = createBlock( 'activitypub/reply', { url: inReplyTo } );
				const store = dispatch( blockEditorStore );
				store.insertBlock( block );
				// add a new block after it so the user can just type
				store.insertAfterBlock( block.clientId );
			}, 200 );
		}

		setDidHandle( true );
	}, [ didHandle ] );

	return null;
};

registerPlugin( 'activitypub-reply-intent', { render: HandleReplyIntent } );