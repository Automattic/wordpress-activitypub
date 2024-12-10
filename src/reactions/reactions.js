import { RichText } from '@wordpress/components';
import { useState, useEffect } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';

const { namespace } = window._activityPubOptions;

const FacepileRow = ( { reactions } ) => {
	return (
		<div className="reaction-facepile">
			{ reactions.map( ( reaction, index ) => (
				<a
					key={ index }
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
			) ) }
		</div>
	);
};

export function Reactions( {
	title,
	postId,
	isEditing = false,
	setTitle = () => {},
 } ) {
	const [ reactions, setReactions ] = useState( null );
	const [ loading, setLoading ] = useState( true );

	useEffect( () => {
		setLoading( true );
		apiFetch( {
			path: `/${ namespace }/reactions/${ postId }`,
		} ).then( ( response ) => {
			setReactions( response );
			setLoading( false );
		} ).catch( () => setLoading( false ) );
	}, [ postId ] );

	if ( loading || ! reactions ) {
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
			) : title && <h4>{ title }</h4>  }

			{ reactions?.likes?.length > 0 && (
				<div className="reaction-group activitypub-reactions-likes">
					<span>{ __( 'Likes:', 'activitypub' ) } </span>
					<FacepileRow reactions={ reactions.likes } />
				</div>
			) }

			{ reactions?.reposts?.length > 0 && (
				<div className="reaction-group activitypub-reactions-reposts">
					<span>{ __( 'Reposts:', 'activitypub' ) } </span>
					<FacepileRow reactions={ reactions.reposts } />
				</div>
			) }
		</div>
	);
}