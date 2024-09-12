import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';
import { SelectControl, PanelBody, Card, CardBody } from '@wordpress/components';
import { useUserOptions } from '../shared/use-user-options';
import FollowMe from './follow-me';
import { useEffect, createInterpolateElement } from '@wordpress/element';


export default function Edit( { attributes, setAttributes } ) {
	const blockProps = useBlockProps( {
		className: 'activitypub-follow-me-block-wrapper',
	} );
	const usersOptions = useUserOptions( { withInherit: true } );
	const { selectedUser } = attributes;
	const isInheritMode = selectedUser === 'inherit';

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
			{ usersOptions.length > 1 && (
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
			) }
			{ isInheritMode ? (
				<Card>
					<CardBody>
						{ createInterpolateElement(
							__( 'This <strong>Follow Me</strong> block will adapt to the page it is on, showing the user profile if on a user archive, or the post author on a single post. It will be <strong>empty</strong> on non-author pages.', 'activitypub' ),
							{ strong: <strong /> }
						) }
					</CardBody>
				</Card>
			) : (
				<FollowMe { ...attributes } id={ blockProps.id } />
			) }
		</div>
	);
}
