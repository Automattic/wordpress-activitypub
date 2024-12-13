import { PluginDocumentSettingPanel, PluginPreviewMenuItem } from '@wordpress/editor';
import { registerPlugin } from '@wordpress/plugins';
import { TextControl, RadioControl, CheckboxControl, __experimentalText as Text } from '@wordpress/components';
import { Icon, notAllowed, globe, people, external } from '@wordpress/icons';
import { useSelect, select } from '@wordpress/data';
import { useEntityProp } from '@wordpress/core-data';
import { addQueryArgs } from '@wordpress/url';
import { __ } from '@wordpress/i18n';


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

	// Default to enabled if not set
	const isReactionsEnabled = meta?.activitypub_reactions_enabled !== '0';

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
			<br />
			<CheckboxControl
				label={ __( 'Show federated reactions', 'activitypub' ) }
				checked={ isReactionsEnabled }
				onChange={ ( checked ) => {
					setMeta( { ...meta, activitypub_reactions_enabled: checked ? '1' : '0' } );
				} }
				help={ __( 'When disabled, federated reactions will be hidden for this post.', 'activitypub' ) }
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
