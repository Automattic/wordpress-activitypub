import { SelectControl, RangeControl, PanelBody, Notice } from '@wordpress/components';
import { InspectorControls, useBlockProps, InnerBlocks } from '@wordpress/block-editor';
import { store as coreStore, useEntityRecords, useEntityRecord } from '@wordpress/core-data';
import { useSelect } from '@wordpress/data';
import { useState, useEffect, useMemo } from '@wordpress/element';
import { decodeEntities } from '@wordpress/html-entities';
import { __ } from '@wordpress/i18n';
import { useOptions } from '../shared/use-options';
import { useUserOptions } from '../shared/use-user-options';
import { InheritModeBlockFallback } from '../shared/inherit-block-fallback';

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
 * @param {Object}   props                   Component props.
 * @param {Object}   props.attributes        Block attributes.
 * @param {Function} props.setAttributes     Set block attributes.
 * @param {Object}   props.context           Block context.
 * @param {string}   props.context.postType  Post type.
 * @param {number}   props.context.postId    Post ID.
 *
 * @return {JSX.Element} Edit component.
 */
export default function Edit( { attributes, setAttributes, context: { postType, postId } } ) {
	const { className = '', order, per_page, selectedUser } = attributes;
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
	const { record: siteSettings } = useEntityRecord( 'root', 'site', undefined );
	const blogSocialGraphHidden = !! siteSettings?.activitypub_hide_social_graph;

	// Get current user for capability checks and to determine if they can edit settings.
	const { currentUser, usersWithMeta, siteUrl } = useSelect( ( select ) => {
		const { getCurrentUser, getUsers, getEntityRecord } = select( coreStore );
		const siteData = getEntityRecord( 'root', '__unstableBase' );

		return {
			currentUser: getCurrentUser(),
			usersWithMeta: getUsers( { capabilities: 'activitypub' } ),
			siteUrl: siteData?.home,
		};
	}, [] );

	// Filter user options based on social graph visibility.
	const filteredUsersOptions = useMemo( () => {
		if ( ! usersOptions.length ) {
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
		// Don't show notice for 'inherit' mode.
		if ( selectedUser === 'inherit' ) {
			return false;
		}

		return ! filteredUsersOptions.find( ( { value } ) => value === selectedUser );
	}, [ selectedUser, filteredUsersOptions ] );

	// Determine if current user can edit the settings for the selected user.
	const canEditSettings = useMemo( () => {
		if ( ! showHiddenNotice || ! currentUser ) {
			return false;
		}
		if ( selectedUser === 'blog' ) {
			return currentUser.capabilities?.manage_options;
		}

		return String( currentUser.id ) === selectedUser;
	}, [ showHiddenNotice, currentUser, selectedUser ] );

	// Get the settings URL for the notice.
	const settingsUrl = useMemo( () => {
		if ( ! canEditSettings || ! siteUrl ) {
			return null;
		}
		if ( selectedUser === 'blog' ) {
			return siteUrl + '/wp-admin/options-general.php?page=activitypub&tab=blog-profile';
		}

		return siteUrl + '/wp-admin/profile.php#activitypub';
	}, [ canEditSettings, selectedUser, siteUrl ] );

	const authorId = useSelect(
		( select ) => {
			const { getEditedEntityRecord } = select( coreStore );
			const _authorId = getEditedEntityRecord( 'postType', postType, postId )?.author;

			return _authorId ?? null;
		},
		[ postType, postId ]
	);

	useEffect( () => {
		// if there are no users yet, do nothing
		if ( ! filteredUsersOptions.length ) {
			return;
		}

		// Ensure that the selected user is in the list of options, if not, select the first available user
		// but only if the notice isn't showing (to preserve existing blocks with hidden social graph)
		if ( ! showHiddenNotice && ! filteredUsersOptions.find( ( { value } ) => value === selectedUser ) ) {
			setAttributes( { selectedUser: filteredUsersOptions[ 0 ].value } );
		}
	}, [ selectedUser, filteredUsersOptions, setAttributes, showHiddenNotice ] );

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
							__nextHasNoMarginBottom
						/>
					) }
					<SelectControl
						label={ __( 'Sort', 'activitypub' ) }
						value={ order }
						options={ orderOptions }
						onChange={ setAttributeWithPageReset( 'order' ) }
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>
					<RangeControl
						label={ __( 'Number of Followers', 'activitypub' ) }
						value={ per_page }
						onChange={ setAttributeWithPageReset( 'per_page' ) }
						min={ 1 }
						max={ 10 }
						__next40pxDefaultSize
						__nextHasNoMarginBottom
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

				{ showHiddenNotice ? (
					<Notice status="warning" isDismissible={ false }>
						{ __(
							'The selected user has their social graph hidden. This block will not display followers on the frontend.',
							'activitypub'
						) }
						{ settingsUrl && (
							<>
								{ ' ' }
								<a href={ settingsUrl } target="_blank" rel="noopener noreferrer">
									{ __( 'Edit privacy settings', 'activitypub' ) }
								</a>
							</>
						) }
					</Notice>
				) : selectedUser === 'inherit' ? (
					authorId ? (
						<Followers { ...attributes } page={ page } setPage={ setPage } selectedUser={ authorId } />
					) : (
						<InheritModeBlockFallback name={ __( 'Followers', 'activitypub' ) } />
					)
				) : (
					<Followers { ...attributes } page={ page } setPage={ setPage } />
				) }
			</div>
		</div>
	);
}

