/**
 * Get options from window._activityPubOptions.
 *
 * @param {string|undefined} key - Optional key to retrieve specific option.
 * @return {*|boolean} Option value if found, false if not found, or entire options object if no key provided.
 */
const getOptions = ( key ) => {
	if ( ! window._activityPubOptions ) {
		return false;
	}

	if ( typeof key === 'undefined' ) {
		return window._activityPubOptions;
	}

	return window._activityPubOptions[ key ] || false;
};

export default getOptions;
