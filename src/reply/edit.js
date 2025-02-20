import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls, InnerBlocks } from '@wordpress/block-editor';
import { TextControl, ToggleControl, PanelBody } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { useDispatch } from '@wordpress/data';

export default function Edit( { attributes: attr, setAttributes, clientId, isSelected } ) {
	const { url } = attr;
	const defaultHelpText = __( 'For example: Paste a URL from a Fediverse (e.g. Mastodon, Pixelfed, etc.) post or note into the field above to leave a comment.', 'activitypub' );
	const [ helpText, setHelpText ] = useState( defaultHelpText );
	const blockProps = useBlockProps();
	const { insertAfterBlock, removeBlock } = useDispatch( 'core/block-editor' );

	const onUrlChange = ( newUrl ) => {
		if ( ! isUrl( newUrl ) ) {
			setHelpText( __( 'Please enter a valid URL.', 'activitypub' ) );
		} else {
			setHelpText( defaultHelpText );
		}

		setAttributes( { url: newUrl } );
	};

	const onEmbedPostChange = ( embedPost ) => {
		setAttributes( { embedPost } );
	};

	const onKeyDown = ( event ) => {
		if ( event.key === 'Enter' ) {
			insertAfterBlock( clientId );
		}
		if ( ! attr.url && [ 'Backspace', 'Delete' ].includes( event.key ) ) {
			removeBlock( clientId );
		}
	}

	const embedTemplate = [
		[
			'core/embed',
			{
				url,
				type: 'rich',
				providerNameSlug: 'activitypub',
				responsive: true,
			},
		],
	];

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Settings', 'activitypub' ) }>
					<ToggleControl
						label={ __( 'Embed Post', 'activitypub' ) }
						checked={ attr.embedPost }
						onChange={ onEmbedPostChange }
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				<TextControl
					label={ __( 'This post is a reply to the following URL', 'activitypub' ) }
					value={ url || '' }
					onChange={ onUrlChange }
					onKeyDown={ onKeyDown }
					type='url'
					placeholder={ __( 'Enter URL here...', 'activitypub' ) }
					help={ isSelected ? helpText : '' }
				/>
				{ attr.embedPost && url && (
					<InnerBlocks
						template={ embedTemplate }
						templateLock="all"
					/>
				) }
			</div>
		</>
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