/**
 * Component to display followers of a user.
 *
 * @param {Object}   props              The component props.
 * @param {string}   props.selectedUser The ID of the user whose followers are being fetched.
 * @param {number}   props.per_page     The number of followers to fetch per page.
 * @param {string}   props.order        The order in which to fetch followers ('asc' or 'desc').
 * @param {number}   props.page         The page number to fetch.
 * @param {Function} props.setPage      The function to set the page number.
 * @return {JSX.Element} The followers list component.
 */
function Followers( { selectedUser, per_page, order, page: passedPage, setPage: passedSetPage } ) {
	const userId = selectedUser === 'blog' ? 0 : selectedUser;
	const [ localPage, setLocalPage ] = useState( 1 );
	const page = passedPage || localPage;
	const setPage = passedSetPage || setLocalPage;

	const { records: followers, totalItems } = useEntityRecords( 'postType', 'ap_actor', {
		activitypub_following: userId,
		per_page,
		page,
		order,
		orderby: 'id',
	} );

	const pages = Math.ceil( ( totalItems || 0 ) / per_page );

	return (
		<div className="followers-container">
			{ followers?.length ? (
				<ul className="followers-list">
					{ followers.map( ( follower ) => (
						<li key={ follower.guid.rendered } className="follower-item">
							<Follower { ...follower } />
						</li>
					) ) }
				</ul>
			) : (
				<p className="followers-placeholder">{ __( 'No followers found.', 'activitypub' ) }</p>
			) }

			<Pagination page={ page } pages={ pages } setPage={ setPage } />
		</div>
	);
}

/**
 * Component to display pagination navigation.
 *
 * @param {Object}   props         The component props.
 * @param {number}   props.page    The current page number.
 * @param {number}   props.pages   The total number of pages.
 * @param {Function} props.setPage The function to set the page number.
 */
function Pagination( { page, pages, setPage } ) {
	if ( pages <= 1 ) {
		return null;
	}

	const disablePreviousLink = page <= 1;
	const disableNextLink = page >= pages;

	return (
		<nav className="followers-pagination" role="navigation">
			<h1 className="screen-reader-text">{ __( 'Follower navigation', 'activitypub' ) }</h1>
			<a
				className="pagination-previous"
				aria-disabled={ disablePreviousLink }
				aria-label={ __( 'Previous page', 'activitypub' ) }
				onClick={ ( event ) => {
					event.preventDefault();
					setPage( page - 1 );
				} }
			>
				{ __( 'Previous', 'activitypub' ) }
			</a>

			<div className="pagination-info">{ `${ page } / ${ pages }` }</div>

			<a
				className="pagination-next"
				aria-disabled={ disableNextLink }
				aria-label={ __( 'Next page', 'activitypub' ) }
				onClick={ ( event ) => {
					event.preventDefault();
					setPage( page + 1 );
				} }
			>
				{ __( 'Next', 'activitypub' ) }
			</a>
		</nav>
	);
}

/**
 * @typedef {Object} FollowerMeta
 * @property {string} [_activitypub_acct]       The account handle.
 * @property {string} [_activitypub_avatar_url] The avatar URL.
 */

/**
 * Component to display a single follower.
 *
 * @param {Object}       props       The component props.
 * @param {Object}       props.title The title object containing rendered name.
 * @param {Object}       props.guid  The guid object containing rendered URL.
 * @param {FollowerMeta} props.meta  The object containing follower data.
 * @return {JSX.Element} The follower component.
 */
function Follower( { title, guid, meta } ) {
	const { defaultAvatarUrl, showAvatars } = useOptions();
	const name = decodeEntities( title?.rendered || '' );
	const url = guid?.rendered || '#';
	const handle = meta?._activitypub_acct ? `@${ meta._activitypub_acct }` : '';
	const avatar = meta?._activitypub_avatar_url || defaultAvatarUrl;

	return (
		<a className="follower-link" href={ url } title={ handle } onClick={ ( event ) => event.preventDefault() }>
			{ showAvatars && (
				<img
					width="48"
					height="48"
					src={ avatar }
					className="follower-avatar"
					alt={ name }
					onError={ ( event ) => {
						event.target.src = defaultAvatarUrl;
					} }
				/>
			) }
			<div className="follower-info">
				<span className="follower-name">{ name }</span>
				<span className="follower-username">{ handle }</span>
			</div>
			<svg
				xmlns="http://www.w3.org/2000/svg"
				viewBox="0 0 24 24"
				width="24"
				height="24"
				className="external-link-icon"
				aria-hidden="true"
				focusable="false"
				fill="currentColor"
			>
				<path d="M18.2 17c0 .7-.6 1.2-1.2 1.2H7c-.7 0-1.2-.6-1.2-1.2V7c0-.7.6-1.2 1.2-1.2h3.2V4.2H7C5.5 4.2 4.2 5.5 4.2 7v10c0 1.5 1.2 2.8 2.8 2.8h10c1.5 0 2.8-1.2 2.8-2.8v-3.6h-1.5V17zM14.9 3v1.5h3.7l-6.4 6.4 1.1 1.1 6.4-6.4v3.7h1.5V3h-6.3z"></path>
			</svg>
		</a>
	);
}
