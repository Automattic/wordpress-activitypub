import { useState, useEffect, useRef } from '@wordpress/element';
import { Popover, Button } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';
import { useOptions } from '../shared/use-options';

/**
 * @typedef {Object} JSX
 * @typedef {import('react').ReactElement} JSX.Element
 */

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
				const classes = [ 'reaction-avatar' ].filter( Boolean ).join( ' ' );
				const avatar = reaction.avatar || defaultAvatarUrl;

				return (
					<li key={ index }>
						<a href={ reaction.url } target="_blank" rel="noopener noreferrer">
							<img
								src={ avatar }
								alt={ reaction.name }
								className={ classes }
								width="32"
								height="32"
								onError={ ( e ) => {
									e.target.src = defaultAvatarUrl;
								} }
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
 * @return {JSX.Element} The rendered component.
 */
const ReactionList = ( { reactions } ) => {
	const { defaultAvatarUrl } = useOptions();

	return (
		<ul className="reactions-list">
			{ reactions.map( ( reaction, index ) => {
				const avatar = reaction.avatar || defaultAvatarUrl;
				return (
					<li key={ index } className="reaction-item">
						<a href={ reaction.url } className="reaction-item" target="_blank" rel="noopener noreferrer">
							<img
								src={ avatar }
								alt={ reaction.name }
								width="32"
								height="32"
								onError={ ( e ) => {
									e.target.src = defaultAvatarUrl;
								} }
							/>
							<span className="reaction-name">{ reaction.name }</span>
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
	const containerRef = useRef( null );

	const visibleItems = items.slice( 0, 20 );

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
				<Popover anchor={ buttonRef } onClose={ () => setIsOpen( false ) }>
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
 * @param {?number} props.postId    The Post ID.
 * @param {?Object} props.reactions Optional reactions data.
 * @param {?Object} props.fallbackReactions Optional fallback reactions data to use if no real reactions are found.
 * @return {?JSX.Element}               The rendered component.
 */
export function Reactions( { postId = null, reactions: providedReactions = null, fallbackReactions = null } ) {
	const { namespace } = useOptions();
	const [ reactions, setReactions ] = useState( providedReactions );
	const [ loading, setLoading ] = useState( ! providedReactions );

	const onError = () => {
		// On error, use fallback reactions if provided
		if ( fallbackReactions ) {
			setReactions( fallbackReactions );
		}
		setLoading( false );
	};

	useEffect( () => {
		if ( providedReactions ) {
			setReactions( providedReactions );
			setLoading( false );
			return;
		}

		// if no postId is provided or it's not a number (Site Editor), return early.
		if ( ! postId || typeof postId !== 'number' ) {
			onError();
			return;
		}

		setLoading( true );
		apiFetch( {
			path: `/${ namespace }/posts/${ postId }/reactions`,
		} )
			.then( ( response ) => {
				// Check if the response has any actual reactions
				const hasReactions = Object.values( response ).some( ( group ) => group.items?.length > 0 );

				// If there are no real reactions and fallback is provided, use the fallback.
				if ( ! hasReactions && fallbackReactions ) {
					setReactions( fallbackReactions );
				} else {
					setReactions( response );
				}
				setLoading( false );
			} )
			.catch( onError );
	}, [ postId, providedReactions, fallbackReactions, namespace ] );

	if ( loading ) {
		return null;
	}

	// Return null if there are no reactions
	if ( ! reactions || ! Object.values( reactions ).some( ( group ) => group.items?.length > 0 ) ) {
		return null;
	}

	return (
		<>
			{ Object.entries( reactions ).map( ( [ key, group ] ) => {
				if ( ! group.items?.length ) {
					return null;
				}

				return <ReactionGroup key={ key } items={ group.items } label={ group.label } />;
			} ) }
		</>
	);
}

// Export for testing
export { FacepileRow };
