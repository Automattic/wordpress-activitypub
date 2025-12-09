/**
 * Feed Stage
 *
 * Main feed list view with DataViews
 */

import { useMemo, useCallback, useState, useEffect, useRef } from '@wordpress/element';
import { DataViews } from '@wordpress/dataviews';
import { useView } from '@wordpress/views';
import type { View, Field } from '@wordpress/dataviews';
import { __ } from '@wordpress/i18n';
import { addQueryArgs, getQueryArgs } from '@wordpress/url';
import { useSelect } from '@wordpress/data';
import { useFeed } from '../../hooks/use-feed';
import { titleField, dateField, metadataField, contentField, objectTypeField, tagField } from '../../components/fields';
import { normalizeFieldOrder } from './utils';
import { STORE_NAME } from '../../store';
import type { ClientSelectors } from '../../store';
import type { FeedPost } from '../../types';
import { useNavigate } from '../../router';
import './style.scss';

const DEFAULT_VIEW: View = {
	type: 'list',
	perPage: 20,
	page: 1,
	sort: {
		field: 'date',
		direction: 'desc',
	},
	search: '',
	filters: [],
	fields: [ 'metadata', 'title.rendered', 'content' ],
	infiniteScrollEnabled: true,
};

const defaultLayouts = {
	list: {
		primaryField: 'metadata',
		fields: [ 'metadata', 'title.rendered', 'content' ],
		mediaField: undefined,
	},
};

