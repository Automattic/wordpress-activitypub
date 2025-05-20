/**
 * WordPress dependencies
 */
import { useState, useEffect, useRef } from '@wordpress/element';
import { Popover, Button } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';
import { useOptions } from '../shared/use-options';

/**
 * A component that renders a row of user avatars for a given set of reactions.
 *
 * @param {Object} props           Component props.
 * @param {Array}  props.reactions Array of reaction objects.
 * @return {JSX.Element}           The rendered component.
 */
const FacepileRow = ( { reactions } ) => {
	const { defaultAvatarUrl } = useOptions();

	return (
		<ul className="reaction-avatars">
			{ reactions.map( ( reaction, index ) => {
				const classes = [
					'reaction-avatar',
				]
					.filter( Boolean )
					.join( ' ' );
				const avatar = reaction.avatar || defaultAvatarUrl;

				return (
					<li key={ index }>
						<a
							href={ reaction.url }
							target="_blank"
							rel="noopener noreferrer"
						>
							<img
								src={ avatar }
								alt={ reaction.name }
								className={ classes }
								width="32"
								height="32"
								onError={ (e) => { e.target.src = defaultAvatarUrl; } }
							/>
						</a>
					</li>
				);
			} ) }
		</ul>
	);
};

/**
 * A component that renders a dropdown list of reactions.
 *
 * @param {Object} props           Component props.
 * @param {Array}  props.reactions Array of reaction objects.
 * @param {string} props.type      Type of reaction (likes/reposts).
 * @return {JSX.Element}            The rendered component.
 */
const ReactionList = ( { reactions, type } ) => {
	const { defaultAvatarUrl } = useOptions();

	return (
		<ul className="activitypub-reaction-list">
			{ reactions.map( ( reaction, index ) => {
				const avatar = reaction.avatar || defaultAvatarUrl;
				return (
					<li key={ index }>
						<a
							href={ reaction.url }
							className="reaction-item"
							target="_blank"
							rel="noopener noreferrer"
						>
							<img
								src={ avatar }
								alt={ reaction.name }
								width="32"
								height="32"
								onError={ (e) => { e.target.src = defaultAvatarUrl; } }
							/>
							<span>{ reaction.name }</span>
						</a>
					</li>
				);
			} ) }
		</ul>
	);
};

/**
 * A component that renders a reaction group with facepile and dropdown.
 *
 * @param {Object} props       Component props.
 * @param {Array}  props.items Array of reaction objects.
 * @param {string} props.label Label for the reaction group.
 * @return {JSX.Element}          The rendered component.
 */
const ReactionGroup = ( { items, label } ) => {
	const [ isOpen, setIsOpen ] = useState( false );
	const [ buttonRef, setButtonRef ] = useState( null );
	const [ visibleCount, setVisibleCount ] = useState( items.length );
	const containerRef = useRef( null );

	// Constants for calculations
	const AVATAR_WIDTH = 32; // Width of each avatar
	const AVATAR_OVERLAP = 10; // How much each avatar overlaps
	const EFFECTIVE_AVATAR_WIDTH = AVATAR_WIDTH - AVATAR_OVERLAP; // Width each additional avatar takes
	const BUTTON_GAP = 12; // Gap between avatars and button (0.75em)

	useEffect( () => {
		if ( ! containerRef.current ) {
			return;
		}

		const calculateVisibleAvatars = () => {
			const container = containerRef.current;
			if ( ! container ) {
				return;
			}

			const containerWidth = container.offsetWidth;
			const labelWidth = buttonRef?.offsetWidth || 0;
			const availableWidth = containerWidth - labelWidth - BUTTON_GAP;

			// Calculate how many avatars can fit
			// First avatar takes full width, rest take effective width
			const maxAvatars = Math.max(
				1,
				Math.floor(
					( availableWidth - AVATAR_WIDTH ) / EFFECTIVE_AVATAR_WIDTH
				)
			);

			// Ensure we don't show more than we have
			setVisibleCount( Math.min( maxAvatars, items.length ) );
		};

		// Initial calculation
		calculateVisibleAvatars();

		// Setup resize observer
		const resizeObserver = new ResizeObserver( calculateVisibleAvatars );
		resizeObserver.observe( containerRef.current );

		return () => {
			resizeObserver.disconnect();
		};
	}, [ buttonRef, items.length ] );

	const visibleItems = items.slice( 0, visibleCount );

	return (
		<div className="reaction-group" ref={ containerRef }>
			<FacepileRow reactions={ visibleItems } />
			<Button
				ref={ setButtonRef }
				className="reaction-label is-link"
				onClick={ () => setIsOpen( ! isOpen ) }
				aria-expanded={ isOpen }
			>
				{ label }
			</Button>
			{ isOpen && buttonRef && (
				<Popover
					anchor={ buttonRef }
					onClose={ () => setIsOpen( false ) }
				>
					<ReactionList reactions={ items } />
				</Popover>
			) }
		</div>
	);
};

/**
 * The Reactions component.
 *
 * @param {Object}  props           Component props.
 * @param {?number} props.postId    The post ID.
 * @param {?Object} props.reactions Optional reactions data.
 * @return {?JSX.Element}               The rendered component.
 */
export function Reactions( {
	postId = null,
	reactions: providedReactions = null,
} ) {
	const { namespace } = useOptions();
	const [ reactions, setReactions ] = useState( providedReactions );
	const [ loading, setLoading ] = useState( ! providedReactions );

	useEffect( () => {
		if ( providedReactions ) {
			setReactions( providedReactions );
			setLoading( false );
			return;
		}

		if ( ! postId ) {
			setLoading( false );
			return;
		}

		setLoading( true );
		apiFetch( {
			path: `/${ namespace }/posts/${ postId }/reactions`,
		} )
			.then( ( response ) => {
				setReactions( response );
				setLoading( false );
			} )
			.catch( () => setLoading( false ) );
	}, [ postId, providedReactions ] );

	if ( loading ) {
		return null;
	}

	// Return null if there are no reactions
	if (
		! reactions ||
		! Object.values( reactions ).some(
			( group ) => group.items?.length > 0
		)
	) {
		return null;
	}

	return (
		<>
			{ Object.entries( reactions ).map( ( [ key, group ] ) => {
				if ( ! group.items?.length ) {
					return null;
				}

				return (
					<ReactionGroup
						key={ key }
						items={ group.items }
						label={ group.label }
					/>
				);
			} ) }
		</>
	);
}
