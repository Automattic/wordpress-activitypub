import clsx from 'clsx';
import { useBlockProps, InnerBlocks, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, ToggleControl } from '@wordpress/components';
import { __, _x, sprintf } from '@wordpress/i18n';
import { select } from '@wordpress/data';
import { useEffect, useRef, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { Reactions } from './reactions';
import { useOptions } from '../shared/use-options';

// Generate reaction items with SVG avatars.
const generateReactionItems = ( count, prefix, startChar, colors ) =>
	Array.from( { length: count }, ( _, i ) => ( {
		name: `${ prefix } ${ i + 1 }`,
		url: '#',
		avatar: `data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'%3E%3Ccircle cx='32' cy='32' r='32' fill='%23${
			colors[ i % colors.length ]
		}'/%3E%3Ctext x='32' y='38' font-family='sans-serif' font-size='24' fill='white' text-anchor='middle'%3E${ String.fromCharCode(
			startChar + i
		) }%3C/text%3E%3C/svg%3E`,
	} ) );

// Colors for avatars.
const COLORS = [ 'FF6B6B', '4ECDC4', '45B7D1', '96CEB4', 'D4A5A5', '9B59B6', '3498DB', 'E67E22' ];

// Simple predefined dummy Reactions data.
const DUMMY_REACTIONS = {
	likes: {
		label: sprintf(
			/* translators: %d: Number of likes */
			_x( '%d likes', 'number of likes', 'activitypub' ),
			9
		),
		items: generateReactionItems( 9, 'User', 65, COLORS ), // 65 is ASCII for 'A'
	},
	reposts: {
		label: sprintf(
			/* translators: %d: Number of reposts */
			_x( '%d reposts', 'number of reposts', 'activitypub' ),
			6
		),
		items: generateReactionItems( 6, 'Reposter', 82, COLORS ), // 82 is ASCII for 'R'
	},
	quotes: {
		label: sprintf(
			/* translators: %d: Number of quotes */
			_x( '%d quotes', 'number of quotes', 'activitypub' ),
			7
		),
		items: generateReactionItems( 7, 'Quoter', 81, COLORS ), // 81 is ASCII for 'Q'
	},
};

// Dummy counts for summary view.
const DUMMY_COUNTS = {
	comments: 5,
	likes: 9,
	reposts: 6,
	quotes: 0,
};

/**
 * Summary component for displaying reaction counts.
 *
 * @param {Object}  props                     Component props.
 * @param {?number} props.postId              The Post ID.
 * @param {boolean} props.showComments        Whether to show comments count.
 * @param {boolean} props.showEmpty           Whether to show items with zero count.
 * @param {?Object} props.fallbackCounts      Optional fallback counts to use if no real data is found.
 * @return {JSX.Element} Component to render.
 */
function Summary( { postId = null, showComments, showEmpty, fallbackCounts = null } ) {
	const { namespace } = useOptions();
	const [ counts, setCounts ] = useState( fallbackCounts );
	const [ loading, setLoading ] = useState( ! fallbackCounts );

	useEffect( () => {
		// If no postId is provided or it's not a number (Site Editor), use fallback.
		if ( ! postId || typeof postId !== 'number' ) {
			if ( fallbackCounts ) {
				setCounts( fallbackCounts );
			}
			setLoading( false );
			return;
		}

		setLoading( true );
		apiFetch( {
			path: `/${ namespace }/posts/${ postId }/reactions`,
		} )
			.then( ( response ) => {
				// Build counts from the response
				const newCounts = {
					likes: response.likes?.count || 0,
					reposts: response.reposts?.count || 0,
					quotes: response.quotes?.count || 0,
				};

				// Check if there are any real reactions
				const hasReactions = Object.values( newCounts ).some( ( count ) => count > 0 );

				// If there are no real reactions and fallback is provided, use the fallback.
				if ( ! hasReactions && fallbackCounts ) {
					setCounts( fallbackCounts );
				} else {
					setCounts( newCounts );
				}
				setLoading( false );
			} )
			.catch( () => {
				if ( fallbackCounts ) {
					setCounts( fallbackCounts );
				}
				setLoading( false );
			} );
	}, [ postId, fallbackCounts, namespace ] );

	if ( loading || ! counts ) {
		return null;
	}

	const allItems = [
		{ key: 'comments', label: __( 'Comments', 'activitypub' ), count: counts.comments || 0 },
		{ key: 'likes', label: __( 'Likes', 'activitypub' ), count: counts.likes || 0 },
		{ key: 'reposts', label: __( 'Reposts', 'activitypub' ), count: counts.reposts || 0 },
		{ key: 'quotes', label: __( 'Quotes', 'activitypub' ), count: counts.quotes || 0 },
	];

	// Filter items based on settings.
	const items = allItems.filter( ( item ) => {
		if ( item.key === 'comments' && ! showComments ) {
			return false;
		}
		if ( ! showEmpty && item.count === 0 ) {
			return false;
		}
		return true;
	} );

	return (
		<div className="activitypub-reactions-summary">
			{ items.map( ( item ) => (
				<span key={ item.key } className="reactions-summary-item">
					<span className="reactions-summary-count">{ item.count }</span>
					<span className="reactions-summary-label">{ item.label }</span>
				</span>
			) ) }
		</div>
	);
}

/**
 * Edit component for the Reactions block.
 *
 * @param {Object} props                              Block props.
 * @param {Object} props.attributes                   Block attributes.
 * @param {string} props.__unstableLayoutClassNames   Layout class names.
 * @return {JSX.Element} Component to render.
 */
export default function Edit( { attributes, setAttributes, __unstableLayoutClassNames } ) {
	const { className = '', displayStyle = 'facepile', showComments = true, showEmpty = true } = attributes;
	const blockProps = useBlockProps( {
		className: __unstableLayoutClassNames,
	} );
	const { getCurrentPostId } = select( 'core/editor' );
	const { showAvatars = true } = useOptions();
	const hasInitialized = useRef( false );

	// On first render, if avatars are disabled and no style class is set, default to summary.
	useEffect( () => {
		if ( hasInitialized.current ) {
			return;
		}
		hasInitialized.current = true;

		// Only apply default if no style has been explicitly chosen yet.
		const hasStyleClass = className?.includes( 'is-style-' );
		if ( ! showAvatars && ! hasStyleClass ) {
			setAttributes( {
				className: clsx( className, 'is-style-summary' ),
				displayStyle: 'summary',
			} );
		}
	}, [ className, showAvatars, setAttributes ] );

	// Sync displayStyle attribute with className when style changes.
	const classNameStyle = className?.includes( 'is-style-summary' ) ? 'summary' : 'facepile';
	useEffect( () => {
		if ( classNameStyle !== displayStyle ) {
			setAttributes( { displayStyle: classNameStyle } );
		}
	}, [ classNameStyle, displayStyle, setAttributes ] );

	// Use displayStyle attribute for rendering decision.
	const isSummaryStyle = displayStyle === 'summary';

	// Template for InnerBlocks - allows only a heading block.
	const TEMPLATE = [
		[
			'core/heading',
			{
				level: 6,
				placeholder: __( 'Fediverse Reactions', 'activitypub' ),
				content: __( 'Fediverse Reactions', 'activitypub' ),
			},
		],
	];

	return (
		<div { ...blockProps }>
			{ isSummaryStyle && (
				<InspectorControls>
					<PanelBody title={ __( 'Summary Settings', 'activitypub' ) }>
						<ToggleControl
							label={ __( 'Show Comments', 'activitypub' ) }
							help={ __( 'Include the comment count in the summary.', 'activitypub' ) }
							checked={ showComments }
							onChange={ ( value ) => setAttributes( { showComments: value } ) }
							__nextHasNoMarginBottom
						/>
						<ToggleControl
							label={ __( 'Show empty Reactions', 'activitypub' ) }
							help={ __( 'Display Reaction types even when they have no count.', 'activitypub' ) }
							checked={ showEmpty }
							onChange={ ( value ) => setAttributes( { showEmpty: value } ) }
							__nextHasNoMarginBottom
						/>
					</PanelBody>
				</InspectorControls>
			) }
			<InnerBlocks
				template={ TEMPLATE }
				allowedBlocks={ [ 'core/heading' ] }
				templateLock={ 'all' }
				renderAppender={ false }
			/>
			{ isSummaryStyle ? (
				<Summary
					postId={ getCurrentPostId() }
					showComments={ showComments }
					showEmpty={ showEmpty }
					fallbackCounts={ DUMMY_COUNTS }
				/>
			) : (
				<Reactions postId={ getCurrentPostId() } fallbackReactions={ DUMMY_REACTIONS } />
			) }
		</div>
	);
}
