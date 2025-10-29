/**
 * WordPress dependencies
 */
import React from '@wordpress/element';
import classnames from 'classnames';

/**
 * Internal dependencies
 */
import { usePanelContext } from '../contexts/panel-context';

interface PanelLayoutProps {
	sidebar: React.ReactNode;
	content?: React.ReactNode;
	detail?: React.ReactNode;
	canvas?: React.ReactNode;
	className?: string;
}

/**
 * Reusable layout component that manages panel visibility
 * Supports different view modes: list, detail, split
 */
export default function PanelLayout( { sidebar, content, detail, canvas, className = '' }: PanelLayoutProps ) {
	const { viewMode, hasSelection, isPanelExpanded } = usePanelContext();

	const layoutClasses = classnames( 'activitypub-panel-layout', className, {
		'has-selection': hasSelection,
		'is-split-view': viewMode === 'split',
		'is-detail-view': viewMode === 'detail',
		'is-list-view': viewMode === 'list',
		'is-panel-expanded': isPanelExpanded,
	} );

	// Determine what to show based on view mode
	const showContent = viewMode !== 'detail' || ! hasSelection;
	const showDetail = ( viewMode === 'split' && hasSelection ) || ( viewMode === 'detail' && hasSelection );
	const showCanvas = canvas && ( viewMode === 'split' || ( viewMode === 'detail' && ! hasSelection ) );

	return (
		<div className={ layoutClasses }>
			{ /* Sidebar - always visible */ }
			<div className="activitypub-panel-layout__sidebar">{ sidebar }</div>

			{ /* Main content area */ }
			<div className="activitypub-panel-layout__main">
				{ /* Content panel (list/grid view) */ }
				{ showContent && content && <div className="activitypub-panel-layout__content">{ content }</div> }

				{ /* Detail panel */ }
				{ showDetail && detail && <div className="activitypub-panel-layout__detail">{ detail }</div> }

				{ /* Canvas/Preview area */ }
				{ showCanvas && (
					<div className="activitypub-panel-layout__canvas">
						<div className="edit-site-resizable-frame__inner-content">{ canvas }</div>
					</div>
				) }
			</div>
		</div>
	);
}

interface PanelLayoutColumnProps {
	children: React.ReactNode;
	width?: number | string;
	className?: string;
}

export function PanelLayoutColumn( { children, width, className = '' }: PanelLayoutColumnProps ) {
	const style = width ? { flexBasis: width, maxWidth: width } : {};

	return (
		<div className={ classnames( 'activitypub-panel-layout__column', className ) } style={ style }>
			{ children }
		</div>
	);
}
