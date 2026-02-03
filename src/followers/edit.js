import { SelectControl, RangeControl, PanelBody, Notice } from '@wordpress/components';
import { InspectorControls, useBlockProps, InnerBlocks } from '@wordpress/block-editor';
import { store as coreStore } from '@wordpress/core-data';
import { useSelect } from '@wordpress/data';
import { useState, useEffect, useMemo, createInterpolateElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { useUserOptions } from '../shared/use-user-options';
import { InheritModeBlockFallback } from '../shared/inherit-block-fallback';
import { ActorList } from '../shared/actor-list';

/**
 * Check if a user has their social graph hidden based on user meta.
 *
 * @param {Object} userMeta The user's metadata.
 * @return {boolean} True if social graph is hidden.
 */
function hasSocialGraphHidden( userMeta ) {
	if ( ! userMeta ) {
		return false;
	}

	return Object.entries( userMeta ).some(
		( [ key, value ] ) => key.endsWith( 'activitypub_hide_social_graph' ) && value
	);
}

/**
 * Edit component.
 *
 * @param {Object}   props                  Component props.
 * @param {Object}   props.attributes       Block attributes.
 * @param {Function} props.setAttributes    Set block attributes.
 * @param {Object}   props.context          Block context.
 * @param {string}   props.context.postType Post type.
 * @param {number}   props.context.postId   Post ID.
 *
 * @return {JSX.Element} Edit component.
 */
export default function Edit( { attributes, setAttributes, context: { postType, postId } } ) {
	const { className = '', order, per_page: perPage, selectedUser } = attributes;
	const blockProps = useBlockProps();
	const [ page, setPage ] = useState( 1 );
	const orderOptions = [
		{ label: __( 'New to old', 'activitypub' ), value: 'desc' },
		{ label: __( 'Old to new', 'activitypub' ), value: 'asc' },
	];
	const usersOptions = useUserOptions( { withInherit: true } );
	const setAttributeWithPageReset = ( key ) => ( value ) => {
		setPage( 1 );
		setAttributes( { [ key ]: value } );
	};

	// Get site settings to check blog social graph visibility.
	const { blogSocialGraphHidden, currentUser, usersWithMeta, siteUrl, canManageOptions } = useSelect( ( select ) => {
		const { getCurrentUser, getUsers, getEntityRecord, canUser } = select( coreStore );
		const siteSettings = getEntityRecord( 'root', 'site' );
		const siteData = getEntityRecord( 'root', '__unstableBase' );

		return {
			blogSocialGraphHidden: !! siteSettings?.activitypub_hide_social_graph,
			currentUser: getCurrentUser(),
			usersWithMeta: getUsers( { capabilities: 'activitypub', context: 'edit' } ),
			siteUrl: siteData?.home,
			canManageOptions: canUser( 'update', { kind: 'root', name: 'site' } ),
		};
	}, [] );

	const authorId = useSelect(
		( select ) => {
			const { getEditedEntityRecord } = select( coreStore );
			const _authorId = getEditedEntityRecord( 'postType', postType, postId )?.author;

			return _authorId ?? null;
		},
		[ postType, postId ]
	);

	// Filter user options based on social graph visibility.
	const filteredUsersOptions = useMemo( () => {
		if ( ! usersOptions.length || ! usersWithMeta ) {
			return [];
		}

		return usersOptions.filter( ( { value } ) => {
			// Always keep 'inherit' (Dynamic User) option.
			if ( value === 'inherit' ) {
				return true;
			}
			// Check blog social graph visibility.
			if ( value === 'blog' ) {
				return ! blogSocialGraphHidden;
			}
			// Check individual user social graph visibility.
			const user = usersWithMeta?.find( ( u ) => String( u.id ) === value );
			return ! hasSocialGraphHidden( user?.meta );
		} );
	}, [ usersOptions, blogSocialGraphHidden, usersWithMeta ] );

	// Determine if we should show a notice for hidden social graph.
	const showHiddenNotice = useMemo( () => {
		if ( ! usersWithMeta ) {
			return false;
		}

		// Check blog social graph visibility.
		if ( selectedUser === 'blog' ) {
			return blogSocialGraphHidden;
		}

		// For 'inherit' mode, check if the resolved author has hidden social graph.
		if ( selectedUser === 'inherit' ) {
			if ( ! authorId ) {
				return false;
			}
			const author = usersWithMeta.find( ( u ) => u.id === authorId );
			return author ? hasSocialGraphHidden( author.meta ) : false;
		}

		return false;
	}, [ selectedUser, authorId, usersWithMeta, blogSocialGraphHidden ] );

	// Determine if current user can edit the settings for the selected user.
	const canEditSettings = useMemo( () => {
		if ( ! showHiddenNotice || ! currentUser ) {
			return false;
		}

		if ( selectedUser === 'blog' ) {
			return canManageOptions;
		}

		return currentUser.id === authorId;
	}, [ showHiddenNotice, currentUser, selectedUser, authorId, canManageOptions ] );

	// Get the settings URL for the notice.
	const settingsUrl = useMemo( () => {
		if ( ! canEditSettings || ! siteUrl ) {
			return null;
		}

		if ( selectedUser === 'blog' ) {
			return siteUrl + '/wp-admin/options-general.php?page=activitypub&tab=blog-profile';
		}

		return siteUrl + '/wp-admin/profile.php#activitypub';
	}, [ canEditSettings, siteUrl, selectedUser ] );

	useEffect( () => {
		// if there are no users yet, do nothing
		if ( ! filteredUsersOptions.length ) {
			return;
		}

		// If selected user is not in the filtered options, auto-switch to first available.
		// Exception: 'blog' and 'inherit' show a notice instead of auto-switching.
		if (
			selectedUser !== 'blog' &&
			selectedUser !== 'inherit' &&
			! filteredUsersOptions.find( ( { value } ) => value === selectedUser )
		) {
			setAttributes( { selectedUser: filteredUsersOptions[ 0 ].value } );
		}
	}, [ selectedUser, filteredUsersOptions, setAttributes ] );

	// Template for InnerBlocks - allows only a heading block.
	const TEMPLATE = [
		[
			'core/heading',
			{
				level: 3,
				placeholder: __( 'Fediverse Followers', 'activitypub' ),
				content: __( 'Fediverse Followers', 'activitypub' ),
			},
		],
	];

	return (
		<div { ...blockProps }>
			<InspectorControls key="setting">
				<PanelBody title={ __( 'Followers Options', 'activitypub' ) }>
					{ filteredUsersOptions.length > 1 && (
						<SelectControl
							label={ __( 'Select User', 'activitypub' ) }
							value={ selectedUser }
							options={ filteredUsersOptions }
							onChange={ setAttributeWithPageReset( 'selectedUser' ) }
							__next40pxDefaultSize
						/>
					) }
					<SelectControl
						label={ __( 'Sort', 'activitypub' ) }
						value={ order }
						options={ orderOptions }
						onChange={ setAttributeWithPageReset( 'order' ) }
						__next40pxDefaultSize
					/>
					<RangeControl
						label={ __( 'Number of Followers', 'activitypub' ) }
						value={ perPage }
						onChange={ setAttributeWithPageReset( 'per_page' ) }
						min={ 1 }
						max={ 10 }
						__next40pxDefaultSize
					/>
				</PanelBody>
			</InspectorControls>

			<div className={ 'wp-block-activitypub-followers ' + className }>
				<InnerBlocks
					template={ TEMPLATE }
					allowedBlocks={ [ 'core/heading' ] }
					templateLock={ 'all' }
					renderAppender={ false }
				/>

				{ showHiddenNotice && (
					<Notice status="warning" isDismissible={ false }>
						{ settingsUrl
							? createInterpolateElement(
									/* translators: <a> is a link to the profile settings page. */
									__(
										'The selected user has their social graph hidden. This block will not display followers on the frontend. <a>Edit privacy settings</a>',
										'activitypub'
									),
									{
										/* eslint-disable-next-line jsx-a11y/anchor-has-content -- Content provided by createInterpolateElement */
										a: <a href={ settingsUrl } target="_blank" rel="noopener noreferrer" />,
									}
							  )
							: __(
									'The selected user has their social graph hidden. This block will not display followers on the frontend.',
									'activitypub'
							  ) }
					</Notice>
				) }

				{ ! showHiddenNotice && selectedUser === 'inherit' && authorId && (
					<ActorList
						selectedUser={ authorId }
						perPage={ perPage }
						order={ order }
						endpoint="followers"
						page={ page }
						setPage={ setPage }
						emptyMessage={ __( 'No followers found.', 'activitypub' ) }
						navLabel={ __( 'Follower navigation', 'activitypub' ) }
					/>
				) }

				{ ! showHiddenNotice && selectedUser === 'inherit' && ! authorId && (
					<InheritModeBlockFallback name={ __( 'Followers', 'activitypub' ) } />
				) }

				{ ! showHiddenNotice && selectedUser !== 'inherit' && (
					<ActorList
						selectedUser={ selectedUser }
						perPage={ perPage }
						order={ order }
						endpoint="followers"
						page={ page }
						setPage={ setPage }
						emptyMessage={ __( 'No followers found.', 'activitypub' ) }
						navLabel={ __( 'Follower navigation', 'activitypub' ) }
					/>
				) }
			</div>
		</div>
	);
}
