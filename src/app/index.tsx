/**
 * External dependencies
 */
import type { ReactNode } from 'react';

/**
 * Internal dependencies
 */
import { ObjectTypeProvider } from './contexts/object-type-context';
import { Layout } from './components/layout';
import './store'; // Import to register the store
import './style.scss'; // Import all styles

interface AppProvidersProps {
	children: ReactNode;
}

/**
 * App-level providers used by route content modules.
 *
 * Core's @wordpress/boot owns the React root and routing. The app shell only
 * wires ActivityPub-specific providers and store registration.
 *
 * @param props          Component props.
 * @param props.children Route surface children.
 * @return Wrapped route surface.
 */
export function AppProviders( { children }: AppProvidersProps ): ReactNode {
	return <ObjectTypeProvider>{ children }</ObjectTypeProvider>;
}

interface AppShellProps {
	children: ReactNode;
}

/**
 * App shell used by route content modules.
 *
 * @param props          Component props.
 * @param props.children Route surface children.
 * @return Wrapped app shell.
 */
export function AppShell( { children }: AppShellProps ): ReactNode {
	return (
		<AppProviders>
			<Layout>{ children }</Layout>
		</AppProviders>
	);
}
