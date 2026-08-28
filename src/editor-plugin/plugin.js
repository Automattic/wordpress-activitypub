import { PluginDocumentSettingPanel, PluginPreviewMenuItem, store as editorStore } from '@wordpress/editor';
import { PluginDocumentSettingPanel as DocumentSettingPanel } from '@wordpress/edit-post';
import { registerPlugin } from '@wordpress/plugins';
import {
	TextControl,
	RadioControl,
	RangeControl,
	__experimentalText as Text,
	Tooltip,
	SelectControl,
} from '@wordpress/components';
import { Icon, globe, people, external } from '@wordpress/icons';
import { useSelect, useDispatch, select } from '@wordpress/data';
import { useEntityProp } from '@wordpress/core-data';
import { useEffect } from '@wordpress/element';
import { store as noticesStore } from '@wordpress/notices';
import { addQueryArgs } from '@wordpress/url';
import { __, sprintf } from '@wordpress/i18n';
import { SVG, Path } from '@wordpress/primitives';
import { getDefaultVisibility, shouldWarnAboutFederatedDeletion } from './utils';

/**
 * Notice ID for the "removed from the Fediverse" warning, so it can be updated and removed idempotently.
 *
 * @member {string} DELETION_NOTICE_ID
 */
const DELETION_NOTICE_ID = 'activitypub/federated-deletion-warning';

/**
 * Editor plugin for ActivityPub settings in the block editor.
 *
 * @return {React.JSX.Element|null} The settings panel for ActivityPub or null for sync blocks.
 */
const EditorPlugin = () => {
	const postType = useSelect( ( selectFn ) => selectFn( editorStore ).getCurrentPostType(), [] );
	const [ meta, setMeta ] = useEntityProp( 'postType', postType, 'meta' );

	// The edited date, status and password drive the visibility and the "removed from the Fediverse" warning.
	const { editedDate, editedStatus, editedPassword } = useSelect( ( selectFn ) => {
		const editor = selectFn( editorStore );

		return {
			editedDate: editor.getEditedPostAttribute( 'date' ),
			editedStatus: editor.getEditedPostAttribute( 'status' ),
			editedPassword: editor.getEditedPostAttribute( 'password' ),
		};
	}, [] );

	const { createWarningNotice, removeNotice } = useDispatch( noticesStore );

	// Get the computed default visibility.
	const defaultVisibility = getDefaultVisibility( meta, editedDate );

	// Get the default quote policy from settings.
	const defaultQuotePolicy = window._activityPubOptions?.defaultQuotePolicy || 'anyone';

	// Whether hiding this already-federated post will send a Delete to followers.
	const warnAboutDeletion = shouldWarnAboutFederatedDeletion( {
		federationStatus: meta?.activitypub_status,
		status: editedStatus,
		password: editedPassword,
		visibility: defaultVisibility,
	} );

	// Sync computed default to meta when it differs from stored value.
	// This ensures the default is persisted even if user doesn't change it.
	useEffect( () => {
		const storedVisibility = meta?.activitypub_content_visibility;

		// Only sync if visibility was never set and the default isn't 'public'.
		// WordPress may return '' (empty string) or undefined for unset meta.
		// We skip 'public' since it's the implicit default.
		if ( ! storedVisibility && defaultVisibility !== 'public' ) {
			setMeta( { ...meta, activitypub_content_visibility: defaultVisibility } );
		}
	}, [ defaultVisibility, meta, setMeta ] );

	// Surface the deletion warning as a native editor notice so it can't be missed.
	useEffect( () => {
		if ( warnAboutDeletion ) {
			createWarningNotice(
				__(
					"This post has already been shared on the Fediverse. If you make it a draft, private, or password-protected post, other servers will be asked to remove it from your followers' feeds. Not all platforms support restoring removed content, so the post may disappear permanently.",
					'activitypub'
				),
				{ id: DELETION_NOTICE_ID, isDismissible: true }
			);
		} else {
			removeNotice( DELETION_NOTICE_ID );
		}

		// Clear the notice when the editor unmounts so it doesn't linger.
		return () => removeNotice( DELETION_NOTICE_ID );
	}, [ warnAboutDeletion, createWarningNotice, removeNotice ] );

	// Don't show when editing sync blocks.
	if ( 'wp_block' === postType ) {
		return null;
	}

	/**
	 * SVG for the not-allowed icon. Defining our own because it's too new in @wordpress/icons.
	 *
	 * @see https://github.com/WordPress/gutenberg/blob/trunk/packages/icons/src/library/not-allowed.js
	 *
	 * @member {React.JSX.Element} notAllowed The SVG for the not-allowed icon.
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
	 * @return {React.JSX.Element} The enhanced label component.
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

	/**
	 * Get the help text for the quote policy selector, including the site default.
	 *
	 * @return {string} The help text with site default.
	 */
	const getQuotePolicyHelp = () => {
		const policyLabels = {
			anyone: __( 'Anyone', 'activitypub' ),
			followers: __( 'Followers only', 'activitypub' ),
			me: __( 'Just me', 'activitypub' ),
		};
		const defaultLabel = policyLabels[ defaultQuotePolicy ] || policyLabels.anyone;

		return (
			__( 'Quoting allows others to cite your post while adding their own commentary.', 'activitypub' ) +
			' ' +
			sprintf(
				/* translators: %s: The site default quote policy (e.g., "Anyone", "Followers only", "Just me") */
				__( 'Site default: %s', 'activitypub' ),
				defaultLabel
			)
		);
	};

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
			/>

			<RadioControl
				label={ __( 'Visibility', 'activitypub' ) }
				help={ __(
					"This adjusts the visibility of a post in the fediverse, but note that it won't affect how the post appears on the blog.",
					'activitypub'
				) }
				selected={ defaultVisibility }
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

			<SelectControl
				label={ __( 'Who can quote this post?', 'activitypub' ) }
				help={ getQuotePolicyHelp() }
				value={ meta?.activitypub_interaction_policy_quote || defaultQuotePolicy }
				options={ [
					{ label: __( 'Anyone', 'activitypub' ), value: 'anyone' },
					{ label: __( 'Followers only', 'activitypub' ), value: 'followers' },
					{ label: __( 'Just me', 'activitypub' ), value: 'me' },
				] }
				onChange={ ( value ) => {
					setMeta( { ...meta, activitypub_interaction_policy_quote: value } );
				} }
				__next40pxDefaultSize
			/>
		</SettingsPanel>
	);
};

/**
 * Renders the preview menu item for Fediverse preview.
 *
 * @return {React.JSX.Element} The preview menu item component.
 */
const EditorPreview = () => {
	const postStatus = useSelect( ( selectFn ) => selectFn( editorStore ).getCurrentPost().status, [] );

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
					disabled={ postStatus === 'auto-draft' }
				>
					{ __( 'Fediverse preview ⁂', 'activitypub' ) }
				</PluginPreviewMenuItem>
			) : null }
		</>
	);
};

registerPlugin( 'activitypub-editor-plugin', { render: EditorPlugin } );
registerPlugin( 'activitypub-editor-preview', { render: EditorPreview } );
