import apiFetch from '@wordpress/api-fetch';
import { useState, useEffect, useCallback } from '@wordpress/element';
import { addQueryArgs } from '@wordpress/url';
import { __ } from '@wordpress/i18n';
import { useOptions } from '../use-options';
import { ActorItem } from './actor-item';
import { Pagination } from './pagination';

/**
 * Component to display a list of actors (followers/following).
 *
 * @param {Object}   props              The component props.
 * @param {string}   props.selectedUser The ID of the user whose actors are being fetched.
 * @param {number}   props.perPage      The number of actors to fetch per page.
 * @param {string}   props.order        The order in which to fetch actors ('asc' or 'desc').
 * @param {string}   props.endpoint     The API endpoint ('followers' or 'following').
 * @param {number}   props.page         The page number (controlled mode).
 * @param {Function} props.setPage      The function to set the page number (controlled mode).
 * @param {Object}   props.initialData  Optional pre-fetched data.
 * @param {string}   props.emptyMessage Message to show when no actors found.
 * @param {string}   props.navLabel     Navigation label for screen readers.
 * @return {JSX.Element} The actor list component.
 */
export function ActorList( {
	selectedUser,
	perPage,
	order,
	endpoint = 'followers',
	page: passedPage,
	setPage: passedSetPage,
	initialData = false,
	emptyMessage = __( 'No results found.', 'activitypub' ),
	navLabel = __( 'Navigation', 'activitypub' ),
} ) {
	const { namespace } = useOptions();
	const userId = selectedUser === 'blog' ? 0 : selectedUser;
	const [ actors, setActors ] = useState( [] );
	const [ pages, setPages ] = useState( 0 );
	const [ localPage, setLocalPage ] = useState( 1 );
	const page = passedPage || localPage;
	const setPage = passedSetPage || setLocalPage;

	const setData = useCallback(
		( newActors, total ) => {
			setActors( newActors );
			setPages( Math.ceil( total / perPage ) );
		},
		[ perPage ]
	);

	useEffect( () => {
		if ( initialData && page === 1 ) {
			return setData( initialData.items, initialData.total );
		}

		const path = addQueryArgs( `/${ namespace }/actors/${ userId }/${ endpoint }`, {
			per_page: perPage,
			order,
			page,
			context: 'full',
		} );
		apiFetch( { path } )
			.then( ( { orderedItems = [], totalItems = 0 } ) => setData( orderedItems, totalItems ) )
			.catch( () => setData( [], 0 ) );
	}, [ namespace, userId, perPage, order, page, endpoint, initialData, setData ] );

	return (
		<div className="activitypub-actor-list-container">
			{ actors.length ? (
				<ul className="activitypub-actor-list">
					{ actors.map( ( actor ) => (
						<li key={ actor.url } className="activitypub-actor-item">
							<ActorItem { ...actor } />
						</li>
					) ) }
				</ul>
			) : (
				<p className="activitypub-actor-list-placeholder">{ emptyMessage }</p>
			) }

			<Pagination page={ page } pages={ pages } setPage={ setPage } navLabel={ navLabel } />
		</div>
	);
}
