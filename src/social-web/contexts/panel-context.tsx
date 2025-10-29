/**
 * WordPress dependencies
 */
import React, { createContext, useContext, useState, useCallback, useMemo } from '@wordpress/element';

/**
 * Internal dependencies
 */
import type { Location } from '../types';

export type PanelView = 'list' | 'detail' | 'split';

interface PanelState {
	// Currently selected item (controls detail panel visibility)
	selectedItem: any | null;

	// Active feature/tab in detail panel
	activeFeature: string;

	// Panel layout mode
	viewMode: PanelView;

	// Whether the panel is expanded (for mobile)
	isPanelExpanded: boolean;

	// Additional panel-specific data
	panelData: Record< string, any >;
}

interface PanelContextValue extends PanelState {
	// Actions
	setSelectedItem: ( item: any | null ) => void;
	setActiveFeature: ( feature: string ) => void;
	setViewMode: ( mode: PanelView ) => void;
	togglePanelExpanded: () => void;
	setPanelData: ( key: string, value: any ) => void;
	clearSelection: () => void;

	// Computed values
	hasSelection: boolean;
	isDetailView: boolean;
	isSplitView: boolean;
}

const PanelContext = createContext< PanelContextValue | undefined >( undefined );

interface PanelProviderProps {
	children: React.ReactNode;
	initialState?: Partial< PanelState >;
	onStateChange?: ( state: PanelState ) => void;
}

export function PanelProvider( { children, initialState = {}, onStateChange }: PanelProviderProps ) {
	const [ state, setState ] = useState< PanelState >( {
		selectedItem: null,
		activeFeature: 'overview',
		viewMode: 'split',
		isPanelExpanded: false,
		panelData: {},
		...initialState,
	} );

	const setSelectedItem = useCallback(
		( item: any | null ) => {
			setState( ( prev ) => {
				const newState = { ...prev, selectedItem: item };
				onStateChange?.( newState );
				return newState;
			} );
		},
		[ onStateChange ]
	);

	const setActiveFeature = useCallback(
		( feature: string ) => {
			setState( ( prev ) => {
				const newState = { ...prev, activeFeature: feature };
				onStateChange?.( newState );
				return newState;
			} );
		},
		[ onStateChange ]
	);

	const setViewMode = useCallback(
		( mode: PanelView ) => {
			setState( ( prev ) => {
				const newState = { ...prev, viewMode: mode };
				onStateChange?.( newState );
				return newState;
			} );
		},
		[ onStateChange ]
	);

	const togglePanelExpanded = useCallback( () => {
		setState( ( prev ) => {
			const newState = { ...prev, isPanelExpanded: ! prev.isPanelExpanded };
			onStateChange?.( newState );
			return newState;
		} );
	}, [ onStateChange ] );

	const setPanelData = useCallback(
		( key: string, value: any ) => {
			setState( ( prev ) => {
				const newState = {
					...prev,
					panelData: { ...prev.panelData, [ key ]: value },
				};
				onStateChange?.( newState );
				return newState;
			} );
		},
		[ onStateChange ]
	);

	const clearSelection = useCallback( () => {
		setState( ( prev ) => {
			const newState = {
				...prev,
				selectedItem: null,
				isPanelExpanded: false,
			};
			onStateChange?.( newState );
			return newState;
		} );
	}, [ onStateChange ] );

	const contextValue = useMemo< PanelContextValue >(
		() => ( {
			...state,
			setSelectedItem,
			setActiveFeature,
			setViewMode,
			togglePanelExpanded,
			setPanelData,
			clearSelection,
			hasSelection: state.selectedItem !== null,
			isDetailView: state.viewMode === 'detail' || ( state.viewMode === 'split' && state.selectedItem !== null ),
			isSplitView: state.viewMode === 'split',
		} ),
		[ state, setSelectedItem, setActiveFeature, setViewMode, togglePanelExpanded, setPanelData, clearSelection ]
	);

	return <PanelContext.Provider value={ contextValue }>{ children }</PanelContext.Provider>;
}

export function usePanelContext() {
	const context = useContext( PanelContext );
	if ( ! context ) {
		throw new Error( 'usePanelContext must be used within a PanelProvider' );
	}
	return context;
}

// Helper hook for feature panels
export function useFeaturePanel( featureName: string ) {
	const context = usePanelContext();

	return {
		isActive: context.activeFeature === featureName,
		activate: () => context.setActiveFeature( featureName ),
		selectedItem: context.selectedItem,
		clearSelection: context.clearSelection,
	};
}
