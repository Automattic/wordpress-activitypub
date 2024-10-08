import { SelectControl, RangeControl, PanelBody, TextControl } from '@wordpress/components';
import { useState, useEffect } from '@wordpress/element';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { useSelect } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';
import { __ } from '@wordpress/i18n';
import { Followers } from './followers';
import { useUserOptions } from '../shared/use-user-options';
import { InheritModeBlockFallback } from '../shared/inherit-block-fallback';

export default function Edit( {
	attributes,
	setAttributes,
	context: { postType, postId },
} ) {
	const { order, per_page, selectedUser, title } = attributes;
	const blockProps = useBlockProps();
	const [ page, setPage ] = useState( 1 );
	const orderOptions = [
		{ label: __( 'New to old', 'activitypub' ), value: 'desc' },
		{ label: __( 'Old to new', 'activitypub' ), value: 'asc' },
	];
	const usersOptions = useUserOptions( { withInherit: true } );
	const setAttributestAndResetPage = ( key ) => {
		return ( value ) => {
			setPage( 1 );
			setAttributes( { [ key ]: value } );
		};
	}
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
			<InspectorControls key="setting">
				<PanelBody title={ __( 'Followers Options', 'activitypub' ) }>
					<TextControl
						label={ __( 'Title', 'activitypub' ) }
						help={ __( 'Title to display above the list of followers. Blank for none.', 'activitypub' ) }
						value={ title }
						onChange={ value => setAttributes( { title: value } ) }
					/>
					{ usersOptions.length > 1 && (
						<SelectControl
							label= { __( 'Select User', 'activitypub' ) }
							value={ selectedUser }
							options={ usersOptions }
							onChange={ setAttributestAndResetPage( 'selectedUser' ) }
						/>
					) }
					<SelectControl
						label={ __( 'Sort', 'activitypub' ) }
						value={ order }
						options={ orderOptions }
						onChange={ setAttributestAndResetPage( 'order' ) }
					/>
					<RangeControl
						label={ __( 'Number of Followers', 'activitypub' ) }
						value={ per_page }
						onChange={ setAttributestAndResetPage( 'per_page' ) }
						min={ 1 }
						max={ 10 }
					/>
				</PanelBody>
			</InspectorControls>
			{ selectedUser === 'inherit' ?
				authorId ? (
					<Followers { ...attributes } page={ page } setPage={ setPage } followLinks={ false } selectedUser={ authorId } />
				) : (
					<InheritModeBlockFallback name={ __( 'Followers', 'activitypub' ) } />
				)
			: (
				<Followers { ...attributes } page={ page } setPage={ setPage } followLinks={ false } />
			) }
		</div>
	);
}