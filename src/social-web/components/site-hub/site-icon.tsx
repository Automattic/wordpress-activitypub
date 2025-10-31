/**
 * WordPress dependencies
 */
import React from 'react';
import { Icon } from '@wordpress/components';
import { wordpress } from '@wordpress/icons';
import clsx from 'clsx';

interface SiteIconProps {
	className?: string;
	siteIconUrl?: string;
}

function SiteIcon( { className, siteIconUrl }: SiteIconProps ) {
	const icon = siteIconUrl ? (
		<img className="site-icon__image" alt="Site Icon" src={ siteIconUrl } />
	) : (
		<Icon className="site-icon__icon" icon={ wordpress } size={ 48 } />
	);

	return <div className={ clsx( className, 'site-icon' ) }>{ icon }</div>;
}

export default SiteIcon;
