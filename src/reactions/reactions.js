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
	const [activeIndices, setActiveIndices] = useState(new Set());
	const [rotationStates, setRotationStates] = useState(new Map());
	const timeoutRefs = useRef([]);

	const clearTimeouts = () => {
		timeoutRefs.current.forEach(timeout => clearTimeout(timeout));
		timeoutRefs.current = [];
	};

	const startWave = (startIndex, isEntering) => {
		clearTimeouts();
		const delay = 100; // 100ms between each avatar
		const totalAvatars = reactions.length;

		if (isEntering) {
			setRotationStates(current => {
				const updated = new Map(current);
				updated.set(startIndex, 'clockwise');
				return updated;
			});
		}

		// Helper function to create wave in either direction
		const createWave = (direction) => {
			const isRightward = direction === 'right';
			const start = isRightward ? startIndex : startIndex - 1;
			const end = isRightward ? totalAvatars - 1 : 0;
			const step = isRightward ? 1 : -1;

			for (let i = start; isRightward ? i <= end : i >= end; i += step) {
				const delayMultiplier = Math.abs(i - startIndex);
				const timeout = setTimeout(() => {
					setActiveIndices(current => {
						const updated = new Set(current);
						if (isEntering) {
							updated.add(i);
						} else {
							updated.delete(i);
						}
						return updated;
					});

					if (isEntering && i !== startIndex) {
						setRotationStates(current => {
							const updated = new Map(current);
							const neighborIndex = i - step;
							const neighborRotation = updated.get(neighborIndex);
							updated.set(i, neighborRotation === 'clockwise' ? 'counter' : 'clockwise');
							return updated;
						});
					}
				}, delayMultiplier * delay);
				timeoutRefs.current.push(timeout);
			}
		};

		// Create waves in both directions
		createWave('right');
		createWave('left');

		// Clear rotations when wave finishes retracting
		if (!isEntering) {
			const maxDelay = Math.max(
				(totalAvatars - startIndex) * delay,
				startIndex * delay
			);
			const timeout = setTimeout(() => {
				setRotationStates(new Map());
			}, maxDelay + delay);
			timeoutRefs.current.push(timeout);
		}
	};

	// Cleanup timeouts on unmount
	useEffect(() => {
		return () => clearTimeouts();
	}, []);

	return (
		<ul className="reaction-avatars">
			{ reactions.map( ( reaction, index ) => {
				const rotationClass = rotationStates.get(index);
				const classes = [
					'reaction-avatar',
					activeIndices.has(index) ? 'wave-active' : '',
					rotationClass ? `rotate-${rotationClass}` : ''
				].filter(Boolean).join(' ');
				const avatar = reaction.avatar || defaultAvatarUrl;

				return (
					<li key={ index }>
						<a
							href={ reaction.url }
							target="_blank"
							rel="noopener noreferrer"
							onMouseEnter={() => startWave(index, true)}
							onMouseLeave={() => startWave(index, false)}
						>
							<img
								src={ avatar }
								alt={ reaction.name }
								className={ classes }
								width="32"
								height="32"
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
 * @param {Object}   props           Component props.
 * @param {Array}    props.reactions Array of reaction objects.
 * @param {Object}   props.anchor    Reference to anchor element.
 * @param {Function} props.onClose   Callback when dropdown closes.
 * @return {JSX.Element}            The rendered component.
 */
const ReactionDropdown = ( { reactions, anchor, onClose } ) => (
	<Popover
		anchor={ anchor }
		placement="bottom-end"
		onClose={ onClose }
		className="reaction-dropdown"
		noArrow={ false }
		offset={ 10 }
	>
		<ul className="activitypub-reaction-list">
			{ reactions.map( ( reaction, index ) => (
				<li key={ index }>
					<a
						href={ reaction.url }
						className="reaction-item"
						target="_blank"
						rel="noopener noreferrer"
					>
						<img
							src={ reaction.avatar }
							alt={ reaction.name }
							width="32"
							height="32"
						/>
						<span>{ reaction.name }</span>
					</a>
				</li>
			) ) }
		</ul>
	</Popover>
);

/**
 * A component that renders a dropdown list of reactions.
 *
 * @param {Object}   props           Component props.
 * @param {Array}    props.reactions Array of reaction objects.
 * @param {string}   props.type      Type of reaction (likes/reposts).
 * @return {JSX.Element}            The rendered component.
 */
const ReactionList = ( { reactions, type } ) => (
	<ul className="activitypub-reaction-list">
		{ reactions.map( ( reaction, index ) => (
			<li key={ index }>
				<a
					href={ reaction.url }
					className="reaction-item"
					target="_blank"
					rel="noopener noreferrer"
				>
					<img
						src={ reaction.avatar }
						alt={ reaction.name }
						width="32"
						height="32"
					/>
					<span>{ reaction.name }</span>
				</a>
			</li>
		) ) }
	</ul>
);

/**
 * A component that renders a reaction group with facepile and dropdown.
 *
 * @param {Object} props           Component props.
 * @param {Array}  props.items     Array of reaction objects.
 * @param {string} props.label     Label for the reaction group.
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
			const maxAvatars = Math.max( 1, Math.floor( ( availableWidth - AVATAR_WIDTH ) / EFFECTIVE_AVATAR_WIDTH ) );

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
 * @param {Object}    props                  Component props.
 * @param {string}    props.title            The title text.
 * @param {?number}   props.postId           The post ID.
 * @param {?Object}   props.reactions        Optional reactions data.
 * @param {?JSX.Element} props.titleComponent Optional component for title editing.
 * @return {?JSX.Element}                    The rendered component.
 */
export function Reactions( {
	title = '',
	postId = null,
	reactions: providedReactions = null,
	titleComponent = null,
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
	if ( ! reactions || ! Object.values( reactions ).some( group => group.items?.length > 0 ) ) {
		return null;
	}

	return (
		<div className="activitypub-reactions">
			{ titleComponent || ( title && <h6>{ title }</h6> ) }

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
		</div>
	);
}
