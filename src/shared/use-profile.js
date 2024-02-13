import apiFetch from '@wordpress/api-fetch';
import { useState, useEffect } from '@wordpress/element';
const { namespace } = window._activityPubOptions;
import { __ } from '@wordpress/i18n';

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
	data.header = data?.image?.url;
	return data;
}

function fetchProfile( userId ) {
	const fetchOptions = {
		headers: { Accept: 'application/activity+json' },
		path: `/${ namespace }/users/${ userId }`,
	};
	return apiFetch( fetchOptions );
}

export default function useProfile( userId ) {
	const [ profile, setProfileState ] = useState( getNormalizedProfile() );
	const [ isLoading, setIsLoading ] = useState( false );
	const [ error, setError ] = useState( null );
	const [ dirtyProfile, setDirtyProfile ] = useState( {} );
	const mergedProfile = { ...profile, ...dirtyProfile };
	const isDirty = !! Object.keys( dirtyProfile ).length;

	function setProfile( data ) {
		data = getNormalizedProfile( data );
		setProfileState( data );
		resetProfile();
	}

	function updateProfile( field, value, ...args ) {
		const data = { ...dirtyProfile }
		data[ field ] = value;
		// we are only accepting an extra ID argument for the avatar
		if ( args.length && field === 'avatar' ) {
			data[ 'avatarId' ] = args[ 0 ];
		}

		setDirtyProfile( data );
	}

	function resetProfile() {
		setDirtyProfile( {} );
	}

	async function saveProfile() {
		setIsLoading( true );
		const fetchOptions = {
			method: 'PUT',
			headers: { 'Content-Type': 'application/activity+json' },
			path: `/${ namespace }/users/${ userId }`,
			data: dirtyProfile,
		};
		return apiFetch( fetchOptions )
			.then( ( profileResponse ) => {
				setProfile( profileResponse );
			} )
			.catch( setError )
			.finally( () => setIsLoading( false ) );
	}

	useEffect( () => {
		if ( typeof userId !== 'number' ) {
			return;
		}
		setIsLoading( true );
		fetchProfile( userId )
			.then( setProfile )
			.catch( setError )
			.finally( () => setIsLoading( false ) );
	}, [ userId ] );

	return { profile: mergedProfile, isLoading, error, updateProfile, saveProfile, resetProfile, isDirty };
}
