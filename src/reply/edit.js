import { __ } from '@wordpress/i18n';
import { useBlockProps, store as blockEditorStore } from '@wordpress/block-editor';
import { TextControl } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { useDispatch } from '@wordpress/data';
import './edit.scss';

export default function Edit( { attributes: attr, setAttributes, clientId } ) {
	const [ className, setClassName ] = useState( '' );
	const { insertAfterBlock, removeBlock } = useDispatch( blockEditorStore );
	const defaultHelpText = __( 'For example: Paste a URL from a Mastodon post or note into the field above to leave a comment.', 'activitypub' );
	const [ helpText, setHelpText ] = useState( defaultHelpText );
	const reset = () => {
		setClassName( '' );
		setHelpText( defaultHelpText )
	};

	const onUrlChange = ( url ) => {
		if ( ! isUrl( url ) ) {
			setClassName( 'error' );
			setHelpText( __( 'Please enter a valid URL.', 'activitypub' ) );
		} else {
			reset();
		}

		setAttributes( { url } );
	};

	const onKeyDown = ( event ) => {
		if ( event.key === 'Enter' ) {
			insertAfterBlock( clientId );
		}
		if ( ! attr.url && [ 'Backspace', 'Delete' ].includes( event.key ) ) {
			removeBlock( clientId );
		}
	}


	return (
		<div { ...useBlockProps() }>
			<TextControl
				label={ __( 'This post is a reply to the following URL', 'activitypub' ) }
				value={ attr.url }
				onChange={ onUrlChange }
				onKeyDown={ onKeyDown }
				type='url'
				placeholder='https://example.org/path'
				className={ className }
				help={ helpText }
			/>
		</div>
	);
}

function isUrl( string ) {
	try {
		new URL( string );
		return true;
	} catch ( _ ) {
		return false;
	}
}
