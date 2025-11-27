/**
 * Site Hub Component
 *
 * Displays site icon, title, and command palette toggle
 */

/**
 * WordPress dependencies
 */
import { useSelect } from '@wordpress/data';
import { Button, __experimentalHStack as HStack, VisuallyHidden } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { store as coreStore } from '@wordpress/core-data';
import { decodeEntities } from '@wordpress/html-entities';
import { search } from '@wordpress/icons';
import { filterURLForDisplay } from '@wordpress/url';
import type { UnstableBase } from '@wordpress/core-data';
import { useState, useEffect } from '@wordpress/element';

/**
 * Internal dependencies
 */
import SiteIcon from '../site-icon';
import './style.scss';

interface SiteHubProps {
	onNavigateBack?: () => void;
	selectedItemId?: string | number | null;
}

function SiteHub( { onNavigateBack, selectedItemId }: SiteHubProps = {} ) {
	const { homeUrl, siteTitle } = useSelect( ( select ) => {
		const { getEntityRecord } = select( coreStore );
		const _base = getEntityRecord< UnstableBase >( 'root', '__unstableBase' );
		return {
			homeUrl: _base?.home,
			siteTitle: ! _base?.name && !! _base?.url ? filterURLForDisplay( _base?.url ) : _base?.name,
		};
	}, [] );

	// Track hash changes to trigger re-renders
	const [ currentHash, setCurrentHash ] = useState( window.location.hash );

	useEffect( () => {
		const handleHashChange = () => {
			setCurrentHash( window.location.hash );
		};

		window.addEventListener( 'hashchange', handleHashChange );
		return () => {
			window.removeEventListener( 'hashchange', handleHashChange );
		};
	}, [] );

	// On mobile, determine if we should show back functionality
	// Similar to WordPress Site Editor's approach: check if we're at the "root" view
	const isAtRoot = ! currentHash || currentHash === '#' || currentHash === '#/';
	const shouldEnableBack = onNavigateBack && ! isAtRoot;

	const handleIconClick = ( e: React.MouseEvent ) => {
		if ( shouldEnableBack ) {
			e.preventDefault();
			onNavigateBack();
		}
	};

	return (
		<div className="site-hub">
			<HStack justify="flex-start" spacing="0">
				<div className="site-hub__icon-container">
					<Button
						__next40pxDefaultSize
						href={ shouldEnableBack ? undefined : '/wp-admin/' }
						onClick={ shouldEnableBack ? handleIconClick : undefined }
						label={
							shouldEnableBack
								? __( 'Navigate back', 'activitypub' )
								: __( 'Go to the Dashboard', 'activitypub' )
						}
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
							label={ __( 'Open command palette', 'activitypub' ) }
						/>
					</HStack>
				</HStack>
			</HStack>
		</div>
	);
}

export default SiteHub;
