import { __ } from '@wordpress/i18n';
import { useSelect } from '@wordpress/data';
import { useMemo, useState, useEffect } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { useOptions } from './use-options';

/**
 * React hook providing user options for ActivityPub blocks.
 *
 * @param {Object} params
 * @param {boolean} params.withInherit - Whether to include the inherit option.
 * @returns {Array} List of user option objects.
 */
export function useUserOptions( { withInherit = false } ) {
	/**
	 * ActivityPub options.
	 *
	 * @type {Object}
	 * @property {boolean} enabled.users - Whether users are enabled.
	 * @property {boolean} enabled.blog - Whether the blog user is enabled.
	 */
	const { enabled, namespace } = useOptions();
	const fetchedUsers = enabled?.users
		? useSelect( ( select ) => select( 'core' ).getUsers( { capabilities: 'activitypub' } ), [] )
		: [];
	const [ currentUserCanActivityPub, setCurrentUserCanActivityPub ] = useState( false );

	// Only fetch current user and test capability if fetchedUsers failed
	const currentUser = fetchedUsers === null ? useSelect( ( select ) => select( 'core' ).getCurrentUser(), [] ) : null;

	// Test if current user has activitypub capability by trying to access their actor endpoint.
	useEffect( () => {
		if ( fetchedUsers !== null || ! currentUser || ! namespace ) {
			return;
		}

		apiFetch( {
			path: `/${ namespace }/actors/${ currentUser.id }`,
			method: 'HEAD',
			headers: { Accept: 'application/activity+json' },
			parse: false,
		} )
			.then( () => setCurrentUserCanActivityPub( true ) )
			.catch( () => setCurrentUserCanActivityPub( false ) );
	}, [ fetchedUsers, currentUser?.id, namespace ] );

	const users =
		fetchedUsers ||
		( currentUser && currentUserCanActivityPub ? [ { id: currentUser.id, name: currentUser.name } ] : [] );

	/**
	 * Memoized computation of user options for block settings.
	 */
	return useMemo( () => {
		if ( ! users ) {
			return [];
		}
		const userKeywords = [];

		if ( enabled?.blog && fetchedUsers ) {
			userKeywords.push( {
				label: __( 'Blog', 'activitypub' ),
				value: 'blog',
			} );
		}

		// Only show the inherit option when explicitly asked for and users are enabled.
		if ( withInherit && enabled?.users && fetchedUsers ) {
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
