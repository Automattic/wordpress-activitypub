/**
 * WordPress dependencies
 */
import { createContext, useContext } from '@wordpress/element';
import type { Context, ReactNode } from 'react';

/**
 * Internal dependencies
 */
import type { AppSettings } from '../types';

const SettingsContext: Context< AppSettings | undefined > = createContext< AppSettings | undefined >( undefined );

interface SettingsProviderProps {
	children: ReactNode;
	settings: AppSettings;
}

export function SettingsProvider( { children, settings }: SettingsProviderProps ): ReactNode {
	return <SettingsContext.Provider value={ settings }>{ children }</SettingsContext.Provider>;
}

export function useSettings(): AppSettings {
	const settings: AppSettings | undefined = useContext( SettingsContext );
	if ( ! settings ) {
		throw new Error( 'useSettings must be used within a SettingsProvider' );
	}

	return settings;
}
