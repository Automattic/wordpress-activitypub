import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';
import { SelectControl, PanelBody } from '@wordpress/components';
import { useUserOptions } from '../shared/use-user-options';
import FollowMe from './follow-me';

export default function Edit( { attributes, setAttributes } ) {
	const blockProps = useBlockProps();
	const usersOptions = useUserOptions();

	return (
		<div { ...blockProps }>
			<InspectorControls key="setting">
				<PanelBody title={ __( 'Followers Options', 'activitypub' ) }>
					<SelectControl
						label= { __( 'Select User', 'activitypub' ) }
						value={ attributes.selectedUser }
						options={ usersOptions }
						onChange={ ( value ) => setAttributes( { selectedUser: value } ) }
					/>
				</PanelBody>
			</InspectorControls>
			<FollowMe { ...attributes } id={ blockProps.id } />
		</div>
	);
}
