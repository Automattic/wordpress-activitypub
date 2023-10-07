
import apiFetch from '@wordpress/api-fetch';
import { useCallback, useEffect, useState, createInterpolateElement } from '@wordpress/element';
import { Button, Modal } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { copy, check, Icon } from '@wordpress/icons';
import { useCopyToClipboard } from '@wordpress/compose';
import { ButtonStyle, getPopupStyles } from './button-style';
import './style.scss';
const { namespace } = window._activityPubOptions;

const DEFAULT_PROFILE_DATA = {
	avatar: '',
	resource: '@well@hello.dolly',
	name: __( 'Hello Dolly Fan Account', 'activitypub' ),
	url: '#',
};

function getNormalizedProfile( profile ) {
	if ( ! profile ) {
		return DEFAULT_PROFILE_DATA;
	}
	const data = { ...DEFAULT_PROFILE_DATA, ...profile };
	data.avatar = data?.icon?.url;
	return data;
}

function fetchProfile( userId ) {
	const fetchOptions = {
		headers: { Accept: 'application/activity+json' },
		path: `/${ namespace }/users/${ userId }`,
	};
	return apiFetch( fetchOptions );
}

function Profile( { profile, popupStyles, userId } ) {
	const { avatar, name, resource } = profile;
	return (
		<div className="activitypub-profile">
			<img className="activitypub-profile__avatar" src={ avatar } />
			<div className="activitypub-profile__content">
				<div className="activitypub-profile__name">{ name }</div>
				<div className="activitypub-profile__handle" title={ resource }>{ resource }</div>
			</div>
			<Follow profile={ profile } popupStyles={ popupStyles } userId={ userId } />
		</div>
	);
}

function Follow( { profile, popupStyles, userId } ) {
	const [ isOpen, setIsOpen ] = useState( false );
	const title = sprintf( __( 'Follow %s', 'activitypub' ), profile?.name );

	return (
		<>
			<Button className="activitypub-profile__follow" onClick={ () => setIsOpen( true ) } >
				{ __( 'Follow', 'activitypub' ) }
			</Button>
			{ isOpen && (
				<Modal
				className="activitypub-profile__confirm"
				onRequestClose={ () => setIsOpen( false ) }
				title={ title }
				>
					<Dialog profile={ profile } userId={ userId } />
					<style>{ popupStyles }</style>
			</Modal>
			) }
		</>
	);
}

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

function Dialog( { profile, userId } ) {
	const { resource } = profile;
	const followText = __( 'Follow', 'activitypub' );
	const loadingText = __( 'Loading...', 'activitypub' );
	const openingText = __( 'Opening...', 'activitypub' );
	const errorText = __( 'Error', 'activitypub' );
	const invalidText = __( 'Invalid', 'activitypub' );
	const [ buttonText, setButtonText ] = useState( followText );
	const [ buttonIcon, setButtonIcon ] = useState( copy );
	const ref = useCopyToClipboard( resource, () => {
		setButtonIcon( check );
		setTimeout( () => setButtonIcon( copy ), 1000 );
	} );
	const [ remoteProfile, setRemoteProfile ] = useState( '' );
	const retrieveAndFollow = useCallback( () => {
		let timeout;
		if ( ! ( isUrl( remoteProfile ) || isHandle( remoteProfile ) ) ) {
			setButtonText( invalidText );
			timeout = setTimeout( () => setButtonText( followText ), 2000 );
			return () => clearTimeout( timeout );
		}
		const path = `/${ namespace }/users/${userId}/remote-follow?resource=${ remoteProfile }`;
		setButtonText( loadingText );
		apiFetch( { path } ).then( ( { url } ) => {
			setButtonText( openingText );
			setTimeout( () => {
				window.open( url, '_blank' );
				setButtonText( followText );
			}, 200 );
		} ).catch( () => {
			setButtonText( errorText );
			setTimeout( () => setButtonText( followText ), 2000 );
		} );
	}, [ remoteProfile ] );

	return (
		<div className="activitypub-follow-me__dialog">
			<div className="apmfd__section">
				<h4>{ __( 'My Profile', 'activitypub' ) }</h4>
				<div className="apfmd-description">
					{ __( 'Copy and paste my profile into the search field of your favorite fediverse app or server.', 'activitypub' ) }
				</div>
				<div className="apfmd__button-group">
					<input type="text" value={ resource } readOnly />
					<Button ref={ ref }>
						<Icon icon={ buttonIcon } />
						{ __( 'Copy', 'activitypub' ) }
					</Button>
				</div>
			</div>
			<div className="apmfd__section">
				<h4>{ __( 'Your Profile', 'activitypub' ) }</h4>
				<div className="apfmd-description">
					{ createInterpolateElement(
						__( 'Or, if you know your own profile, we can start things that way! (eg <code>https://example.com/yourusername</code> or <code>yourusername@example.com</code>)', 'activitypub' ),
						{ code: <code /> }
					) }
				</div>
				<div className="apfmd__button-group">
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

export default function FollowMe( { selectedUser, style, backgroundColor, id, useId = false, profileData = false } ) {
	const [ profile, setProfile ] = useState( getNormalizedProfile() );
	const userId = selectedUser === 'site' ? 0 : selectedUser;
	const popupStyles = getPopupStyles( style );
	const wrapperProps = useId ? { id } : {};
	function setProfileData( profile ) {
		setProfile( getNormalizedProfile( profile ) );
	}
	useEffect( () => {
		if ( profileData ) {
			return setProfileData( profileData );
		}
		fetchProfile( userId ).then( setProfileData );
	}, [ userId, profileData ] );

	return(
		<div { ...wrapperProps }>
			<ButtonStyle selector={ `#${ id }` } style={ style } backgroundColor={ backgroundColor } />
			<Profile profile={ profile } userId={ userId } popupStyles={ popupStyles } />
		</div>
	)
}