import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';
import { SelectControl, PanelBody, Button } from '@wordpress/components';
import { useUserOptions } from '../shared/use-user-options';
import apiFetch from '@wordpress/api-fetch';
import { useEffect, useState } from '@wordpress/element';
const { namespace } = window._activityPubOptions;
import './style.scss';


function fetchProfile( userId ) {
	const fetchOptions = {
		headers: { Accept: 'application/activity+json' },
		path: `/${ namespace }/users/${ userId }`,
	};
	return apiFetch( fetchOptions );
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
	data.avatar = data.icon.url;
	return data;
}

function generateHandle( profile ) {
	try {
		const urlObject = new URL( profile.url );
		const { host, pathname } = urlObject;
		const first = pathname.replace( /^\//, '' );
		return `${ first}@${ host }`;
	} catch ( e ) {
		return '@error@error';
	}
}

export default function Edit( { attributes, setAttributes } ) {
	const [ profile, setProfile ] = useState( getNormalizedProfile() );
	const { selectedUser } = attributes;
	const userId = selectedUser === 'site' ? 0 : selectedUser;
	function setProfileData( profile ) {
		setProfile( getNormalizedProfile( profile ) );
	}
	useEffect( () => {
		fetchProfile( userId ).then( setProfileData );
	}, [ userId ] );

	const blockProps = useBlockProps();
	const usersOptions = useUserOptions();
	return (
		<div { ...blockProps }>
			<InspectorControls key="setting">
				<PanelBody title={ __( 'Followers Options', 'activitypub' ) }>
					<SelectControl
						label= { __( 'Select User', 'activitypub' ) }
						value={ selectedUser }
						options={ usersOptions }
						onChange={ ( value ) => setAttributes( { selectedUser: value } ) }
					/>
				</PanelBody>
			</InspectorControls>
			<Profile { ...profile } />
		</div>
	);
}

function Profile( profile ) {
	const { handle, avatar, name } = profile;
	return (
		<div className="activitypub-profile">
			<img className="activitypub-profile__avatar" src={ avatar } />
			<div className="activitypub-profile__content">
				<div className="activitpub-profile__name">{ name }</div>
				<div className="activitypub-profile__handle">{ handle }</div>
			</div>
			<Follow profile={ profile } />
		</div>
	);
}

function Follow( { profile } ) {
	return (
		<Button className="activitypub-profile__follow" isPrimary onClick={ () => console.log( profile ) } >
			{ __( 'Follow', 'fediverse' ) }
		</Button>
	);
}

