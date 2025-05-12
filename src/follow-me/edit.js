import apiFetch from '@wordpress/api-fetch';
import { InspectorControls, useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';
import { useSelect } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';
import { SelectControl, PanelBody, ToggleControl } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { useUserOptions } from '../shared/use-user-options';
import { InheritModeBlockFallback } from '../shared/inherit-block-fallback';
import { useOptions } from '../shared/use-options';

/**
 * Default profile data.
 *
 * @type {Object}
 */
const DEFAULT_PROFILE_DATA = {
	avatar: 'https://secure.gravatar.com/avatar/default?s=120',
	webfinger: '@well@hello.dolly',
	name: __( 'Hello Dolly Fan Account', 'activitypub' ),
	url: '#',
};

/**
 * Get normalized profile data.
 *
 * @param {Object} profile Profile data.
 * @return {Object} Normalized profile data.
 */
function getNormalizedProfile( profile ) {
	if ( ! profile ) {
		return DEFAULT_PROFILE_DATA;
	}

	const data = { ...DEFAULT_PROFILE_DATA, ...profile };
	data.avatar = data?.icon?.url;

	// Ensure webfinger always has the @ prefix.
	if ( data.webfinger && ! data.webfinger.startsWith( '@' ) ) {
		data.webfinger = '@' + data.webfinger;
	}

	return data;
}

/**
 * Fetch profile data.
 *
 * @param {number} userId User ID.
 * @return {Promise} Promise resolving with profile data.
 */
function fetchProfile( userId ) {
	const { namespace } = useOptions();
	const fetchOptions = {
		headers: { Accept: 'application/activity+json' },
		path: `/${ namespace }/actors/${ userId }`,
	};
	return apiFetch( fetchOptions );
}

/**
 * Profile component for the editor.
 *
 * @param {Object} props Component props.
 * @return {JSX.Element} Profile component.
 */
function EditorProfile( { profile, buttonOnly, innerBlocksProps } ) {
	const { webfinger, avatar, name } = profile;

	if ( buttonOnly ) {
		return (
			<div className="activitypub-profile">
				<div { ...innerBlocksProps } />
			</div>
		);
	}

	return (
		<div className="activitypub-profile">
			<img className="activitypub-profile__avatar" src={ avatar } alt={ name } />
			<div className="activitypub-profile__content">
				<div className="activitypub-profile__name">{ name }</div>
				<div className="activitypub-profile__handle" title={ webfinger }>
					{ webfinger }
				</div>
			</div>
			<div { ...innerBlocksProps } />
		</div>
	);
}

/**
 * Edit component.
 *
 * @param {Object} props Component props.
 * @param {Object} props.attributes Block attributes.
 * @param {Function} props.setAttributes Set block attributes.
 * @param {Object} props.context Block context.
 * @param {string} props.context.postType Post type.
 * @param {number} props.context.postId Post ID.
 * @return {JSX.Element} Edit component.
 */
export default function Edit( { attributes, setAttributes, context: { postType, postId } } ) {
	const blockProps = useBlockProps( {
		className: 'activitypub-follow-me-block-wrapper',
	} );
	const usersOptions = useUserOptions( { withInherit: true } );
	const { selectedUser, buttonOnly } = attributes;
	const isInheritMode = selectedUser === 'inherit';
	const [ profile, setProfile ] = useState( getNormalizedProfile( DEFAULT_PROFILE_DATA ) );
	const userId = selectedUser === 'site' ? 0 : selectedUser;

	const TEMPLATE = [ [ 'core/button', { text: __( 'Follow', 'activitypub' ), tagName: 'button' } ] ];

	const innerBlocksProps = useInnerBlocksProps(
		{},
		{
			allowedBlocks: [ 'core/button' ],
			template: TEMPLATE,
			templateLock: false,
			renderAppender: false,
		}
	);

	const authorId = useSelect(
		( select ) => {
			const { getEditedEntityRecord } = select( coreStore );
			const _authorId = getEditedEntityRecord( 'postType', postType, postId )?.author;

			return _authorId ?? null;
		},
		[ postType, postId ]
	);

	useEffect( () => {
		// Fetch profile data when userId changes.
		if ( isInheritMode && ! authorId ) {
			return;
		}

		const effectiveUserId = isInheritMode ? authorId : userId;
		fetchProfile( effectiveUserId ).then( ( data ) => {
			setProfile( getNormalizedProfile( data ) );
		} );
	}, [ userId, authorId, isInheritMode ] );

	useEffect( () => {
		// If there are no users yet, do nothing.
		if ( ! usersOptions.length ) {
			return;
		}
		// Ensure that the selected user is in the list of options, if not, select the first available user.
		if ( ! usersOptions.find( ( { value } ) => value === selectedUser ) ) {
			setAttributes( { selectedUser: usersOptions[ 0 ].value } );
		}
	}, [ selectedUser, usersOptions ] );

	return (
		<div { ...blockProps }>
			<InspectorControls key="activitypub-follow-me">
				<PanelBody title={ __( 'Follow Me Options', 'activitypub' ) }>
					{ usersOptions.length > 1 && (
						<SelectControl
							label={ __( 'Select User', 'activitypub' ) }
							value={ attributes.selectedUser }
							options={ usersOptions }
							onChange={ ( value ) => setAttributes( { selectedUser: value } ) }
						/>
					) }
					<ToggleControl
						label={ __( 'Button Only Mode', 'activitypub' ) }
						checked={ buttonOnly }
						onChange={ ( value ) => setAttributes( { buttonOnly: value } ) }
						help={ __( 'Only show the follow button without profile information', 'activitypub' ) }
					/>
				</PanelBody>
			</InspectorControls>

			{ isInheritMode && ! authorId ? (
				<InheritModeBlockFallback name={ __( 'Follow Me', 'activitypub' ) } />
			) : (
				<EditorProfile
					profile={ profile }
					userId={ isInheritMode ? authorId : userId }
					buttonOnly={ buttonOnly }
					innerBlocksProps={ innerBlocksProps }
				/>
			) }
		</div>
	);
}
