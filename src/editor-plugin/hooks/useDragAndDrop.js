import { useState, useCallback } from '@wordpress/element';

/**
 * Custom hook for managing drag and drop functionality.
 *
 * @param {Function} onReorder Callback function called when items are reordered.
 * @returns {Object} Drag and drop state and handlers.
 */
const useDragAndDrop = ( onReorder ) => {
	const [ draggedItem, setDraggedItem ] = useState( null );
	const [ draggedOver, setDraggedOver ] = useState( null );

	/**
	 * Handles drag start.
	 *
	 * @param {Event} e The drag event.
	 * @param {number} attachmentId The attachment being dragged.
	 */
	const handleDragStart = useCallback( ( e, attachmentId ) => {
		setDraggedItem( attachmentId );
		e.dataTransfer.effectAllowed = 'move';
	}, [] );

	/**
	 * Handles drag over.
	 *
	 * @param {Event} e The drag event.
	 * @param {number} attachmentId The attachment being dragged over.
	 */
	const handleDragOver = useCallback( ( e, attachmentId ) => {
		e.preventDefault();
		setDraggedOver( attachmentId );
	}, [] );

	/**
	 * Handles drag enter.
	 *
	 * @param {Event} e The drag event.
	 * @param {number} attachmentId The attachment being entered.
	 */
	const handleDragEnter = useCallback( ( e, attachmentId ) => {
		e.preventDefault();
		setDraggedOver( attachmentId );
	}, [] );

	/**
	 * Handles drag leave.
	 */
	const handleDragLeave = useCallback( () => {
		setDraggedOver( null );
	}, [] );

	/**
	 * Handles drop.
	 *
	 * @param {Event} e The drop event.
	 * @param {number} targetId The attachment being dropped on.
	 * @param {number} dropPosition Position relative to target: -1 (before), 0 (on), 1 (after).
	 * @param {Object} selectionInfo Object containing selection state info.
	 * @param {Array} selectionInfo.currentSelection Current effective selection array.
	 * @param {boolean} selectionInfo.isAutoMode Whether in auto mode.
	 * @param {Array} selectionInfo.autoSelection Auto-selected attachments array.
	 * @param {Array} selectionInfo.manualSelection Manual selection array.
	 */
	const handleDrop = useCallback(
		( e, targetId, dropPosition = 0, selectionInfo ) => {
			e.preventDefault();

			if ( ! draggedItem ) {
				setDraggedItem( null );
				setDraggedOver( null );
				return;
			}

			// When user drags, switch from auto mode to manual mode if needed
			// Start with current effective selection as the baseline
			const { currentSelection, isAutoMode, autoSelection } = selectionInfo;
			const baseSelection = isAutoMode ? [ ...autoSelection ] : [ ...currentSelection ];
			const newSelection = [ ...baseSelection ];

			// Remove dragged item from selection
			const draggedIndex = newSelection.indexOf( draggedItem );
			if ( draggedIndex !== -1 ) {
				newSelection.splice( draggedIndex, 1 );
			}

			if ( targetId === null ) {
				// Dropped at the beginning or end
				if ( dropPosition === -1 ) {
					newSelection.unshift( draggedItem ); // Add to beginning
				} else {
					newSelection.push( draggedItem ); // Add to end
				}
			} else {
				// Find target position in the selection array
				let targetIndex = newSelection.indexOf( targetId );
				if ( targetIndex === -1 ) {
					// Target not in selection, add to end
					newSelection.push( draggedItem );
				} else {
					// Insert relative to target
					if ( dropPosition >= 0 ) {
						targetIndex++; // Insert after target
					}
					newSelection.splice( targetIndex, 0, draggedItem );
				}
			}

			onReorder( newSelection );
			setDraggedItem( null );
			setDraggedOver( null );
		},
		[ draggedItem, onReorder ]
	);

	/**
	 * Creates drag handlers for an attachment item.
	 *
	 * @param {number} attachmentId The attachment ID.
	 * @returns {Object} Object with drag event handlers.
	 */
	const createDragHandlers = useCallback(
		( attachmentId ) => ( {
			onDragOver: ( e ) => handleDragOver( e, attachmentId ),
			onDragEnter: ( e ) => handleDragEnter( e, attachmentId ),
			onDragLeave: handleDragLeave,
			onDrop: ( e ) => handleDrop( e, attachmentId, 0 ),
		} ),
		[ handleDragOver, handleDragEnter, handleDragLeave, handleDrop ]
	);

	/**
	 * Creates drop handler for drop zones.
	 *
	 * @param {number|null} targetId The target attachment ID or null for start/end zones.
	 * @param {number} dropPosition The drop position relative to target.
	 * @returns {Function} Drop event handler.
	 */
	const createDropHandler = useCallback(
		( targetId, dropPosition ) => ( e, selectionInfo ) => {
			handleDrop( e, targetId, dropPosition, selectionInfo );
		},
		[ handleDrop ]
	);

	return {
		draggedItem,
		draggedOver,
		handleDragStart,
		setDraggedOver,
		createDragHandlers,
		createDropHandler,
	};
};

export default useDragAndDrop;
