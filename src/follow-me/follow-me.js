
import apiFetch from '@wordpress/api-fetch';
import { useCallback, useEffect, useState } from '@wordpress/element';
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
				<div className="activitypub-profile__handle">{ resource }</div>
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

function Dialog( { profile, userId } ) {
	const { resource } = profile;
	const followText = __( 'Follow', 'activitypub' );
	const loadingText = __( 'Loading...', 'activitypub' );
	const openingText = __( 'Opening...', 'activitypub' );
	const errorText = __( 'Error', 'activitypub' );
	const [ buttonText, setButtonText ] = useState( followText );
	const [ buttonIcon, setButtonIcon ] = useState( copy );
	const ref = useCopyToClipboard( resource, () => {
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
		} ).catch( () => {
			setButtonText( errorText );
			setTimeout( () => setButtonText( followText ), 2000 );
		} );
	}, [ remoteProfile ] );

	return (
		<div className="activitypub-follow-me__dialog">
			<div className="apmfd__section">
				<h4>{ __( 'Remote Follow', 'activitypub' ) }</h4>
				<div className="apfmd-description">
					{ __( 'Copy and paste this URL into the search field of your favourite Fediverse app or server.', 'activitypub' ) }
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
				<h4>{ __( 'Different Server', 'activitypub' ) }</h4>
				<div className="apfmd-description">
					{ __( 'Give us your Username/URL and we will start the process for you. (E.g. https://example.com/username or username@example.com)', 'activitypub' ) }
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

export default function FollowMe( { selectedUser, style, backgroundColor, id, useId = false } ) {
	const [ profile, setProfile ] = useState( getNormalizedProfile() );
	const userId = selectedUser === 'site' ? 0 : selectedUser;
	const popupStyles = getPopupStyles( style, backgroundColor );
	const wrapperProps = useId ? { id } : {};
	function setProfileData( profile ) {
		setProfile( getNormalizedProfile( profile ) );
	}
	useEffect( () => {
		fetchProfile( userId ).then( setProfileData );
	}, [ userId ] );

	return(
		<div { ...wrapperProps }>
			<ButtonStyle selector={ `#${ id }` } style={ style } backgroundColor={ backgroundColor } />
			<Profile profile={ profile } userId={ userId } popupStyles={ popupStyles } />
		</div>
	)
}