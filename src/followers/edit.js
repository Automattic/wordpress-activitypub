import apiFetch from '@wordpress/api-fetch';
import { SelectControl, RangeControl, PanelBody } from '@wordpress/components';
import { InspectorControls, useBlockProps, InnerBlocks } from '@wordpress/block-editor';
import { store as coreStore } from '@wordpress/core-data';
import { useSelect } from '@wordpress/data';
import { useState, useEffect } from '@wordpress/element';
import { addQueryArgs } from '@wordpress/url';
import { __ } from '@wordpress/i18n';
import { useOptions } from '../shared/use-options';
import { useUserOptions } from '../shared/use-user-options';
import { InheritModeBlockFallback } from '../shared/inherit-block-fallback';

/**
 * Edit component.
 *
 * @param {Object} props Component props.
 * @param {Object} props.attributes Block attributes.
 * @param {Function} props.setAttributes Set block attributes.
 * @param {Object} props.context Block context.
 * @param {string} props.context.postType Post type.
 * @param {number} props.context.postId Post ID.
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
		if ( ! usersOptions.length ) {
			return;
		}
		// ensure that the selected user is in the list of options, if not, select the first available user
		if ( ! usersOptions.find( ( { value } ) => value === selectedUser ) ) {
			setAttributes( { selectedUser: usersOptions[ 0 ].value } );
		}
	}, [ selectedUser, usersOptions ] );

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
					{ usersOptions.length > 1 && (
						<SelectControl
							label={ __( 'Select User', 'activitypub' ) }
							value={ selectedUser }
							options={ usersOptions }
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

				{ selectedUser === 'inherit' ? (
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
 * Builds the API path for fetching followers.
 *
 * @param {number} userId - The ID of the user whose followers are being fetched.
 * @param {number} per_page - The number of followers to fetch per page.
 * @param {string} order - The order in which to fetch followers ('asc' or 'desc').
 * @param {number} page - The page number to fetch.
 * @return {string} The API path with query arguments for fetching followers.
 */
function getPath( userId, per_page, order, page ) {
	const { namespace } = useOptions();
	const path = `/${ namespace }/actors/${ userId }/followers`;
	const args = { per_page, order, page, context: 'full' };

	return addQueryArgs( path, args );
}

/**
 * Component to display followers of a user.
 *
 * @param {Object} props - The component props.
 * @param {String} props.selectedUser - The ID of the user whose followers are being fetched.
 * @param {number} props.per_page - The number of followers to fetch per page.
 * @param {string} props.order - The order in which to fetch followers ('asc' or 'desc').
 * @param {number} props.page - The page number to fetch.
 * @param {function} props.setPage - The function to set the page number.
 * @param {Object} props.followerData - Optional pre-fetched follower data.
 */
function Followers( {
	selectedUser,
	per_page,
	order,
	page: passedPage,
	setPage: passedSetPage,
	followerData = false,
} ) {
	const userId = selectedUser === 'site' ? 0 : selectedUser;
	const [ followers, setFollowers ] = useState( [] );
	const [ pages, setPages ] = useState( 0 );
	const [ total, setTotal ] = useState( 0 );
	const [ localPage, setLocalPage ] = useState( 1 );
	const page = passedPage || localPage;
	const setPage = passedSetPage || setLocalPage;

	const setData = ( followers, total ) => {
		setFollowers( followers );
		setTotal( total );
		setPages( Math.ceil( total / per_page ) );
	};

	useEffect( () => {
		if ( followerData && page === 1 ) {
			return setData( followerData.followers, followerData.total );
		}

		const path = getPath( userId, per_page, order, page );
		apiFetch( { path } )
			.then( ( { orderedItems, totalItems } ) => setData( orderedItems, totalItems ) )
			.catch( () => setData( [], 0 ) );
	}, [ userId, per_page, order, page, followerData ] );

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
 * @param {Object} props - The component props.
 * @param {number} props.page - The current page number.
 * @param {number} props.pages - The total number of pages.
 * @param {function} props.setPage - The function to set the page number.
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
 * Component to display a single follower.
 *
 * @param {Object} props - The component props.
 * @param {string} props.name - The name of the follower.
 * @param {Object} props.icon - The icon of the follower.
 * @param {string} props.url - The URL of the follower.
 * @param {string} props.preferredUsername - The preferred username of the follower.
 */
function Follower( { name, icon, url, preferredUsername } ) {
	const handle = `@${ preferredUsername }`;
	const { defaultAvatarUrl } = useOptions();
	const avatar = icon.url || defaultAvatarUrl;

	return (
		<a className="follower-link" href={ url } title={ handle } onClick={ ( event ) => event.preventDefault() }>
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
