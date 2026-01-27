/**
 * ThemedSurface Component
 *
 * This component wraps content with appropriate theme context for consistent styling.
 */

/**
 * External dependencies
 */
import type { ReactNode } from 'react';
import clsx from 'clsx';

/**
 * Internal dependencies
 */
import './style.scss';

interface ThemedSurfaceProps {
	className?: string;
	children: ReactNode;
}

/**
 * ThemedSurface component
 *
 * Wraps content in a themed surface with light background.
 * Uses wpds design tokens that are provided by ThemeProvider context.
 *
 * @param props           Component props.
 * @param props.className Additional CSS class name.
 * @param props.children  Content to render inside the surface.
 */
export default function ThemedSurface( { className, children }: ThemedSurfaceProps ): ReactNode {
	return <div className={ clsx( 'themed-surface', className ) }>{ children }</div>;
}
