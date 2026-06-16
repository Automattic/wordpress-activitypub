/**
 * Calculates the default visibility for a post based on its metadata and age.
 *
 * Note: JS uses 'public' as the value, while PHP uses '' (empty string).
 * PHP's get_content_visibility() treats any value not in ['quiet_public', 'private', 'local']
 * as public, so 'public' works correctly.
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
 * @return {string} The default visibility value ('public', 'quiet_public', or 'local').
 */
export const getDefaultVisibility = ( meta, postDate ) => {
	// If already set, use that value (handles both 'public' and '' as public).
	if ( meta?.activitypub_content_visibility ) {
		return meta.activitypub_content_visibility;
	}

	// If post is federated, use public.
	if ( meta?.activitypub_status === 'federated' ) {
		return 'public';
	}

	// If post is older than 1 month, default to local.
	if ( postDate ) {
		const postTimestamp = new Date( postDate ).getTime();
		const oneMonthAgo = Date.now() - 30 * 24 * 60 * 60 * 1000;

		if ( postTimestamp < oneMonthAgo ) {
			return 'local';
		}
	}

	// Default to public for new posts.
	return 'public';
};

/**
 * Whether to warn that editing the post will remove it from the Fediverse.
 *
 * A post that has been federated is torn down with a Delete activity the moment
 * it stops being publicly queryable. That happens through several unrelated
 * controls — the WordPress status (draft, pending, private, trash), a post
 * password, or the ActivityPub "Do not federate" visibility — so the warning is
 * keyed on the outcome ("the post is being hidden"), not on any single control.
 *
 * @param {Object} args                    Named arguments.
 * @param {string} [args.federationStatus] The stored `activitypub_status` meta.
 * @param {string} [args.status]           The edited post status.
 * @param {string} [args.password]         The edited post password.
 * @param {string} [args.visibility]       The effective ActivityPub content visibility.
 *
 * @return {boolean} True when a deletion warning should be shown.
 */
export const shouldWarnAboutFederatedDeletion = ( { federationStatus, status, password, visibility } = {} ) => {
	// Only a post that is currently federated can be deleted from the Fediverse.
	if ( 'federated' !== federationStatus ) {
		return false;
	}

	const hiddenByStatus = !! status && 'publish' !== status;
	const hiddenByPassword = !! password;
	const hiddenByVisibility = 'local' === visibility || 'private' === visibility;

	return hiddenByStatus || hiddenByPassword || hiddenByVisibility;
};
