/**
 * Following Route
 */
import { useState } from '@wordpress/element';
import FollowingStage from './stage';
import FollowingInspector from './inspector';

export default function Following() {
	const [ selectedId, setSelectedId ] = useState< string | null >( null );

	return (
		<>
			<FollowingStage onSelectItem={ setSelectedId } />
			{ selectedId && (
				<div className="inspector-region">
					<FollowingInspector id={ selectedId } onClose={ () => setSelectedId( null ) } />
				</div>
			) }
		</>
	);
}
