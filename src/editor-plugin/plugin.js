import { PluginDocumentSettingPanel, PluginPreviewMenuItem } from '@wordpress/editor';
import { registerPlugin } from '@wordpress/plugins';
import { TextControl, RadioControl, CheckboxControl, __experimentalText as Text } from '@wordpress/components';
import { Icon, globe, people, external } from '@wordpress/icons';
import { useSelect, select } from '@wordpress/data';
import { useEntityProp } from '@wordpress/core-data';
import { addQueryArgs } from '@wordpress/url';
import { __ } from '@wordpress/i18n';
import { SVG, Path } from '@wordpress/primitives';

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


const EditorPlugin = () => {
	const postType = useSelect(
		( select ) => select( 'core/editor' ).getCurrentPostType(),
		[]
	);
	const [ meta, setMeta ] = useEntityProp( 'postType', postType, 'meta' );

	const labelStyling = {
		verticalAlign: "middle",
		gap: "4px",
		justifyContent: "start",
		display: "inline-flex",
		alignItems: "center"
	}

	const labelWithIcon = ( text, icon ) => (
		<Text style={labelStyling}>
			<Icon icon={ icon } />
			{text}
		</Text>
	);

	// Don't show when editing sync blocks.
	if ( 'wp_block' === postType ) {
		return null;
	}

	return (
		<PluginDocumentSettingPanel
			name="activitypub"
			title={ __( '⁂ Fediverse', 'activitypub' ) }
		>
			<TextControl
				label={ __( 'Content Warning', 'activitypub' ) }
				value={ meta?.activitypub_content_warning }
				onChange={ ( value ) => {
					setMeta( { ...meta, activitypub_content_warning: value } );
				} }
				placeholder={ __( 'Optional content warning', 'activitypub' ) }
				help={ __( 'Content warnings do not change the content on your site, only in the fediverse.', 'activitypub' ) }
			/>

			<RadioControl
				label={ __( 'Visibility', 'activitypub' ) }
				help={ __( 'This adjusts the visibility of a post in the fediverse, but note that it won\'t affect how the post appears on the blog.', 'activitypub' ) }
				selected={ meta?.activitypub_content_visibility || 'public' }
				options={ [
					{ label: labelWithIcon( __( 'Public', 'activitypub' ), globe ), value: 'public' },
					{ label: labelWithIcon( __( 'Quiet public', 'activitypub' ), people ), value: 'quiet_public' },
					{ label: labelWithIcon( __( 'Do not federate', 'activitypub' ), notAllowed ), value: 'local' },
				] }
				onChange={ ( value ) => {
					setMeta( { ...meta, activitypub_content_visibility: value } );
				} }
				className="activitypub-visibility"
			/>
		</PluginDocumentSettingPanel>
	);
}

function onActivityPubPreview() {
	const previewLink = select( 'core/editor' ).getEditedPostPreviewLink();
	const fediversePreviewLink = addQueryArgs( previewLink, { activitypub: 'true' } );

	window.open( fediversePreviewLink, '_blank' );
}

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
					{ __( '⁂ Fediverse preview', 'activitypub' ) }
				</PluginPreviewMenuItem>
			) : null }
		</>
	);
};

registerPlugin( 'activitypub-editor-plugin', { render: EditorPlugin } );
registerPlugin( 'activitypub-editor-preview', { render: EditorPreview } );
