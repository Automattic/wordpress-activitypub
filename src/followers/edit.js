import { SelectControl, RangeControl, PanelBody } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { useMemo } from '@wordpress/element';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';
import { Followers } from './followers';

export default function Edit( { attributes, setAttributes } ) {
	const { selectedUser, followersToShow, sort } = attributes;
	const blockProps = useBlockProps();
	const sortOptions = [
		{ label: __( 'New to old', 'activitypub' ), value: 'DESC' },
		{ label: __( 'Old to new', 'activitypub' ), value: 'ASC' },
	];
	const users = useSelect( ( select ) => select( 'core' ).getUsers( { who: 'authors' } ) );
	const usersOptions = useMemo( () => {
		if ( ! users ) {
			return [];
		}
		const withBlogUser =[ {
			label: 'Whole Site',
			value: 'site'
		} ];
		return users.reduce( ( acc, user ) => {
			acc.push({
				label: user.name,
				value: user.id
			} );
			return acc;
		}, withBlogUser );
	}, [ users ] );

	return (
		<div { ...blockProps }>
			<InspectorControls key="setting">
				<PanelBody title={ __( 'Followers Options', 'activitypub' ) }>
					<SelectControl
						label="Select User"
						value={ selectedUser }
						options={ usersOptions }
						onChange={ value => setAttributes( { selectedUser: value } ) }
					/>
					<SelectControl
						label={ __( 'Sort', 'activitypub' ) }
						value={ sort }
						options={ sortOptions }
						onChange={ value => setAttributes( { sort: value } ) }
					/>
					<RangeControl
						label={ __( 'Number of Followers', 'activitypub' ) }
						value={ followersToShow }
						onChange={ value => setAttributes( { followersToShow: value } ) }
						min={ 1 }
						max={ 10 }
					/>
				</PanelBody>
			</InspectorControls>
			<Followers { ...attributes } />
		</div>
	);
}