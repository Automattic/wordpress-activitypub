/**
 * Trending Widget Component
 *
 * Displays trending/popular tags for the feed
 */

import { __ } from '@wordpress/i18n';
import { PopularTags } from '../../popular-tags';
import './trending-widget.scss';

export default function TrendingWidget() {
	return (
		<div className="inspector-widget trending-widget">
			<h2 className="inspector-widget__title">{ __( 'Trending', 'activitypub' ) }</h2>
			<div className="inspector-widget__content">
				<PopularTags />
			</div>
		</div>
	);
}
