/**
 * Feed Stage
 *
 * Main feed list view with DataViews
 */

/**
 * External dependencies
 */
import type { ReactNode } from 'react';
import type { UseNavigateResult } from '@wordpress/route';

/**
 * WordPress dependencies
 */
import { useMemo, useCallback, useState, useEffect, useRef } from '@wordpress/element';
import { DataViews } from '@wordpress/dataviews/wp';
import type { Field, View as DataViewsView } from '@wordpress/dataviews/wp';
import { useView } from '@wordpress/views';
import { useSelect } from '@wordpress/data';
import { useNavigate, useSearch } from '@wordpress/route';

/**
 * Internal dependencies
 */
import { useFeed } from '../../hooks/use-feed';
import type { FeedQuery } from '../../hooks/use-feed';
import { titleField, dateField, metadataField, contentField, objectTypeField, tagField } from '../../components/fields';
import EmptyState from '../../components/empty-state';
import { DEFAULT_VIEW, defaultLayouts, getFeedViewUpdate, normalizeFieldOrder, viewToQuery } from './utils';
import type { ViewType } from './utils';
import { STORE_NAME } from '../../store';
import type { AppSelectors } from '../../store';
import type { FeedPost } from '../../types';
import './style.scss';

interface SearchParams {
	page?: number;
	search?: string;
	postIds?: string[];
}

export default function FeedStage(): ReactNode {
	const navigate: UseNavigateResult< string > = useNavigate();
	const searchParams: SearchParams = useSearch( { strict: false } ) as SearchParams;

	// The selection lives in the URL; the inspector shows the first selected post.
	// No view transition: boot would animate the stage resize as a pinned-corner crossfade.
	const selectItems = useCallback(
		( items: string[] ): void => {
			void navigate( {
				search: ( ( prev: Record< string, unknown > ): Record< string, unknown > => ( {
					...prev,
					postIds: items.length > 0 ? items : undefined,
				} ) ) as never,
				viewTransition: false,
			} );
		},
		[ navigate ]
	);
	// Get active actor ID from store
	const activeActorId: number | null = useSelect(
		( select ): number | null => ( select( STORE_NAME ) as AppSelectors ).getActiveActorId(),
		[]
	);

	// Page and search live in the URL, so the view survives reloads and back/forward.
	const handleChangeQueryParams = useCallback(
		( params: SearchParams ): void => {
			void navigate( {
				search: ( ( prev: Record< string, unknown > ): Record< string, unknown > => ( {
					...prev,
					...params,
				} ) ) as never,
			} );
		},
		[ navigate ]
	);

	// Use the views hook to persist user preferences
	const { view, updateView } = useView( {
		kind: 'postType',
		name: 'ap_post',
		slug: 'feed',
		defaultView: DEFAULT_VIEW,
		defaultLayouts,
		queryParams: searchParams,
		onChangeQueryParams: handleChangeQueryParams,
	} );

	// Wrap updateView to reset page when filters change and to translate
	// dataviews' infinite-scroll `startPosition` into our page-based loader.
	const updateFeedView = useCallback(
		( updatedView: ViewType ): void => {
			updateView( getFeedViewUpdate( view, updatedView ) );
		},
		[ view, updateView ]
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

	const query: FeedQuery = useMemo( (): FeedQuery => viewToQuery( view, activeActorId ), [ view, activeActorId ] );
	const { feed, isResolving, totalItems, totalPages } = useFeed( query );

	const fields: Field< FeedPost >[] = useMemo(
		(): Field< FeedPost >[] => [ metadataField, titleField, contentField, dateField, objectTypeField, tagField ],
		[]
	);

	// Normalize view.fields to maintain the canonical order defined in fields array
	const normalizedView: ViewType = useMemo( () => normalizeFieldOrder( view, fields ), [ view, fields ] );

	const selection: string[] = searchParams.postIds ?? [];

	// State for infinite scroll
	const [ allLoadedRecords, setAllLoadedRecords ] = useState< FeedPost[] >( [] );
	const lastProcessedPage = useRef< number >( 0 );

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
			onClickItem={ ( item: FeedPost ): void => selectItems( [ item.id.toString() ] ) }
			isItemClickable={ (): true => true }
			getItemId={ ( item: FeedPost ): string => item.id.toString() }
			selection={ selection }
			onChangeSelection={ selectItems }
			empty={ <EmptyState /> }
			paginationInfo={ {
				totalItems,
				totalPages,
			} }
			defaultLayouts={ defaultLayouts }
		/>
	);
}
