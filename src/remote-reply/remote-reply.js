import apiFetch from '@wordpress/api-fetch';
import { useCallback, useEffect, useState, createInterpolateElement } from '@wordpress/element';
import { Button, Modal } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { copy, check, Icon } from '@wordpress/icons';
import { useCopyToClipboard } from '@wordpress/compose';
import './style.scss';
const { namespace } = window._activityPubOptions;

function isUrl( string ) {
	try {
		new URL( string );
		return true;
	} catch ( _ ) {
		return false;
	}
}

function isHandle( string ) {
	// remove leading @, there should still be an @ in there
	const parts = string.replace( /^@/, '' ).split( '@' );
	return parts.length === 2 && isUrl( `https://${ parts[ 1 ] }` );
}

function Dialog( { selectedComment, commentId } ) {
	const replyText = __( 'Reply', 'activitypub' );
	const loadingText = __( 'Loading...', 'activitypub' );
	const openingText = __( 'Opening...', 'activitypub' );
	const errorText = __( 'Error', 'activitypub' );
	const invalidText = __( 'Invalid', 'activitypub' );
	const [ buttonText, setButtonText ] = useState( replyText );
	const [ buttonIcon, setButtonIcon ] = useState( copy );
	const ref = useCopyToClipboard( selectedComment, () => {
		setButtonIcon( check );
		setTimeout( () => setButtonIcon( copy ), 1000 );
	} );

	const [ remoteProfile, setRemoteProfile ] = useState( '' );
	const retrieveAndFollow = useCallback( () => {
		let timeout;
		if ( ! ( isUrl( remoteProfile ) || isHandle( remoteProfile ) ) ) {
			setButtonText( invalidText );
			timeout = setTimeout( () => setButtonText( replyText ), 2000 );
			return () => clearTimeout( timeout );
		}
		const path = `/${ namespace }/comments/${commentId}/remote-reply?resource=${ remoteProfile }`;
		setButtonText( loadingText );
		apiFetch( { path } ).then( ( { url } ) => {
			setButtonText( openingText );
			setTimeout( () => {
				window.open( url, '_blank' );
				setButtonText( replyText );
			}, 200 );
		} ).catch( () => {
			setButtonText( errorText );
			setTimeout( () => setButtonText( replyText ), 2000 );
		} );
	}, [ remoteProfile ] );

	return (
		<div className="activitypub__dialog">
			<div className="activitypub-dialog__section">
				<h4>{ __( 'The Comment-URL', 'activitypub' ) }</h4>
				<div className="activitypub-dialog__description">
					{ __( 'Copy and paste the Comment-URL into the search field of your favorite fediverse app or server to reply to this Comment.', 'activitypub' ) }
				</div>
				<div className="activitypub-dialog__button-group">
					<input type="text" value={ selectedComment } readOnly />
					<Button ref={ ref }>
						<Icon icon={ buttonIcon } />
						{ __( 'Copy', 'activitypub' ) }
					</Button>
				</div>
			</div>
			<div className="activitypub-dialog__section">
				<h4>{ __( 'Your Profile', 'activitypub' ) }</h4>
				<div className="activitypub-dialog__description">
					{ createInterpolateElement(
						__( 'Or, if you know your own profile, we can start things that way! (eg <code>https://example.com/yourusername</code> or <code>yourusername@example.com</code>)', 'activitypub' ),
						{ code: <code /> }
					) }
				</div>
				<div className="activitypub-dialog__button-group">
					<input
						type="text"
						value={ remoteProfile }
						onKeyDown={ ( event ) => { event?.code === 'Enter' && retrieveAndFollow() } }
						onChange={ e => setRemoteProfile( e.target.value ) }
					/>
					<Button onClick={ retrieveAndFollow }>{ buttonText }</Button>
				</div>
			</div>
		</div>
	);
}

export default function RemoteReply( { selectedComment, commentId } ) {
	const [ isOpen, setIsOpen ] = useState( false );
	const title = __( 'Remote Reply', 'activitypub' );

	return(
		<>
			<a href="javascript:;" className="comment-reply-link activitypub-remote-reply__button" onClick={ () => setIsOpen( true ) } >
				{ __( 'Reply on the Fediverse', 'activitypub' ) }
			</a>
			{ isOpen && (
				<Modal
					className="activitypub-remote-reply__modal activitypub__modal"
					onRequestClose={ () => setIsOpen( false ) }
					title={ title }
					>
					<Dialog selectedComment={ selectedComment } commentId={ commentId } />
				</Modal>
			) }
		</>
	)
}
