/**
 * Inspector Default View
 *
 * Persistent sidebar shown when no post is selected
 * Displays trending tags and other contextual information
 */

import { __ } from '@wordpress/i18n';
import { PopularTags } from '../../components/popular-tags';
import SidebarDescription from '../../components/sidebar-description';
import './inspector-default.scss';

export default function InspectorDefault() {
	return (
		<div className="activitypub-inspector-default">
			<div className="activitypub-inspector-default-section">
				<h2 className="activitypub-inspector-default-title">{ __( 'Welcome to your Feed', 'activitypub' ) }</h2>
				<SidebarDescription />
			</div>

			<div className="activitypub-inspector-default-section">
				<h2 className="activitypub-inspector-default-title">{ __( 'Trending', 'activitypub' ) }</h2>
				<PopularTags />
			</div>
		</div>
	);
}
