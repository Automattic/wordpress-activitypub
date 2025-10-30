/**
 * Page Component
 *
 * A reusable page wrapper that provides consistent header layout and content structure.
 */

import { ReactNode } from 'react';
import classNames from 'classnames';
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
}: PageProps ) {
	return (
		<div className="page">
			<header className={ classNames( 'header', { 'has-border': hasBorder } ) }>
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
				className={ classNames( 'content', {
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
