/**
 * Interactions Route
 */
import { useState } from '@wordpress/element';
import InteractionsStage from './stage';
import InteractionInspector from './inspector';

export default function Interactions() {
	const [ selectedId, setSelectedId ] = useState< string | null >( null );

	return (
		<>
			<InteractionsStage onSelectItem={ setSelectedId } />
			{ selectedId && (
				<div className="inspector-region">
					<InteractionInspector id={ selectedId } onClose={ () => setSelectedId( null ) } />
				</div>
			) }
		</>
	);
}
