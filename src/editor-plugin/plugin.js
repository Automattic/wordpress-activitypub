import { PluginDocumentSettingPanel, PluginPreviewMenuItem, store as editorStore } from '@wordpress/editor';
import { PluginDocumentSettingPanel as DocumentSettingPanel } from '@wordpress/edit-post';
import { registerPlugin } from '@wordpress/plugins';
import {
	BaseControl,
	TextControl,
	RadioControl,
	__experimentalText as Text,
	Tooltip,
	Spinner,
	__experimentalVStack as VStack,
} from '@wordpress/components';
import { Icon, globe, people, external, dragHandle, image as imageIcon } from '@wordpress/icons';
import { useSelect, select } from '@wordpress/data';
import { useEntityProp, useEntityRecords } from '@wordpress/core-data';
import { addQueryArgs } from '@wordpress/url';
import { __ } from '@wordpress/i18n';
import { SVG, Path } from '@wordpress/primitives';
import { useState, useCallback, useMemo } from '@wordpress/element';

/**
 * Attachment selection component for choosing which attachments to federate.
 *
 * @param {Object} props Component props.
 * @param {number} props.postId The current post ID.
 * @param {Array} props.selectedAttachments Array of selected attachment IDs.
 * @param {Function} props.onSelectionChange Callback for selection changes.
 * @param {number} props.maxAttachments Maximum number of attachments allowed.
 * @returns {React.JSX.Element|null} The attachment selection component.
 */
