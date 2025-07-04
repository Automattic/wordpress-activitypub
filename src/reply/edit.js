import { useBlockProps, InspectorControls, useInnerBlocksProps } from '@wordpress/block-editor';
import { TextControl, PanelBody, ToggleControl, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useEffect, useState, useRef } from '@wordpress/element';
import { useDebounce } from '@wordpress/compose';
import { useDispatch } from '@wordpress/data';
import apiFetch from '@wordpress/api-fetch';
import { addQueryArgs } from '@wordpress/url';
import { createBlock } from '@wordpress/blocks';

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
	error: __( 'This URL probably won’t receive your reply. We’ll still try.', 'activitypub' ),
};

/**
 * Edit component for the ActivityPub Reply block.
 *
 * @param {Object} props - Component props.
 * @param {Object} props.attributes - Block attributes.
 * @param {Function} props.setAttributes - Function to update block attributes.
 * @param {string} props.clientId - Block client ID.
 * @param {boolean} props.isSelected - Whether the block is selected.
 */
export default function Edit( { attributes, setAttributes, clientId, isSelected } ) {
	const { url = '', embedPost = false } = attributes;
	const [ helpText, setHelpText ] = useState( HELP_TEXT.default );
	const [ isValidEmbed, setIsValidEmbed ] = useState( false );
	const [ isCheckingEmbed, setIsCheckingEmbed ] = useState( false );
	const urlInputRef = useRef();
	const { insertAfterBlock, removeBlock, replaceInnerBlocks } = useDispatch( 'core/block-editor' );

	// Setup inner blocks.
	const innerBlocksProps = useInnerBlocksProps(
		{ className: 'activitypub-embed-container' },
		{
			allowedBlocks: [ 'core/embed' ],
			template: url && embedPost ? [ [ 'core/embed', { url } ] ] : [],
			templateLock: 'all',
		}
	);

	// Update inner blocks when URL, embedPost, or isValidEmbed changes.
	useEffect( () => {
		if ( url && embedPost && isValidEmbed ) {
			replaceInnerBlocks( clientId, [ createBlock( 'core/embed', { url } ) ] );
		} else {
			// Remove all inner blocks if embedding is disabled or URL is not embeddable.
			replaceInnerBlocks( clientId, [] );
		}
	}, [ url, embedPost, isValidEmbed, clientId, replaceInnerBlocks ] );

	const focusInput = () => {
		setTimeout( () => urlInputRef.current?.focus(), 50 );
	};

	// Check URL when it changes
	const checkUrl = async ( urlToCheck ) => {
		if ( ! urlToCheck ) {
			setHelpText( HELP_TEXT.default );
			setIsValidEmbed( false );
			return;
		}

		try {
			setIsCheckingEmbed( true );
			setHelpText( HELP_TEXT.checking() );

			// Simple URL validation.
			new URL( urlToCheck ); // Will throw if invalid.

			// Fetch the embed information using the WordPress oEmbed API.
			try {
				const response = await apiFetch( {
					path: addQueryArgs( '/oembed/1.0/proxy', {
						url: urlToCheck,
					} ),
				} );

				setIsValidEmbed( !! response );
				setHelpText( HELP_TEXT.valid );

				// Auto-enable embedding when we get valid embed info.
				if ( response && response.provider_name && ! embedPost ) {
					setAttributes( { embedPost: true } );
				}
			} catch ( error ) {
				console.log( 'Could not fetch embed:', error );
				setIsValidEmbed( false );
				setHelpText( HELP_TEXT.error );
				// We'll still allow the reply even if embedding fails.
			}
		} catch ( error ) {
			setIsValidEmbed( false );
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
			insertAfterBlock( clientId );
		}
		if ( ! url && [ 'Backspace', 'Delete' ].includes( event.key ) ) {
			removeBlock( clientId );
		}
	};

	// Show embed in both selected and non-selected states when embedPost is true.
	const showEmbed = embedPost && ! isCheckingEmbed && isValidEmbed;
	const showLinkPreview = ! showEmbed || isSelected;

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Settings', 'activitypub' ) }>
					<ToggleControl
						label={ __( 'Embed Post', 'activitypub' ) }
						checked={ !! embedPost }
						onChange={ onEmbedPostChange }
						disabled={ ! isValidEmbed }
						help={ __( 'Show embedded content from the URL.', 'activitypub' ) }
						__nextHasNoMarginBottom
					/>
				</PanelBody>
			</InspectorControls>

			<div onClick={ focusInput } { ...useBlockProps() }>
				{ isSelected && (
					<TextControl
						label={ __( 'Your post is a reply to the following URL', 'activitypub' ) }
						value={ url }
						onChange={ ( value ) => setAttributes( { url: value } ) }
						help={ helpText }
						onKeyDown={ onKeyDown }
						ref={ urlInputRef }
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>
				) }

				{ showEmbed && <div { ...innerBlocksProps } /> }

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
