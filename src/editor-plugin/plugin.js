import { PluginDocumentSettingPanel, PluginPreviewMenuItem, store as editorStore } from '@wordpress/editor';
import { PluginDocumentSettingPanel as DocumentSettingPanel } from '@wordpress/edit-post';
import { registerPlugin } from '@wordpress/plugins';
import {
	TextControl,
	RadioControl,
	RangeControl,
	__experimentalText as Text,
	Tooltip,
	Panel,
	PanelBody,
	CheckboxControl,
	Button,
	Spinner,
	__experimentalHStack as HStack,
	__experimentalVStack as VStack,
} from '@wordpress/components';
import { Icon, globe, people, external, dragHandle, image as imageIcon, media as mediaIcon } from '@wordpress/icons';
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

	// Fetch all post attachments
	const { records: attachments, isResolving: isLoadingAttachments } = useEntityRecords( 'root', 'media', {
		post_parent: postId,
		per_page: -1,
		orderby: 'menu_order',
		order: 'asc',
	} );

	// Get featured image
	const featuredImageId = useSelect( ( select ) => {
		return select( 'core/editor' ).getEditedPostAttribute( 'featured_media' );
	}, [] );

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

	// Use auto-selected if no manual selection has been made
	const effectiveSelection = selectedAttachments.length > 0 ? selectedAttachments : autoSelectedAttachments;

	/**
	 * Gets the media type icon for an attachment.
	 *
	 * @param {string} mediaType The media type (image, video, audio).
	 * @returns {React.JSX.Element} The appropriate icon.
	 */
	const getMediaTypeIcon = ( mediaType ) => {
		switch ( mediaType ) {
			case 'image':
				return imageIcon;
			case 'video':
			case 'audio':
				return mediaIcon;
			default:
				return mediaIcon;
		}
	};

	/**
	 * Handles attachment selection toggle.
	 *
	 * @param {number} attachmentId The attachment ID to toggle.
	 */
	const handleToggleAttachment = useCallback(
		( attachmentId ) => {
			const newSelection = effectiveSelection.includes( attachmentId )
				? effectiveSelection.filter( ( id ) => id !== attachmentId )
				: [ ...effectiveSelection, attachmentId ].slice( 0, maxAttachments );

			onSelectionChange( newSelection );
		},
		[ effectiveSelection, onSelectionChange, maxAttachments ]
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
	 * Handles drop.
	 *
	 * @param {Event} e The drop event.
	 * @param {number} targetId The attachment being dropped on.
	 */
	const handleDrop = useCallback(
		( e, targetId ) => {
			e.preventDefault();

			if ( ! draggedItem || draggedItem === targetId ) {
				setDraggedItem( null );
				setDraggedOver( null );
				return;
			}

			const newSelection = [ ...effectiveSelection ];
			const draggedIndex = newSelection.indexOf( draggedItem );
			const targetIndex = newSelection.indexOf( targetId );

			if ( draggedIndex !== -1 && targetIndex !== -1 ) {
				newSelection.splice( draggedIndex, 1 );
				newSelection.splice( targetIndex, 0, draggedItem );
				onSelectionChange( newSelection );
			}

			setDraggedItem( null );
			setDraggedOver( null );
		},
		[ draggedItem, effectiveSelection, onSelectionChange ]
	);

	/**
	 * Resets selection to auto-selected attachments.
	 */
	const handleReset = useCallback( () => {
		onSelectionChange( [] );
	}, [ onSelectionChange ] );

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

	return (
		<VStack spacing={ 4 }>
			<HStack>
				<Text>{ __( 'Select attachments to federate:', 'activitypub' ) }</Text>
				{ selectedAttachments.length > 0 && (
					<Button
						variant="link"
						onClick={ handleReset }
						style={ { fontSize: '12px', textDecoration: 'underline' } }
					>
						{ __( 'Reset to default', 'activitypub' ) }
					</Button>
				) }
			</HStack>

			<div style={ { fontSize: '12px', color: '#8c8f94', marginBottom: '8px' } }>
				{ __( 'Drag to reorder • Check to select • Maximum:', 'activitypub' ) } { maxAttachments }
			</div>

			<VStack spacing={ 2 }>
				{ attachments.map( ( attachment ) => {
					const isSelected = effectiveSelection.includes( attachment.id );
					const isDragging = draggedItem === attachment.id;
					const isDraggedOver = draggedOver === attachment.id;

					return (
						<div
							key={ attachment.id }
							draggable={ isSelected }
							onDragStart={ ( e ) => handleDragStart( e, attachment.id ) }
							onDragOver={ ( e ) => handleDragOver( e, attachment.id ) }
							onDrop={ ( e ) => handleDrop( e, attachment.id ) }
							style={ {
								display: 'flex',
								alignItems: 'center',
								padding: '8px',
								border: '1px solid #ddd',
								borderRadius: '4px',
								backgroundColor: isSelected ? '#f0f6fc' : '#fff',
								opacity: isDragging ? 0.5 : 1,
								borderColor: isDraggedOver ? '#0073aa' : '#ddd',
								cursor: isSelected ? 'move' : 'default',
								gap: '8px',
							} }
						>
							<CheckboxControl
								checked={ isSelected }
								onChange={ () => handleToggleAttachment( attachment.id ) }
								disabled={ ! isSelected && effectiveSelection.length >= maxAttachments }
							/>

							{ isSelected && <Icon icon={ dragHandle } size={ 16 } style={ { color: '#8c8f94' } } /> }

							<Icon
								icon={ getMediaTypeIcon( attachment.media_type ) }
								size={ 20 }
								style={ { color: '#8c8f94' } }
							/>

							{ attachment.media_details?.sizes?.thumbnail?.source_url ? (
								<img
									src={ attachment.media_details.sizes.thumbnail.source_url }
									alt={ attachment.alt_text || attachment.title.rendered }
									style={ {
										width: '32px',
										height: '32px',
										objectFit: 'cover',
										borderRadius: '2px',
									} }
								/>
							) : null }

							<VStack spacing={ 0 } style={ { flex: 1 } }>
								<Text style={ { fontWeight: 500, fontSize: '14px' } }>
									{ attachment.title.rendered || __( 'Untitled', 'activitypub' ) }
								</Text>
								<Text style={ { fontSize: '12px', color: '#8c8f94' } }>
									{ attachment.media_type } •{ ' ' }
									{ Math.round( attachment.media_details?.filesize / 1024 ) }KB
								</Text>
							</VStack>

							{ isSelected && (
								<Text style={ { fontSize: '12px', color: '#0073aa', fontWeight: 500 } }>
									#{ effectiveSelection.indexOf( attachment.id ) + 1 }
								</Text>
							) }
						</div>
					);
				} ) }
			</VStack>

			{ effectiveSelection.length > 0 && (
				<div style={ { fontSize: '12px', color: '#8c8f94', textAlign: 'center' } }>
					{ selectedAttachments.length > 0
						? __( 'Custom selection:', 'activitypub' ) +
						  ' ' +
						  effectiveSelection.length +
						  '/' +
						  maxAttachments
						: __( 'Auto-selected:', 'activitypub' ) +
						  ' ' +
						  effectiveSelection.length +
						  '/' +
						  maxAttachments }
				</div>
			) }
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

			<RangeControl
				label={ __( 'Maximum Image Attachments', 'activitypub' ) }
				value={ meta?.activitypub_max_image_attachments }
				onChange={ ( value ) => {
					setMeta( { ...meta, activitypub_max_image_attachments: value } );
				} }
				min={ 0 }
				max={ 10 }
				help={ __(
					'Maximum number of image attachments to include when sharing to the fediverse.',
					'activitypub'
				) }
				__next40pxDefaultSize
				__nextHasNoMarginBottom
			/>

			{ meta?.activitypub_max_image_attachments > 0 && (
				<div style={ { marginTop: '16px' } }>
					<AttachmentSelector
						postId={ postId }
						selectedAttachments={ meta?.activitypub_selected_attachments || [] }
						onSelectionChange={ ( selection ) => {
							setMeta( { ...meta, activitypub_selected_attachments: selection } );
						} }
						maxAttachments={ meta?.activitypub_max_image_attachments || 4 }
					/>
				</div>
			) }

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
