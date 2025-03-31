import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { TextControl, PanelBody, ToggleControl, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useEffect, useState, useRef, useCallback } from '@wordpress/element';
import { useDebounce } from '@wordpress/compose';
import apiFetch from '@wordpress/api-fetch';
import { addQueryArgs } from '@wordpress/url';
import { useOptions } from '../shared/use-options';
import { useDispatch } from '@wordpress/data';

/**
 * Maps HTML attribute names to React prop names.
 */
const attributeMap = {
	class: 'className',
	frameborder: 'frameBorder',
	allowfullscreen: 'allowFullScreen',
	allowtransparency: 'allowTransparency',
	marginheight: 'marginHeight',
	marginwidth: 'marginWidth',
};

/**
 * Embed Overlay component for capturing clicks.
 *
 * @param {Object} props Component props.
 * @param {Function} props.onClick Function to call when the overlay is clicked.
 * @return {JSX.Element} The component.
 */
function EmbedOverlay( { onClick } ) {
	return (
		<div
			className="activitypub-embed-overlay"
			onClick={ onClick }
			style={{
				position: 'absolute',
				top: 0,
				left: 0,
				width: '100%',
				height: '100%',
				cursor: 'pointer',
				zIndex: 1,
			}}
		/>
	);
}

/**
 * WordPress Embed Preview component, adapted from Core.
 * Handles WordPress-specific embeds that use the wp-embed format.
 *
 * @param {Object} props Component props.
 * @param {string} props.html The HTML content to embed.
 * @param {Function} props.onSelectBlock Function to call when the embed is clicked.
 * @return {JSX.Element} The component.
 */
function WpEmbedPreview( { html, onSelectBlock } ) {
	const ref = useRef();
	const [ height, setHeight ] = useState( 282 ); // Default WordPress embed height
	const [ interactive, setInteractive ] = useState( false );

	// Parse iframe attributes from the HTML
	const props = useCallback( () => {
		const doc = new window.DOMParser().parseFromString( html, 'text/html' );
		const iframe = doc.querySelector( 'iframe' );
		const iframeProps = {};

		if ( ! iframe ) {
			return iframeProps;
		}

		Array.from( iframe.attributes ).forEach( ( { name, value } ) => {
			if ( name === 'style' ) {
				return;
			}
			iframeProps[ attributeMap[ name ] || name ] = value;
		} );

		return iframeProps;
	}, [ html ] );

	// Extract iframe properties
	const iframeProps = props();

	// Set up message listener for iframe height changes
	useEffect( () => {
		if ( ! ref.current ) {
			return;
		}

		const { ownerDocument } = ref.current;
		const { defaultView } = ownerDocument;

		/**
		 * Handles resize messages from the embedded iframe.
		 *
		 * @param {MessageEvent} event Message event.
		 */
		function resizeWPembeds( { data: { secret, message, value } = {} } ) {
			if ( message !== 'height' || secret !== iframeProps[ 'data-secret' ] ) {
				return;
			}

			setHeight( value );
		}

		defaultView.addEventListener( 'message', resizeWPembeds );
		return () => {
			defaultView.removeEventListener( 'message', resizeWPembeds );
		};
	}, [ iframeProps ] );

	// If no iframe was found, render the HTML directly with an overlay
	if ( ! iframeProps.src ) {
		return (
			<div className="wp-block-embed__wrapper" style={{ position: 'relative' }}>
				<div dangerouslySetInnerHTML={{ __html: html }} />
				<EmbedOverlay onClick={onSelectBlock} />
			</div>
		);
	}

	return (
		<div className="wp-block-embed__wrapper" style={{ position: 'relative' }}>
			<iframe
				ref={ ref }
				title={ iframeProps.title || __( 'Embedded WordPress content', 'activitypub' ) }
				{ ...iframeProps }
				height={ height }
				style={{
					width: '100%',
					maxWidth: '100%'
				}}
			/>
			{ ! interactive && <EmbedOverlay onClick={onSelectBlock} />}
		</div>
	);
}

/**
 * Help text messages for different reply states.
 */
const HELP_TEXT = {
	default: __( 'Enter the URL of a post from the Fediverse (Mastodon, Pixelfed, etc.) that you want to reply to.', 'activitypub' ),
	checking: () => (
		<>
			<Spinner />
			{ ' ' + __( 'Checking if this URL supports ActivityPub replies...', 'activitypub' ) }
		</>
	),
	valid: __( 'The author will be notified of your response.', 'activitypub' ),
	error: __( 'This URL probably won\'t receive your reply. We\'ll still try.', 'activitypub' ),
};

/**
 * Help text messages for embed toggle states.
 */
