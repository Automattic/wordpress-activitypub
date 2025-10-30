/**
 * Panel Component
 *
 * A reusable surface wrapper for white panels.
 * Uses 8px border radius and no box shadows.
 */

import { ReactNode } from 'react';
import classNames from 'classnames';
import './style.scss';

interface PanelProps {
	className?: string;
	children: ReactNode;
}

export default function Panel( { className, children }: PanelProps ) {
	return <div className={ classNames( 'panel', className ) }>{ children }</div>;
}
