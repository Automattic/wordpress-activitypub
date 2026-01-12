/**
 * Visibility constants matching PHP ACTIVITYPUB_CONTENT_VISIBILITY_* constants.
 */
export const VISIBILITY_PUBLIC = '';
export const VISIBILITY_QUIET_PUBLIC = 'quiet_public';
export const VISIBILITY_LOCAL = 'local';

/**
 * Calculates the default visibility for a post based on its metadata and age.
 *
 * Priority order:
 * 1. Explicitly set visibility value
 * 2. Federated posts default to public
 * 3. Posts older than 1 month default to local
 * 4. New posts default to public
 *
 * @param {Object}      meta     The post metadata object.
 * @param {string|Date} postDate The post date.
 *
 * @return {string} The default visibility value ('' for public, 'quiet_public', or 'local').
 */
export const getDefaultVisibility = ( meta, postDate ) => {
	// If already set, use that value.
	if ( meta?.activitypub_content_visibility ) {
		return meta.activitypub_content_visibility;
	}

	// If post is federated, use public.
	if ( meta?.activitypub_status === 'federated' ) {
		return VISIBILITY_PUBLIC;
	}

	// If post is older than 1 month, default to local.
	if ( postDate ) {
		const postTimestamp = new Date( postDate ).getTime();
		const oneMonthAgo = Date.now() - 30 * 24 * 60 * 60 * 1000;

		if ( postTimestamp < oneMonthAgo ) {
			return VISIBILITY_LOCAL;
		}
	}

	// Default to public for new posts.
	return VISIBILITY_PUBLIC;
};
