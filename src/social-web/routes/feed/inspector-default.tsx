/**
 * Inspector Default View
 *
 * Persistent sidebar shown when no post is selected
 * Displays trending tags and other contextual information
 */

import { Card, CardBody, CardHeader } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { PopularTags } from '../../components/popular-tags';
import './inspector-default.scss';

export default function InspectorDefault() {
	return (
		<div className="activitypub-inspector-default">
			<Card className="activitypub-inspector-default-card">
				<CardHeader>
					<h2 className="activitypub-inspector-default-title">
						{ __( 'Welcome to your Feed', 'activitypub' ) }
					</h2>
				</CardHeader>
				<CardBody>
					<p className="activitypub-inspector-default-description">
						{ __( 'Select a post from your feed to view details, comments, and more.', 'activitypub' ) }
					</p>
				</CardBody>
			</Card>

			<Card className="activitypub-inspector-default-card">
				<CardHeader>
					<h2 className="activitypub-inspector-default-title">{ __( 'Trending', 'activitypub' ) }</h2>
				</CardHeader>
				<CardBody>
					<PopularTags />
				</CardBody>
			</Card>
		</div>
	);
}
