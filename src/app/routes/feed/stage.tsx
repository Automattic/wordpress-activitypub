/**
 * Feed Stage
 *
 * Main feed list view with DataViews
 */

/**
 * External dependencies
 */
import type { ReactNode } from 'react';
import { UseNavigateResult } from '@tanstack/react-router';

/**
 * WordPress dependencies
 */
import { useMemo, useCallback, useState, useEffect, useRef } from '@wordpress/element';
import { DataViews } from '@wordpress/dataviews/wp';
import type { Field, View as DataViewsView } from '@wordpress/dataviews/wp';
import { useView } from '@wordpress/views';
import { addQueryArgs, getQueryArgs } from '@wordpress/url';
import { useSelect } from '@wordpress/data';

/**
 * Internal dependencies
 */
import { useFeed } from '../../hooks/use-feed';
import { titleField, dateField, metadataField, contentField, objectTypeField, tagField } from '../../components/fields';
import EmptyState from '../../components/empty-state';
import { normalizeFieldOrder } from './utils';
import { STORE_NAME } from '../../store';
import type { AppSelectors } from '../../store';
import type { FeedPost } from '../../types';
import { useNavigate } from '../../router';
import './style.scss';

// Using ReturnType to get the View type from useView to avoid version conflicts between @wordpress/views and @wordpress/dataviews
type ViewType = ReturnType< typeof useView >[ 'view' ];

const DEFAULT_VIEW: ViewType = {
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

export default function FeedStage(): ReactNode {
	const navigate: UseNavigateResult< string > = useNavigate();

	// Navigate to inspector by updating search params
	const selectItem: ( id: number ) => void = useCallback(
		( id: number ): void => {
			void navigate( {
				search: ( ( prev: Record< string, unknown > ): Record< string, unknown > => ( {
					...prev,
					postId: id,
				} ) ) as never,
			} );
		},
		[ navigate ]
	);
	// Get active actor ID from store
	const activeActorId: number | null = useSelect(
		( select ): number | null => ( select( STORE_NAME ) as AppSelectors ).getActiveActorId(),
		[]
	);

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
		const updateQueryParams = (): void => {
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
		return (): void => window.removeEventListener( 'popstate', updateQueryParams );
	}, [] );

	// Push view changes (page / search) to the URL so browser back works.
	const handleChangeQueryParams = useCallback( ( params: { page?: number; search?: string } ): void => {
		const currentUrl: string = window.location.href;
		const currentArgs = getQueryArgs( currentUrl );
		const newUrl: string = addQueryArgs( currentUrl, {
			...currentArgs,
			paged: params.page || undefined,
			search: params.search || undefined,
		} );
		window.history.pushState( null, '', newUrl );

		setUrlQueryParams( {
			page: params.page,
			search: params.search,
		} );
	}, [] );

	// Use the views hook to persist user preferences
	const { view, updateView } = useView( {
		kind: 'postType',
		name: 'ap_post',
		slug: 'feed',
		defaultView: DEFAULT_VIEW,
		queryParams: urlQueryParams,
		onChangeQueryParams: handleChangeQueryParams,
	} );

	// Wrap updateView to reset page when filters change
	const updateFeedView = useCallback(
		( updatedView: ViewType ): void => {
			// Reset to page 1 when filters change
			const filtersChanged: boolean = JSON.stringify( view.filters ) !== JSON.stringify( updatedView.filters );
			const page: number = filtersChanged ? 1 : updatedView.page ?? 1;

			updateView( { ...updatedView, page } );
		},
		[ view.filters, updateView ]
	);

	// Reset view to default state when actor switches
	const prevActiveActorId = useRef( activeActorId );
	useEffect( (): void => {
		if ( prevActiveActorId.current !== activeActorId ) {
			// Actor changed - reset to default view, preserving only field visibility
			updateView( {
				...DEFAULT_VIEW,
				fields: view.fields,
			} );
			prevActiveActorId.current = activeActorId;
		}
		// eslint-disable-next-line react-hooks/exhaustive-deps -- updateView changes reference frequently; condition guards against repeated calls
	}, [ activeActorId ] );

	const { feed, isResolving, totalItems, totalPages } = useFeed( {
		perPage: view.perPage || 20,
		page: view.page || 1,
		orderBy: view.sort?.field || 'date',
		order: view.sort?.direction || 'desc',
		search: view.search || '',
		userId: activeActorId,
		filters: view.filters || DEFAULT_VIEW.filters,
	} );

	const fields: Field< FeedPost >[] = useMemo(
		(): Field< FeedPost >[] => [ metadataField, titleField, contentField, dateField, objectTypeField, tagField ],
		[]
	);

	// Normalize view.fields to maintain the canonical order defined in fields array
	const normalizedView: ViewType = useMemo( () => normalizeFieldOrder( view, fields ), [ view, fields ] );

	const [ selection, setSelection ] = useState< string[] >( [] );

	// Accumulate data across pages for infinite scroll
	const [ allLoadedRecords, setAllLoadedRecords ] = useState< FeedPost[] >( [] );
	const lastProcessedPage = useRef< number >( 0 );

	const changeSelection = useCallback(
		( nextSelection: string[] ): void => {
			setSelection( nextSelection );

			if ( nextSelection.length === 0 ) {
				return;
			}

			// We already have the ID from the selection - no need to look up the item
			selectItem( Number( nextSelection[ 0 ] ) );
		},
		[ selectItem ]
	);

	// Accumulate data across pages for infinite scroll
	useEffect( (): void => {
		const currentPage: number = normalizedView.page || 1;
		const infiniteScrollEnabled: boolean = normalizedView.infiniteScrollEnabled;

		// Clear records when on first page with no results (handles filter/search changes)
		if ( feed.length === 0 && currentPage === 1 ) {
			setAllLoadedRecords( [] );
			lastProcessedPage.current = currentPage;
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
		} else {
			// Append new records while avoiding duplicates
			setAllLoadedRecords( ( prev: FeedPost[] ): FeedPost[] => {
				const existingIds = new Set( prev.map( ( item: FeedPost ): number => item.id ) );
				const newRecords: FeedPost[] = feed.filter(
					( record: FeedPost ): boolean => ! existingIds.has( record.id )
				);
				return newRecords.length > 0 ? [ ...prev, ...newRecords ] : prev;
			} );
			lastProcessedPage.current = currentPage;
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
			view={ normalizedView as DataViewsView }
			onChangeView={ updateFeedView as ( view: DataViewsView ) => void }
			isLoading={ isResolving }
			onClickItem={ ( item: FeedPost ): void => selectItem( item.id ) }
			isItemClickable={ (): true => true }
			getItemId={ ( item: FeedPost ): string => item.id.toString() }
			selection={ selection }
			onChangeSelection={ changeSelection }
			empty={ <EmptyState /> }
			paginationInfo={ {
				totalItems,
				totalPages,
			} }
			defaultLayouts={ defaultLayouts }
		/>
	);
}
