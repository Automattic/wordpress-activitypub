import { PluginPrePublishPanel, store as editorStore } from '@wordpress/editor';
import { store as blockEditorStore } from '@wordpress/block-editor';
import { registerPlugin } from '@wordpress/plugins';
import { Button } from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';
import { useMemo } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { getSuggestedPostFormat, FORMAT_LABELS } from './utils';

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
		// Show confirmation when the user has a non-default format applied.
		if ( postFormat && postFormat !== 'standard' ) {
			const formatLabel = FORMAT_LABELS[ postFormat ] || postFormat;
			return (
				<PluginPrePublishPanel title={ __( 'Fediverse ⁂', 'activitypub' ) } initialOpen>
					<p>
						{ sprintf(
							/* translators: %s: The current post format name (e.g., "Image", "Gallery", "Video"). */
							__( 'This post will be shared as %s on the Fediverse.', 'activitypub' ),
							formatLabel
						) }
					</p>
				</PluginPrePublishPanel>
			);
		}

		return null;
	}

	return (
		<PluginPrePublishPanel title={ __( 'Fediverse ⁂', 'activitypub' ) } initialOpen>
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

registerPlugin( 'activitypub-pre-publish', { render: PrePublishPanel } );
