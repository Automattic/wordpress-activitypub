/**
 * Feed Stage
 *
 * Main feed list view with DataViews
 */

import './style.scss';
import { useMemo, useCallback, useState, useEffect, useRef } from '@wordpress/element';
import { DataViews } from '@wordpress/dataviews';
import { useView } from '@wordpress/views';
import type { View, Field } from '@wordpress/dataviews';
import { __ } from '@wordpress/i18n';
import { addQueryArgs, getQueryArgs } from '@wordpress/url';
import { useSelect } from '@wordpress/data';
import { useEntityRecords } from '@wordpress/core-data';
import { Page } from '../../components/page';
import { useFeed } from '../../hooks/use-feed';
import { titleField, dateField, excerptField, metadataField, contentField } from '../../components/fields';
import { enforceContentExcerptMutualExclusion, normalizeFieldOrder } from './utils';
import type { FeedPost } from '../../types';
import { STORE_NAME } from '../../store';
import type { SocialWebSelectors } from '../../store';

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
	fields: [ 'metadata', 'title.rendered', 'excerpt.rendered' ],
	infiniteScrollEnabled: true,
};

const defaultLayouts = {
	list: {
		primaryField: 'metadata',
		fields: [ 'metadata', 'title.rendered', 'excerpt.rendered' ],
		mediaField: undefined,
	},
};

interface FeedStageProps {
	onSelectItem: ( id: number ) => void;
	registerTagHandler?: ( handler: ( tagId: number ) => void, selectedTagId?: number ) => void;
}

export default function FeedStage( { onSelectItem, registerTagHandler }: FeedStageProps ) {
	// Get active actor ID from store
	const activeActorId = useSelect(
		( select ) => ( select( STORE_NAME ) as SocialWebSelectors ).getActiveActorId(),
		[]
	);

	// Fetch ap_object_type taxonomy terms for filter
	const { records: apObjectTypes } = useEntityRecords( 'taxonomy', 'ap_object_type', {
		per_page: 100,
	} );

	// Fetch ap_tag taxonomy terms for filter (top 5 trending)
	const { records: apTags } = useEntityRecords( 'taxonomy', 'ap_tag', {
		per_page: 5,
		orderby: 'count',
		order: 'desc',
	} );

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

	// Wrap updateView to enforce mutual exclusion between excerpt and content fields
	const updateFeedView = useCallback(
		( updatedView: View ) => {
			const oldFields = view.fields || [];
			const newFields = updatedView.fields || [];
			const fields = enforceContentExcerptMutualExclusion( oldFields, newFields );

			updateView( { ...updatedView, fields } );
		},
		[ view.fields, updateView ]
	);

	// Handle tag click from sidebar tag cloud
	const handleTagClick = useCallback(
		( tagId: number ) => {
			const currentFilters = view.filters || [];
			const tagFilterIndex = currentFilters.findIndex( ( f ) => f.field === 'ap_tag' );

			let newFilters;
			let shouldOpenFilters = false;

			if ( tagFilterIndex !== -1 ) {
				// Tag filter exists - toggle it
				const currentValue = currentFilters[ tagFilterIndex ].value as number[];
				if ( currentValue.includes( tagId ) ) {
					// Remove the tag filter if it's the same tag
					newFilters = currentFilters.filter( ( f ) => f.field !== 'ap_tag' );
				} else {
					// Replace with new tag
					newFilters = [
						...currentFilters.slice( 0, tagFilterIndex ),
						{ field: 'ap_tag', operator: 'isAny', value: [ tagId ] },
						...currentFilters.slice( tagFilterIndex + 1 ),
					];
					shouldOpenFilters = true;
				}
			} else {
				// No tag filter exists - add one
				newFilters = [ ...currentFilters, { field: 'ap_tag', operator: 'isAny', value: [ tagId ] } ];
				shouldOpenFilters = true;
			}

			// Open filters when adding a new tag filter
			updateView( {
				...view,
				filters: newFilters,
				openFilters: shouldOpenFilters ? true : view.openFilters,
			} );
		},
		[ view, updateView ]
	);

	// Extract ap_object_type filter from view.filters
	const apObjectTypeFilter = useMemo( () => {
		const typeFilter = view.filters?.find( ( f ) => f.field === 'ap_object_type' );
		return typeFilter?.value as number[] | undefined;
	}, [ view.filters ] );

	// Extract ap_tag filter from view.filters
	const apTagFilter = useMemo( () => {
		const tagFilter = view.filters?.find( ( f ) => f.field === 'ap_tag' );
		return tagFilter?.value as number[] | undefined;
	}, [ view.filters ] );

	// Get selected tag ID (first tag in filter if any)
	const selectedTagId = useMemo( () => {
		return apTagFilter && apTagFilter.length > 0 ? apTagFilter[ 0 ] : undefined;
	}, [ apTagFilter ] );

	// Register tag click handler with Layout
	useEffect( () => {
		if ( registerTagHandler ) {
			registerTagHandler( handleTagClick, selectedTagId );
		}
	}, [ registerTagHandler, handleTagClick, selectedTagId ] );

	const { feed, isResolving, totalItems, totalPages } = useFeed( {
		perPage: view.perPage || 20,
		page: view.page || 1,
		orderBy: view.sort?.field || 'date',
		order: view.sort?.direction || 'desc',
		search: view.search || '',
		userId: activeActorId,
		apObjectType: apObjectTypeFilter,
		apTag: apTagFilter,
	} );

	// Create ap_object_type filter field
	const apObjectTypeField: Field< FeedPost > = useMemo(
		() => ( {
			id: 'ap_object_type',
			label: __( 'Type', 'activitypub' ),
			enableHiding: false,
			enableSorting: false,
			elements:
				apObjectTypes?.map( ( term: any ) => ( {
					value: term.id,
					label: term.name,
				} ) ) || [],
			filterBy: {
				operators: [ 'isAny' ],
			},
		} ),
		[ apObjectTypes ]
	);

	// Create ap_tag filter field
	const apTagField: Field< FeedPost > = useMemo(
		() => ( {
			id: 'ap_tag',
			label: __( 'Tag', 'activitypub' ),
			enableHiding: false,
			enableSorting: false,
			elements:
				apTags?.map( ( term: any ) => ( {
					value: term.id,
					label: term.name,
				} ) ) || [],
			filterBy: {
				operators: [ 'isAny' ],
			},
		} ),
		[ apTags ]
	);

	const fields: Field< FeedPost >[] = useMemo(
		() => [ metadataField, titleField, excerptField, contentField, dateField, apObjectTypeField, apTagField ],
		[ apObjectTypeField, apTagField ]
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
				onSelectItem( selectedItem.id );
			}
		},
		[ feed, onSelectItem ]
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
	}, [ feed, normalizedView.page, normalizedView.search, normalizedView.infiniteScrollEnabled ] );

	return (
		<Page
			title={ __( 'Feed', 'activitypub' ) }
			subTitle={ __( 'ActivityPub posts from your network', 'activitypub' ) }
			hasPadding={ false }
		>
			<DataViews
				data={ allLoadedRecords }
				fields={ fields }
				view={ normalizedView }
				onChangeView={ updateFeedView }
				isLoading={ isResolving || isLoadingMore }
				onClickItem={ ( item ) => onSelectItem( item.id ) }
				isItemClickable={ () => true }
				getItemId={ ( item ) => item.id.toString() }
				selection={ selection }
				onChangeSelection={ changeSelection }
				empty={
					<p>
						{ normalizedView.search
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
		</Page>
	);
}
