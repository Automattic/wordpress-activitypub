import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { TextControl, PanelBody, ToggleControl, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useEmbedUrl } from '../shared/use-embed-url';

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
			{ ' ' + __( 'Checking URL…', 'activitypub' ) }
		</>
	),
	valid: __( 'The author will be notified of your response.', 'activitypub' ),
	error: __( 'This site doesn\u2019t have ActivityPub enabled and won\u2019t receive your reply.', 'activitypub' ),
};

/**
 * Edit component for the ActivityPub Reply block.
 *
 * @param {Object}   props               Component props.
 * @param {Object}   props.attributes    Block attributes.
 * @param {Function} props.setAttributes Function to update block attributes.
 * @param {string}   props.clientId      Block client ID.
 * @param {boolean}  props.isSelected    Whether the block is selected.
 */
export default function Edit( { attributes, setAttributes, clientId, isSelected } ) {
	const { url = '', embedPost = false } = attributes;
	const { isValidEmbed, isCheckingEmbed, showEmbed, innerBlocksProps, urlInputRef, focusInput, onKeyDown } =
		useEmbedUrl( { url, clientId, embedPost, setAttributes } );

	let helpText = HELP_TEXT.default;
	if ( url && isCheckingEmbed ) {
		helpText = HELP_TEXT.checking();
	} else if ( url ) {
		helpText = isValidEmbed ? HELP_TEXT.valid : HELP_TEXT.error;
	}

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
				{ isSelected && (
					<TextControl
						label={ __( 'Your post is a reply to the following URL', 'activitypub' ) }
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
