import { SelectControl, RangeControl, PanelBody, TextControl } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { useMemo, useState } from '@wordpress/element';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';
import { Followers } from './followers';

const enabled = window._activityPubOptions?.enabled;

function useUserOptions() {
	const users = enabled?.users ? useSelect( ( select ) => select( 'core' ).getUsers( { who: 'authors' } ) ) : [];
	return useMemo( () => {
		if ( ! users ) {
			return [];
		}
		const withBlogUser = enabled?.site ? [ {
			label: __( 'Whole Site', 'activitypub' ),
			value: 'site'
		} ] : [];
		return users.reduce( ( acc, user ) => {
			acc.push({
				label: user.name,
				value: user.id
			} );
			return acc;
		}, withBlogUser );
	}, [ users ] );
}

export default function Edit( { attributes, setAttributes } ) {
	const { order, per_page, selectedUser, title } = attributes;
	const blockProps = useBlockProps();
	const [ page, setPage ] = useState( 1 );
	const orderOptions = [
		{ label: __( 'New to old', 'activitypub' ), value: 'desc' },
		{ label: __( 'Old to new', 'activitypub' ), value: 'asc' },
	];
	const usersOptions = useUserOptions();
	const setAttributestAndResetPage = ( key ) => {
		return ( value ) => {
			setPage( 1 );
			setAttributes( { [ key ]: value } );
		};
	}

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
					<SelectControl
						label= { __( 'Select User', 'activitypub' ) }
						value={ selectedUser }
						options={ usersOptions }
						onChange={ setAttributestAndResetPage( 'selectedUser' ) }
					/>
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
			<Followers { ...attributes } page={ page } setPage={ setPage } followLinks={ false } />
		</div>
	);
}