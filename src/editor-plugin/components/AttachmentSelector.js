import { store as editorStore } from '@wordpress/editor';
import { useSelect } from '@wordpress/data';
import { useEntityRecords } from '@wordpress/core-data';
import { __experimentalVStack as VStack } from '@wordpress/components';
import { useCallback, useMemo } from '@wordpress/element';

import DropZone from './DropZone';
import AttachmentItem from './AttachmentItem';
import LoadingState from './LoadingState';
import EmptyState from './EmptyState';
import useDragAndDrop from '../hooks/useDragAndDrop';

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
	// Drag and drop functionality
	const { draggedItem, draggedOver, handleDragStart, setDraggedOver, createDragHandlers, createDropHandler } =
		useDragAndDrop( onSelectionChange );

	const postId = useSelect( ( select ) => select( editorStore ).getCurrentPostId(), [] );
	const postStatus = useSelect( ( select ) => select( editorStore ).getCurrentPost()?.status, [] );
	const postContent = useSelect( ( select ) => select( 'core/editor' ).getEditedPostContent(), [] );
	const featuredImageId = useSelect(
		( select ) => select( 'core/editor' ).getEditedPostAttribute( 'featured_media' ),
		[]
	);

	// Extract attachment IDs from post content and featured image
	const contentAttachmentIds = useMemo( () => {
		const ids = [];

		// Add featured image first if it exists
		if ( featuredImageId && featuredImageId > 0 ) {
			ids.push( featuredImageId );
		}

		// Add content attachment IDs
		if ( postContent ) {
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
		}

		return ids;
	}, [ postContent, featuredImageId ] );

	// Fetch attachments by post_parent (traditional attachments)
	// Skip for new/auto-draft posts to avoid fetching unrelated attachments
	const shouldFetchParentAttachments = postId && postId > 0 && postStatus !== 'auto-draft';

	const { records: parentAttachments, isResolving: isLoadingParentAttachments } = useEntityRecords(
		'root',
		'media',
		{
			post_parent: postId,
			per_page: -1,
			orderby: 'menu_order',
			order: 'asc',
		},
		{ enabled: shouldFetchParentAttachments }
	);

	// Fetch content-referenced attachments and featured image if we have IDs
	const { records: contentAttachments, isResolving: isLoadingContentAttachments } = useEntityRecords(
		'root',
		'media',
		{
			include: contentAttachmentIds,
			per_page: -1,
		},
		{ enabled: contentAttachmentIds.length > 0 }
	);

	// Combine and deduplicate attachments
	const attachments = useMemo( () => {
		const combined = [];
		const seenIds = new Set();

		// Add content attachments (includes featured image)
		if ( contentAttachments ) {
			contentAttachments.forEach( ( attachment ) => {
				if ( ! seenIds.has( attachment.id ) ) {
					combined.push( attachment );
					seenIds.add( attachment.id );
				}
			} );
		}

		// Add parent attachments
		if ( parentAttachments ) {
			parentAttachments.forEach( ( attachment ) => {
				if ( ! seenIds.has( attachment.id ) ) {
					combined.push( attachment );
					seenIds.add( attachment.id );
				}
			} );
		}

		return combined;
	}, [ contentAttachments, parentAttachments ] );

	// Get auto-selected attachments (mimicking the backend logic)
	const autoSelectedAttachments = useMemo( () => {
		if ( ! attachments || attachments.length === 0 ) {
			return [];
		}

		const autoSelected = [];

		// Add featured image first if it exists
		if ( featuredImageId ) {
			autoSelected.push( featuredImageId );
		}

		// Add other attachments up to the limit
		attachments.forEach( ( attachment ) => {
			if ( autoSelected.length >= maxAttachments ) {
				return;
			}
			if ( ! autoSelected.includes( attachment.id ) ) {
				autoSelected.push( attachment.id );
			}
		} );

		return autoSelected.slice( 0, maxAttachments );
	}, [ attachments, maxAttachments, featuredImageId ] );

	// Three-state system:
	// null/undefined = Auto-selection active
	// [] = Manual selection of no attachments
	// [id1, id2...] = Manual selection of specific attachments
	const isAutoMode = selectedAttachments === null || selectedAttachments === undefined;
	const effectiveSelection = isAutoMode ? autoSelectedAttachments : selectedAttachments;

	/**
	 * Handles attachment selection toggle.
	 *
	 * @param {number} attachmentId The attachment ID to toggle.
	 */
	const handleToggleAttachment = useCallback(
		( attachmentId ) => {
			// When user first interacts, switch from auto mode to manual mode
			// Start with current effective selection as the baseline
			const currentSelection = isAutoMode ? [ ...autoSelectedAttachments ] : [ ...selectedAttachments ];

			const newSelection = currentSelection.includes( attachmentId )
				? currentSelection.filter( ( id ) => id !== attachmentId )
				: [ ...currentSelection, attachmentId ];

			onSelectionChange( newSelection );
		},
		[ isAutoMode, autoSelectedAttachments, selectedAttachments, onSelectionChange ]
	);

	if ( isLoadingParentAttachments || isLoadingContentAttachments ) {
		return <LoadingState />;
	}

	if ( ! attachments || attachments.length === 0 ) {
		return <EmptyState />;
	}

	// Filter unselected attachments once to avoid redundant computation
	const unselectedAttachments = attachments?.filter( ( { id } ) => ! effectiveSelection.includes( id ) ) || [];

	// Selection info for drag & drop handlers
	const selectionInfo = {
		currentSelection: effectiveSelection,
		isAutoMode,
		autoSelection: autoSelectedAttachments,
		manualSelection: selectedAttachments,
	};

	return (
		<VStack spacing={ 1 } className="activitypub-attachment-selector">
			{ effectiveSelection.length > 0 && (
				<>
					{ /* Drop zone at start */ }
					{ draggedItem && (
						<DropZone
							zoneId="drop-zone-start"
							isActive={ draggedOver === 'drop-zone-start' }
							onDrop={ ( e ) => createDropHandler( null, -1 )( e, selectionInfo ) }
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
										onDrop={ ( e ) => createDropHandler( attachment.id, 1 )( e, selectionInfo ) }
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
							onDrop={ ( e ) => createDropHandler( null, 1 )( e, selectionInfo ) }
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
