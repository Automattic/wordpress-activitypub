/**
 * Interaction Inspector
 *
 * Detail view for a single interaction
 */

import { Button } from '@wordpress/components';
import { Page } from '../../components/page';

interface InteractionInspectorProps {
	id: string;
	onClose: () => void;
}

export default function InteractionInspector( { id, onClose }: InteractionInspectorProps ) {
	return (
		<Page
			title="Interaction Details"
			hasPadding={ true }
			actions={
				<Button size="small" onClick={ onClose }>
					Close
				</Button>
			}
		>
			<p>Interaction details for ID: { id }</p>
		</Page>
	);
}
