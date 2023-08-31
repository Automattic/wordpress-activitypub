import { __ } from '@wordpress/i18n';
import { useSelect } from '@wordpress/data';
import { useMemo } from '@wordpress/element';
const enabled = window._activityPubOptions?.enabled;

export function useUserOptions() {
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