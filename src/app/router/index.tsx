/**
 * External dependencies
 */
import type { ReactNode } from 'react';

/**
 * WordPress dependencies
 */
import { Link, useNavigate, useSearch } from '@wordpress/route';

export { Link, useNavigate, useSearch };

export function Outlet(): ReactNode {
	return null;
}

export function useLoaderData(): undefined {
	return undefined;
}

interface RouterProps {
	routes?: unknown[];
	rootComponent?: unknown;
}

export default function Router( _props: RouterProps ): ReactNode {
	return null;
}
