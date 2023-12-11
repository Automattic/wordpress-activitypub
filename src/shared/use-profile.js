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
	const [ cleanProfile, setCleanProfile ] = useState( profile );
	const [ isDirty, setIsDirty ] = useState( false );

	function setProfile ( profile ) {
		profile = getNormalizedProfile( profile );
		setProfileState( profile );
	}

	function updateProfile( field, value ) {
		profile[ field ] = value;
		setIsDirty( true );
		setProfile( { ...profile } );
	}

	function resetProfile() {
		setProfile( cleanProfile );
		setIsDirty( false );
	}

	function saveProfile() {
		const fetchOptions = {
			method: 'PUT',
			headers: { 'Content-Type': 'application/activity+json' },
			path: `/${ namespace }/users/${ userId }`,
			data: JSON.stringify( profile ),
		};
		return apiFetch( fetchOptions )
			.then( ( profileResponse ) => {
				setCleanProfile( getNormalizedProfile( profileResponse ) );
				resetProfile();
			} );
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

	return { profile, isLoading, error, updateProfile, saveProfile, resetProfile, isDirty };
}
