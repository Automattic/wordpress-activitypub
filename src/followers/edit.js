import apiFetch from '@wordpress/api-fetch';
import { SelectControl, RangeControl, PanelBody, Notice } from '@wordpress/components';
import { InspectorControls, useBlockProps, InnerBlocks } from '@wordpress/block-editor';
import { store as coreStore } from '@wordpress/core-data';
import { useSelect } from '@wordpress/data';
import { useState, useEffect, useMemo, useCallback, createInterpolateElement } from '@wordpress/element';
import { addQueryArgs } from '@wordpress/url';
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
					<Followers { ...attributes } page={ page } setPage={ setPage } selectedUser={ authorId } />
				) }

				{ ! showHiddenNotice && selectedUser === 'inherit' && ! authorId && (
					<InheritModeBlockFallback name={ __( 'Followers', 'activitypub' ) } />
				) }

				{ ! showHiddenNotice && selectedUser !== 'inherit' && (
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
 * @param {Object}   props.followerData Optional pre-fetched follower data.
 * @return {JSX.Element} The followers list component.
 */
function Followers( {
	selectedUser,
	per_page: perPage,
	order,
	page: passedPage,
	setPage: passedSetPage,
	followerData = false,
} ) {
	const { namespace } = useOptions();
	const userId = selectedUser === 'blog' ? 0 : selectedUser;
	const [ followers, setFollowers ] = useState( [] );
	const [ pages, setPages ] = useState( 0 );
	const [ localPage, setLocalPage ] = useState( 1 );
	const page = passedPage || localPage;
	const setPage = passedSetPage || setLocalPage;

	const setData = useCallback(
		( newFollowers, total ) => {
			setFollowers( newFollowers );
			setPages( Math.ceil( total / perPage ) );
		},
		[ perPage ]
	);

	useEffect( () => {
		if ( followerData && page === 1 ) {
			return setData( followerData.followers, followerData.total );
		}

		const path = addQueryArgs( `/${ namespace }/actors/${ userId }/followers`, {
			per_page: perPage,
			order,
			page,
			context: 'full',
		} );
		apiFetch( { path } )
			.then( ( { orderedItems = [], totalItems = 0 } ) => setData( orderedItems, totalItems ) )
			.catch( () => setData( [], 0 ) );
	}, [ namespace, userId, perPage, order, page, followerData, setData ] );

	return (
		<div className="followers-container">
			{ followers.length ? (
				<ul className="followers-list">
					{ followers.map( ( follower ) => (
						<li key={ follower.url } className="follower-item">
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
 * @return {JSX.Element|null} The pagination component or null if not needed.
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
			{ /* eslint-disable-next-line jsx-a11y/anchor-is-valid -- Using anchor for visual consistency with frontend pagination */ }
			<a
				href="#followers-pagination"
				className="pagination-previous"
				aria-disabled={ disablePreviousLink }
				aria-label={ __( 'Previous page', 'activitypub' ) }
				onClick={ ( event ) => {
					event.preventDefault();
					if ( ! disablePreviousLink ) {
						setPage( page - 1 );
					}
				} }
			>
				{ __( 'Previous', 'activitypub' ) }
			</a>

			<div className="pagination-info">{ `${ page } / ${ pages }` }</div>

			{ /* eslint-disable-next-line jsx-a11y/anchor-is-valid -- Using anchor for visual consistency with frontend pagination */ }
			<a
				href="#followers-pagination"
				className="pagination-next"
				aria-disabled={ disableNextLink }
				aria-label={ __( 'Next page', 'activitypub' ) }
				onClick={ ( event ) => {
					event.preventDefault();
					if ( ! disableNextLink ) {
						setPage( page + 1 );
					}
				} }
			>
				{ __( 'Next', 'activitypub' ) }
			</a>
		</nav>
	);
}

/**
 * Component to display a single follower.
 *
 * @param {Object} props                   The component props.
 * @param {string} props.name              The name of the follower.
 * @param {Object} props.icon              The icon of the follower.
 * @param {string} props.url               The URL of the follower.
 * @param {string} props.preferredUsername The preferred username of the follower.
 * @return {JSX.Element} The follower component.
 */
function Follower( { name, icon, url, preferredUsername } ) {
	const handle = `@${ preferredUsername }`;
	const { defaultAvatarUrl, showAvatars } = useOptions();
	const avatar = icon?.url || defaultAvatarUrl;

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
