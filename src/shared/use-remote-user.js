import { useState, useCallback } from '@wordpress/element';

const storageKey = 'fediverse-remote-user';

function getStore() {
	const data = localStorage.getItem( storageKey );
	if ( ! data ) {
		return {};
	}
	return JSON.parse( data );
}

function setStore( data ) {
	localStorage.setItem( storageKey, JSON.stringify( data ) );
}

function deleteStore() {
	localStorage.removeItem( storageKey );
}

export function useRemoteUser() {
  const [ remoteUser, setRemoteUserInternal ] = useState( getStore() );

	const setRemoteUser = useCallback( ( data ) => {
    setStore( data );
    setRemoteUserInternal( data );
  }, [] );

  const deleteRemoteUser = useCallback( () => {
    deleteStore();
    setRemoteUserInternal( {} );
  }, [] );

  return {
    template: remoteUser?.template || false,
    profileURL: remoteUser?.profileURL || false,
    setRemoteUser,
    deleteRemoteUser
  };
}

