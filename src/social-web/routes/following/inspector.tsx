/**
 * Following Inspector
 *
 * Detail view for a followed account
 */

import { Button } from '@wordpress/components';
import { Page } from '../../components/page';

interface FollowingInspectorProps {
	id: string;
	onClose: () => void;
}

export default function FollowingInspector( { id, onClose }: FollowingInspectorProps ) {
	return (
		<Page
			title="Following Details"
			hasPadding={ true }
			actions={
				<Button size="small" onClick={ onClose }>
					Close
				</Button>
			}
		>
			<p>Following details for ID: { id }</p>
		</Page>
	);
}
