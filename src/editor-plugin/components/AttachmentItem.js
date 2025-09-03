import { Icon, dragHandle, image as imageIcon } from '@wordpress/icons';
import { __ } from '@wordpress/i18n';

/**
 * Attachment item component.
 *
 * @param {Object} props Component props.
 * @param {Object} props.attachment The attachment object.
 * @param {boolean} props.isSelected Whether this attachment is selected.
 * @param {number} props.selectionIndex The index in selection (for numbering).
 * @param {boolean} props.isDragging Whether this item is being dragged.
 * @param {boolean} props.isDraggedOver Whether this item is being dragged over.
 * @param {Function} props.onToggle Toggle selection handler.
 * @param {Function} props.onDragStart Drag start handler.
 * @param {Function} props.dragHandlers Object with drag event handlers.
 * @returns {React.JSX.Element} The attachment item component.
 */
const AttachmentItem = ( {
	attachment,
	isSelected,
	selectionIndex,
	isDragging,
	isDraggedOver,
	onToggle,
	onDragStart,
	dragHandlers,
} ) => {
	const classNames = [
		'activitypub-attachment-item',
		isSelected && 'activitypub-attachment-item--selected',
		isDragging && 'activitypub-attachment-item--dragging',
		isDraggedOver && isSelected && 'activitypub-attachment-item--drag-over',
	]
		.filter( Boolean )
		.join( ' ' );

	return (
		<div
			draggable={ isSelected }
			onClick={ onToggle }
			onDragStart={ onDragStart }
			{ ...( isSelected ? dragHandlers : {} ) }
			className={ classNames }
		>
			{ isSelected ? (
				<Icon
					icon={ dragHandle }
					size={ 16 }
					className="activitypub-attachment-item__drag-handle activitypub-attachment-item__drag-handle--selected"
				/>
			) : (
				<div className="activitypub-attachment-item__spacer" />
			) }

			{ attachment.media_details?.sizes?.thumbnail?.source_url ? (
				<img
					src={ attachment.media_details.sizes.thumbnail.source_url }
					alt={ attachment.alt_text || attachment.title.rendered }
					className="activitypub-attachment-item__thumbnail"
				/>
			) : (
				<div className="activitypub-attachment-item__thumbnail activitypub-attachment-item__thumbnail--placeholder">
					<Icon icon={ imageIcon } size={ 20 } />
				</div>
			) }

			<div className="activitypub-attachment-item__content">
				<div className="activitypub-attachment-item__title">
					{ attachment.title.rendered || __( 'Untitled', 'activitypub' ) }
				</div>
			</div>

			{ isSelected && <div className="activitypub-attachment-item__number">#{ selectionIndex + 1 }</div> }
		</div>
	);
};

export default AttachmentItem;
