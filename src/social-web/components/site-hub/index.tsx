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

	// On mobile, when inspector or stage is showing, clicking site icon navigates back
	const isMobileBackEnabled = onNavigateBack && ( selectedItemId || window.location.hash !== '#/' );

	const handleIconClick = ( e: React.MouseEvent ) => {
		if ( isMobileBackEnabled ) {
			e.preventDefault();
			onNavigateBack?.();
		}
	};

	return (
		<div className="site-hub">
			<HStack justify="flex-start" spacing="0">
				<div className="site-hub__icon-container">
					<Button
						__next40pxDefaultSize
						href={ isMobileBackEnabled ? undefined : '/wp-admin/' }
						onClick={ isMobileBackEnabled ? handleIconClick : undefined }
						label={
							isMobileBackEnabled
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
