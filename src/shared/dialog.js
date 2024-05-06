import apiFetch from '@wordpress/api-fetch';
import { useCallback, useState, createInterpolateElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { copy, check, Icon } from '@wordpress/icons';
import { useCopyToClipboard } from '@wordpress/compose';
import { Button, Modal } from '@wordpress/components';

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

export function Dialog( { actionText, copyDescription, handle, resourceUrl } ) {
	const loadingText = __( 'Loading...', 'activitypub' );
	const openingText = __( 'Opening...', 'activitypub' );
	const errorText = __( 'Error', 'activitypub' );
	const invalidText = __( 'Invalid', 'activitypub' );
	const [ buttonText, setButtonText ] = useState( actionText );
	const [ buttonIcon, setButtonIcon ] = useState( copy );
	const ref = useCopyToClipboard( handle, () => {
		setButtonIcon( check );
		setTimeout( () => setButtonIcon( copy ), 1000 );
	} );
	const [ remoteProfile, setRemoteProfile ] = useState( '' );
	const retrieveAndFollow = useCallback( () => {
		let timeout;
		if ( ! ( isUrl( remoteProfile ) || isHandle( remoteProfile ) ) ) {
			setButtonText( invalidText );
			timeout = setTimeout( () => setButtonText( actionText ), 2000 );
			return () => clearTimeout( timeout );
		}
		// use the resourceUrl
		const path = resourceUrl + remoteProfile;
		setButtonText( loadingText );
		apiFetch( { path } ).then( ( { url } ) => {
			setButtonText( openingText );
			setTimeout( () => {
				window.open( url, '_blank' );
				setButtonText( actionText );
			}, 200 );
		} ).catch( () => {
			setButtonText( errorText );
			setTimeout( () => setButtonText( actionText ), 2000 );
		} );
	}, [ remoteProfile ] );

	return (
		<div className="activitypub__dialog">
			<div className="activitypub-dialog__section">
				<h4>{ __( 'My Profile', 'activitypub' ) }</h4>
				<div className="activitypub-dialog-description">
					{ copyDescription }
				</div>
				<div className="activitypub-dialog__button-group">
					<input type="text" value={ handle } readOnly />
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
