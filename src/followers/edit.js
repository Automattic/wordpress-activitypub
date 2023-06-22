import { SelectControl, RangeControl } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { useMemo } from '@wordpress/element';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';
import { Followers } from './followers';

export default function Edit( { attributes, setAttributes } ) {
	const { selectedUser, followersToShow, title } = attributes;
	const blockProps = useBlockProps();
	const users = useSelect( ( select ) => select( 'core' ).getUsers( { who: 'authors' } ) );
	const usersOptions = useMemo( () => {
		if ( ! users ) {
			return null;
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
				<div id="activitypub-followers-settings">
					<fieldset>
						<legend className="blocks-base-control__label">
							{ __( 'Select User', 'activitypub' ) }
						</legend>
						<SelectControl
							label="Select User"
							value={ selectedUser }
							options={ usersOptions }
							onChange={ value => setAttributes( { selectedUser: value } ) }
						/>
					</fieldset>
					<fieldset>
						<legend className="blocks-base-control__label">
							{ __( 'Number of Followers to Show', 'activitypub' ) }
						</legend>
						<RangeControl
							label="Number of Followers to Show"
							value={ followersToShow }
							onChange={ value => setAttributes( { followersToShow: value } ) }
							min={ 1 }
							max={ 100 }
						/>
					</fieldset>
				</div>
			</InspectorControls>
			<Followers selectedUser={ selectedUser } followersToShow={ followersToShow } title={ title } />
		</div>
	);
}