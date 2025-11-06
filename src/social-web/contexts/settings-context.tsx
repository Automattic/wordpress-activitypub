/**
 * WordPress dependencies
 */
import { createContext, useContext } from '@wordpress/element';
import type { ReactNode } from 'react';

/**
 * Internal dependencies
 */
import type { SocialWebSettings } from '../types';

const SettingsContext = createContext< SocialWebSettings | undefined >( undefined );

export function SettingsProvider( { children, settings }: { children: ReactNode; settings: SocialWebSettings } ) {
	return <SettingsContext.Provider value={ settings }>{ children }</SettingsContext.Provider>;
}

export function useSettings() {
	const settings = useContext( SettingsContext );
	if ( ! settings ) {
		throw new Error( 'useSettings must be used within a SettingsProvider' );
	}
	return settings;
}
