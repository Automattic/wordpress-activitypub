/**
 * WordPress dependencies
 */
import React, { useContext } from 'react';
import { __ } from '@wordpress/i18n';
import { isRTL } from '@wordpress/i18n';
import {
	__experimentalHStack as HStack,
	__experimentalVStack as VStack,
	__experimentalHeading as Heading,
	Button,
	Icon,
} from '@wordpress/components';
import { chevronLeftSmall, chevronRightSmall, arrowLeft } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import { NavigationContext } from './navigation-context';

interface SidebarNavigationScreenProps {
	isRoot?: boolean;
	title: string;
	description?: string;
	content: React.ReactNode;
	footer?: React.ReactNode;
	actions?: React.ReactNode;
	backPath?: string;
	backLabel?: string;
}

export default function SidebarNavigationScreen( {
	isRoot = false,
	title,
	description,
	content,
	footer,
	actions,
	backPath,
	backLabel,
}: SidebarNavigationScreenProps ) {
	const { navigate } = useContext( NavigationContext );

	const handleBack = () => {
		if ( backPath ) {
			navigate( backPath, 'back' );
		}
	};

	return (
		<VStack className="edit-site-sidebar-navigation-screen" spacing={ 0 } justify="flex-start">
			<div className="edit-site-sidebar-navigation-screen__header">
				<HStack
					spacing={ 3 }
					alignment="flex-start"
					className="edit-site-sidebar-navigation-screen__title-icon"
				>
					{ ! isRoot && backPath && (
						<Button
							__next40pxDefaultSize
							icon={ isRTL() ? chevronRightSmall : chevronLeftSmall }
							label={ backLabel || __( 'Back', 'activitypub' ) }
							onClick={ handleBack }
							className="edit-site-sidebar-button is-small has-icon"
							size="compact"
						/>
					) }
					<Heading
						className="edit-site-sidebar-navigation-screen__title"
						color="#e0e0e0"
						level={ 1 }
						size={ 20 }
					>
						{ title }
					</Heading>
					{ actions && <div className="edit-site-sidebar-navigation-screen__actions">{ actions }</div> }
				</HStack>
				{ description && (
					<div className="edit-site-sidebar-navigation-screen__description">{ description }</div>
				) }
			</div>
			<div className="edit-site-sidebar-navigation-screen__content">{ content }</div>
			{ footer && <div className="edit-site-sidebar-navigation-screen__footer">{ footer }</div> }
		</VStack>
	);
}
