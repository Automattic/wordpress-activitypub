import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { TextControl, PanelBody, ToggleControl, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useEffect, useState, useRef, useCallback } from '@wordpress/element';
import { useDebounce } from '@wordpress/compose';
import { useDispatch } from '@wordpress/data';
import apiFetch from '@wordpress/api-fetch';
import { addQueryArgs } from '@wordpress/url';

// Map HTML attribute names to React prop names
const attributeMap = {
	class: 'className',
	frameborder: 'frameBorder',
	allowfullscreen: 'allowFullScreen',
	allowtransparency: 'allowTransparency',
	marginwidth: 'marginWidth',
	marginheight: 'marginHeight',
	scrolling: 'scrolling',
	srcdoc: 'srcDoc',
};

/**
 * Overlay component for embeds to handle click events.
 *
 * @param {Object} props Component props.
 * @param {Function} props.onClick Function to call when the overlay is clicked.
 * @return {JSX.Element} The component.
 */
function EmbedOverlay( { onClick } ) {
	return (
		<div
			onClick={ onClick }
			style={ {
				position: 'absolute',
				top: 0,
				left: 0,
				width: '100%',
				height: '100%',
				cursor: 'pointer',
				zIndex: 1,
			} }
		/>
	);
}

/**
 * WordPress Embed Preview component for displaying WordPress embedded content.
 *
 * @param {Object} props Component props.
 * @param {string} props.html The HTML content to embed.
 * @param {Function} props.onClick Function to call when the embed is clicked.
 * @return {JSX.Element} The component.
 */
function WpEmbedPreview( { html, onClick } ) {
	const ref = useRef();
	const [ height, setHeight ] = useState( 282 ); // Default WordPress embed height

	// Parse iframe attributes from the HTML
	const iframeProps = useCallback( () => {
		const doc = new window.DOMParser().parseFromString( html, 'text/html' );
		const iframe = doc.querySelector( 'iframe' );
		const props = {};

		if ( ! iframe ) {
			return props;
		}

		Array.from( iframe.attributes ).forEach( ( { name, value } ) => {
			if ( name === 'style' ) {
				return;
			}

			// Convert attribute names to React prop format
			const propName = attributeMap[ name ] || name;

			// Handle boolean attributes
			if ( value === '' || value === 'true' ) {
				props[ propName ] = true;
			} else if ( value === 'false' ) {
				props[ propName ] = false;
			} else {
				props[ propName ] = value;
			}
		} );

		return props;
	}, [ html ] )();

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
			<div className="wp-block-embed__wrapper" style={ { position: 'relative' } }>
				<div dangerouslySetInnerHTML={ { __html: html } } />
				<EmbedOverlay onClick={ onClick } />
			</div>
		);
	}

	return (
		<div className="wp-block-embed__wrapper" style={ { position: 'relative' } }>
			<iframe
				ref={ ref }
				title={ iframeProps.title || __( 'Embedded WordPress content', 'activitypub' ) }
				{ ...iframeProps }
				height={ height }
				style={ {
					width: '100%',
					maxWidth: '100%',
				} }
			/>
			<EmbedOverlay onClick={ onClick } />
		</div>
	);
}

/**
 * Generic Embed Preview component for displaying embedded content.
 *
 * @param {Object} props Component props.
 * @param {string} props.html The HTML content to embed.
 * @param {Function} props.onClick Function to call when the embed is clicked.
 * @return {JSX.Element} The component.
 */
function EmbedPreview( { html, onClick } ) {
	const iframeRef = useRef( null );
	const [ iframeHeight, setIframeHeight ] = useState( 300 );

	// Check if this is a WordPress embed (contains iframe with wp-embedded-content)
	const isWordPressEmbed = html && html.includes( 'wp-embedded-content' );

	// If this is a WordPress embed, use the specialized WordPress embed preview
	if ( isWordPressEmbed ) {
		return <WpEmbedPreview html={ html } onClick={ onClick } />;
	}

	// Create a sandboxed document with the HTML content
	const sandboxedContent = `
		<!DOCTYPE html>
		<html>
		<head>
			<meta charset="UTF-8">
			<meta name="viewport" content="width=device-width, initial-scale=1.0">
			<style>
				body { margin: 0; padding: 0; overflow-x: hidden; }
				img { max-width: 100%; height: auto; }
				a { color: #2271b1; text-decoration: underline; }
			</style>
		</head>
		<body>
			${ html }
		</body>
		</html>
	`;

	// Function to adjust iframe height based on content
	const adjustIframeHeight = useCallback( () => {
		if ( ! iframeRef.current ) return;

		try {
			const iframe = iframeRef.current;
			let newHeight = 300; // Default fallback height

			try {
				// Try to get the scrollHeight of the body
				if ( iframe.contentDocument && iframe.contentDocument.body ) {
					newHeight = iframe.contentDocument.body.scrollHeight + 5; // Add small buffer
				} else if (
					iframe.contentWindow &&
					iframe.contentWindow.document &&
					iframe.contentWindow.document.body
				) {
					newHeight = iframe.contentWindow.document.body.scrollHeight + 5;
				}
			} catch ( e ) {
				// This is expected in some cases due to same-origin policy
				console.log( 'Could not access iframe content document:', e );
			}

			setIframeHeight( newHeight );
		} catch ( e ) {
			console.error( 'Error adjusting iframe height:', e );
		}
	}, [] );

	// Set up iframe load handler and interval for height adjustments
	useEffect( () => {
		if ( ! iframeRef.current ) return;

		// Initial load handler
		const handleLoad = () => adjustIframeHeight();
		iframeRef.current.addEventListener( 'load', handleLoad );

		// Set up interval for periodic height checks
		const intervalId = setInterval( adjustIframeHeight, 1000 );

		// Initial height adjustment after render
		const initialAdjustment = setTimeout( adjustIframeHeight, 100 );

		return () => {
			clearInterval( intervalId );
			clearTimeout( initialAdjustment );
			iframeRef.current?.removeEventListener( 'load', handleLoad );
		};
	}, [ adjustIframeHeight, html ] );

	return (
		<div className="wp-block-embed__wrapper" style={ { position: 'relative' } }>
			<iframe
				ref={ iframeRef }
				srcDoc={ sandboxedContent }
				sandbox="allow-scripts allow-same-origin allow-popups allow-forms"
				style={ {
					width: '100%',
					height: `${ iframeHeight }px`,
					border: 'none',
					overflow: 'hidden',
				} }
			/>
			<EmbedOverlay onClick={ onClick } />
		</div>
	);
}

