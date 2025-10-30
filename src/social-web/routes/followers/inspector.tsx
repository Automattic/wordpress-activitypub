/**
 * Follower Inspector
 *
 * Detail view for a single follower in the side panel
 */

import { Button, Card, CardBody } from '@wordpress/components';
import { Page } from '../../components/page';
import { useSocialWebData } from '../../hooks/use-social-web-data';

interface FollowerInspectorProps {
	id: string;
	onClose: () => void;
}

export default function FollowerInspector( { id, onClose }: FollowerInspectorProps ) {
	const { items: follower, isLoading } = useSocialWebData( 'followers', id );

	if ( isLoading ) {
		return <div>Loading...</div>;
	}

	if ( ! follower ) {
		return <div>Follower not found</div>;
	}

	return (
		<Page
			title={ follower.name }
			hasPadding={ true }
			actions={
				<Button size="small" onClick={ onClose }>
					Close
				</Button>
			}
		>
			<Card>
				<CardBody>
					<h3>Overview</h3>
					<p>
						<strong>URL:</strong> { follower.url }
					</p>
					<p>
						<strong>Followers:</strong> { follower.followers }
					</p>
				</CardBody>
			</Card>

			<Card>
				<CardBody>
					<h3>Recent Activity</h3>
					<p>Activity timeline coming soon...</p>
				</CardBody>
			</Card>
		</Page>
	);
}
