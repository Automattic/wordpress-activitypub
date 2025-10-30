/**
 * Following Stage
 *
 * Main following list view
 */

import { Page } from '../../components/page';

interface FollowingStageProps {
	onSelectItem: ( id: string ) => void;
}

export default function FollowingStage( { onSelectItem }: FollowingStageProps ) {
	return (
		<Page title="Following" subTitle="Accounts you follow" hasPadding={ true } contentWidth="constrained">
			<p>Following list coming soon...</p>
		</Page>
	);
}