const EMBED_HELP_TEXT = {
	valid: __( 'This post can be embedded with your reply.', 'activitypub' ),
	invalid: __( 'This post cannot be embedded.', 'activitypub' ),
};

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
	// Use a ref to track optimisticEmbed without causing re-renders
	const optimisticEmbedRef = useRef( optimisticEmbed );

	const focusInput = () => {
		setTimeout( () => urlInputRef.current?.focus(), 50 );
	};

	// Update the ref when optimisticEmbed changes
	useEffect( () => {
		optimisticEmbedRef.current = optimisticEmbed;
	}, [ optimisticEmbed ] );

	// Create a stable callback that uses the ref value
	const setIsValidEmbedAndMaybeEnableEmbed = useCallback( ( isValid ) => {
		setIsValidEmbed( isValid );
		if ( optimisticEmbedRef.current && isValid ) {
			setAttributes( { embedPost: true } );
		}
	}, [ setAttributes ] );

	const resetEmbedState = ( isChecking = false ) => {
		setIsCheckingEmbed( isChecking );
		setIsValidEmbed( false );
		setIsRealOembed( false );
		setEmbedHtml( '' );
	};

	/**
	 * Check if a URL is an ActivityPub URL.
	 *
	 * @param {string} urlToCheck The URL to check.
	 */
	const checkUrl = async ( urlToCheck ) => {
		// Don't check empty URLs.
		if ( ! urlToCheck ) {
			resetEmbedState();
			return;
		}

		try {
			resetEmbedState( true );
			setHelpText( HELP_TEXT.checking() );

			const response = await apiFetch( {
				path: addQueryArgs( `${ namespace }/url/validate`, {
					url: urlToCheck,
				} ),
			} );

			setIsValidEmbedAndMaybeEnableEmbed( response.is_activitypub );
			setIsRealOembed( response.is_real_oembed );
			setEmbedHtml( response.html || '' );
			setHelpText( HELP_TEXT.valid );
		} catch ( error ) {
			resetEmbedState();
			setHelpText( HELP_TEXT.error );
		} finally {
			setIsCheckingEmbed( false );
		}
	};

	// Debounce the URL check to avoid too many requests.
	const debouncedCheckUrl = useDebounce( checkUrl, 250 );

	// Check URL when it changes.
	useEffect( () => {
		if ( url ) {
			debouncedCheckUrl( url );
		}
	}, [ url ] );

	// Prepare the HTML content with auto-height script
	const getEnhancedHtml = ( html ) => {
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
		if ( ! iframeRef.current ) return;

		try {
			// Initial height adjustment
			adjustIframeHeight();

			// Set up a timer to periodically check height (catches dynamic content changes)
			const intervalId = setInterval( adjustIframeHeight, 1000 );

			// Clean up interval on component unmount
			return () => clearInterval( intervalId );
		} catch ( e ) {
			console.error( 'Error setting up iframe height adjustment:', e );
		}
	};

	// Function to adjust iframe height based on content
	const adjustIframeHeight = () => {
		if ( ! iframeRef.current ) return;

		try {
			const iframe = iframeRef.current;

			// Try to access iframe content height
			let newHeight = 300; // Default fallback height

			try {
				// Try to get the scrollHeight of the body
				if ( iframe.contentDocument && iframe.contentDocument.body ) {
					newHeight = iframe.contentDocument.body.scrollHeight;
				} else if ( iframe.contentWindow && iframe.contentWindow.document && iframe.contentWindow.document.body ) {
					newHeight = iframe.contentWindow.document.body.scrollHeight;
				}
			} catch ( e ) {
				console.log( 'Could not access iframe content document:', e );
				// This is expected in some cases due to same-origin policy
			}

			// Add a small buffer to prevent scrollbars
			newHeight += 30;

			// Update height state if it changed
			if ( newHeight !== iframeHeight ) {
				setIframeHeight( newHeight );
			}
		} catch ( e ) {
			console.error( 'Error adjusting iframe height:', e );
		}
	};

	// Set up iframe load handler
	useEffect( () => {
		if ( iframeRef.current ) {
			iframeRef.current.addEventListener( 'load', handleIframeLoad );

			return () => {
				if ( iframeRef.current ) {
					iframeRef.current.removeEventListener( 'load', handleIframeLoad );
				}
			};
		}
	}, [ embedHtml ] );

	/**
	 * Handle embed toggle changes.
	 *
	 * @param {boolean} value - New embed toggle value.
	 */
	const onEmbedPostChange = ( value ) => {
		setAttributes( { embedPost: value } );
		// Explicitly setting this value implies an intent towards embedding the post.
		setOptimisticEmbed( value );
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

				{ isValidEmbed && attr.embedPost && embedHtml && (
					<div className="activitypub-embed-container">
						{ isRealOembed ? (
							<WpEmbedPreview
								html={ embedHtml }
								onSelectBlock={ focusInput}
							/>
						) : (
							<div
								ref={ iframeContainerRef }
								style={{
									height: iframeHeight + 'px',
									overflow: 'hidden',
									transition: 'height 0.2s ease-in-out',
									position: 'relative',
								}}
							>
								<iframe
									ref={ iframeRef }
									srcDoc={ getEnhancedHtml( embedHtml ) }
									style={{
										position: 'absolute',
										top: 0,
										left: 0,
										width: '100%',
										height: '100%',
									}}
									allowFullScreen
									title={ __( 'Embedded content from', 'activitypub' ) + ' ' + url }
								></iframe>
								<EmbedOverlay onClick={ focusInput } />
							</div>
						) }
					</div>
				) }

				{ url && ! attr.embedPost && (
					<div
						className="activitypub-reply-block-editor__preview"
						contentEditable={ false }
						onClick={ focusInput }
						style={ { cursor: 'pointer' } }
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