const AttachmentSelector = ( { postId, selectedAttachments, onSelectionChange, maxAttachments } ) => {
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

	// Fetch attachments by post_parent (traditional attachments)
	const { records: parentAttachments, isResolving: isLoadingParentAttachments } = useEntityRecords( 'root', 'media', {
		post_parent: postId,
		per_page: -1,
		orderby: 'menu_order',
		order: 'asc',
	} );

	// Fetch content-referenced attachments if we have IDs
	const { records: contentAttachments, isResolving: isLoadingContentAttachments } = useEntityRecords(
		'root',
		'media',
		contentAttachmentIds.length > 0
			? {
					include: contentAttachmentIds,
					per_page: -1,
			  }
			: null
	);

	/**
	 * Determines the normalized media type from attachment.
	 *
	 * @param {Object} attachment The attachment object.
	 * @returns {string} The normalized media type (image, video, audio, or file).
	 */
	const getMediaType = ( attachment ) => {
		// First try the media_type field
		let mediaType = attachment.media_type;

		// Fallback to mime_type if media_type is not reliable
		if ( ! mediaType || mediaType === 'file' ) {
			const mimeType = attachment.mime_type || '';
			if ( mimeType.startsWith( 'image/' ) ) {
				mediaType = 'image';
			} else if ( mimeType.startsWith( 'audio/' ) ) {
				mediaType = 'audio';
			} else if ( mimeType.startsWith( 'video/' ) ) {
				mediaType = 'video';
			}
		}

		return mediaType;
	};

	// Helper function to check if attachment is an image
	const isImageAttachment = ( attachment ) => {
		const mediaType = getMediaType( attachment );
		return mediaType === 'image';
	};

	// Combine and deduplicate attachments, filter for images only
	const attachments = useMemo( () => {
		const combined = [];
		const seenIds = new Set();

		// Add parent attachments first
		if ( parentAttachments ) {
			parentAttachments.forEach( ( attachment ) => {
				if ( ! seenIds.has( attachment.id ) && isImageAttachment( attachment ) ) {
					combined.push( attachment );
					seenIds.add( attachment.id );
				}
			} );
		}

		// Add content attachments
		if ( contentAttachments ) {
			contentAttachments.forEach( ( attachment ) => {
				if ( ! seenIds.has( attachment.id ) && isImageAttachment( attachment ) ) {
					combined.push( attachment );
					seenIds.add( attachment.id );
				}
			} );
		}

		return combined;
	}, [ parentAttachments, contentAttachments ] );

	const isLoadingAttachments = isLoadingParentAttachments || isLoadingContentAttachments;

	// Use selected attachments directly
	const effectiveSelection = selectedAttachments;

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

			// Work with all attachments array to maintain correct order
			const allAttachmentIds = attachments.map( ( a ) => a.id );
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
			<div style={ { textAlign: 'center', padding: '20px' } }>
				<Spinner />
				<p>{ __( 'Loading attachments...', 'activitypub' ) }</p>
			</div>
		);
	}

	if ( ! attachments || attachments.length === 0 ) {
		return (
			<div style={ { textAlign: 'center', padding: '20px', color: '#8c8f94' } }>
				<Icon icon={ imageIcon } size={ 24 } />
				<p>{ __( 'No images found for this post.', 'activitypub' ) }</p>
			</div>
		);
	}

	return (
		<VStack spacing={ 4 }>
			<Text>{ __( 'Select images to federate:', 'activitypub' ) }</Text>

			<div>
				{ draggedItem && draggedOver === 'drop-zone-start' && (
					<div
						onDragOver={ ( e ) => {
							e.preventDefault();
							setDraggedOver( 'drop-zone-start' );
						} }
						onDragEnter={ ( e ) => {
							e.preventDefault();
							setDraggedOver( 'drop-zone-start' );
						} }
						onDragLeave={ () => setDraggedOver( null ) }
						onDrop={ ( e ) => handleDrop( e, null, -1 ) }
						style={ {
							height: '72px',
							backgroundColor: '#0073aa20',
							border: '2px dashed #0073aa',
							borderRadius: '6px',
							marginBottom: '4px',
							transition: 'all 0.15s ease',
							display: 'flex',
							alignItems: 'center',
							justifyContent: 'center',
							fontSize: '12px',
							color: '#0073aa',
							fontWeight: 500,
						} }
					>
						{ __( 'Drop here', 'activitypub' ) }
					</div>
				) }

				{ draggedItem && draggedOver !== 'drop-zone-start' && (
					<div
						onDragOver={ ( e ) => {
							e.preventDefault();
							setDraggedOver( 'drop-zone-start' );
						} }
						onDragEnter={ ( e ) => {
							e.preventDefault();
							setDraggedOver( 'drop-zone-start' );
						} }
						style={ {
							height: '4px',
							marginBottom: '4px',
						} }
					/>
				) }

				{ effectiveSelection.map( ( attachmentId, index ) => {
					const attachment = attachments.find( ( a ) => a.id === attachmentId );
					if ( ! attachment ) return null;

					const isSelected = true; // Always true since we're mapping over selection
					const isDragging = draggedItem === attachment.id;
					const isDraggedOver = draggedOver === attachment.id;

					return (
						<div key={ attachment.id }>
							<div
								draggable={ isSelected }
								onClick={ () => {
									if ( ! ( ! isSelected && effectiveSelection.length >= maxAttachments ) ) {
										handleToggleAttachment( attachment.id );
									}
								} }
								onDragStart={ ( e ) => handleDragStart( e, attachment.id ) }
								onDragOver={ isSelected ? ( e ) => handleDragOver( e, attachment.id ) : undefined }
								onDragEnter={ isSelected ? ( e ) => handleDragEnter( e, attachment.id ) : undefined }
								onDragLeave={ isSelected ? handleDragLeave : undefined }
								onDrop={ isSelected ? ( e ) => handleDrop( e, attachment.id ) : undefined }
								style={ {
									display: 'flex',
									alignItems: 'center',
									padding: '10px 12px',
									border: `1px solid ${ isSelected ? '#0073aa' : '#ddd' }`,
									borderRadius: '6px',
									backgroundColor: isSelected ? '#f6f7f7' : '#fff',
									opacity: isDragging ? 0.6 : 1,
									borderColor:
										isDraggedOver && isSelected ? '#005177' : isSelected ? '#0073aa' : '#ddd',
									cursor: isSelected ? 'grab' : 'pointer',
									gap: '10px',
									transition: 'all 0.15s ease',
									boxShadow: isSelected ? '0 0 0 1px rgba(0, 115, 170, 0.1)' : 'none',
									marginBottom: '4px',
								} }
							>
								{ isSelected && (
									<Icon
										icon={ dragHandle }
										size={ 16 }
										style={ {
											color: '#666',
											cursor: 'grab',
											flexShrink: 0,
										} }
									/>
								) }

								{ attachment.media_details?.sizes?.thumbnail?.source_url ? (
									<img
										src={ attachment.media_details.sizes.thumbnail.source_url }
										alt={ attachment.alt_text || attachment.title.rendered }
										style={ {
											width: '40px',
											height: '40px',
											objectFit: 'cover',
											borderRadius: '4px',
											flexShrink: 0,
											border: '1px solid #e0e0e0',
										} }
									/>
								) : (
									<div
										style={ {
											width: '40px',
											height: '40px',
											backgroundColor: '#f0f0f0',
											borderRadius: '4px',
											display: 'flex',
											alignItems: 'center',
											justifyContent: 'center',
											flexShrink: 0,
											border: '1px solid #e0e0e0',
										} }
									>
										<Icon icon={ imageIcon } size={ 20 } style={ { color: '#666' } } />
									</div>
								) }

								<div style={ { flex: 1, minWidth: 0 } }>
									<div
										style={ {
											fontWeight: 500,
											fontSize: '14px',
											color: '#1e1e1e',
											marginBottom: '4px',
											overflow: 'hidden',
											textOverflow: 'ellipsis',
											whiteSpace: 'nowrap',
										} }
									>
										{ attachment.title.rendered || __( 'Untitled', 'activitypub' ) }
									</div>
								</div>

								{ isSelected && (
									<div
										style={ {
											fontSize: '12px',
											color: '#0073aa',
											fontWeight: 600,
											backgroundColor: 'rgba(0, 115, 170, 0.1)',
											padding: '4px 8px',
											borderRadius: '12px',
											minWidth: '24px',
											textAlign: 'center',
											flexShrink: 0,
										} }
									>
										#{ index + 1 }
									</div>
								) }
							</div>

							{ draggedItem &&
								index < effectiveSelection.length - 1 &&
								draggedOver === `drop-zone-after-${ attachment.id }` && (
									<div
										onDragOver={ ( e ) => {
											e.preventDefault();
											setDraggedOver( `drop-zone-after-${ attachment.id }` );
										} }
										onDragEnter={ ( e ) => {
											e.preventDefault();
											setDraggedOver( `drop-zone-after-${ attachment.id }` );
										} }
										onDragLeave={ () => setDraggedOver( null ) }
										onDrop={ ( e ) => handleDrop( e, attachment.id, 1 ) }
										style={ {
											height: '72px',
											backgroundColor: '#0073aa20',
											border: '2px dashed #0073aa',
											borderRadius: '6px',
											marginBottom: '4px',
											transition: 'all 0.15s ease',
											display: 'flex',
											alignItems: 'center',
											justifyContent: 'center',
											fontSize: '12px',
											color: '#0073aa',
											fontWeight: 500,
										} }
									>
										{ __( 'Drop here', 'activitypub' ) }
									</div>
								) }

							{ draggedItem &&
								index < effectiveSelection.length - 1 &&
								draggedOver !== `drop-zone-after-${ attachment.id }` && (
									<div
										onDragOver={ ( e ) => {
											e.preventDefault();
											setDraggedOver( `drop-zone-after-${ attachment.id }` );
										} }
										onDragEnter={ ( e ) => {
											e.preventDefault();
											setDraggedOver( `drop-zone-after-${ attachment.id }` );
										} }
										style={ {
											height: '4px',
											marginBottom: '4px',
										} }
									/>
								) }
						</div>
					);
				} ) }

				{ draggedItem && draggedOver === 'drop-zone-end' && (
					<div
						onDragOver={ ( e ) => {
							e.preventDefault();
							setDraggedOver( 'drop-zone-end' );
						} }
						onDragEnter={ ( e ) => {
							e.preventDefault();
							setDraggedOver( 'drop-zone-end' );
						} }
						onDragLeave={ () => setDraggedOver( null ) }
						onDrop={ ( e ) => handleDrop( e, null, 1 ) }
						style={ {
							height: '72px',
							backgroundColor: '#0073aa20',
							border: '2px dashed #0073aa',
							borderRadius: '6px',
							transition: 'all 0.15s ease',
							display: 'flex',
							alignItems: 'center',
							justifyContent: 'center',
							fontSize: '12px',
							color: '#0073aa',
							fontWeight: 500,
						} }
					>
						{ __( 'Drop here', 'activitypub' ) }
					</div>
				) }

				{ draggedItem && draggedOver !== 'drop-zone-end' && (
					<div
						onDragOver={ ( e ) => {
							e.preventDefault();
							setDraggedOver( 'drop-zone-end' );
						} }
						onDragEnter={ ( e ) => {
							e.preventDefault();
							setDraggedOver( 'drop-zone-end' );
						} }
						style={ {
							height: '4px',
						} }
					/>
				) }

				{ /* Show unselected attachments for selection */ }
				{ attachments
					.filter( ( attachment ) => ! effectiveSelection.includes( attachment.id ) )
					.map( ( attachment ) => {
						return (
							<div key={ attachment.id }>
								<div
									draggable={ false }
									onClick={ () => {
										handleToggleAttachment( attachment.id );
									} }
									style={ {
										display: 'flex',
										alignItems: 'center',
										padding: '10px 12px',
										border: '1px solid #ddd',
										borderRadius: '6px',
										backgroundColor: '#fff',
										cursor: 'pointer',
										gap: '10px',
										transition: 'all 0.15s ease',
										marginBottom: '4px',
									} }
								>
									<Icon
										icon={ dragHandle }
										size={ 16 }
										style={ {
											color: '#ccc',
											cursor: 'pointer',
											flexShrink: 0,
										} }
									/>
									{ attachment.media_details?.sizes?.thumbnail?.source_url ? (
										<img
											src={ attachment.media_details.sizes.thumbnail.source_url }
											alt={ attachment.alt_text || attachment.title.rendered }
											style={ {
												width: '40px',
												height: '40px',
												objectFit: 'cover',
												borderRadius: '4px',
												flexShrink: 0,
												border: '1px solid #e0e0e0',
											} }
										/>
									) : (
										<div
											style={ {
												width: '40px',
												height: '40px',
												backgroundColor: '#f0f0f0',
												borderRadius: '4px',
												display: 'flex',
												alignItems: 'center',
												justifyContent: 'center',
												flexShrink: 0,
												border: '1px solid #e0e0e0',
											} }
										>
											<Icon icon={ imageIcon } size={ 20 } style={ { color: '#666' } } />
										</div>
									) }

									<div style={ { flex: 1, minWidth: 0 } }>
										<div
											style={ {
												fontWeight: 500,
												fontSize: '14px',
												color: '#1e1e1e',
												marginBottom: '4px',
												overflow: 'hidden',
												textOverflow: 'ellipsis',
												whiteSpace: 'nowrap',
											} }
										>
											{ attachment.title.rendered || __( 'Untitled', 'activitypub' ) }
										</div>
									</div>
								</div>
							</div>
						);
					} ) }
			</div>
		</VStack>
	);
};