/**
 * Help text messages for different reply states.
 */
const HELP_TEXT = {
	default: __(
		'Enter the URL of a post from the Fediverse (Mastodon, Pixelfed, etc.) that you want to reply to.',
		'activitypub'
	),
	checking: () => (
		<>
			<Spinner />
			{ ' ' + __( 'Checking URL...', 'activitypub' ) }
		</>
	),
	valid: __( 'The author will be notified of your response.', 'activitypub' ),
	error: __( "This URL probably won't receive your reply. We'll still try.", 'activitypub' ),
};

/**
 * Edit component for the ActivityPub Reply block.
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
	const [ helpText, setHelpText ] = useState( HELP_TEXT.default );
	const [ isCheckingEmbed, setIsCheckingEmbed ] = useState( false );
	const [ embedHtml, setEmbedHtml ] = useState( null );
	const urlInputRef = useRef();
	const { removeBlock } = useDispatch( 'core/block-editor' );
	const blockProps = useBlockProps();

	const focusInput = () => {
		setTimeout( () => urlInputRef.current?.focus(), 50 );
	};

	// Check URL when it changes
	const checkUrl = async ( urlToCheck ) => {
		if ( ! urlToCheck ) {
			setHelpText( HELP_TEXT.default );
			setEmbedHtml( null );
			return;
		}

		try {
			setIsCheckingEmbed( true );
			setHelpText( HELP_TEXT.checking() );

			// Simple URL validation
			new URL( urlToCheck ); // Will throw if invalid

			// Fetch the embed HTML directly using the WordPress oEmbed API
			try {
				const response = await apiFetch( {
					path: addQueryArgs( '/oembed/1.0/proxy', {
						url: urlToCheck,
					} ),
				} );

				if ( response && response.html ) {
					setEmbedHtml( response.html );
					// Auto-enable embedding when we get valid HTML
					if ( ! attr.embedPost ) {
						setAttributes( { embedPost: true } );
					}
				}
			} catch ( embedError ) {
				console.log( 'Could not fetch embed:', embedError );
				// We'll still allow the reply even if embedding fails
			}

			setHelpText( HELP_TEXT.valid );
		} catch ( error ) {
			setHelpText( HELP_TEXT.error );
			setEmbedHtml( null );
		} finally {
			setIsCheckingEmbed( false );
		}
	};

	// Debounce the URL check to avoid too many requests
	const debouncedCheckUrl = useDebounce( checkUrl, 250 );

	// Check URL when it changes
	useEffect( () => {
		if ( url ) {
			debouncedCheckUrl( url );
		} else {
			setEmbedHtml( null );
		}
	}, [ url ] );

	/**
	 * Handle embed toggle changes.
	 *
	 * @param {boolean} value - New embed toggle value.
	 */
	const onEmbedPostChange = ( value ) => {
		setAttributes( { embedPost: value } );
	};

	const onKeyDown = ( event ) => {
		if ( event.key === 'Enter' ) {
			const { insertAfterBlock } = useDispatch( 'core/block-editor' );
			insertAfterBlock( clientId );
		}
		if ( ! attr.url && [ 'Backspace', 'Delete' ].includes( event.key ) ) {
			removeBlock( clientId );
		}
	};

	// Show embed in both selected and non-selected states when embedPost is true
	const showEmbed = attr.embedPost && embedHtml && ! isCheckingEmbed;

	// Show link preview when not showing embed or when block is selected
	const showLinkPreview = ! showEmbed || isSelected;

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Settings', 'activitypub' ) }>
					<ToggleControl
						label={ __( 'Embed Post', 'activitypub' ) }
						checked={ attr.embedPost }
						onChange={ onEmbedPostChange }
						help={ __( 'Show embedded content from the URL.', 'activitypub' ) }
						disabled={ ! embedHtml }
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

				{ showEmbed && (
					<div className="activitypub-embed-container">
						<EmbedPreview html={ embedHtml } onClick={ focusInput } />
					</div>
				) }

				{ url && showLinkPreview && ! isSelected && (
					<div
						className="activitypub-reply-block-editor__preview"
						contentEditable={ false }
						onClick={ focusInput }
						style={ { cursor: 'pointer' } }
					>
						<a href={ url } className="u-in-reply-to" target="_blank" rel="noreferrer">
							{ '↬' + url.replace( /^https?:\/\//, '' ) }
						</a>
					</div>
				) }
			</div>
		</>
	);
}
