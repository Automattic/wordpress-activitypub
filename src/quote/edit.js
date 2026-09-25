import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { TextControl, PanelBody, ToggleControl, Spinner, Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useCallback, useState } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import apiFetch from '@wordpress/api-fetch';
import { useEmbedUrl } from '../shared/use-embed-url';

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
 * Whether the quoted author's interaction policy excludes everyone but themselves.
 *
 * Advisory only (FEP-044f): the policy must not be used for verification, it only
 * tells the writer up front that the request is unlikely to be accepted.
 *
 * @param {string} url The quoted URL.
 *
 * @return {Promise<boolean>} Whether quotes are disallowed.
 */
async function quotesDisallowedFor( url ) {
	try {
		const object = await apiFetch( {
			path: '/activitypub/1.0/proxy',
			method: 'POST',
			data: { id: url },
		} );
		const canQuote = object?.interactionPolicy?.canQuote;
		if ( ! canQuote ) {
			return false;
		}

		const author = [].concat( object?.attributedTo || [] ).map( ( a ) => a?.id ?? a );
		const automatic = [].concat( canQuote.automaticApproval || [] );
		const manual = [].concat( canQuote.manualApproval || [] );

		return manual.length === 0 && automatic.every( ( a ) => author.includes( a?.id ?? a ) );
	} catch ( error ) {
		return false;
	}
}

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
	const [ quotesDisallowed, setQuotesDisallowed ] = useState( false );
	const quoteState = useSelect( ( select ) => {
		// The block is insertable in editors that do not register `core/editor`, the widget screen for one.
		const editorStore = select( 'core/editor' );

		return editorStore?.getEditedPostAttribute?.( 'activitypub_quote' );
	}, [] );

	// An answer belongs to the URL it was given for; editing the block to another one starts over.
	const answeredUrl = quoteState?.request && quoteState.request === url;

	// The policy is worth a look only once the URL is known to be an ActivityPub object.
	const onChecked = useCallback( async ( checkedUrl, isStale ) => {
		const disallowed = checkedUrl ? await quotesDisallowedFor( checkedUrl ) : false;

		// The policy lookup is slower than the check around it, so the URL may have moved on.
		if ( ! isStale?.() ) {
			setQuotesDisallowed( disallowed );
		}
	}, [] );

	const { isValidEmbed, isCheckingEmbed, showEmbed, innerBlocksProps, urlInputRef, focusInput, onKeyDown } =
		useEmbedUrl( { url, clientId, embedPost, setAttributes, onChecked } );

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
				{ quotesDisallowed && (
					<Notice status="warning" isDismissible={ false }>
						{ __(
							'The author of this post does not allow quotes. You can still publish, but the quote will show as unverified.',
							'activitypub'
						) }
					</Notice>
				) }
				{ answeredUrl && quoteState?.rejected && (
					<Notice status="error" isDismissible={ false }>
						{ __( 'The author declined this quote. It is shown as a link only.', 'activitypub' ) }
					</Notice>
				) }
				{ answeredUrl && quoteState?.authorization && ! quoteState?.rejected && (
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
