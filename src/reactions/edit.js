import clsx from 'clsx';
import { useBlockProps, InnerBlocks, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, ToggleControl } from '@wordpress/components';
import { __, _x, sprintf } from '@wordpress/i18n';
import { select, useSelect } from '@wordpress/data';
import { store as editorStore } from '@wordpress/editor';
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

/**
 * Edit component for the Reactions block.
 *
 * @param {Object}   props               Block props.
 * @param {Object}   props.attributes    Block attributes.
 * @param {Function} props.setAttributes Set block attributes.
 * @return {JSX.Element} Component to render.
 */
export default function Edit( { attributes, setAttributes } ) {
	const { className = '', displayStyle = 'facepile', showActions = false, showLabels = true } = attributes;
	const blockProps = useBlockProps();
	const { getCurrentPostId } = select( 'core/editor' );
	const { showAvatars = true, reactionTypes = {} } = useOptions();
	const hasInitialized = useRef( false );

	/*
	 * The post endpoints refuse a post that is not federated, so only ask for one they will
	 * answer for. The saved post decides, not the edited one: the endpoints check the database,
	 * and the field is refreshed from every save response.
	 */
	const isPubliclyQueryable = useSelect(
		( selectFn ) => !! selectFn( editorStore ).getCurrentPost()?.activitypub_publicly_queryable,
		[]
	);

	// On first render, set default style based on avatar setting.
	useEffect( () => {
		if ( hasInitialized.current ) {
			return;
		}
		hasInitialized.current = true;

		// Only apply default if no style has been explicitly chosen yet.
		const hasStyleClass = className?.includes( 'is-style-' );
		if ( ! hasStyleClass ) {
			const defaultStyle = showAvatars ? 'facepile' : 'compact';
			setAttributes( {
				className: clsx( className, `is-style-${ defaultStyle }` ),
				displayStyle: defaultStyle,
			} );
		}
	}, [ className, showAvatars, setAttributes ] );

	// Sync displayStyle attribute with className when style changes.
	let classNameStyle = 'facepile';
	if ( className?.includes( 'is-style-compact' ) ) {
		classNameStyle = 'compact';
	} else if ( className?.includes( 'is-style-counts' ) ) {
		classNameStyle = 'counts';
	}
	useEffect( () => {
		if ( classNameStyle !== displayStyle ) {
			setAttributes( { displayStyle: classNameStyle } );
		}
	}, [ classNameStyle, displayStyle, setAttributes ] );

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

	// Render from the class, the attribute only follows it after the next render.
	const isCounts = 'counts' === classNameStyle;

	return (
		<>
			<InspectorControls>
				{ isCounts ? (
					<PanelBody title={ __( 'Labels', 'activitypub' ) }>
						<ToggleControl
							__nextHasNoMarginBottom
							label={ __( 'Show labels', 'activitypub' ) }
							help={ __(
								'Show "3 Comments" next to the icon instead of only the number.',
								'activitypub'
							) }
							checked={ showLabels }
							onChange={ ( value ) => setAttributes( { showLabels: value } ) }
						/>
					</PanelBody>
				) : (
					<PanelBody title={ __( 'Action Buttons', 'activitypub' ) }>
						<ToggleControl
							__nextHasNoMarginBottom
							label={ __( 'Show action buttons', 'activitypub' ) }
							help={ __(
								'Display Like and Boost buttons so visitors can interact from their Fediverse server.',
								'activitypub'
							) }
							checked={ showActions }
							onChange={ ( value ) => setAttributes( { showActions: value } ) }
						/>
					</PanelBody>
				) }
			</InspectorControls>
			<div { ...blockProps }>
				{ isCounts ? (
					Object.entries( reactionTypes ).map( ( [ type, reactionType ], index ) => {
						// Example counts for the preview.
						const count = ( index + 1 ) * 3;
						const label = 1 === count ? reactionType.count_single : reactionType.count_plural;

						return (
							<span key={ type } className="reaction-count">
								<span
									className="reaction-count__icon"
									dangerouslySetInnerHTML={ { __html: reactionType.icon } }
								/>
								<span className="reaction-count__text">
									{ showLabels ? sprintf( label, count ) : count }
								</span>
							</span>
						);
					} )
				) : (
					<>
						<InnerBlocks
							template={ TEMPLATE }
							allowedBlocks={ [ 'core/heading' ] }
							templateLock={ 'all' }
							renderAppender={ false }
						/>
						<Reactions
							postId={ getCurrentPostId() }
							reactions={ isPubliclyQueryable ? null : DUMMY_REACTIONS }
							fallbackReactions={ DUMMY_REACTIONS }
							displayStyle={ 'facepile' === classNameStyle && ! showAvatars ? 'compact' : classNameStyle }
							showActions={ showActions }
						/>
					</>
				) }
			</div>
		</>
	);
}
