/**
 * Checks whether a URL is safe to open or link to.
 *
 * Intent URLs are built from a template that a remote WebFinger server supplied, so they are not
 * trusted. Only absolute `http` and `https` URLs pass. `Webfinger::get_intent_endpoint()` gates the
 * same thing server-side, each side using its own parser: this one asks `new URL()`, because the
 * question at a browser sink is what the browser will treat the value as. Do not confuse it with
 * `safeUrl()` in `src/app/utils.ts`, which resolves against the origin and therefore accepts
 * `//host` and `/path` on purpose.
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
