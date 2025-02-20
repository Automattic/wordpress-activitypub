import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls, InnerBlocks } from '@wordpress/block-editor';
import { TextControl, ToggleControl, PanelBody, Spinner } from '@wordpress/components';
import { useState, useEffect, useCallback } from '@wordpress/element';
import { useDispatch, useSelect } from '@wordpress/data';
import { useDebounce } from '@wordpress/compose';
import apiFetch from '@wordpress/api-fetch';

export default function Edit( { attributes: attr, setAttributes, clientId, isSelected } ) {
	const { url } = attr;
	const defaultHelpText = __( 'For example: Paste a URL from a Fediverse (e.g. Mastodon, Pixelfed, etc.) post or note into the field above to leave a comment.', 'activitypub' );
	const successHelpText = __( 'Embed success!', 'activitypub' );
	const errorHelpText = __( 'This URL cannot be embedded. We will still attempt to notify the post\'s author on publish.', 'activitypub' );
	const [ helpText, setHelpText ] = useState( defaultHelpText );
	const [ isValidEmbed, setIsValidEmbed ] = useState( false );
	const [ isCheckingEmbed, setIsCheckingEmbed ] = useState( false );
	const blockProps = useBlockProps();
	const { insertAfterBlock, removeBlock, updateBlockAttributes } = useDispatch( 'core/block-editor' );
	const innerBlocks = useSelect( ( select ) => select( 'core/block-editor' ).getBlocks( clientId ), [ clientId ] );

	useEffect(() => {
		if ( attr.embedPost === null ) {
			// No existing attributes means this is a new block.
			setAttributes({ embedPost: ! attr.url });
		}
	}, []);

	// Debounced URL validation check.
	const checkUrl = useCallback( async ( urlToCheck ) => {
		if ( ! urlToCheck || ! isUrl( urlToCheck ) ) {
			setIsValidEmbed( false );
			return;
		}

		setIsCheckingEmbed( true );
		setHelpText( __( 'Checking URL…', 'activitypub' ) );

		try {
			const response = await apiFetch( {
				path: `/oembed/1.0/proxy?url=${ encodeURIComponent( urlToCheck ) }`,
			} );
			const isValid = !! response?.html;
			setIsValidEmbed( isValid );
			isValid
				? setHelpText( successHelpText )
				: setHelpText( errorHelpText );
		} catch ( error ) {
			setIsValidEmbed( false );
			setHelpText( errorHelpText );
		} finally {
			setIsCheckingEmbed( false );
		}
	}, [ defaultHelpText, errorHelpText, successHelpText ] );

	const debouncedCheck = useDebounce( checkUrl, 250 );

	// Check if URL is embeddable.
	useEffect( () => {
		debouncedCheck( url );
	}, [ url, debouncedCheck ] );

	// Update inner embed block URL when parent URL changes.
	useEffect( () => {
		if ( innerBlocks?.length && innerBlocks[0]?.name === 'core/embed' ) {
			updateBlockAttributes( innerBlocks[0].clientId, { url } );
		}
	}, [ url, innerBlocks ] );

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
						disabled={ ! isValidEmbed }
						help={ ! isValidEmbed && url ? __( 'Embedding is not available for this URL.', 'activitypub' ) : '' }
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				{ isSelected && (
					<TextControl
						label={ __( 'This post is a reply to the following URL', 'activitypub' ) }
						value={ url || '' }
						onChange={ onUrlChange }
						onKeyDown={ onKeyDown }
						type='url'
						placeholder={ __( 'Enter URL here...', 'activitypub' ) }
						help={ helpText }
					/>
				) }
				{ attr.embedPost && url && (
					<div
						className="activitypub-embed-container"
						contentEditable={ false }
						onFocus={ ( e ) => e.stopPropagation() }
						onClick={ ( e ) => e.stopPropagation() }
					>
						{ isCheckingEmbed && (
							<div className="activitypub-embed-loading">
								<Spinner />
							</div>
						) }
						{ isValidEmbed && ! isCheckingEmbed && (
							<div className="activitypub-embed-preview">
								<InnerBlocks
									template={ embedTemplate }
									templateLock="all"
								/>
							</div>
						) }
					</div>
				) }
				{ url && (
					<div className="activitypub-reply-display">
						<p>
							<a
								title={ __( 'This post is a response to the referenced content.', 'activitypub' ) }
								aria-label={ __( 'This post is a response to the referenced content.', 'activitypub' ) }
								href={ url }
								className="u-in-reply-to"
								target="_blank"
								rel="noreferrer"
							>
								{ '↬' + url.replace( /^https?:\/\//, '' ) }
							</a>
						</p>
					</div>
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
