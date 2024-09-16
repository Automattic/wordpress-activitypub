import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';
import { useSelect } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';
import { SelectControl, PanelBody } from '@wordpress/components';
import { useUserOptions } from '../shared/use-user-options';
import FollowMe from './follow-me';
import { useEffect } from '@wordpress/element';
import { InheritModeBlockFallback } from '../shared/inherit-block-fallback';


export default function Edit( {
	attributes,
	setAttributes,
	context: { postType, postId },
} ) {
	const blockProps = useBlockProps( {
		className: 'activitypub-follow-me-block-wrapper',
	} );
	const usersOptions = useUserOptions( { withInherit: true } );
	const { selectedUser } = attributes;
	const isInheritMode = selectedUser === 'inherit';

	const authorId = useSelect(
		( select ) => {
			const { getEditedEntityRecord } = select( coreStore );
			const _authorId = getEditedEntityRecord(
				'postType',
				postType,
				postId
			)?.author;

			return _authorId ?? null;
		},
		[ postType, postId ]
	);

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
			{ isInheritMode ?
					authorId ? (
						<FollowMe { ...attributes } id={ blockProps.id } selectedUser={ authorId } />
					) : (
						<InheritModeBlockFallback name={ __( 'Follow Me', 'activitypub' ) } />
					)
			 : (
				<FollowMe { ...attributes } id={ blockProps.id } />
			) }
		</div>
	);
}
