/**
 * Checks whether a URL is safe to open or link to.
 *
 * Intent URLs are built from a template that a remote WebFinger server supplied, so they are not
 * trusted. Only absolute `http` and `https` URLs pass. `Webfinger::get_intent_endpoint()` is the
 * authoritative gate and applies the same allow list, keep the two in step.
 *
 * @param {*} url The URL to check.
 *
 * @return {boolean} Whether the URL is an absolute `http(s)` URL.
 */
export function isSafeUrl( url ) {
	try {
		return [ 'http:', 'https:' ].includes( new URL( url ).protocol );
	} catch ( _ ) {
		return false;
	}
}
