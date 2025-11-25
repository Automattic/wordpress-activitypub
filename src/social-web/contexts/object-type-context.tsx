import { createContext, useContext, useMemo, useCallback } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import { store as coreDataStore } from '@wordpress/core-data';
import type { Term } from '@wordpress/core-data';

interface ObjectTypeContextValue {
	getObjectTypeName: ( id: number | undefined ) => string | null;
	isLoading: boolean;
}

const ObjectTypeContext = createContext< ObjectTypeContextValue >( {
	getObjectTypeName: () => null,
	isLoading: true,
} );

export function ObjectTypeProvider( { children }: { children: React.ReactNode } ) {
	const { terms, isResolving } = useSelect( ( select ) => {
		const { getEntityRecords, isResolving: checkResolving } = select( coreDataStore );
		return {
			terms: getEntityRecords( 'taxonomy', 'ap_object_type', { per_page: -1 } ) as Term[] | null,
			isResolving: checkResolving( 'getEntityRecords', [ 'taxonomy', 'ap_object_type', { per_page: -1 } ] ),
		};
	}, [] );

	// Create a lookup map for fast access
	const termMap = useMemo( () => {
		if ( ! terms ) {
			return new Map< number, string >();
		}
		return new Map( terms.map( ( term ) => [ term.id, term.name ] ) );
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

	const value = useMemo(
		() => ( {
			getObjectTypeName,
			isLoading: isResolving,
		} ),
		[ getObjectTypeName, isResolving ]
	);

	return <ObjectTypeContext.Provider value={ value }>{ children }</ObjectTypeContext.Provider>;
}

export function useObjectType() {
	return useContext( ObjectTypeContext );
}
