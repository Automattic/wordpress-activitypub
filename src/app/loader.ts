/**
 * WordPress dependencies
 */
import { initSinglePage } from '@wordpress/boot';

/**
 * Internal dependencies
 */
import type { Route } from './router/types';

interface LoaderData {
	mountId?: string;
	routes?: Route[];
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
	const { mountId, routes } = getLoaderData();

	if ( ! mountId || ! Array.isArray( routes ) ) {
		return;
	}

	initSinglePage( { mountId, routes } );
};

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', bootApp, { once: true } );
} else {
	bootApp();
}
