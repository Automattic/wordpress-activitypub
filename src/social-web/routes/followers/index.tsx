/**
 * Followers Route
 */
import { useState } from '@wordpress/element';
import FollowersStage from './stage';
import FollowerInspector from './inspector';

export default function Followers() {
	const [ selectedId, setSelectedId ] = useState< string | null >( null );

	return (
		<>
			<FollowersStage onSelectItem={ setSelectedId } />
			{ selectedId && (
				<div className="inspector-region">
					<FollowerInspector id={ selectedId } onClose={ () => setSelectedId( null ) } />
				</div>
			) }
		</>
	);
}
