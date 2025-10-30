/**
 * Interactions Stage
 *
 * Main interactions list view
 */

import { Page } from '../../components/page';

interface InteractionsStageProps {
	onSelectItem: ( id: string ) => void;
}

export default function InteractionsStage( { onSelectItem }: InteractionsStageProps ) {
	return (
		<Page
			title="Interactions"
			subTitle="Your ActivityPub interactions"
			hasPadding={ true }
			contentWidth="constrained"
		>
			<p>Interactions list coming soon...</p>
		</Page>
	);
}
