/**
 * Panel Component
 *
 * A reusable surface wrapper for themed content areas.
 * Uses ThemedSurface component with margin spacing.
 */

import { ReactNode } from 'react';
import clsx from 'clsx';
import ThemedSurface from '../themed-surface';
import './style.scss';

interface PanelProps {
	className?: string;
	children: ReactNode;
}

export default function Panel( { className, children }: PanelProps ) {
	return (
		<div className={ clsx( 'panel', className ) }>
			<ThemedSurface>{ children }</ThemedSurface>
		</div>
	);
}
