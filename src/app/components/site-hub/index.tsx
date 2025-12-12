/**
 * Site Hub Component
 *
 * Displays site icon, title, and command palette toggle.
 * SiteHubMobile provides a mobile-specific header with back navigation and menu button.
 */

/**
 * WordPress dependencies
 */
import { useSelect, useDispatch } from '@wordpress/data';
import { Button, __experimentalHStack as HStack, VisuallyHidden } from '@wordpress/components';
import { __, isRTL } from '@wordpress/i18n';
import { store as coreStore } from '@wordpress/core-data';
import { decodeEntities } from '@wordpress/html-entities';
import { search, chevronLeft, chevronRight, menu } from '@wordpress/icons';
import { store as commandsStore } from '@wordpress/commands';
import { displayShortcut } from '@wordpress/keycodes';
import { filterURLForDisplay } from '@wordpress/url';
import { forwardRef } from '@wordpress/element';
import type { UnstableBase } from '@wordpress/core-data';

/**
 * Internal dependencies
 */
import SiteIcon from '../site-icon';
import './style.scss';
import { ForwardedRef, ForwardRefExoticComponent } from 'react';

function SiteHub() {
	const { homeUrl, siteTitle } = useSelect( ( select ) => {
		const { getEntityRecord } = select( coreStore );
		const _base = getEntityRecord< UnstableBase >( 'root', '__unstableBase' );
		return {
			homeUrl: _base?.home,
			siteTitle: ! _base?.name && !! _base?.url ? filterURLForDisplay( _base?.url ) : _base?.name,
		};
	}, [] );

	const { open: openCommandCenter } = useDispatch( commandsStore );

	return (
		<div className="site-hub">
			<HStack justify="flex-start" spacing="0">
				<div className="site-hub__icon-container">
					<Button
						__next40pxDefaultSize
						href="/wp-admin/"
						label={ __( 'Go to the Dashboard', 'activitypub' ) }
						className="site-hub__icon-button"
						style={ {
							transform: 'scale(0.5333) translateX(-4px)', // Offset to position the icon 12px from viewport edge
							borderRadius: 4,
						} }
					>
						<SiteIcon className="site-hub__icon" />
					</Button>
				</div>

				<HStack>
					<div className="site-hub__title">
						<Button variant="link" href={ homeUrl } target="_blank">
							{ decodeEntities( siteTitle ) }
							<VisuallyHidden as="span">
								{
									/* translators: accessibility text */
									__( '(opens in a new tab)', 'activitypub' )
								}
							</VisuallyHidden>
						</Button>
					</div>
					<HStack spacing={ 0 } expanded={ false } className="site-hub__actions">
						<Button
							size="compact"
							className="site-hub__command-button"
							icon={ search }
							onClick={ () => openCommandCenter() }
							label={ __( 'Open command palette', 'activitypub' ) }
							shortcut={ displayShortcut.primary( 'k' ) }
						/>
					</HStack>
				</HStack>
			</HStack>
		</div>
	);
}

/**
 * Mobile Site Hub props.
 */
interface SiteHubMobileProps {
	onMenuClick: () => void;
	title?: string;
}

/**
 * Mobile Site Hub Component
 *
 * Provides a mobile-specific header with:
 * - Back button (chevron) that navigates to dashboard
 * - Title showing the current navigation context
 * - Menu button (hamburger) to open sidebar drawer
 */
export const SiteHubMobile: ForwardRefExoticComponent< SiteHubMobileProps > = forwardRef<
	HTMLDivElement,
	SiteHubMobileProps
>( function SiteHubMobile( { onMenuClick, title }: SiteHubMobileProps, ref: ForwardedRef< HTMLDivElement > ) {
	return (
		<div className="site-hub-mobile" ref={ ref }>
			<HStack spacing={ 2 } justify="flex-start">
				<Button
					icon={ isRTL() ? chevronRight : chevronLeft }
					href="/wp-admin/"
					label={ __( 'Go to the Dashboard', 'activitypub' ) }
					className="site-hub-mobile__button"
					size="compact"
				/>
				<span className="site-hub-mobile__title">{ title || __( 'Social Web', 'activitypub' ) }</span>
			</HStack>
			<Button
				icon={ menu }
				onClick={ onMenuClick }
				label={ __( 'Open menu', 'activitypub' ) }
				className="site-hub-mobile__button"
				size="compact"
			/>
		</div>
	);
} );

export default SiteHub;
