import { __ } from '@wordpress/i18n';
import { useSelect } from '@wordpress/data';
import { useMemo } from '@wordpress/element';
import { useOptions } from './use-options';

export function useUserOptions( { withInherit = false } ) {
	const { enabled } = useOptions();
	const users = enabled?.users ? useSelect( ( select ) => select( 'core' ).getUsers( { who: 'authors' } ) ) : [];
	return useMemo( () => {
		if ( ! users ) {
			return [];
		}
		const userKeywords = [];

		if ( enabled?.site ) {
			userKeywords.push( {
				label: __( 'Site', 'activitypub' ),
				value: 'site'
			} );
		}

		// Only show inherit option when explicitly asked for and users are enabled.
		if ( withInherit && enabled?.users ) {
			userKeywords.push( {
				label: __( 'Dynamic User', 'activitypub' ),
				value: 'inherit'
			} );
		}

		return users.reduce( ( acc, user ) => {
			acc.push({
				label: user.name,
				value: `${ user.id }` // casting to string because that's how the attribute is stored by Gutenberg
			} );
			return acc;
		}, userKeywords );
	}, [ users ] );
}