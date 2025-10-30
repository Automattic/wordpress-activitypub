/**
 * WordPress dependencies
 */
import React from 'react';
import { Icon } from '@wordpress/components';
import { wordpress } from '@wordpress/icons';
import classnames from 'classnames';

/**
 * Internal dependencies
 */
import type { SocialWebSettings } from '../types';

interface SiteIconProps {
	className?: string;
	siteIconUrl?: string;
}

function SiteIcon( { className, siteIconUrl }: SiteIconProps ) {
	const icon = siteIconUrl ? (
		<img className="edit-site-site-icon__image" alt="Site Icon" src={ siteIconUrl } />
	) : (
		<Icon className="edit-site-site-icon__icon" icon={ wordpress } size={ 48 } />
	);

	return <div className={ classnames( className, 'edit-site-site-icon' ) }>{ icon }</div>;
}

export default SiteIcon;
