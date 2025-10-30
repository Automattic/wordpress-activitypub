/**
 * ThemedSurface Component
 *
 * This component wraps content with appropriate theme context for consistent styling.
 */

import { ReactNode } from 'react';
import clsx from 'clsx';
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
 */
export default function ThemedSurface( { className, children }: ThemedSurfaceProps ) {
	return <div className={ clsx( 'themed-surface', className ) }>{ children }</div>;
}
