/**
 * Menu Description Component
 *
 * Renders the description for a menu item.
 * Supports string, function, or component descriptions.
 */

/**
 * External dependencies
 */
import type { ComponentType, ReactNode } from 'react';

/**
 * Internal dependencies
 */
import type { MenuItemConfig } from './index';

interface MenuDescriptionProps {
	menuItem: MenuItemConfig;
}

export default function MenuDescription( { menuItem: { description } }: MenuDescriptionProps ): ReactNode | null {
	if ( ! description ) {
		return null;
	}

	if ( typeof description === 'string' ) {
		return <p className="sidebar-description">{ description }</p>;
	}

	const Description: ( () => ReactNode ) | ComponentType = description;

	return (
		<p className="sidebar-description">
			<Description />
		</p>
	);
}
