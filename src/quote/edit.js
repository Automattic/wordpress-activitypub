import { useBlockProps, InspectorControls, useInnerBlocksProps } from '@wordpress/block-editor';
import { TextControl, PanelBody, ToggleControl, Spinner, Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useCallback, useEffect, useState, useRef } from '@wordpress/element';
import { useDebounce } from '@wordpress/compose';
import { useDispatch, useSelect } from '@wordpress/data';
import apiFetch from '@wordpress/api-fetch';
import { addQueryArgs } from '@wordpress/url';
import { createBlock } from '@wordpress/blocks';

/**
 * Help text messages for different quote states.
 */
const HELP_TEXT = {
	default: __(
		'Enter the URL of a post from the Fediverse (Mastodon, Pixelfed, etc.) that you want to quote.',
		'activitypub'
	),
	checking: () => (
		<>
			<Spinner />
			{ ' ' + __( 'Checking URL…', 'activitypub' ) }
		</>
	),
	valid: __( 'The author will be asked for permission. The quote shows as verified once they agree.', 'activitypub' ),
	error: __( 'This site doesn’t have ActivityPub enabled and can’t be quoted.', 'activitypub' ),
};

/**
 * Edit component for the ActivityPub Quote block.
 *
 * @param {Object}   props               Component props.
 * @param {Object}   props.attributes    Block attributes.
 * @param {Function} props.setAttributes Function to update block attributes.
 * @param {string}   props.clientId      Block client ID.
 * @param {boolean}  props.isSelected    Whether the block is selected.
 */