export default function FeedStage() {
	const navigate = useNavigate();

	// Navigate to inspector by updating search params
	const selectItem = useCallback(
		( id: number ) => {
			navigate( {
				search: ( prev: Record< string, unknown > ) => ( { ...prev, postId: id } ),
			} );
		},
		[ navigate ]
	);
	// Get active actor ID from store
	const activeActorId = useSelect( ( select ) => ( select( STORE_NAME ) as ClientSelectors ).getActiveActorId(), [] );

	// Track URL query parameters as state for reactivity
	const [ urlQueryParams, setUrlQueryParams ] = useState( () => {
		const args = getQueryArgs( window.location.href ) as {
			// Using 'paged' instead of 'page' to avoid conflict with WP admin menu 'page' parameter.
			paged?: string;
			search?: string;
		};

		return {
			page: args.paged ? Number( args.paged ) : undefined,
			search: args.search || undefined,
		};
	} );

	// Listen for URL changes (browser back/forward).
	useEffect( () => {
		const updateQueryParams = () => {
			const args = getQueryArgs( window.location.href ) as {
				paged?: string;
				search?: string;
			};
			setUrlQueryParams( {
				page: args.paged ? Number( args.paged ) : undefined,
				search: args.search || undefined,
			} );
		};

		window.addEventListener( 'popstate', updateQueryParams );
		window.addEventListener( 'hashchange', updateQueryParams );

		return () => {
			window.removeEventListener( 'popstate', updateQueryParams );
			window.removeEventListener( 'hashchange', updateQueryParams );
		};
	}, [] );

	// Use the views hook to persist user preferences
	const { view, updateView } = useView( {
		kind: 'postType',
		name: 'ap_post',
		slug: 'feed',
		defaultView: DEFAULT_VIEW,
		queryParams: urlQueryParams,
		onChangeQueryParams: ( params ) => {
			const currentUrl = window.location.href;
			const currentArgs = getQueryArgs( currentUrl );
			const newUrl = addQueryArgs( currentUrl, {
				...currentArgs,
				paged: params.page || undefined,
				search: params.search || undefined,
			} );
			window.history.pushState( null, '', newUrl );

			setUrlQueryParams( {
				page: params.page,
				search: params.search,
			} );
		},
	} );

	// Wrap updateView to reset page when filters change
	const updateFeedView = useCallback(
		( updatedView: View ) => {
			// Reset to page 1 when filters change
			const filtersChanged = JSON.stringify( view.filters ) !== JSON.stringify( updatedView.filters );
			const page = filtersChanged ? 1 : updatedView.page;

			updateView( { ...updatedView, page } );
		},
		[ view.filters, updateView ]
	);

	// Reset view to default state when actor switches
	const prevActiveActorId = useRef( activeActorId );
	useEffect( () => {
		if ( prevActiveActorId.current !== activeActorId ) {
			// Actor changed - reset to default view, preserving only field visibility
			updateView( {
				...DEFAULT_VIEW,
				fields: view.fields,
			} );
			prevActiveActorId.current = activeActorId;
		}
	}, [ activeActorId, updateView ] );

	const { feed, isResolving, totalItems, totalPages } = useFeed( {
		perPage: view.perPage || 20,
		page: view.page || 1,
		orderBy: view.sort?.field || 'date',
		order: view.sort?.direction || 'desc',
		search: view.search || '',
		userId: activeActorId,
		filters: view.filters || [],
	} );

	const fields: Field< FeedPost >[] = useMemo(
		() => [ metadataField, titleField, contentField, dateField, objectTypeField, tagField ],
		[]
	);

	// Normalize view.fields to maintain the canonical order defined in fields array
	const normalizedView = useMemo( () => normalizeFieldOrder( view, fields ), [ view, fields ] );

	const [ selection, setSelection ] = useState< string[] >( [] );

	// State for infinite scroll
	const [ allLoadedRecords, setAllLoadedRecords ] = useState< FeedPost[] >( [] );
	const [ isLoadingMore, setIsLoadingMore ] = useState( false );
	const lastProcessedPage = useRef< number >( 0 );

	useEffect( () => {
		if ( selection.length === 0 ) {
			return;
		}

		const selectedId = selection[ 0 ];
		const exists = feed.some( ( item ) => item.id.toString() === selectedId );
		if ( ! exists ) {
			setSelection( [] );
		}
	}, [ feed, selection ] );

	const changeSelection = useCallback(
		( nextSelection: string[] ) => {
			setSelection( nextSelection );

			if ( nextSelection.length === 0 ) {
				return;
			}

			const selectedId = nextSelection[ 0 ];
			const selectedItem = feed.find( ( item ) => item.id.toString() === selectedId );

			if ( selectedItem ) {
				selectItem( selectedItem.id );
			}
		},
		[ feed, selectItem ]
	);

	// Infinite scroll handler
	const infiniteScrollHandler = useCallback( () => {
		const currentPage = view.page || 1;

		// Prevent concurrent requests or loading beyond available pages
		if ( isLoadingMore || currentPage >= ( totalPages || 1 ) ) {
			return;
		}

		setIsLoadingMore( true );
		updateFeedView( {
			...view,
			page: currentPage + 1,
		} );
	}, [ isLoadingMore, view, totalPages, updateFeedView ] );

	// Accumulate data across pages for infinite scroll
	useEffect( () => {
		const currentPage = normalizedView.page || 1;
		const infiniteScrollEnabled = normalizedView.infiniteScrollEnabled;

		// Clear records when on first page with no results (handles filter/search changes)
		if ( feed.length === 0 && currentPage === 1 ) {
			setAllLoadedRecords( [] );
			lastProcessedPage.current = currentPage;
			setIsLoadingMore( false );
			return;
		}

		// Don't process until feed data is available
		if ( feed.length === 0 ) {
			return;
		}

		// Skip if we've already processed this page (but always process page 1 for search/initial load)
		if ( currentPage > 1 && lastProcessedPage.current === currentPage ) {
			return;
		}

		// Reset to new data on first page or when infinite scroll is disabled
		if ( currentPage === 1 || ! infiniteScrollEnabled ) {
			setAllLoadedRecords( feed );
			lastProcessedPage.current = currentPage;
			setIsLoadingMore( false );
		} else {
			// Append new records while avoiding duplicates
			setAllLoadedRecords( ( prev ) => {
				const existingIds = new Set( prev.map( ( item ) => item.id ) );
				const newRecords = feed.filter( ( record ) => ! existingIds.has( record.id ) );
				return newRecords.length > 0 ? [ ...prev, ...newRecords ] : prev;
			} );
			lastProcessedPage.current = currentPage;
			setIsLoadingMore( false );
		}
	}, [
		feed,
		normalizedView.page,
		normalizedView.search,
		normalizedView.infiniteScrollEnabled,
		normalizedView.filters,
	] );

	return (
		<DataViews
			data={ allLoadedRecords }
			fields={ fields }
			view={ normalizedView }
			onChangeView={ updateFeedView }
			isLoading={ isResolving || isLoadingMore }
			onClickItem={ ( item ) => selectItem( item.id ) }
			isItemClickable={ () => true }
			getItemId={ ( item ) => item.id.toString() }
			selection={ selection }
			onChangeSelection={ changeSelection }
			empty={
				<p>
					{ normalizedView.search || ( normalizedView.filters && normalizedView.filters.length > 0 )
						? __( 'No posts found.', 'activitypub' )
						: __(
								'No posts found in your feed. Posts from ActivityPub actors you follow will appear here.',
								'activitypub'
						  ) }
				</p>
			}
			paginationInfo={ {
				totalItems,
				totalPages,
				infiniteScrollHandler,
			} }
			defaultLayouts={ defaultLayouts }
		/>
	);
}
