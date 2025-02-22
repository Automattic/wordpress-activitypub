import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { TextControl, PanelBody, ToggleControl, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useEffect, useState } from '@wordpress/element';
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

// Help text messages for different states.
const HELP_TEXT = {
	default: __( 'Enter the URL of a post from the Fediverse (Mastodon, Pixelfed, etc.) that you want to reply to. Your reply will be sent to their inbox when published.', 'activitypub' ),
	checking: __( 'Checking if this URL supports ActivityPub replies...', 'activitypub' ),
	valid: __( 'Great! This URL supports ActivityPub replies. When you publish, the author will be notified of your response. You can also choose to embed the original post below.', 'activitypub' ),
	invalid: __( 'This URL does not appear to support ActivityPub replies.', 'activitypub' ),
	error: __( 'Unable to verify this URL. Please check that it is correct and try again.', 'activitypub' ),
};

export default function Edit( { attributes: attr, setAttributes, clientId, isSelected } ) {
	const { url } = attr;
	const { namespace } = useOptions();

	// State variables for help text, embed validity, and embed checking status.
	const [ helpText, setHelpText ] = useState( HELP_TEXT.default );
	const [ isValidEmbed, setIsValidEmbed ] = useState( false );
	const [ isCheckingEmbed, setIsCheckingEmbed ] = useState( false );
	const [ nullAtTheStart, setNullAtTheStart ] = useState( attr.embedPost === null );
	const [ embedHtml, setEmbedHtml ] = useState( null );
	const { insertAfterBlock, removeBlock } = useDispatch( 'core/block-editor' );
	// Get block props and dispatch functions.
	const blockProps = useBlockProps();

	/**
	 * Check if a URL is an ActivityPub URL.
	 *
	 * @param {string} urlToCheck The URL to check.
	 */
	const checkUrl = async ( urlToCheck ) => {
		// Don't check empty URLs.
		if ( ! urlToCheck ) {
			setIsCheckingEmbed( false );
			setIsValidEmbed( false );
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
			setEmbedHtml( response.html || '' );
			/**
			 * Null at the start means that we're a new block, or an old block from before embeds were added.
			 * In that case, we should set embedPost to true by default when we have a good result.
			 * This will make the choice explicit when editing old posts, as a kind of upgrade, but which will otherwise be left alone.
			 */
			if ( nullAtTheStart ) {
				setAttributes( { embedPost: true } );
			}
			setHelpText( HELP_TEXT.valid );
		} catch ( error ) {
			setIsValidEmbed( false );
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
						help={ helpText }
					/>
				</PanelBody>
			</InspectorControls>

			<div { ...blockProps }>
				{ isSelected && (
					<TextControl
						label={ __( 'This post is a reply to the following URL', 'activitypub' ) }
						value={ url }
						onChange={ ( value ) => setAttributes( { url: value } ) }
						help={ helpText }
						onKeyDown={ onKeyDown }
					/>
				) }

				{ isCheckingEmbed && (
					<div className="activitypub-embed-container activitypub-embed-loading">
						<Spinner />
					</div>
				) }

				{ isValidEmbed && attr.embedPost && embedHtml && (
					<div
						className="activitypub-embed-container"
						contentEditable={ false }
						onFocus={ ( e ) => e.stopPropagation() }
						onClick={ ( e ) => e.stopPropagation() }
						dangerouslySetInnerHTML={{ __html: embedHtml }}
					/>
				) }

				{ url && (
					<div
						className="activitypub-reply-block-editor__preview"
						contentEditable={ false }
						onMouseDown={ ( e ) => e.preventDefault() }
						style={ { pointerEvents: 'none' } }
					>
						<a
							href={ url }
							className="u-in-reply-to"
							target="_blank"
							rel="noreferrer"
						>
							{ url.replace( /^https?:\/\//, '' ) }
						</a>
					</div>
				) }
			</div>
		</>
	);
}
