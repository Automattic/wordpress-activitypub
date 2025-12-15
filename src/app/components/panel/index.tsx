/**
 * Panel Component
 *
 * A reusable surface wrapper for themed content areas.
 * Uses ThemedSurface component with margin spacing.
 */

/**
 * External dependencies
 */
import type { ReactNode } from 'react';
import clsx from 'clsx';

/**
 * Internal dependencies
 */
import ThemedSurface from '../themed-surface';
import './style.scss';

interface PanelProps {
	className?: string;
	children: ReactNode;
}

export default function Panel( { className, children }: PanelProps ): ReactNode {
	return (
		<div className={ clsx( 'panel', className ) }>
			<ThemedSurface>{ children }</ThemedSurface>
		</div>
	);
}
