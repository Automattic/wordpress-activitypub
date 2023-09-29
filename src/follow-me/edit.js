import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';
import { SelectControl, PanelBody } from '@wordpress/components';
import { useUserOptions } from '../shared/use-user-options';
import FollowMe from './follow-me';
import { useEffect } from '@wordpress/element';

export default function Edit( { attributes, setAttributes } ) {
	const blockProps = useBlockProps();
	const usersOptions = useUserOptions();
	const { selectedUser } = attributes;

	useEffect( () => {
		// if there are no users yet, do nothing
		if ( ! usersOptions.length ) {
			return;
		}
		// ensure that the selected user is in the list of options, if not, select the first available user
		if ( ! usersOptions.find( ( { value } ) => value === selectedUser ) ) {
			setAttributes( { selectedUser: usersOptions[ 0 ].value } );
		}
	}, [ selectedUser, usersOptions ] );

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