/**
 * Editor plugin for ActivityPub settings in the block editor.
 *
 * @returns {React.JSX.Element|null} The settings panel for ActivityPub or null for sync blocks.
 */
const EditorPlugin = () => {
	const postType = useSelect( ( select ) => select( editorStore ).getCurrentPostType(), [] );
	const postId = useSelect( ( select ) => select( editorStore ).getCurrentPostId(), [] );
	const [ meta, setMeta ] = useEntityProp( 'postType', postType, 'meta' );

	// Don't show when editing sync blocks.
	if ( 'wp_block' === postType ) {
		return null;
	}

	/**
	 * SVG for the not-allowed icon. Defining our own because it's too new in @wordpress/icons.
	 *
	 * @see https://github.com/WordPress/gutenberg/blob/trunk/packages/icons/src/library/not-allowed.js
	 *
	 * @var {React.JSX.Element} notAllowed The SVG for the not-allowed icon.
	 */
	const notAllowed = (
		<SVG xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
			<Path
				fillRule="evenodd"
				clipRule="evenodd"
				d="M12 18.5A6.5 6.5 0 0 1 6.93 7.931l9.139 9.138A6.473 6.473 0 0 1 12 18.5Zm5.123-2.498a6.5 6.5 0 0 0-9.124-9.124l9.124 9.124ZM4 12a8 8 0 1 1 16 0 8 8 0 0 1-16 0Z"
			/>
		</SVG>
	);

	const labelStyling = {
		verticalAlign: 'middle',
		gap: '4px',
		justifyContent: 'start',
		display: 'inline-flex',
		alignItems: 'center',
	};

	/**
	 * Enhances a label with an icon and tooltip.
	 *
	 * @param {React.JSX.Element} icon    The icon to display.
	 * @param {string}            text    The label text.
	 * @param {string}            tooltip The tooltip text.
	 *
	 * @returns {React.JSX.Element} The enhanced label component.
	 */
	const enhancedLabel = ( icon, text, tooltip ) => (
		<Tooltip text={ tooltip }>
			<Text style={ labelStyling }>
				<Icon icon={ icon } />
				{ text }
			</Text>
		</Tooltip>
	);

	/*
	 * Backwards compatibility with WordPress 6.5.
	 * @todo Remove when 6.5 is no longer supported.
	 */
	const SettingsPanel = PluginDocumentSettingPanel || DocumentSettingPanel;

	return (
		<SettingsPanel
			name="activitypub"
			className="block-editor-block-inspector"
			title={ __( 'Fediverse ⁂', 'activitypub' ) }
		>
			<TextControl
				label={ __( 'Content Warning', 'activitypub' ) }
				value={ meta?.activitypub_content_warning }
				onChange={ ( value ) => {
					setMeta( { ...meta, activitypub_content_warning: value } );
				} }
				placeholder={ __( 'Optional content warning', 'activitypub' ) }
				help={ __(
					'Content warnings do not change the content on your site, only in the fediverse.',
					'activitypub'
				) }
				__next40pxDefaultSize
				__nextHasNoMarginBottom
			/>

			<BaseControl label={ __( 'Image Attachments', 'activitypub' ) } __next40pxDefaultSize>
				<AttachmentSelector
					postId={ postId }
					selectedAttachments={ meta?.activitypub_selected_attachments || [] }
					onSelectionChange={ ( selection ) => {
						setMeta( { ...meta, activitypub_selected_attachments: selection } );
					} }
					maxAttachments={ 10 }
				/>
			</BaseControl>

			<RadioControl
				label={ __( 'Visibility', 'activitypub' ) }
				help={ __(
					"This adjusts the visibility of a post in the fediverse, but note that it won't affect how the post appears on the blog.",
					'activitypub'
				) }
				selected={ meta?.activitypub_content_visibility || 'public' }
				options={ [
					{
						label: enhancedLabel(
							globe,
							__( 'Public', 'activitypub' ),
							__( 'Post will be visible to everyone and appear in public timelines.', 'activitypub' )
						),
						value: 'public',
					},
					{
						label: enhancedLabel(
							people,
							__( 'Quiet public', 'activitypub' ),
							__(
								'Post will be visible to everyone but will not appear in public timelines.',
								'activitypub'
							)
						),
						value: 'quiet_public',
					},
					{
						label: enhancedLabel(
							notAllowed,
							__( 'Do not federate', 'activitypub' ),
							__( 'Post will not be shared to the Fediverse.', 'activitypub' )
						),
						value: 'local',
					},
				] }
				onChange={ ( value ) => {
					setMeta( { ...meta, activitypub_content_visibility: value } );
				} }
				className="activitypub-visibility"
			/>
		</SettingsPanel>
	);
};

/**
 * Renders the preview menu item for Fediverse preview.
 *
 * @returns {React.JSX.Element} The preview menu item component.
 */
const EditorPreview = () => {
	const post_status = useSelect( ( select ) => select( editorStore ).getCurrentPost().status, [] );

	/**
	 * Opens the Fediverse preview for the current post in a new tab.
	 */
	const onActivityPubPreview = () => {
		const previewLink = select( editorStore ).getEditedPostPreviewLink();
		const fediversePreviewLink = addQueryArgs( previewLink, { activitypub: 'true' } );

		window.open( fediversePreviewLink, '_blank' );
	};

	return (
		<>
			{ PluginPreviewMenuItem ? (
				<PluginPreviewMenuItem
					onClick={ onActivityPubPreview }
					icon={ external }
					disabled={ post_status === 'auto-draft' }
				>
					{ __( 'Fediverse preview ⁂', 'activitypub' ) }
				</PluginPreviewMenuItem>
			) : null }
		</>
	);
};

registerPlugin( 'activitypub-editor-plugin', { render: EditorPlugin } );
registerPlugin( 'activitypub-editor-preview', { render: EditorPreview } );
