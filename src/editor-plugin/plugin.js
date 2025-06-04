import { PluginDocumentSettingPanel, PluginPreviewMenuItem, store as editorStore } from '@wordpress/editor';
import { registerPlugin } from '@wordpress/plugins';
import { TextControl, RadioControl, RangeControl, __experimentalText as Text, Tooltip } from '@wordpress/components';
import { Icon, globe, people, external, notAllowed } from '@wordpress/icons';
import { useSelect, select } from '@wordpress/data';
import { useEntityProp } from '@wordpress/core-data';
import { addQueryArgs } from '@wordpress/url';
import { __ } from '@wordpress/i18n';
import { useOptions } from '../shared/use-options';

/**
 * Custom hook to update metadata in the post editor.
 *
 * @param {string} metaKey The key of the metadata to update.
 * @param {string} postType The type of post to update the metadata for.
 * @returns {[string, (value: string) => void]} The current value of the metadata and a function to update it.
 */
function useSetMeta( metaKey, postType ) {
	const [ meta, setMeta ] = useEntityProp( 'postType', postType || 'default', 'meta' );

	const setValue = ( value ) => {
		setMeta( { ...meta, [ metaKey ]: value } );
	};

	return [ meta?.[ metaKey ], setValue ];
}

/**
 * Editor plugin for ActivityPub settings in the block editor.
 *
 * @returns {JSX.Element|null} The settings panel for ActivityPub or null for sync blocks.
 */
const EditorPlugin = () => {
	const postType = useSelect( ( select ) => select( editorStore ).getCurrentPostType(), [] );

	const [ contentWarning, setContentWarning ] = useSetMeta( 'activitypub_content_warning', postType );
	const [ maxImageAttachments, setMaxImageAttachments ] = useSetMeta( 'activitypub_max_image_attachments', postType );
	const [ contentVisibility, setContentVisibility ] = useSetMeta( 'activitypub_content_visibility', postType );

	const handleContentWarningChange = ( value ) => {
		setContentWarning( value );
	};

	const handleMaxImageAttachmentsChange = ( value ) => {
		setMaxImageAttachments( value );
	};

	const handleVisibilityChange = ( value ) => {
		setContentVisibility( value );
	};

	// Don't show when editing sync blocks.
	if ( 'wp_block' === postType ) {
		return null;
	}

	const { maxImageAttachments: defaultMaxImageAttachments = 4 } = useOptions();

	const labelStyling = {
		verticalAlign: 'middle',
		gap: '4px',
		justifyContent: 'start',
		display: 'inline-flex',
		alignItems: 'center',
	};

	/**
	 * Enhances a label with an icon and tooltip.
	 *
	 * @param {JSX.Element} icon    The icon to display.
	 * @param {string}      text    The label text.
	 * @param {string}      tooltip The tooltip text.
	 *
	 * @returns {JSX.Element} The enhanced label component.
	 */
	const enhancedLabel = ( icon, text, tooltip ) => (
		<Tooltip text={ tooltip }>
			<Text style={ labelStyling }>
				<Icon icon={ icon } />
				{ text }
			</Text>
		</Tooltip>
	);

	return (
		<PluginDocumentSettingPanel
			name="activitypub"
			className="block-editor-block-inspector"
			title={ __( 'Fediverse ⁂', 'activitypub' ) }
		>
			<TextControl
				label={ __( 'Content Warning', 'activitypub' ) }
				value={ contentWarning ?? '' }
				onChange={ handleContentWarningChange }
				placeholder={ __( 'Optional content warning', 'activitypub' ) }
				help={ __(
					'Content warnings do not change the content on your site, only in the fediverse.',
					'activitypub'
				) }
				__next40pxDefaultSize
				__nextHasNoMarginBottom
			/>

			<RangeControl
				label={ __( 'Maximum Image Attachments', 'activitypub' ) }
				value={ maxImageAttachments ?? defaultMaxImageAttachments }
				onChange={ handleMaxImageAttachmentsChange }
				min={ 0 }
				max={ 10 }
				help={ __(
					'Maximum number of image attachments to include when sharing to the fediverse.',
					'activitypub'
				) }
				__next40pxDefaultSize
				__nextHasNoMarginBottom
			/>

			<RadioControl
				label={ __( 'Visibility', 'activitypub' ) }
				help={ __(
					"This adjusts the visibility of a post in the fediverse, but note that it won't affect how the post appears on the blog.",
					'activitypub'
				) }
				selected={ contentVisibility || 'public' }
				options={ [
					{
						label: enhancedLabel(
							globe,
							__( 'Public', 'activitypub' ),
							__( 'Post will be visible to everyone and appear in public timelines.', 'activitypub' )
						),
						value: 'public',
					},
					{
						label: enhancedLabel(
							people,
							__( 'Quiet public', 'activitypub' ),
							__(
								'Post will be visible to everyone but will not appear in public timelines.',
								'activitypub'
							)
						),
						value: 'quiet_public',
					},
					{
						label: enhancedLabel(
							notAllowed,
							__( 'Do not federate', 'activitypub' ),
							__( 'Post will not be shared to the Fediverse.', 'activitypub' )
						),
						value: 'local',
					},
				] }
				onChange={ handleVisibilityChange }
				className="activitypub-visibility"
			/>
		</PluginDocumentSettingPanel>
	);
};

/**
 * Opens the Fediverse preview for the current post in a new tab.
 */
function onActivityPubPreview() {
	const previewLink = select( editorStore ).getEditedPostPreviewLink();
	const fediversePreviewLink = addQueryArgs( previewLink, { activitypub: 'true' } );

	window.open( fediversePreviewLink, '_blank' );
}

/**
 * Renders the preview menu item for Fediverse preview.
 *
 * @returns {JSX.Element} The preview menu item component.
 */
const EditorPreview = () => {
	// check if post was saved
	const post_status = useSelect( ( select ) => select( editorStore ).getCurrentPost().status );

	return (
		<>
			{ PluginPreviewMenuItem ? (
				<PluginPreviewMenuItem
					onClick={ () => onActivityPubPreview() }
					icon={ external }
					disabled={ post_status === 'auto-draft' }
				>
					{ __( 'Fediverse preview ⁂', 'activitypub' ) }
				</PluginPreviewMenuItem>
			) : null }
		</>
	);
};

registerPlugin( 'activitypub-editor-plugin', { render: EditorPlugin } );
registerPlugin( 'activitypub-editor-preview', { render: EditorPreview } );
