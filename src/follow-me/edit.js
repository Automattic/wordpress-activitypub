import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';
import { useSelect } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';
import { SelectControl, PanelBody, ToggleControl, TextControl } from '@wordpress/components';
import { useUserOptions } from '../shared/use-user-options';
import FollowMe from './follow-me';
import { useEffect } from '@wordpress/element';
import { InheritModeBlockFallback } from '../shared/inherit-block-fallback';

/**
 * Edit component.
 *
 * @param {Object} props Component props.
 * @param {Object} props.attributes Block attributes.
 * @param {Function} props.setAttributes Set block attributes.
 * @param {Object} props.context Block context.
 * @param {string} props.context.postType Post type.
 * @param {number} props.context.postId Post ID.
 * @return {JSX.Element} Edit component.
 */
export default function Edit( {
	attributes,
	setAttributes,
	context: {
		postType,
		postId,
	},
} ) {
	const blockProps = useBlockProps( {
		className: 'activitypub-follow-me-block-wrapper',
	} );
	const usersOptions = useUserOptions( { withInherit: true } );
	const { selectedUser, buttonOnly, buttonText, buttonSize } = attributes;
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
			<InspectorControls key="activitypub-follow-me">
				<PanelBody title={ __( 'Follow Me Options', 'activitypub' ) }>
					{ usersOptions.length > 1 && (
						<SelectControl
							label={ __( 'Select User', 'activitypub' ) }
							value={ attributes.selectedUser }
							options={ usersOptions }
							onChange={ ( value ) => setAttributes( { selectedUser: value } ) }
						/>
					) }
					<ToggleControl
						label={ __( 'Button Only Mode', 'activitypub' ) }
						checked={ buttonOnly }
						onChange={ ( value ) => setAttributes( { buttonOnly: value } ) }
						help={ __( 'Only show the follow button without profile information', 'activitypub' ) }
					/>
					<TextControl
						label={ __( 'Button Text', 'activitypub' ) }
						value={ buttonText }
						onChange={ ( value ) => setAttributes( { buttonText: value } ) }
					/>
					<SelectControl
						label={ __( 'Button Size', 'activitypub' ) }
						value={ buttonSize }
						options={ [
							{ label: __( 'Default', 'activitypub' ), value: 'default' },
							{ label: __( 'Compact', 'activitypub' ), value: 'compact' },
							{ label: __( 'Small', 'activitypub' ), value: 'small' },
						] }
						onChange={ ( value ) => setAttributes( { buttonSize: value } ) }
						help={ __( 'Choose the size of the follow button', 'activitypub' ) }
					/>
				</PanelBody>
			</InspectorControls>
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
