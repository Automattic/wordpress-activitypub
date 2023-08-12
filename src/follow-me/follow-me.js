
import apiFetch from '@wordpress/api-fetch';
import { useEffect, useState } from '@wordpress/element';
import { Button, __experimentalConfirmDialog as ConfirmDialog } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { ButtonStyle, BODY_CLASS, getPopupStyles } from './button-style';
import './style.scss';
const { namespace } = window._activityPubOptions;

function fetchProfile( userId ) {
	const fetchOptions = {
		headers: { Accept: 'application/activity+json' },
		path: `/${ namespace }/users/${ userId }`,
	};
	return apiFetch( fetchOptions );
}

function Profile( { profile, popupStyles } ) {
	const { handle, avatar, name } = profile;
	return (
		<div className="activitypub-profile">
			<img className="activitypub-profile__avatar" src={ avatar } />
			<div className="activitypub-profile__content">
				<div className="activitypub-profile__name">{ name }</div>
				<div className="activitypub-profile__handle">{ handle }</div>
			</div>
			<Follow profile={ profile } popupStyles={ popupStyles } />
		</div>
	);
}

const DEFAULT_PROFILE_DATA = {
	avatar: '',
	handle: '@well@hello.dolly',
	name: __( 'Hello Dolly Fan Account', 'fediverse' ),
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

function Follow( { profile, popupStyles } ) {
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
				{ __( 'Follow', 'fediverse' ) }
			</Button>
			<ConfirmDialog
				className="activitypub-profile__confirm"
				isOpen={ isOpen }
				onConfirm={ () => setModalIsOpen( false ) }
				onCancel={ () => setModalIsOpen( false ) }
			>
				<p>Howdy let's put some dialogs here</p>
				<style>{ popupStyles }</style>
			</ConfirmDialog>
		</>
	);
}

export default function FollowMe( attributes ) {
	const [ profile, setProfile ] = useState( getNormalizedProfile() );
	const { selectedUser } = attributes;
	const userId = selectedUser === 'site' ? 0 : selectedUser;
	const selector = attributes?.id ? `#${ attributes.id }` : '.activitypub-follow-me-block-wrapper';
	const popupStyles = getPopupStyles( attributes.style );
	function setProfileData( profile ) {
		setProfile( getNormalizedProfile( profile ) );
	}
	useEffect( () => {
		fetchProfile( userId ).then( setProfileData );
	}, [ userId ] );

	return(
		<>
			<ButtonStyle selector={ selector } style={ attributes.style } backgroundColor={ attributes.backgroundColor } />
			<Profile profile={ profile } popupStyles={ popupStyles } />
		</>
	)
}