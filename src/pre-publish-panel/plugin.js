import { PluginPrePublishPanel, store as editorStore } from '@wordpress/editor';
import { store as blockEditorStore } from '@wordpress/block-editor';
import { store as coreStore } from '@wordpress/core-data';
import { registerPlugin } from '@wordpress/plugins';
import { Button } from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';
import { useMemo } from '@wordpress/element';
import { addFilter } from '@wordpress/hooks';
import { __, sprintf } from '@wordpress/i18n';
import { getSuggestedPostFormat, FORMAT_LABELS } from './utils';

/**
 * The asterism, the icon that marks the plugin's panels.
 *
 * @type {React.JSX.Element}
 */
const asterism = (
	<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
		<text x="12" y="18" fontSize="18" textAnchor="middle" fill="currentColor">
			⁂
		</text>
	</svg>
);

/**
 * Pre-publish panel that suggests a post format for better federation.
 *
 * Only renders when the object type setting is 'wordpress-post-format' and
 * the post content suggests a better format than the default Article.
 * A suggestion is left out while core's own post format suggestion shows.
 *
 * @return {React.JSX.Element|null} The pre-publish panel or null.
 */
const PrePublishPanel = () => {
	const { blocks, postFormat, coreSuggestsFormat } = useSelect( ( selectFn ) => {
		const format = selectFn( editorStore ).getEditedPostAttribute( 'format' );
		const coreFormat = selectFn( editorStore ).getSuggestedPostFormat();
		const themeFormats = selectFn( coreStore ).getThemeSupports()?.formats ?? [];

		return {
			blocks: selectFn( blockEditorStore ).getBlocks(),
			postFormat: format,
			// Same check as core's own "Use a post format" suggestion.
			coreSuggestsFormat: !! coreFormat && themeFormats.includes( coreFormat ) && coreFormat !== format,
		};
	}, [] );

	const { editPost } = useDispatch( editorStore );

	const suggestion = useMemo( () => getSuggestedPostFormat( blocks, postFormat ), [ blocks, postFormat ] );

	// Only show when object type is set to post format mapping.
	if ( window._activityPubOptions?.objectType !== 'wordpress-post-format' ) {
		return null;
	}

	if ( ! suggestion ) {
		// Show confirmation when the user has a known non-default format applied.
		if ( postFormat && postFormat !== 'standard' && FORMAT_LABELS[ postFormat ] ) {
			const formatLabel = FORMAT_LABELS[ postFormat ] || postFormat;
			return (
				<PluginPrePublishPanel title={ __( 'Post format', 'activitypub' ) } initialOpen>
					<p>
						{ sprintf(
							/* translators: %s: The current post format name (e.g., "Image", "Gallery", "Video"). */
							__( 'This post will be shared in %s format on the Fediverse.', 'activitypub' ),
							formatLabel
						) }
					</p>
				</PluginPrePublishPanel>
			);
		}

		return null;
	}

	// Core already suggests a post format, a second suggestion would repeat it.
	if ( coreSuggestsFormat ) {
		return null;
	}

	return (
		<PluginPrePublishPanel
			title={ [
				__( 'Suggestion:', 'activitypub' ),
				<span className="editor-post-publish-panel__link" key="label">
					{ __( 'Use a post format', 'activitypub' ) }
				</span>,
			] }
			initialOpen={ false }
		>
			<p>{ suggestion.message }</p>
			<p>
				<Button
					__next40pxDefaultSize
					variant="link"
					onClick={ () => editPost( { format: suggestion.format } ) }
				>
					{ sprintf(
						/* translators: %s: The suggested post format name (e.g., "Image", "Gallery", "Video"). */
						__( 'Apply the "%s" format.', 'activitypub' ),
						FORMAT_LABELS[ suggestion.format ] || suggestion.format
					) }
				</Button>
			</p>
		</PluginPrePublishPanel>
	);
};

/**
 * The text of core's tags suggestion, which gets a note on hashtags.
 *
 * @type {string}
 */
const CORE_TAGS_TEXT =
	'%s help users and search engines navigate your site and find your content. Add a few keywords to describe your post.';

addFilter( 'i18n.gettext_default', 'activitypub/tags-suggestion', ( translation, text ) => {
	if ( CORE_TAGS_TEXT !== text ) {
		return translation;
	}

	let note = __(
		/* translators: Appended to the editor's tag suggestion, after "Tags help users and search engines navigate your site […]". */
		'Tags are also shared as hashtags, which help people on the Fediverse find your post.',
		'activitypub'
	);

	if ( window._activityPubOptions?.useHashtags ) {
		note +=
			' ' +
			/* translators: Appended after the previous sentence. "#hashtag" stands for a word written with a leading # in the post content. */
			__( 'Any #hashtag you write in your post is added as a tag too.', 'activitypub' );
	}

	// Core fills in the taxonomy name with sprintf(), so a percent sign in the note must stay literal.
	return translation + ' ' + note.replace( /%/g, '%%' );
} );

registerPlugin( 'activitypub-pre-publish', {
	icon: asterism,
	render: PrePublishPanel,
} );
