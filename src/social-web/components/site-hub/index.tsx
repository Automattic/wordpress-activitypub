/**
 * Site Hub Component
 *
 * Displays site icon, title, and command palette toggle
 */

/**
 * WordPress dependencies
 */
import { useSelect, useDispatch } from '@wordpress/data';
import {
	Button,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis -- HStack is widely used in core and stable in practice
	__experimentalHStack as HStack,
	VisuallyHidden,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { store as coreStore } from '@wordpress/core-data';
import { decodeEntities } from '@wordpress/html-entities';
import { search } from '@wordpress/icons';
import { store as commandsStore } from '@wordpress/commands';
import { displayShortcut } from '@wordpress/keycodes';
import { filterURLForDisplay } from '@wordpress/url';
import type { UnstableBase } from '@wordpress/core-data';

/**
 * Internal dependencies
 */
import SiteIcon from './site-icon';
import './style.scss';

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

export default SiteHub;
