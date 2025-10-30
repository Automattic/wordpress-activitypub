/**
 * Dashboard Stage
 *
 * Main dashboard view showing overview statistics
 */

import { Card, CardBody } from '@wordpress/components';
import { Page } from '../../components/page';
import { useSocialWebData } from '../../hooks/use-social-web-data';

export default function DashboardStage() {
	const { items: followers } = useSocialWebData( 'followers' );
	const { items: following } = useSocialWebData( 'following' );
	const { items: interactions } = useSocialWebData( 'interactions' );

	return (
		<Page
			title="Dashboard"
			subTitle="Overview of your ActivityPub network"
			hasPadding={ true }
			contentWidth="constrained"
		>
			<div
				style={ {
					display: 'grid',
					gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))',
					gap: 'var(--wpds-spacing-60, 24px)',
				} }
			>
				<Card>
					<CardBody>
						<h3>Followers</h3>
						<p style={ { fontSize: '32px', margin: '8px 0' } }>{ followers?.length || 0 }</p>
					</CardBody>
				</Card>

				<Card>
					<CardBody>
						<h3>Following</h3>
						<p style={ { fontSize: '32px', margin: '8px 0' } }>{ following?.length || 0 }</p>
					</CardBody>
				</Card>

				<Card>
					<CardBody>
						<h3>Interactions</h3>
						<p style={ { fontSize: '32px', margin: '8px 0' } }>{ interactions?.length || 0 }</p>
					</CardBody>
				</Card>
			</div>
		</Page>
	);
}
