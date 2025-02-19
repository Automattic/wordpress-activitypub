import { __ } from '@wordpress/i18n';
import { useBlockProps, store as blockEditorStore, InspectorControls } from '@wordpress/block-editor';
import { TextControl, ToggleControl, PanelBody, Placeholder, Spinner } from '@wordpress/components';
import { useState, useEffect } from '@wordpress/element';
import { useDispatch } from '@wordpress/data';
import { addQueryArgs } from '@wordpress/url';
import apiFetch from '@wordpress/api-fetch';
import { useOptions } from '../shared/use-options';

export default function Edit( { attributes: attr, setAttributes, clientId, isSelected } ) {
	const { url } = attr;
	const [ preview, setPreview ] = useState( null );
	const [ loading, setLoading ] = useState( false );
	const [ error, setError ] = useState( null );
	const { insertAfterBlock, removeBlock } = useDispatch( blockEditorStore );
	const defaultHelpText = __( 'For example: Paste a URL from a Fediverse (e.g. Mastodon, Pixelfed, etc.) post or note into the field above to leave a comment.', 'activitypub' );
	const [ helpText, setHelpText ] = useState( defaultHelpText );
	const blockProps = useBlockProps();
	const { namespace } = useOptions();

	const reset = () => {
		setHelpText( defaultHelpText );
		setError( null );
	};

	useEffect( () => {
		if ( ! url || ! attr.embedPost ) {
			setPreview( null );
			setError( null );
			return;
		}

		setLoading( true );
		setError( null );

		apiFetch( {
			path: `/${ namespace }/embed?url=${ encodeURIComponent( url ) }`,
		} )
			.then( ( response ) => {
				setPreview( response.html );
				setError( null );
			} )
			.catch( ( err ) => {
				setError( err.message || __( 'Failed to load embed preview', 'activitypub' ) );
				setPreview( null );
			} )
			.finally( () => {
				setLoading( false );
			} );
	}, [ url, namespace, attr.embedPost ] );

	const onUrlChange = ( url ) => {
		if ( ! isUrl( url ) ) {
			setHelpText( __( 'Please enter a valid URL.', 'activitypub' ) );
		} else {
			reset();
		}

		setAttributes( { url } );
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
				{ attr.embedPost && url && (
					<div className="wp-block-activitypub-reply__preview">
						{ loading && (
							<Placeholder>
								<Spinner />
								{ __( 'Loading preview...', 'activitypub' ) }
							</Placeholder>
						) }
						{ ! loading && error && (
							<Placeholder>
								<p className="components-placeholder__error">
									{ error }
								</p>
							</Placeholder>
						) }
						{ ! loading && preview && (
							<div
								className="wp-block-activitypub-reply__preview-content"
								dangerouslySetInnerHTML={ { __html: preview } }
							/>
						) }
					</div>
				) }
				<TextControl
					label={ __( 'This post is a reply to the following URL', 'activitypub' ) }
					value={ url || '' }
					onChange={ onUrlChange }
					onKeyDown={ onKeyDown }
					type='url'
					placeholder={ __( 'Enter URL here...', 'activitypub' ) }
					help={ isSelected ? helpText : '' }
				/>
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
