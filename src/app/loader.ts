/**
 * WordPress dependencies
 */
import { init } from '@wordpress/boot';

/**
 * Internal dependencies
 */
import type { Route } from './router/types';

interface MenuItem {
	id: string;
	label: string;
	to: string;
	parent?: string;
	parent_type?: 'drilldown' | 'dropdown';
}

interface LoaderData {
	mountId?: string;
	routes?: Route[];
	menuItems?: MenuItem[];
	dashboardLink?: string;
	initModules?: string[];
}

const MODULE_DATA_ID = 'wp-script-module-data-@activitypub/app';

const getLoaderData = (): LoaderData => {
	const dataContainer = document.getElementById( MODULE_DATA_ID );

	if ( ! dataContainer?.textContent ) {
		return {};
	}

	try {
		return JSON.parse( dataContainer.textContent ) as LoaderData;
	} catch {
		return {};
	}
};

const bootApp = (): void => {
	const { mountId, routes, menuItems, dashboardLink, initModules } = getLoaderData();

	if ( ! mountId || ! Array.isArray( routes ) ) {
		return;
	}

	// Full-page mode: boot renders the sidebar from `menuItems` and the back button from `dashboardLink`.
	void init( { mountId, routes, menuItems: menuItems ?? [], dashboardLink, initModules: initModules ?? [] } );
};

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', bootApp, { once: true } );
} else {
	bootApp();
}
