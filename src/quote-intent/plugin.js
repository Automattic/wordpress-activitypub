import { registerPlugin } from '@wordpress/plugins';
import { createBlock } from '@wordpress/blocks';
import { dispatch } from '@wordpress/data';
import { store as blockEditorStore } from '@wordpress/block-editor';
import { useEffect } from '@wordpress/element';

// We use a kind of global state to ensure only a single embed is rendered across component re-renders.
let didHandleEmbed = false;

const HandleQuoteIntent = () => {
	useEffect( () => {
		if ( didHandleEmbed ) {
			return;
		}
		// Get the GET['quotation_of'] value from the URL
		const urlParams = new URLSearchParams( window.location.search );
		const quotationOf = urlParams.get( 'quotation_of' );
		if ( quotationOf && ! didHandleEmbed ) {
			// prepend an activitypub/quote block to the editor
			// it appears to need a slight delay
			setTimeout( () => {
				const block = createBlock( 'activitypub/quote', { url: quotationOf, embedPost: true } );
				const store = dispatch( blockEditorStore );
				store.insertBlock( block );
				// add a new block after it so the user can just type
				store.insertAfterBlock( block.clientId );
			}, 200 );
		}
		didHandleEmbed = true;
	}, [] );

	return null;
};

registerPlugin( 'activitypub-quote-intent', { render: HandleQuoteIntent } );
