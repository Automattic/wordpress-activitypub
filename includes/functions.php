<?php
namespace Activitypub;

/**
 * Returns the ActivityPub default JSON-context
 *
 * @return array the activitypub context
 */
function get_context() {
	$context = array(
		'https://www.w3.org/ns/activitystreams',
		'https://w3id.org/security/v1',
		array(
			'manuallyApprovesFollowers' => 'as:manuallyApprovesFollowers',
			'PropertyValue' => 'schema:PropertyValue',
			'schema' => 'http://schema.org#',
			'pt' => 'https://joinpeertube.org/ns#',
			'toot' => 'http://joinmastodon.org/ns#',
			'value' => 'schema:value',
			'Hashtag' => 'as:Hashtag',
			'featured' => array(
				'@id' => 'toot:featured',
				'@type' => '@id',
			),
			'featuredTags' => array(
				'@id' => 'toot:featuredTags',
				'@type' => '@id',
			),
		),
	);

	return \apply_filters( 'activitypub_json_context', $context );
}

function safe_remote_post( $url, $body, $user_id ) {
	return \Activitypub\Http::post( $url, $body, $user_id );
}

function safe_remote_get( $url, $user_id ) {
	return \Activitypub\Http::get( $url, $user_id );
}

/**
 * Returns a users WebFinger "resource"
 *
 * @param int $user_id
 *
 * @return string The user-resource
 */
function get_webfinger_resource( $user_id ) {
	return Webfinger::get_user_resource( $user_id );
}

/**
 * Requests the Meta-Data from the Actors profile
 *
 * @param string $actor The Actor URL
 *
 * @return array The Actor profile as array
 */
function get_remote_metadata_by_actor( $actor ) {
	$pre = apply_filters( 'pre_get_remote_metadata_by_actor', false, $actor );
	if ( $pre ) {
		return $pre;
	}
	if ( preg_match( '/^@?' . ACTIVITYPUB_USERNAME_REGEXP . '$/i', $actor ) ) {
		$actor = Webfinger::resolve( $actor );
	}

	if ( ! $actor ) {
		return null;
	}

	if ( is_wp_error( $actor ) ) {
		return $actor;
	}

	$transient_key = 'activitypub_' . $actor;
	$metadata = \get_transient( $transient_key );

	if ( $metadata ) {
		return $metadata;
	}

	if ( ! \wp_http_validate_url( $actor ) ) {
		$metadata = new \WP_Error( 'activitypub_no_valid_actor_url', \__( 'The "actor" is no valid URL', 'activitypub' ), $actor );
		\set_transient( $transient_key, $metadata, HOUR_IN_SECONDS ); // Cache the error for a shorter period.
		return $metadata;
	}

	$user = \get_users(
		array(
			'number' => 1,
			'capability__in' => array( 'publish_posts' ),
			'fields' => 'ID',
		)
	);

	// we just need any user to generate a request signature
	$user_id = \reset( $user );
	$short_timeout = function() {
		return 3;
	};
	add_filter( 'activitypub_remote_get_timeout', $short_timeout );
	$response = Http::get( $actor, $user_id );
	remove_filter( 'activitypub_remote_get_timeout', $short_timeout );
	if ( \is_wp_error( $response ) ) {
		\set_transient( $transient_key, $response, HOUR_IN_SECONDS ); // Cache the error for a shorter period.
		return $response;
	}

	$metadata = \wp_remote_retrieve_body( $response );
	$metadata = \json_decode( $metadata, true );

	\set_transient( $transient_key, $metadata, WEEK_IN_SECONDS );

	if ( ! $metadata ) {
		$metadata = new \WP_Error( 'activitypub_invalid_json', \__( 'No valid JSON data', 'activitypub' ), $actor );
		\set_transient( $transient_key, $metadata, HOUR_IN_SECONDS ); // Cache the error for a shorter period.
		return $metadata;
	}

	return $metadata;
}

function get_identifier_settings( $user_id ) {
	?>
<table class="form-table">
	<tbody>
		<tr>
			<th scope="row">
				<label><?php \esc_html_e( 'Profile identifier', 'activitypub' ); ?></label>
			</th>
			<td>
				<p><code><?php echo \esc_html( \Activitypub\get_webfinger_resource( $user_id ) ); ?></code> or <code><?php echo \esc_url( \get_author_posts_url( $user_id ) ); ?></code></p>
				<?php // translators: the webfinger resource ?>
				<p class="description"><?php \printf( \esc_html__( 'Try to follow "@%s" by searching for it on Mastodon,Friendica & Co.', 'activitypub' ), \esc_html( \Activitypub\get_webfinger_resource( $user_id ) ) ); ?></p>
			</td>
		</tr>
	</tbody>
</table>
	<?php
}

function get_followers( $user_id ) {
	return Collection\Followers::get_followers( $user_id );
}

function count_followers( $user_id ) {
	return Collection\Followers::count_followers( $user_id );
}

/**
 * Examine a url and try to determine the author ID it represents.
 *
 * Checks are supposedly from the hosted site blog.
 *
 * @param string $url Permalink to check.
 *
 * @return int User ID, or 0 on failure.
 */
function url_to_authorid( $url ) {
	global $wp_rewrite;

	// check if url hase the same host
	if ( \wp_parse_url( \site_url(), \PHP_URL_HOST ) !== \wp_parse_url( $url, \PHP_URL_HOST ) ) {
		return 0;
	}

	// first, check to see if there is a 'author=N' to match against
	if ( \preg_match( '/[?&]author=(\d+)/i', $url, $values ) ) {
		$id = \absint( $values[1] );
		if ( $id ) {
			return $id;
		}
	}

	// check to see if we are using rewrite rules
	$rewrite = $wp_rewrite->wp_rewrite_rules();

	// not using rewrite rules, and 'author=N' method failed, so we're out of options
	if ( empty( $rewrite ) ) {
		return 0;
	}

	// generate rewrite rule for the author url
	$author_rewrite = $wp_rewrite->get_author_permastruct();
	$author_regexp = \str_replace( '%author%', '', $author_rewrite );

	// match the rewrite rule with the passed url
	if ( \preg_match( '/https?:\/\/(.+)' . \preg_quote( $author_regexp, '/' ) . '([^\/]+)/i', $url, $match ) ) {
		$user = \get_user_by( 'slug', $match[2] );
		if ( $user ) {
			return $user->ID;
		}
	}

	return 0;
}
