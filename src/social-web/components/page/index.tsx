/**
 * Page Component
 *
 * A reusable page wrapper that provides consistent header layout and content structure.
 */

import { ReactNode } from 'react';
import clsx from 'clsx';
import SiteHub from '../site-hub';
import './style.scss';

interface PageProps {
	title: string;
	subTitle?: string;
	badges?: ReactNode;
	actions?: ReactNode;
	breadcrumbs?: ReactNode;
	hasPadding?: boolean;
	hasBorder?: boolean;
	contentWidth?: 'default' | 'full' | 'constrained';
	children: ReactNode;
	// Mobile navigation props
	onNavigateBack?: () => void;
	selectedItemId?: string | number | null;
}

export function Page( {
	title,
	subTitle,
	badges,
	actions,
	breadcrumbs,
	hasPadding = true,
	hasBorder = false,
	contentWidth = 'default',
	children,
	onNavigateBack,
	selectedItemId,
}: PageProps ) {
	return (
		<div className="page">
			{ /* Mobile header - only shown on mobile, hidden on desktop */ }
			{ onNavigateBack && (
				<div className="page-mobile-header">
					<SiteHub onNavigateBack={ onNavigateBack } selectedItemId={ selectedItemId } />
				</div>
			) }

			<header className={ clsx( 'header', { 'has-border': hasBorder } ) }>
				{ breadcrumbs && <div className="breadcrumbs">{ breadcrumbs }</div> }

				<div className="title-row">
					<div className="title-group">
						<h1 className="title">{ title }</h1>
						{ badges && <div className="badges">{ badges }</div> }
					</div>
					{ actions && <div className="actions">{ actions }</div> }
				</div>

				{ subTitle && <p className="sub-title">{ subTitle }</p> }
			</header>

			<div
				className={ clsx( 'content', {
					padded: hasPadding,
					constrained: contentWidth === 'constrained',
					full: contentWidth === 'full',
				} ) }
			>
				{ children }
			</div>
		</div>
	);
}
