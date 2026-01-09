/**
 * React hook to return the ActivityPub options object from the global window.
 *
 * @return {Object} The options object.
 */
export function useOptions() {
	return window._activityPubOptions || {};
}
