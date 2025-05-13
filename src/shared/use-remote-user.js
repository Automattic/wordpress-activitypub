import { useState, useCallback } from '@wordpress/element';

const storageKey = 'fediverse-remote-user';

/**
 * Retrieve the remote user data from localStorage.
 *
 * @returns {Object} Remote user data or empty object, if not set.
 */
function getStore() {
	const data = localStorage.getItem( storageKey );
	if ( ! data ) {
		return {};
	}
	return JSON.parse( data );
}

/**
 * Store remote user data in localStorage.
 *
 * @param {Object} data - Remote user data to store.
 */
function setStore( data ) {
	localStorage.setItem( storageKey, JSON.stringify( data ) );
}

/**
 * Remove remote user data from localStorage.
 */
function deleteStore() {
	localStorage.removeItem( storageKey );
}

/**
 * React hook to manage the remote user state.
 *
 * @returns {Object} Object with template, profileURL, setRemoteUser, deleteRemoteUser.
 */
export function useRemoteUser() {
	const [ remoteUser, setRemoteUserInternal ] = useState( getStore() );

	/**
	 * Set the remote user and update localStorage.
	 *
	 * @param {Object} data - Remote user data to set.
	 */
	const setRemoteUser = useCallback( ( data ) => {
		setStore( data );
		setRemoteUserInternal( data );
	}, [] );

	/**
	 * Delete the remote user and clear localStorage.
	 */
	const deleteRemoteUser = useCallback( () => {
		deleteStore();
		setRemoteUserInternal( {} );
	}, [] );

	return {
		template: remoteUser?.template || false,
		profileURL: remoteUser?.profileURL || false,
		setRemoteUser,
		deleteRemoteUser,
	};
}
