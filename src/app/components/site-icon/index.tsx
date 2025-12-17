/**
 * External dependencies
 */
import type { ReactNode } from 'react';
import clsx from 'clsx';

/**
 * WordPress dependencies
 */
import { Icon } from '@wordpress/components';
import { wordpress } from '@wordpress/icons';
import { useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { store as coreStore } from '@wordpress/core-data';
import type { UnstableBase } from '@wordpress/core-data';

/**
 * Internal dependencies
 */
import './style.scss';

interface SiteIconProps {
	className?: string;
}

interface SiteIconData {
	isRequestingSite: boolean;
	siteIconUrl: string | undefined;
}

function SiteIcon( { className }: SiteIconProps ): ReactNode {
	const { isRequestingSite, siteIconUrl }: SiteIconData = useSelect( ( select ): SiteIconData => {
		const { getEntityRecord } = select( coreStore );
		const siteData: UnstableBase | undefined = getEntityRecord< UnstableBase >(
			'root',
			'__unstableBase',
			undefined
		);

		return {
			isRequestingSite: ! siteData,
			siteIconUrl: siteData?.site_icon_url,
		};
	}, [] );

	let icon: ReactNode = <Icon className="site-icon__icon" icon={ wordpress } size={ 32 } />;

	if ( isRequestingSite ) {
		icon = <div className="site-icon__image" />;
	} else if ( siteIconUrl ) {
		icon = <img className="site-icon__image" alt={ __( 'Site Icon', 'activitypub' ) } src={ siteIconUrl } />;
	}

	return <div className={ clsx( className, 'site-icon' ) }>{ icon }</div>;
}

export default SiteIcon;
