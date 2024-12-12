import { RichText } from '@wordpress/block-editor';
import { useState, useEffect } from '@wordpress/element';
import { Popover, Button } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';
import { __, _nx, sprintf } from '@wordpress/i18n';

/**
 * Extract the namespace from the global _activityPubOptions object.
 *
 * @type {string}
 */
const { namespace } = window._activityPubOptions;

/**
 * A component that renders a row of user avatars for a given set of reactions.
 *
 * @param {Object} props           Component props.
 * @param {Array}  props.reactions Array of reaction objects.
 * @return {JSX.Element}           The rendered component.
 */
const FacepileRow = ( { reactions } ) => (
	<div className="reaction-facepile">
		<ul className="reaction-avatars">
			{ reactions.map( ( reaction, index ) => (
				<li key={ index }>
					<a
						href={ reaction.url }
						target="_blank"
						rel="noopener noreferrer"
					>
						<img
							src={ reaction.avatar }
							alt={ reaction.name }
							className="reaction-avatar"
							width="32"
							height="32"
						/>
					</a>
				</li>
			) ) }
		</ul>
	</div>
);

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
		<ul className="reaction-list">
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
 * A component that renders a reaction group with facepile and dropdown.
 *
 * @param {Object} props           Component props.
 * @param {Array}  props.reactions Array of reaction objects.
 * @param {string} props.type      Type of reaction (likes/reposts).
 * @return {JSX.Element}          The rendered component.
 */
const ReactionGroup = ( { reactions, type } ) => {
	const [ isOpen, setIsOpen ] = useState( false );
	const [ buttonRef, setButtonRef ] = useState( null );
	const count = reactions.length;

	const label = sprintf(
		/* translators: %d: number of reactions */
		_nx(
			'%d Like',
			'%d Likes',
			count,
			'number of likes',
			'activitypub'
		),
		count
	);

	const repostLabel = sprintf(
		/* translators: %d: number of reactions */
		_nx(
			'%d Repost',
			'%d Reposts',
			count,
			'number of reposts',
			'activitypub'
		),
		count
	);

	return (
		<div className="reaction-group">
			<FacepileRow reactions={ reactions } />
			<Button
				ref={ setButtonRef }
				variant="link"
				className="reaction-label"
				onClick={ () => setIsOpen( ! isOpen ) }
				aria-expanded={ isOpen }
			>
				{ type === 'likes' ? label : repostLabel }
			</Button>
			{ isOpen && buttonRef && (
				<ReactionDropdown
					reactions={ reactions }
					anchor={ buttonRef }
					onClose={ () => setIsOpen( false ) }
				/>
			) }
		</div>
	);
};

/**
 * The Reactions component.
 *
 * @param {Object}   props                  Component props.
 * @param {string}   props.title            The title text.
 * @param {?number}  props.postId           The post ID.
 * @param {boolean}  props.isEditing        Whether in edit mode.
 * @param {Function} props.setTitle         Title update callback.
 * @param {?Object}  props.reactions        Optional reactions data.
 * @return {?JSX.Element}                   The rendered component.
 */
export function Reactions( {
	title = '',
	postId = null,
	isEditing = false,
	setTitle = () => {},
	reactions: providedReactions = null,
} ) {
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
			path: `/${ namespace }/reactions/${ postId }`,
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

	return (
		<div className="activitypub-reactions">
			{ isEditing ? (
				<RichText
					tagName="h4"
					value={ title }
					onChange={ setTitle }
					placeholder={ __( 'Fediverse reactions', 'activitypub' ) }
				/>
			) : (
				title && <h4>{ title }</h4>
			) }

			{ reactions?.likes?.length > 0 && (
				<ReactionGroup
					reactions={ reactions.likes }
					type="likes"
				/>
			) }

			{ reactions?.reposts?.length > 0 && (
				<ReactionGroup
					reactions={ reactions.reposts }
					type="reposts"
				/>
			) }
		</div>
	);
}