import { __ } from '@wordpress/i18n';

/**
 * Drop zone component for drag and drop functionality.
 *
 * @param {Object} props Component props.
 * @param {string} props.zoneId The unique ID for this drop zone.
 * @param {boolean} props.isActive Whether this zone is currently active.
 * @param {Function} props.onDrop Drop handler function.
 * @param {Function} props.setDraggedOver Function to set dragged over state.
 * @returns {React.JSX.Element} The drop zone component.
 */
const DropZone = ( { zoneId, isActive, onDrop, setDraggedOver } ) => {
	const dragHandlers = {
		onDragOver: ( e ) => {
			e.preventDefault();
			setDraggedOver( zoneId );
		},
		onDragEnter: ( e ) => {
			e.preventDefault();
			setDraggedOver( zoneId );
		},
		onDragLeave: () => setDraggedOver( null ),
		onDrop,
	};

	if ( isActive ) {
		return (
			<div
				{ ...dragHandlers }
				className="activitypub-attachment-dropzone activitypub-attachment-dropzone--active"
			>
				{ __( 'Drop here', 'activitypub' ) }
			</div>
		);
	}

	return (
		<div
			{ ...dragHandlers }
			className="activitypub-attachment-dropzone activitypub-attachment-dropzone--inactive"
		/>
	);
};

export default DropZone;
