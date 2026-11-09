import { PluginPrePublishPanel, store as editorStore } from '@wordpress/editor';
import { store as blockEditorStore } from '@wordpress/block-editor';
import { store as coreStore } from '@wordpress/core-data';
import { registerPlugin } from '@wordpress/plugins';
import { Button } from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';
import { useEffect, useMemo } from '@wordpress/element';
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
 *
 * @return {React.JSX.Element|null} The pre-publish panel or null.
 */
const PrePublishPanel = () => {
	const { blocks, postFormat } = useSelect( ( selectFn ) => {
		return {
			blocks: selectFn( blockEditorStore ).getBlocks(),
			postFormat: selectFn( editorStore ).getEditedPostAttribute( 'format' ),
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

	return (
		<PluginPrePublishPanel
			title={ [
				__( 'Suggestion:', 'activitypub' ),
				<span className="editor-post-publish-panel__link" key="label">
					{ __( 'Change format', 'activitypub' ) }
				</span>,
			] }
			initialOpen
		>
			<p>{ suggestion.message }</p>
			<Button variant="secondary" onClick={ () => editPost( { format: suggestion.format } ) }>
				{ sprintf(
					/* translators: %s: The suggested post format name (e.g., "Image", "Gallery", "Video"). */
					__( 'Set format to %s', 'activitypub' ),
					FORMAT_LABELS[ suggestion.format ] || suggestion.format
				) }
			</Button>
		</PluginPrePublishPanel>
	);
};

/**
 * Opens core's tags suggestion, which starts closed.
 *
 * Core shows it when the site has tags and the post has none. Core has no API
 * to open it, so the panel is found by its title, the taxonomy's "add new item"
 * label, when the pre-publish sidebar opens. If core's markup changes, it just
 * stays closed.
 *
 * @return {null} Renders nothing.
 */
const TagsSuggestion = () => {
	const { isOpened, addLabel, hasSiteTags } = useSelect( ( selectFn ) => {
		const taxonomy = selectFn( coreStore ).getTaxonomy( 'post_tag' );

		return {
			isOpened: selectFn( editorStore ).isPublishSidebarOpened(),
			addLabel: taxonomy?.labels?.add_new_item,
			/*
			 * Core's own query for its suggestion. Asking for it here loads it with the editor,
			 * and the panel renders once it has an answer, so it is checked again then.
			 */
			hasSiteTags: !! (
				taxonomy && selectFn( coreStore ).getEntityRecords( 'taxonomy', 'post_tag', { per_page: 1 } )?.length
			),
		};
	}, [] );

	useEffect( () => {
		if ( ! isOpened || ! addLabel || ! hasSiteTags ) {
			return;
		}

		// Wait for core's panel, which renders with the sidebar.
		window.requestAnimationFrame( () => {
			const label = Array.from(
				document.querySelectorAll( '.editor-post-publish-panel__prepublish .editor-post-publish-panel__link' )
			).find( ( element ) => element.textContent === addLabel );
			const toggle = label?.closest( 'button' );

			if ( 'false' === toggle?.getAttribute( 'aria-expanded' ) ) {
				toggle.click();
			}
		} );
	}, [ isOpened, addLabel, hasSiteTags ] );

	return null;
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
			/* translators: Appended after the previous sentence. "#hashtags" stands for words written with a leading # in the post content. */
			__( 'You can also write #hashtags in your post.', 'activitypub' );
	}

	// Core fills in the taxonomy name with sprintf(), so a percent sign in the note must stay literal.
	return translation + ' ' + note.replace( /%/g, '%%' );
} );

registerPlugin( 'activitypub-pre-publish', {
	icon: asterism,
	render: () => (
		<>
			<PrePublishPanel />
			<TagsSuggestion />
		</>
	),
} );
