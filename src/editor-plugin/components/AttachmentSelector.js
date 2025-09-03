import { Spinner, __experimentalVStack as VStack } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { useEntityRecords } from '@wordpress/core-data';
import { __ } from '@wordpress/i18n';
import { useState, useCallback, useMemo, useEffect } from '@wordpress/element';

import DropZone from './DropZone';
import AttachmentItem from './AttachmentItem';

/**
 * Attachment selection component for choosing which attachments to federate.
 *
 * @param {Object} props Component props.
 * @param {Array} props.selectedAttachments Array of selected attachment IDs.
 * @param {Function} props.onSelectionChange Callback for selection changes.
 * @param {number} props.maxAttachments Maximum number of attachments allowed.
 * @returns {React.JSX.Element|null} The attachment selection component.
 */
const AttachmentSelector = ( { selectedAttachments, onSelectionChange, maxAttachments } ) => {
	const [ draggedItem, setDraggedItem ] = useState( null );
	const [ draggedOver, setDraggedOver ] = useState( null );

	// Get post content to extract attachment IDs
	const postContent = useSelect( ( select ) => {
		return select( 'core/editor' ).getEditedPostContent();
	}, [] );

	// Extract attachment IDs from post content
	const contentAttachmentIds = useMemo( () => {
		if ( ! postContent ) return [];

		const ids = [];
		// Match image blocks: {"id":1045}
		const imageMatches = postContent.match( /"id":(\d+)/g );
		if ( imageMatches ) {
			imageMatches.forEach( ( match ) => {
				const id = parseInt( match.replace( /"id":/, '' ), 10 );
				if ( id && ! ids.includes( id ) ) {
					ids.push( id );
				}
			} );
		}

		return ids;
	}, [ postContent ] );

	// Fetch all attachments referenced in post content - images only
	const { records: attachments, isResolving: isLoadingAttachments } = useEntityRecords(
		'root',
		'media',
		contentAttachmentIds.length > 0
			? {
					include: contentAttachmentIds,
					per_page: -1,
					media_type: 'image',
			  }
			: { per_page: 0 } // Empty query instead of null
	);

	// Get the global max image attachments setting
	const globalMaxAttachments = useSelect( ( select ) => {
		const option = select( 'core' ).getEntityRecord( 'root', 'site' );
		return option?.activitypub_max_image_attachments || 4; // Default to 4 if not set
	}, [] );

	// Filter selected attachments to only include attachments that exist in our fetched list
	// Handle the case where selectedAttachments is undefined (never been set)
	const effectiveSelection = selectedAttachments
		? selectedAttachments.filter( ( id ) => attachments?.some( ( attachment ) => attachment.id === id ) )
		: [];

	// Filter unselected attachments once to avoid redundant computation
	const unselectedAttachments =
		attachments?.filter( ( attachment ) => ! effectiveSelection.includes( attachment.id ) ) || [];

	// Auto-select first X images when no selection has ever been set (undefined) and images are available
	// Don't auto-select if user has intentionally set empty selection ([])
	useEffect( () => {
		if ( selectedAttachments === undefined && attachments && attachments.length > 0 && globalMaxAttachments > 0 ) {
			// Auto-select up to globalMaxAttachments images
			const autoSelectedIds = attachments.slice( 0, globalMaxAttachments ).map( ( attachment ) => attachment.id );
			onSelectionChange( autoSelectedIds );
		}
	}, [ selectedAttachments, attachments, globalMaxAttachments, onSelectionChange ] );

	/**
	 * Handles attachment selection toggle.
	 *
	 * @param {number} attachmentId The attachment ID to toggle.
	 */
	const handleToggleAttachment = useCallback(
		( attachmentId ) => {
			const newSelection = effectiveSelection.includes( attachmentId )
				? effectiveSelection.filter( ( id ) => id !== attachmentId )
				: [ ...effectiveSelection, attachmentId ];

			onSelectionChange( newSelection );
		},
		[ effectiveSelection, onSelectionChange ]
	);

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
		e.dataTransfer.dropEffect = 'move';
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
	 */
	const handleDrop = useCallback(
		( e, targetId, dropPosition = 0 ) => {
			e.preventDefault();

			if ( ! draggedItem ) {
				setDraggedItem( null );
				setDraggedOver( null );
				return;
			}

			// Work with current selection to reorder
			const newSelection = [ ...effectiveSelection ];

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

			onSelectionChange( newSelection );
			setDraggedItem( null );
			setDraggedOver( null );
		},
		[ draggedItem, effectiveSelection, onSelectionChange, attachments ]
	);

	if ( isLoadingAttachments ) {
		return (
			<div className="activitypub-attachment-selector__loading">
				<Spinner />
				<p>{ __( 'Loading images...', 'activitypub' ) }</p>
			</div>
		);
	}

	if ( contentAttachmentIds.length === 0 || ! attachments || attachments.length === 0 ) {
		return (
			<div className="activitypub-attachment-selector__empty-state">
				<p>{ __( 'Images from your post will appear here for selection.', 'activitypub' ) }</p>
			</div>
		);
	}

	// Create drag handlers for attachment items
	const createDragHandlers = ( attachmentId ) => ( {
		onDragOver: ( e ) => handleDragOver( e, attachmentId ),
		onDragEnter: ( e ) => handleDragEnter( e, attachmentId ),
		onDragLeave: handleDragLeave,
		onDrop: ( e ) => handleDrop( e, attachmentId ),
	} );

	return (
		<VStack spacing={ 1 } className="activitypub-attachment-selector">
			{ effectiveSelection.length > 0 && (
				<>
					{ /* Drop zone at start */ }
					{ draggedItem && (
						<DropZone
							zoneId="drop-zone-start"
							isActive={ draggedOver === 'drop-zone-start' }
							onDrop={ ( e ) => handleDrop( e, null, -1 ) }
							setDraggedOver={ setDraggedOver }
						/>
					) }

					{ /* Selected attachments */ }
					{ effectiveSelection.map( ( attachmentId, index ) => {
						const attachment = attachments.find( ( a ) => a.id === attachmentId );
						if ( ! attachment ) return null;

						return (
							<div key={ attachment.id }>
								<AttachmentItem
									attachment={ attachment }
									isSelected={ true }
									selectionIndex={ index }
									isDragging={ draggedItem === attachment.id }
									isDraggedOver={ draggedOver === attachment.id }
									onToggle={ () => handleToggleAttachment( attachment.id ) }
									onDragStart={ ( e ) => handleDragStart( e, attachment.id ) }
									dragHandlers={ createDragHandlers( attachment.id ) }
								/>

								{ /* Drop zone after each item (except last) */ }
								{ draggedItem && index < effectiveSelection.length - 1 && (
									<DropZone
										zoneId={ `drop-zone-after-${ attachment.id }` }
										isActive={ draggedOver === `drop-zone-after-${ attachment.id }` }
										onDrop={ ( e ) => handleDrop( e, attachment.id, 1 ) }
										setDraggedOver={ setDraggedOver }
									/>
								) }
							</div>
						);
					} ) }

					{ /* Drop zone at end */ }
					{ draggedItem && (
						<DropZone
							zoneId="drop-zone-end"
							isActive={ draggedOver === 'drop-zone-end' }
							onDrop={ ( e ) => handleDrop( e, null, 1 ) }
							setDraggedOver={ setDraggedOver }
						/>
					) }
				</>
			) }

			{ /* Section separator and unselected attachments */ }
			{ unselectedAttachments.length > 0 && (
				<>
					{ effectiveSelection.length > 0 && <div className="activitypub-attachment-selector__separator" /> }

					{ /* Unselected attachments */ }
					{ unselectedAttachments.map( ( attachment ) => (
						<AttachmentItem
							key={ attachment.id }
							attachment={ attachment }
							isSelected={ false }
							selectionIndex={ -1 }
							isDragging={ false }
							isDraggedOver={ false }
							onToggle={ () => handleToggleAttachment( attachment.id ) }
							onDragStart={ () => {} }
							dragHandlers={ {} }
						/>
					) ) }
				</>
			) }
		</VStack>
	);
};

export default AttachmentSelector;
