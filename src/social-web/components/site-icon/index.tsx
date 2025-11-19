/**
 * External dependencies
 */
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

function SiteIcon( { className }: SiteIconProps ) {
	const { isRequestingSite, siteIconUrl } = useSelect( ( select ) => {
		const { getEntityRecord } = select( coreStore );
		const siteData = getEntityRecord< UnstableBase >( 'root', '__unstableBase', undefined );

		return {
			isRequestingSite: ! siteData,
			siteIconUrl: siteData?.site_icon_url,
		};
	}, [] );

	let icon = null;

	if ( isRequestingSite && ! siteIconUrl ) {
		icon = <div className="site-icon__image" />;
	} else {
		icon = siteIconUrl ? (
			<img className="site-icon__image" alt={ __( 'Site Icon', 'activitypub' ) } src={ siteIconUrl } />
		) : (
			<Icon className="site-icon__icon" icon={ wordpress } size={ 48 } />
		);
	}

	return <div className={ clsx( className, 'site-icon' ) }>{ icon }</div>;
}

export default SiteIcon;
