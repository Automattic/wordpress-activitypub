import { registerPlugin } from '@wordpress/plugins';
import { createBlock } from '@wordpress/blocks';
import { dispatch } from '@wordpress/data';
import { store as blockEditorStore } from '@wordpress/block-editor';
import { useEffect } from '@wordpress/element';

/**
 * Register a plugin that prefills a block from a URL parameter.
 *
 * The bookmarklets and the interaction endpoint link to `post-new.php` with the address of
 * the post to act on, and the editor opens with the matching block already in place.
 *
 * @param {Object} options           Plugin options.
 * @param {string} options.name      Plugin name to register.
 * @param {string} options.param     URL parameter carrying the address.
 * @param {string} options.blockName Block to insert.
 */
export function registerIntentPlugin( { name, param, blockName } ) {
	// A kind of global state, so only a single embed is inserted across component re-renders.
	let didHandleEmbed = false;

	const HandleIntent = () => {
		useEffect( () => {
			if ( didHandleEmbed ) {
				return;
			}
			didHandleEmbed = true;

			const url = new URLSearchParams( window.location.search ).get( param );
			if ( ! url ) {
				return;
			}

			// Inserting appears to need a slight delay.
			setTimeout( () => {
				const block = createBlock( blockName, { url, embedPost: true } );
				const store = dispatch( blockEditorStore );
				store.insertBlock( block );
				// Add a new block after it so the user can just type.
				store.insertAfterBlock( block.clientId );
			}, 200 );
		}, [] );

		return null;
	};

	registerPlugin( name, { render: HandleIntent } );
}
