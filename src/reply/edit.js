import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { TextControl, PanelBody, ToggleControl, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useEffect, useState, useRef } from '@wordpress/element';
import { useDebounce } from '@wordpress/compose';
import apiFetch from '@wordpress/api-fetch';
import { addQueryArgs } from '@wordpress/url';
import { useOptions } from '../shared/use-options';
import { useDispatch } from '@wordpress/data';

/**
 * Edit component for the ActivityPub block.
 *
 * @param {Object} props - Component props.
 * @param {Object} props.attributes - Block attributes.
 * @param {string} props.attributes.url - URL of the post being replied to.
 * @param {boolean} props.attributes.embedPost - Whether to embed the post.
 * @param {Function} props.setAttributes - Function to update block attributes.
 * @param {string} props.clientId - Block client ID.
 * @param {boolean} props.isSelected - Whether the block is selected.
 */

// Help text messages for different reply states.
const HELP_TEXT = {
	default: __( 'Enter the URL of a post from the Fediverse (Mastodon, Pixelfed, etc.) that you want to reply to.', 'activitypub' ),
	checking: __( 'Checking if this URL supports ActivityPub replies...', 'activitypub' ),
	valid: __( 'The author will be notified of your response.', 'activitypub' ),
	error: __( 'This URL probably won\'t receive your reply. We\'ll still try.', 'activitypub' ),
};

// Help text messages for embed toggle states.
const EMBED_HELP_TEXT = {
	valid: __( 'This post can be embedded with your reply.', 'activitypub' ),
	invalid: __( 'This post cannot be embedded.', 'activitypub' ),
};

