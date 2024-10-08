import { PluginDocumentSettingPanel } from '@wordpress/editor';
import { registerPlugin } from '@wordpress/plugins';
import { TextControl, RadioControl, __experimentalText as Text } from '@wordpress/components';
import { Icon, notAllowed, globe, unseen } from '@wordpress/icons';
import { useSelect } from '@wordpress/data';
import { useEntityProp } from '@wordpress/core-data';
import { __ } from '@wordpress/i18n';


const EditorPlugin = () => {
	const postType = useSelect(
		( select ) => select( 'core/editor' ).getCurrentPostType(),
		[]
	);
	const [ meta, setMeta ] = useEntityProp( 'postType', postType, 'meta' );

	const style = {
		verticalAlign: 'middle',
	};

	const labelStyling = {
		verticalAlign: "middle",
		gap: "4px",
		justifyContent:
		"start", display:
		"inline-flex",
		alignItems: "center"
	}

	const labelWithIcon = ( text, icon ) => (
		<Text style={labelStyling}>
			<Icon icon={ icon } />
			{text}
		</Text>
	);

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
			/>
			<RadioControl
				label={ __( 'Visibility', 'activitypub' ) }
				help={ __( 'This adjusts the visibility of a post in the fediverse, but note that it won\'t affect how the post appears on the blog.', 'activitypub' ) }
				selected={ meta.activitypub_content_visibility ? meta.activitypub_content_visibility : 'public' }
				options={ [
					{ label: labelWithIcon( __( 'Public', 'activitypub' ), globe ), value: 'public' },
					{ label: labelWithIcon( __( 'Quiet public', 'activitypub' ), unseen ), value: 'quiet_public' },
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

registerPlugin( 'activitypub-editor-plugin', { render: EditorPlugin } );
