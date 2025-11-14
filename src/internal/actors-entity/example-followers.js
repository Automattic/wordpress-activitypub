/**
 * Example: Using the Actors Entity for Followers
 *
 * This demonstrates how to fetch followers using the actors entity
 * with relationship filtering.
 *
 * NOTE: This is an example/documentation file only.
 *
 * @package Activitypub
 */

/* eslint-disable */

/**
 * WordPress dependencies
 */
import { useEntityRecords } from '@wordpress/core-data';
import { Spinner } from '@wordpress/components';
import { useState } from '@wordpress/element';

/**
 * Example 1: Fetch followers for a user
 *
 * @param {Object} props          Component props
 * @param {number} props.userId   User ID to fetch followers for
 * @return {Element} Component displaying followers
 */
export function UserFollowers( { userId } ) {
	const {
		records: followers,
		isResolving,
		totalItems,
		totalPages,
	} = useEntityRecords( 'activitypub/v1', 'actor', {
		relationship: 'followers',
		user_id: userId,
		per_page: 10,
		page: 1,
		order: 'desc',
	} );

	if ( isResolving ) {
		return <Spinner />;
	}

	return (
		<div>
			<h3>Followers ({ totalItems })</h3>
			{ followers?.length ? (
				<ul>
					{ followers.map( ( follower ) => (
						<li key={ follower.id }>
							{ follower.icon?.url && <img src={ follower.icon.url } alt="" width="32" height="32" /> }
							<strong>{ follower.name }</strong> <span>@{ follower.preferred_username }</span>
						</li>
					) ) }
				</ul>
			) : (
				<p>No followers found.</p>
			) }
		</div>
	);
}

/**
 * Example 2: Followers list with pagination
 *
 * @param {Object} props          Component props
 * @param {number} props.userId   User ID to fetch followers for
 * @return {Element} Component with paginated followers
 */
export function PaginatedFollowers( { userId } ) {
	const [ page, setPage ] = useState( 1 );
	const perPage = 10;

	const {
		records: followers,
		isResolving,
		totalPages,
	} = useEntityRecords( 'activitypub/v1', 'actor', {
		relationship: 'followers',
		user_id: userId,
		per_page: perPage,
		page,
		order: 'desc',
	} );

	if ( isResolving ) {
		return <Spinner />;
	}

	return (
		<div>
			<ul>
				{ followers?.map( ( follower ) => (
					<li key={ follower.id }>
						<a href={ follower.url }>
							{ follower.name } (@{ follower.preferred_username })
						</a>
					</li>
				) ) }
			</ul>

			{ totalPages > 1 && (
				<div className="pagination">
					<button disabled={ page <= 1 } onClick={ () => setPage( page - 1 ) }>
						Previous
					</button>
					<span>
						Page { page } of { totalPages }
					</span>
					<button disabled={ page >= totalPages } onClick={ () => setPage( page + 1 ) }>
						Next
					</button>
				</div>
			) }
		</div>
	);
}

/**
 * Example 3: Followers with search
 *
 * @param {Object} props          Component props
 * @param {number} props.userId   User ID to fetch followers for
 * @return {Element} Component with searchable followers
 */
export function SearchableFollowers( { userId } ) {
	const [ searchTerm, setSearchTerm ] = useState( '' );
	const [ debouncedSearch, setDebouncedSearch ] = useState( '' );

	// Debounce search
	useEffect( () => {
		const timer = setTimeout( () => {
			setDebouncedSearch( searchTerm );
		}, 300 );
		return () => clearTimeout( timer );
	}, [ searchTerm ] );

	const queryArgs = {
		relationship: 'followers',
		user_id: userId,
		per_page: 20,
	};

	if ( debouncedSearch ) {
		queryArgs.search = debouncedSearch;
	}

	const { records: followers, isResolving } = useEntityRecords( 'activitypub/v1', 'actor', queryArgs );

	return (
		<div>
			<input
				type="search"
				placeholder="Search followers..."
				value={ searchTerm }
				onChange={ ( e ) => setSearchTerm( e.target.value ) }
			/>

			{ isResolving ? (
				<Spinner />
			) : (
				<ul>
					{ followers?.map( ( follower ) => (
						<li key={ follower.id }>
							{ follower.name } (@{ follower.preferred_username })
						</li>
					) ) }
				</ul>
			) }
		</div>
	);
}

/**
 * Example 4: Followers and Following tabs
 *
 * @param {Object} props          Component props
 * @param {number} props.userId   User ID
 * @return {Element} Component with tabs for followers/following
 */
export function FollowersAndFollowing( { userId } ) {
	const [ activeTab, setActiveTab ] = useState( 'followers' );

	const { records, isResolving, totalItems } = useEntityRecords( 'activitypub/v1', 'actor', {
		relationship: activeTab,
		user_id: userId,
		per_page: 20,
	} );

	return (
		<div>
			<div className="tabs">
				<button
					className={ activeTab === 'followers' ? 'active' : '' }
					onClick={ () => setActiveTab( 'followers' ) }
				>
					Followers ({ totalItems || 0 })
				</button>
				<button
					className={ activeTab === 'following' ? 'active' : '' }
					onClick={ () => setActiveTab( 'following' ) }
				>
					Following ({ totalItems || 0 })
				</button>
			</div>

			{ isResolving ? (
				<Spinner />
			) : (
				<ul>
					{ records?.map( ( actor ) => (
						<li key={ actor.id }>
							{ actor.icon?.url && <img src={ actor.icon.url } alt="" width="48" height="48" /> }
							<div>
								<strong>{ actor.name }</strong>
								<br />@{ actor.preferred_username }
							</div>
						</li>
					) ) }
				</ul>
			) }
		</div>
	);
}

/**
 * Example 5: Using with useSelect
 *
 * For more control over the data fetching
 */
import { useSelect } from '@wordpress/data';
import { store as coreDataStore } from '@wordpress/core-data';

export function AdvancedFollowers( { userId } ) {
	const { followers, isResolving, hasResolved } = useSelect(
		( select ) => {
			const {
				getEntityRecords,
				isResolving: isResolvingSelector,
				hasFinishedResolution,
			} = select( coreDataStore );

			const queryArgs = {
				relationship: 'followers',
				user_id: userId,
				per_page: 10,
			};

			return {
				followers: getEntityRecords( 'activitypub/v1', 'actor', queryArgs ),
				isResolving: isResolvingSelector( 'getEntityRecords', [ 'activitypub/v1', 'actor', queryArgs ] ),
				hasResolved: hasFinishedResolution( 'getEntityRecords', [ 'activitypub/v1', 'actor', queryArgs ] ),
			};
		},
		[ userId ]
	);

	if ( isResolving && ! hasResolved ) {
		return <Spinner />;
	}

	return (
		<ul>
			{ followers?.map( ( follower ) => (
				<li key={ follower.id }>{ follower.name }</li>
			) ) }
		</ul>
	);
}