export default function Edit( { attributes: attr, setAttributes, clientId, isSelected } ) {
	const { url } = attr;
	const { namespace } = useOptions();

	// State variables for help text, embed validity, and embed checking status.
	const [ helpText, setHelpText ] = useState( HELP_TEXT.default );
	const [ isValidEmbed, setIsValidEmbed ] = useState( false );
	const [ isRealOembed, setIsRealOembed ] = useState( false );
	const [ isCheckingEmbed, setIsCheckingEmbed ] = useState( false );
	// Optimistic embeds mean that we will toggle embedPost to true whenever we find a valid embed.
	// This will be true when the block is instantiated with `true` because it was saved that way, or because this is a new block with no initial URL.
	const [ optimisticEmbed, setOptimisticEmbed ] = useState( attr.embedPost === true || ! url );
	const [ embedHtml, setEmbedHtml ] = useState( null );
	const [ iframeHeight, setIframeHeight ] = useState( 300 ); // Default height
	const { insertAfterBlock, removeBlock } = useDispatch( 'core/block-editor' );
	// Get block props and dispatch functions.
	const blockProps = useBlockProps();
	const urlInputRef = useRef();
	const iframeRef = useRef();
	const iframeContainerRef = useRef();

	/**
	 * Check if a URL is an ActivityPub URL.
	 *
	 * @param {string} urlToCheck The URL to check.
	 */
	const checkUrl = async ( urlToCheck, optimisticEmbed ) => {
		// Don't check empty URLs.
		if ( ! urlToCheck ) {
			setIsCheckingEmbed( false );
			setIsValidEmbed( false );
			setIsRealOembed( false );
			setEmbedHtml( '' );
			return;
		}

		try {
			setIsCheckingEmbed( true );

			const response = await apiFetch( {
				path: addQueryArgs( `${ namespace }/url/validate`, {
					url: urlToCheck,
				} ),
			} );

			setIsValidEmbed( response.is_activitypub );
			setIsRealOembed( response.is_real_oembed );
			setEmbedHtml( response.html || '' );
			/**
			 * Null at the start means that we're a new block, or an old block from before embeds were added.
			 * In that case, we should set embedPost to true by default when we have a good result.
			 * This will make the choice explicit when editing old posts, as a kind of upgrade, but which will otherwise be left alone.
			 */
			if ( optimisticEmbed ) {
				setAttributes( { embedPost: true } );
			}
			setHelpText( HELP_TEXT.valid );
		} catch ( error ) {
			setIsValidEmbed( false );
			setIsRealOembed( false );
			setAttributes( { embedPost: false } );
			setHelpText( HELP_TEXT.error );
			setEmbedHtml( '' );
		} finally {
			setIsCheckingEmbed( false );
		}
	};

	// Debounce the URL check to avoid too many requests.
	const debouncedCheckUrl = useDebounce( checkUrl, 250 );

	// Check URL when it changes.
	useEffect( () => {
		if ( url ) {
			debouncedCheckUrl( url, optimisticEmbed );
		}
	}, [ url, optimisticEmbed ] );

	// Prepare the HTML content with auto-height script
	const getEnhancedHtml = (html) => {
		return `
			<!DOCTYPE html>
			<html>
			<head>
				<meta charset="UTF-8">
				<meta name="viewport" content="width=device-width, initial-scale=1.0">
				<style>
					body {
						margin: 0;
						padding: 0;
						overflow-x: hidden;
						font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
					}
					img { max-width: 100%; height: auto; }
				</style>
			</head>
			<body>
				${html}
			</body>
			</html>
		`;
	};

	// Handle iframe load and resize events
	const handleIframeLoad = () => {
		if (!iframeRef.current) return;

		try {
			// Initial height adjustment
			adjustIframeHeight();

			// Set up a timer to periodically check height (catches dynamic content changes)
			const intervalId = setInterval(adjustIframeHeight, 1000);

			// Clean up interval on component unmount
			return () => clearInterval(intervalId);
		} catch (e) {
			console.error('Error setting up iframe height adjustment:', e);
		}
	};

	// Function to adjust iframe height based on content
	const adjustIframeHeight = () => {
		if (!iframeRef.current) return;

		try {
			const iframe = iframeRef.current;

			// Try to access iframe content height
			let newHeight = 300; // Default fallback height

			try {
				// Try to get the scrollHeight of the body
				if (iframe.contentDocument && iframe.contentDocument.body) {
					newHeight = iframe.contentDocument.body.scrollHeight;
				} else if (iframe.contentWindow && iframe.contentWindow.document && iframe.contentWindow.document.body) {
					newHeight = iframe.contentWindow.document.body.scrollHeight;
				}
			} catch (e) {
				console.log('Could not access iframe content document:', e);
				// This is expected in some cases due to same-origin policy
			}

			// Add a small buffer to prevent scrollbars
			newHeight += 30;

			// Update height state if it changed
			if (newHeight !== iframeHeight) {
				setIframeHeight(newHeight);
			}
		} catch (e) {
			console.error('Error adjusting iframe height:', e);
		}
	};

	// Set up iframe load handler
	useEffect(() => {
		if (iframeRef.current) {
			iframeRef.current.addEventListener('load', handleIframeLoad);

			return () => {
				if (iframeRef.current) {
					iframeRef.current.removeEventListener('load', handleIframeLoad);
				}
			};
		}
	}, [embedHtml]);

	/**
	 * Handle embed toggle changes.
	 *
	 * @param {boolean} value - New embed toggle value.
	 */
	const onEmbedPostChange = ( value ) => {
		// Every manual toggle indicates a preference about embedding we can default to.
		setOptimisticEmbed( value );
		setAttributes( { embedPost: value } );
	};

	const onKeyDown = ( event ) => {
		if ( event.key === 'Enter' ) {
			insertAfterBlock( clientId );
		}
		if ( ! attr.url && [ 'Backspace', 'Delete' ].includes( event.key ) ) {
			removeBlock( clientId );
		}
	};

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Settings', 'activitypub' ) }>
					<ToggleControl
						label={ __( 'Embed Post', 'activitypub' ) }
						checked={ attr.embedPost }
						onChange={ onEmbedPostChange }
						disabled={ ! isValidEmbed }
						help={ isValidEmbed ? EMBED_HELP_TEXT.valid : EMBED_HELP_TEXT.invalid }
					/>
				</PanelBody>
			</InspectorControls>

			<div { ...blockProps }>
				{ isSelected && (
					<TextControl
						label={ __( 'Your post is a reply to the following URL', 'activitypub' ) }
						value={ url }
						onChange={ ( value ) => setAttributes( { url: value } ) }
						help={ helpText }
						onKeyDown={ onKeyDown }
						ref={ urlInputRef }
					/>
				) }

				{ isCheckingEmbed && (
					<div className="activitypub-embed-container activitypub-embed-loading">
						<Spinner />
						<div>{ HELP_TEXT.checking }</div>
					</div>
				) }

				{ isValidEmbed && attr.embedPost && embedHtml && (
					<div className="activitypub-embed-container" style={{ pointerEvents: 'auto' }}>
						{ isRealOembed ? (
							<div
								ref={iframeContainerRef}
								contentEditable={ false }
								onFocus={ ( e ) => e.stopPropagation() }
								onClick={ ( e ) => e.stopPropagation() }
								style={{
									height: iframeHeight + 'px',
									overflow: 'hidden',
									pointerEvents: 'none',
									transition: 'height 0.2s ease-in-out'
								}}
							>
								<iframe
									ref={iframeRef}
									srcDoc={getEnhancedHtml(embedHtml)}
									style={{
										position: 'absolute',
										top: 0,
										left: 0,
										width: '100%',
										height: '100%',
										pointerEvents: 'none'
									}}
									frameBorder="0"
									allowFullScreen
									title={ __( 'Embedded content from', 'activitypub' ) + ' ' + url }
								></iframe>
							</div>
						) : (
							<div
								contentEditable={ false }
								onFocus={ ( e ) => e.stopPropagation() }
								onClick={ ( e ) => e.stopPropagation() }
								dangerouslySetInnerHTML={{ __html: embedHtml }}
							/>
						) }
					</div>
				) }

				{ url && (
					<div
						className="activitypub-reply-block-editor__preview"
						contentEditable={ false }
						onClick={ ( e ) => {
							e.preventDefault();
							setTimeout( () => urlInputRef.current?.focus(), 20 );
						} }
						style={ { pointerEvents: 'auto', cursor: 'pointer' } }
					>
						<a
							href={ url }
							className="u-in-reply-to"
							target="_blank"
							rel="noreferrer"
						>
							{ '↬' + url.replace( /^https?:\/\//, '' ) }
						</a>
					</div>
				) }
			</div>
		</>
	);
}
