import { useBlockProps, InnerBlocks, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, TextControl, ToggleControl } from '@wordpress/components';
import { __, _x, sprintf } from '@wordpress/i18n';
import { select } from '@wordpress/data';
import { useEffect, useRef } from '@wordpress/element';
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
 * @param {Object} props           Component props.
 * @param {string} props.separator Separator between items.
 * @return {JSX.Element} Component to render.
 */
function Summary( { separator } ) {
	const items = [
		{ label: __( 'Comments', 'activitypub' ), count: DUMMY_COUNTS.comments },
		{ label: __( 'Likes', 'activitypub' ), count: DUMMY_COUNTS.likes },
		{ label: __( 'Reposts', 'activitypub' ), count: DUMMY_COUNTS.reposts },
		{ label: __( 'Quotes', 'activitypub' ), count: DUMMY_COUNTS.quotes },
	];

	return (
		<div className="activitypub-reactions-summary">
			{ items.map( ( item, index ) => (
				<span key={ item.label }>
					{ index > 0 && <span className="reactions-summary-separator">{ separator }</span> }
					<span className="reactions-summary-item">
						<span className="reactions-summary-count">{ item.count }</span>
						<span className="reactions-summary-label">{ item.label }</span>
					</span>
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
	const {
		className = '',
		displayStyle = 'facepile',
		separator = ', ',
		showComments = true,
		showEmpty = true,
	} = attributes;
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
				className: ( className ? className + ' ' : '' ) + 'is-style-summary',
				displayStyle: 'summary',
			} );
		}
	}, [ className, showAvatars, setAttributes ] );

	// Sync displayStyle attribute with className when style changes.
	const classNameStyle = className?.includes( 'is-style-summary' ) ? 'summary' : 'facepile';
	if ( classNameStyle !== displayStyle ) {
		setAttributes( { displayStyle: classNameStyle } );
	}

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
						<TextControl
							label={ __( 'Separator', 'activitypub' ) }
							help={ __( 'Character(s) to separate each item.', 'activitypub' ) }
							value={ separator }
							onChange={ ( value ) => setAttributes( { separator: value } ) }
							__next40pxDefaultSize
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
				<Summary separator={ separator } />
			) : (
				<Reactions postId={ getCurrentPostId() } fallbackReactions={ DUMMY_REACTIONS } />
			) }
		</div>
	);
}
