import { __ } from '@wordpress/i18n';
import { useSelect } from '@wordpress/data';
import { useMemo } from '@wordpress/element';
import { useOptions } from './use-options';

/**
 * React hook providing user options for ActivityPub blocks.
 *
 * @param {Object} params
 * @param {boolean} params.withInherit - Whether to include the inherit option.
 * @returns {Array} List of user option objects.
 */
export function useUserOptions( { withInherit = false } ) {
	const { enabled } = useOptions();
	const users = enabled?.users ? useSelect( ( select ) => select( 'core' ).getUsers( { who: 'authors' } ) ) : [];

	/**
	 * Memoized computation of user options for block settings.
	 */
	return useMemo( () => {
		if ( ! users ) {
			return [];
		}
		const userKeywords = [];

		if ( enabled?.site ) {
			userKeywords.push( {
				label: __( 'Site', 'activitypub' ),
				value: 'site',
			} );
		}

		// Only show the inherit option when explicitly asked for and users are enabled.
		if ( withInherit && enabled?.users ) {
			userKeywords.push( {
				label: __( 'Dynamic User', 'activitypub' ),
				value: 'inherit',
			} );
		}

		/**
		 * Reduce users into keyword/value pairs for options.
		 */
		return users.reduce( ( acc, user ) => {
			acc.push( {
				label: user.name,
				value: `${ user.id }`, // Casting to string because that's how Gutenberg stores the attribute.
			} );
			return acc;
		}, userKeywords );
	}, [ users ] );
}
