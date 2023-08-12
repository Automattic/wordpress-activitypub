
import apiFetch from '@wordpress/api-fetch';
import { useCallback, useEffect, useState } from '@wordpress/element';
import { Button, __experimentalConfirmDialog as ConfirmDialog } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { copy, check, Icon } from '@wordpress/icons';
import { useCopyToClipboard } from '@wordpress/compose';
import { ButtonStyle, BODY_CLASS, getPopupStyles } from './button-style';
import './style.scss';
const { namespace } = window._activityPubOptions;

const DEFAULT_PROFILE_DATA = {
	avatar: '',
	handle: '@well@hello.dolly',
	name: __( 'Hello Dolly Fan Account', 'activitypub' ),
	url: '#',
};

function getNormalizedProfile( profile ) {
if ( ! profile ) {
	return DEFAULT_PROFILE_DATA;
}
profile.handle = generateHandle( profile );
const data = { ...DEFAULT_PROFILE_DATA, ...profile };
data.avatar = data?.icon?.url;
return data;
}

function generateHandle( profile ) {
	try {
		const { host, pathname } = new URL( profile.url )
		const first = profile.preferredUsername ?? pathname.replace( /^\//, '' );
		return `${ first }@${ host }`;
	} catch ( e ) {
		return '@error@error';
	}
}

function fetchProfile( userId ) {
	const fetchOptions = {
		headers: { Accept: 'application/activity+json' },
		path: `/${ namespace }/users/${ userId }`,
	};
	return apiFetch( fetchOptions );
}

function Profile( { profile, popupStyles, userId } ) {
	const { handle, avatar, name } = profile;
	return (
		<div className="activitypub-profile">
			<img className="activitypub-profile__avatar" src={ avatar } />
			<div className="activitypub-profile__content">
				<div className="activitypub-profile__name">{ name }</div>
				<div className="activitypub-profile__handle">{ handle }</div>
			</div>
			<Follow profile={ profile } popupStyles={ popupStyles } userId={ userId } />
		</div>
	);
}

function Follow( { profile, popupStyles, userId } ) {
	const [ isOpen, setIsOpen ] = useState( false );
	// a function that adds/removes the activitypub-follow-modal-active class to the body
	function setModalIsOpen( value ) {
		const method = value ? 'add' : 'remove';
		document.body.classList[ method ]( BODY_CLASS );
		setIsOpen( value );
	}
	return (
		<>
			<Button className="activitypub-profile__follow" onClick={ () => setModalIsOpen( true ) } >
				{ __( 'Follow', 'activitypub' ) }
			</Button>
			<ConfirmDialog
				className="activitypub-profile__confirm"
				isOpen={ isOpen }
				onConfirm={ () => setModalIsOpen( false ) }
				onCancel={ () => setModalIsOpen( false ) }
			>
				<Dialog profile={ profile } userId={ userId } />
				<style>{ popupStyles }</style>
			</ConfirmDialog>
		</>
	);
}

function Dialog( { profile, userId } ) {
	const { name, url } = profile;
	const title = sprintf( __( 'Follow %s', 'activitypub' ), name );
	const followText = __( 'Follow', 'activitypub' );
	const loadingText = __( 'Loading...', 'activitypub' );
	const openingText = __( 'Opening...', 'activitypub' );
	const errorText = __( 'Error', 'activitypub' );
	const [ buttonText, setButtonText ] = useState( followText );
	const [ buttonIcon, setButtonIcon ] = useState( copy );
	const ref = useCopyToClipboard( url, () => {
		setButtonIcon( check );
		setTimeout( () => setButtonIcon( copy ), 1000 );
	} );
	const [ remoteProfile, setRemoteProfile ] = useState( '' );
	const retrieveAndFollow = useCallback( () => {
		const path = `/${ namespace }/users/${userId}/remote-follow?resource=${ remoteProfile }`;
		setButtonText( loadingText );
		apiFetch( { path } ).then( ( { url } ) => {
			setButtonText( openingText );
			setTimeout( () => {
				window.open( url, '_blank' );
				setButtonText( followText );
			}, 200 );
		} ).catch( ( e ) => {
			console.error( e );
			setButtonText( errorText );
			setTimeout( () => setButtonText( followText ), 2000 );
		} );
	}, [ remoteProfile ] );

	return (
		<div className="activitypub-follow-me__dialog">
			<h2>{ title }</h2>
			<div>
				<h3>{ __( 'Remote Follow', 'activitypub' ) }</h3>
				<div>{
					__( 'Copy and paste this URL into the search field of your favourite Fediverse app or server.', 'activitypub' )
				}</div>
				<div>
					<input type="text" value={ profile.url } readOnly />
					<Button ref={ ref }>
						<Icon icon={ buttonIcon } />
						{ __( 'Copy', 'activitypub' ) }
					</Button>
				</div>
			</div>
			<div>
				<h3>{ __( 'Different Server', 'activitypub' ) }</h3>
				<div>{
					__( 'Give us your Username/URL and we will start the process for you. (E.g. https://example.com/username or username@example.com)', 'activitypub' )
				}</div>
				<div>
					<input type="text" value={ remoteProfile } onChange={ e => setRemoteProfile( e.target.value ) } />
					<Button onClick={ retrieveAndFollow }>{ buttonText }</Button>
				</div>
			</div>
		</div>
	);
}

export default function FollowMe( { selectedUser, style, backgroundColor, id } ) {
	const [ profile, setProfile ] = useState( getNormalizedProfile() );
	const userId = selectedUser === 'site' ? 0 : selectedUser;
	const selector = id ? `#${ id }` : '.activitypub-follow-me-block-wrapper';
	const popupStyles = getPopupStyles( style );
	function setProfileData( profile ) {
		setProfile( getNormalizedProfile( profile ) );
	}
	useEffect( () => {
		fetchProfile( userId ).then( setProfileData );
	}, [ userId ] );

	return(
		<>
			<ButtonStyle selector={ selector } style={ style } backgroundColor={ backgroundColor } />
			<Profile profile={ profile } userId={ userId } popupStyles={ popupStyles } />
		</>
	)
}