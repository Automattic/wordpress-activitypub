/**
 * WordPress dependencies
 */
import React, { createContext, useContext, useState, useRef, useEffect, useCallback } from 'react';

/**
 * Navigation context for managing sidebar navigation animations and focus
 */
interface NavigationContextValue {
	direction: 'forward' | 'back' | null;
	navigate: ( path: string, direction?: 'forward' | 'back', focusSelector?: string ) => void;
	focusSelector: string | null;
}

export const NavigationContext = createContext< NavigationContextValue >( {
	direction: null,
	navigate: () => {},
	focusSelector: null,
} );

interface NavigationProviderProps {
	children: React.ReactNode;
	onNavigate: ( path: string ) => void;
}

export function NavigationProvider( { children, onNavigate }: NavigationProviderProps ) {
	const [ direction, setDirection ] = useState< 'forward' | 'back' | null >( null );
	const [ focusSelector, setFocusSelector ] = useState< string | null >( null );
	const animationTimeoutRef = useRef< NodeJS.Timeout >();

	const navigate = useCallback(
		( path: string, navDirection: 'forward' | 'back' = 'forward', selector?: string ) => {
			// Clear any existing animation timeout
			if ( animationTimeoutRef.current ) {
				clearTimeout( animationTimeoutRef.current );
			}

			setDirection( navDirection );
			setFocusSelector( selector || null );
			onNavigate( path );

			// Reset direction after animation completes
			animationTimeoutRef.current = setTimeout( () => {
				setDirection( null );
			}, 300 );
		},
		[ onNavigate ]
	);

	useEffect( () => {
		// Cleanup timeout on unmount
		return () => {
			if ( animationTimeoutRef.current ) {
				clearTimeout( animationTimeoutRef.current );
			}
		};
	}, [] );

	// Focus management after navigation
	useEffect( () => {
		if ( focusSelector ) {
			const timer = setTimeout( () => {
				const element = document.querySelector( focusSelector );
				if ( element instanceof HTMLElement ) {
					element.focus();
				}
				setFocusSelector( null );
			}, 350 ); // Wait for animation to complete

			return () => clearTimeout( timer );
		}
	}, [ focusSelector ] );

	return (
		<NavigationContext.Provider value={ { direction, navigate, focusSelector } }>
			{ children }
		</NavigationContext.Provider>
	);
}

export function useNavigation() {
	return useContext( NavigationContext );
}
