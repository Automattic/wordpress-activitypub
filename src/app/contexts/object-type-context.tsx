/**
 * External dependencies
 */
import type { Context, ReactNode } from 'react';

/**
 * WordPress dependencies
 */
import { createContext, useContext, useMemo, useCallback } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import { store as coreDataStore } from '@wordpress/core-data';
import type { Term } from '@wordpress/core-data';

interface ObjectTypeContextValue {
	getObjectTypeName: ( id: number | undefined ) => string | null;
	isLoading: boolean;
}

const ObjectTypeContext: Context< ObjectTypeContextValue > = createContext< ObjectTypeContextValue >( {
	getObjectTypeName: (): null => null,
	isLoading: true,
} );

interface ObjectTypeProviderProps {
	children: ReactNode;
}

// Type for core-data store with isResolving selector (not in official types)
interface CoreDataStoreSelectors {
	getEntityRecords: ( kind: string, name: string, query?: Record< string, unknown > ) => Term[] | null;
	isResolving: ( selector: string, args: unknown[] ) => boolean;
}

export function ObjectTypeProvider( { children }: ObjectTypeProviderProps ): ReactNode {
	const { terms, isResolving } = useSelect( ( select ): { terms: Term[] | null; isResolving: boolean } => {
		const store = select( coreDataStore ) as unknown as CoreDataStoreSelectors;
		return {
			terms: store.getEntityRecords( 'taxonomy', 'ap_object_type', { per_page: -1 } ),
			isResolving: store.isResolving( 'getEntityRecords', [ 'taxonomy', 'ap_object_type', { per_page: -1 } ] ),
		};
	}, [] );

	// Create a lookup map for fast access
	const termMap: Map< number, string > = useMemo( (): Map< number, string > => {
		if ( ! terms ) {
			return new Map< number, string >();
		}
		return new Map( terms.map( ( term: Term ): [ number, string ] => [ term.id, term.name ] ) );
	}, [ terms ] );

	const getObjectTypeName = useCallback(
		( id: number | undefined ): string | null => {
			if ( ! id ) {
				return null;
			}
			return termMap.get( id ) || null;
		},
		[ termMap ]
	);

	const value: ObjectTypeContextValue = useMemo(
		(): ObjectTypeContextValue => ( {
			getObjectTypeName,
			isLoading: isResolving,
		} ),
		[ getObjectTypeName, isResolving ]
	);

	return <ObjectTypeContext.Provider value={ value }>{ children }</ObjectTypeContext.Provider>;
}

export function useObjectType(): ObjectTypeContextValue {
	return useContext( ObjectTypeContext );
}
