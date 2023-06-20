import { SelectControl, RangeControl } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { useMemo } from '@wordpress/element';

function View( { attributes } ) {

}

export default function Edit( { attributes, setAttributes } ) {
  const { selectedUser, followersToShow, isEditing } = attributes;
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
      <div>
          { usersOptions && <SelectControl
              label="Select User"
              value={ selectedUser }
              options={ usersOptions }
              onChange={ value => setAttributes( { selectedUser: value } ) }
          />
					}
          <RangeControl
              label="Number of Followers to Show"
              value={ followersToShow }
              onChange={ value => setAttributes( { followersToShow: value } ) }
              min={ 1 }
              max={ 100 }
          />
      </div>
  );
}