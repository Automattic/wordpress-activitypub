import { store as editorStore } from '@wordpress/editor';
import { useSelect } from '@wordpress/data';
import { useEntityRecords } from '@wordpress/core-data';
import { Spinner, __experimentalVStack as VStack } from '@wordpress/components';
import { Icon, media as mediaIcon } from '@wordpress/icons';
import { useState, useCallback, useMemo } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

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

	const postId = useSelect( ( select ) => select( editorStore ).getCurrentPostId(), [] );
	const postStatus = useSelect( ( select ) => select( editorStore ).getCurrentPost()?.status, [] );

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

	// Get featured image
	const featuredImageId = useSelect( ( select ) => {
		return select( 'core/editor' ).getEditedPostAttribute( 'featured_media' );
	}, [] );

	// Fetch content-referenced attachments if we have IDs
	const shouldFetchContentAttachments = contentAttachmentIds.length > 0;
	const { records: contentAttachments, isResolving: isLoadingContentAttachments } = useEntityRecords(
		'root',
		'media',
		{
			include: contentAttachmentIds,
			per_page: -1,
		},
		{ enabled: shouldFetchContentAttachments }
	);

	// Fetch featured image if it exists and isn't already included
	// Simplified condition - we'll deduplicate later in the useMemo
	const shouldFetchFeaturedImage =
		featuredImageId && featuredImageId > 0 && ! contentAttachmentIds.includes( featuredImageId );

	const { records: featuredAttachment, isResolving: isLoadingFeaturedAttachment } = useEntityRecords(
		'root',
		'media',
		{
			include: [ featuredImageId ],
			per_page: 1,
		},
		{ enabled: shouldFetchFeaturedImage }
	);

	// Combine and deduplicate attachments, filter for images only
	const attachments = useMemo( () => {
		const combined = [];
		const seenIds = new Set();

		// Add featured image first if it exists
		if ( featuredAttachment && featuredAttachment.length > 0 ) {
			const featured = featuredAttachment[ 0 ];
			if ( ! seenIds.has( featured.id ) ) {
				combined.push( featured );
				seenIds.add( featured.id );
			}
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

		// Add content attachments
		if ( contentAttachments ) {
			contentAttachments.forEach( ( attachment ) => {
				if ( ! seenIds.has( attachment.id ) ) {
					combined.push( attachment );
					seenIds.add( attachment.id );
				}
			} );
		}

		return combined;
	}, [ featuredAttachment, parentAttachments, contentAttachments ] );

	const isLoadingAttachments =
		isLoadingParentAttachments || isLoadingContentAttachments || isLoadingFeaturedAttachment;

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
	 */
	const handleDrop = useCallback(
		( e, targetId, dropPosition = 0 ) => {
			e.preventDefault();

			if ( ! draggedItem ) {
				setDraggedItem( null );
				setDraggedOver( null );
				return;
			}

			// When user drags, switch from auto mode to manual mode if needed
			// Start with current effective selection as the baseline
			const currentSelection = isAutoMode ? [ ...autoSelectedAttachments ] : [ ...selectedAttachments ];
			const newSelection = [ ...currentSelection ];

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
		[ draggedItem, isAutoMode, autoSelectedAttachments, selectedAttachments, onSelectionChange ]
	);

	if ( isLoadingAttachments ) {
		return (
			<div style={ { textAlign: 'center', padding: '20px' } }>
				<Spinner />
				<p>{ __( 'Loading attachments...', 'activitypub' ) }</p>
			</div>
		);
	}

	if ( ! attachments || attachments.length === 0 ) {
		return (
			<div style={ { textAlign: 'center', padding: '20px', color: '#8c8f94' } }>
				<Icon icon={ mediaIcon } size={ 24 } />
				<p>{ __( 'No attachments found for this post.', 'activitypub' ) }</p>
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

	// Filter unselected attachments once to avoid redundant computation
	const unselectedAttachments = attachments?.filter( ( { id } ) => ! effectiveSelection.includes( id ) ) || [];

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
