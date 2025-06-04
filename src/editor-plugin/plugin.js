import { PluginDocumentSettingPanel, PluginPreviewMenuItem } from '@wordpress/editor';
import { PluginDocumentSettingPanel as DocumentSettingPanel } from '@wordpress/edit-post';
import { registerPlugin } from '@wordpress/plugins';
import { TextControl, RadioControl, RangeControl, __experimentalText as Text, Tooltip } from '@wordpress/components';
import { Icon, globe, people, external } from '@wordpress/icons';
import { useSelect, select } from '@wordpress/data';
import { useEntityProp } from '@wordpress/core-data';
import { addQueryArgs } from '@wordpress/url';
import { __ } from '@wordpress/i18n';
import { SVG, Path } from '@wordpress/primitives';
import { useOptions } from '../shared/use-options';

// Defining our own because it's too new in @wordpress/icons
// https://github.com/WordPress/gutenberg/blob/trunk/packages/icons/src/library/not-allowed.js
const notAllowed = (
	<SVG xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
		<Path
			fillRule="evenodd"
			clipRule="evenodd"
			d="M12 18.5A6.5 6.5 0 0 1 6.93 7.931l9.139 9.138A6.473 6.473 0 0 1 12 18.5Zm5.123-2.498a6.5 6.5 0 0 0-9.124-9.124l9.124 9.124ZM4 12a8 8 0 1 1 16 0 8 8 0 0 1-16 0Z"
		/>
	</SVG>
);

/**
 * Editor plugin for ActivityPub settings in the block editor.
 *
 * @returns {JSX.Element|null} The settings panel for ActivityPub or null for sync blocks.
 */
const EditorPlugin = () => {
	const postType = useSelect( ( select ) => select( 'core/editor' ).getCurrentPostType(), [] );
	const [ meta, setMeta ] = useEntityProp( 'postType', postType || 'default', 'meta' );

	// Don't show when editing sync blocks.
	if ( 'wp_block' === postType ) {
		return null;
	}

	const { maxImageAttachments = 4 } = useOptions();
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

	/*
	 * Backwards compatibility with WordPress 6.5.
	 * @todo Remove when 6.5 is no longer supported.
	 */
	const SettingsPanel = PluginDocumentSettingPanel || DocumentSettingPanel;

	return (
		<SettingsPanel
			name="activitypub"
			className="block-editor-block-inspector"
			title={ __( 'Fediverse ⁂', 'activitypub' ) }
		>
			<TextControl
				label={ __( 'Content Warning', 'activitypub' ) }
				value={ meta?.activitypub_content_warning }
				onChange={ ( value ) => {
					setMeta( { ...meta, activitypub_content_warning: value } );
				} }
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
				value={ meta?.activitypub_max_image_attachments ?? maxImageAttachments }
				onChange={ ( value ) => {
					setMeta( { ...meta, activitypub_max_image_attachments: value } );
				} }
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
				selected={ meta?.activitypub_content_visibility || 'public' }
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
				onChange={ ( value ) => {
					setMeta( { ...meta, activitypub_content_visibility: value } );
				} }
				className="activitypub-visibility"
			/>
		</SettingsPanel>
	);
};

/**
 * Opens the Fediverse preview for the current post in a new tab.
 */
function onActivityPubPreview() {
	const previewLink = select( 'core/editor' ).getEditedPostPreviewLink();
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
	const post_status = useSelect( ( select ) => select( 'core/editor' ).getCurrentPost().status );

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
