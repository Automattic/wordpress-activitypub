import { __ } from '@wordpress/i18n';
import { useBlockProps } from '@wordpress/block-editor';
import { TextControl } from '@wordpress/components';
import { useState } from '@wordpress/element';
import './edit.scss';

export default function Edit( { attributes: attr, setAttributes } ) {
	const [ className, setClassName ] = useState( '' );

	const onUrlChange = ( url ) => {
		if ( ! isUrl( url ) ) {
			setClassName( 'error' );
		} else {
			setClassName( '' );
		}

		setAttributes( { url: url } );
	};

	return (
		<div { ...useBlockProps() }>
			<TextControl
				label={ __( 'This post is a reply to the following URL', 'activitypub' ) }
				value={ attr.url }
				onChange={ onUrlChange }
				type='url'
				placeholder='https://example.org/path'
				className={ className }
				help={ __( 'For example: Paste a URL from a Mastodon post or note into the field above to leave a comment.', 'activitypub' ) }
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
