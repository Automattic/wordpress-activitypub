/**
 * Inspector Sidebar Component
 *
 * Persistent right sidebar shown when no post is selected
 * Displays contextual widgets like trending tags
 */

import { NavigationWidget, TrendingWidget } from './widgets';
import './style.scss';

/**
 * Available widgets that can be displayed in the inspector sidebar
 * Add new widgets here to make them available
 */
const WIDGETS = [
	NavigationWidget,
	TrendingWidget,
	// Add more widgets here as they're created
	// Example: WhoToFollowWidget, SuggestedPostsWidget, etc.
];

export default function InspectorSidebar() {
	return (
		<div className="inspector-sidebar">
			{ WIDGETS.map( ( Widget, index ) => (
				<Widget key={ index } />
			) ) }
		</div>
	);
}