export default function Edit( { attributes, setAttributes, clientId, isSelected } ) {
	const { url = '', embedPost = false } = attributes;
	const [ helpText, setHelpText ] = useState( HELP_TEXT.default );
	const [ isValidEmbed, setIsValidEmbed ] = useState( false );
	const [ isCheckingEmbed, setIsCheckingEmbed ] = useState( false );
	const [ quotesDisallowed, setQuotesDisallowed ] = useState( false );
	const quoteState = useSelect(
		( select ) => select( 'core/editor' ).getEditedPostAttribute( 'activitypub_quote' ),
		[]
	);
	const urlInputRef = useRef();
	const { insertAfterBlock, removeBlock, replaceInnerBlocks } = useDispatch( 'core/block-editor' );

	// Show embed in both selected and non-selected states when embedPost is true.
	const showEmbed = embedPost && ! isCheckingEmbed && isValidEmbed;

	// Setup inner blocks.
	const innerBlocksProps = useInnerBlocksProps(
		{ className: 'activitypub-embed-container' },
		{
			allowedBlocks: [ 'core/embed' ],
			template: url && showEmbed ? [ [ 'core/embed', { url } ] ] : [],
			templateLock: 'all',
		}
	);

	// Update inner blocks when URL, embedPost, or isValidEmbed changes.
	useEffect( () => {
		if ( url && showEmbed ) {
			replaceInnerBlocks( clientId, [ createBlock( 'core/embed', { url } ) ] );
		} else {
			// Remove all inner blocks if embedding is disabled or URL is not embeddable.
			replaceInnerBlocks( clientId, [] );
		}
	}, [ url, showEmbed, clientId, replaceInnerBlocks ] );

	// Update help text based on state changes.
	useEffect( () => {
		if ( ! url ) {
			setHelpText( HELP_TEXT.default );
		} else if ( isCheckingEmbed ) {
			setHelpText( HELP_TEXT.checking() );
		} else if ( isValidEmbed ) {
			setHelpText( HELP_TEXT.valid );
		} else {
			setHelpText( HELP_TEXT.error );
		}
	}, [ url, isCheckingEmbed, isValidEmbed ] );

	const focusInput = () => {
		setTimeout( () => urlInputRef.current?.focus(), 50 );
	};

	// Check URL when it changes.
	const checkUrl = useCallback(
		async ( urlToCheck ) => {
			if ( ! urlToCheck ) {
				setIsValidEmbed( false );
				return;
			}

			try {
				setIsCheckingEmbed( true );

				// Simple URL validation.
				new URL( urlToCheck ); // Will throw if invalid.

				try {
					/**
					 * Fetch the embed information using the WordPress oEmbed API.
					 *
					 * @typedef {Object} OEmbedResponse
					 * @property {string} [provider_name] The name of the oEmbed provider.
					 * @property {string} [html]          The HTML content to embed.
					 * @property {string} [title]         The title of the embedded content.
					 * @property {string} [author_name]   The author of the embedded content.
					 * @property {string} [author_url]    The URL of the author.
					 * @property {number} [width]         The width of the embedded content.
					 * @property {number} [height]        The height of the embedded content.
					 * @property {string} [type]          The type of the embedded content (rich, video, photo).
					 */
					const response = await apiFetch( {
						path: addQueryArgs( '/oembed/1.0/proxy', {
							url: urlToCheck,
							activitypub: true,
						} ),
					} );

					if ( response && response.provider_name ) {
						setAttributes( { embedPost: true, isValidActivityPub: true } ); // Auto-enable embedding when we get valid embed info.
						setIsValidEmbed( true );

						// Advisory only (FEP-044f): warn when the author's policy excludes everyone but themselves.
						try {
							const object = await apiFetch( {
								path: '/activitypub/1.0/proxy',
								method: 'POST',
								data: { id: urlToCheck },
							} );
							const canQuote = object?.interactionPolicy?.canQuote || {};
							const automatic = [].concat( canQuote.automaticApproval || [] );
							const manual = [].concat( canQuote.manualApproval || [] );
							const author = object?.attributedTo;
							setQuotesDisallowed(
								automatic.length > 0 && automatic.every( ( a ) => a === author ) && manual.length === 0
							);
						} catch ( error ) {
							setQuotesDisallowed( false );
						}
					} else {
						setAttributes( { isValidActivityPub: false } );
						setIsValidEmbed( false );
					}
				} catch ( error ) {
					// eslint-disable-next-line no-console -- Log error for debugging.
					console.log( 'Could not fetch embed:', error );
					setAttributes( { isValidActivityPub: false } );
					setIsValidEmbed( false );
					setQuotesDisallowed( false );
				}
			} catch ( error ) {
				setAttributes( { isValidActivityPub: false } );
				setIsValidEmbed( false );
				setQuotesDisallowed( false );
			} finally {
				setIsCheckingEmbed( false );
			}
		},
		[ setAttributes, setIsValidEmbed, setIsCheckingEmbed ]
	);

	// Debounce the URL check to avoid too many requests.
	const debouncedCheckUrl = useDebounce( checkUrl, 250 );

	// Check URL when it changes.
	useEffect( () => {
		if ( url ) {
			debouncedCheckUrl( url );
		}
	}, [ url, debouncedCheckUrl ] );

	const onKeyDown = ( event ) => {
		if ( event.key === 'Enter' ) {
			insertAfterBlock( clientId );
		}
		if ( ! url && [ 'Backspace', 'Delete' ].includes( event.key ) ) {
			removeBlock( clientId );
		}
	};

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Settings', 'activitypub' ) }>
					<ToggleControl
						label={ __( 'Embed Post', 'activitypub' ) }
						checked={ !! embedPost }
						onChange={ ( value ) => setAttributes( { embedPost: value } ) }
						disabled={ ! isValidEmbed }
						help={ __( 'Show embedded content from the URL.', 'activitypub' ) }
					/>
				</PanelBody>
			</InspectorControls>

			{ /* eslint-disable-next-line jsx-a11y/click-events-have-key-events, jsx-a11y/no-static-element-interactions -- Block wrapper, keyboard handled by inner TextControl */ }
			<div onClick={ focusInput } { ...useBlockProps() }>
				{ quotesDisallowed && (
					<Notice status="warning" isDismissible={ false }>
						{ __(
							'The author of this post does not allow quotes. You can still publish, but the quote will show as unverified.',
							'activitypub'
						) }
					</Notice>
				) }
				{ quoteState?.rejected && (
					<Notice status="error" isDismissible={ false }>
						{ __( 'The author declined this quote. It is shown as a link only.', 'activitypub' ) }
					</Notice>
				) }
				{ quoteState?.authorization && ! quoteState?.rejected && (
					<Notice status="success" isDismissible={ false }>
						{ __( 'The author approved this quote.', 'activitypub' ) }
					</Notice>
				) }
				{ isSelected && (
					<TextControl
						label={ __( 'Your post quotes the following URL', 'activitypub' ) }
						value={ url }
						onChange={ ( value ) => setAttributes( { url: value } ) }
						help={ helpText }
						onKeyDown={ onKeyDown }
						ref={ urlInputRef }
						__next40pxDefaultSize
					/>
				) }

				{ showEmbed && <div { ...innerBlocksProps } /> }

				{ url && ! showEmbed && ! isSelected && (
					/* eslint-disable-next-line jsx-a11y/click-events-have-key-events, jsx-a11y/no-static-element-interactions -- Preview wrapper, clicking focuses input */
					<div
						className="activitypub-quote-block-editor__preview"
						contentEditable={ false }
						onClick={ focusInput }
						style={ { cursor: 'pointer' } }
					>
						<a href={ url } className="u-quotation-of" target="_blank" rel="noreferrer">
							{ '❝' + url.replace( /^https?:\/\//, '' ) }
						</a>
					</div>
				) }
			</div>
		</>
	);
}
