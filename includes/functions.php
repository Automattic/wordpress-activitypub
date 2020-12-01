<?php
namespace Activitypub;

define('AS_PUBLIC', 'https://www.w3.org/ns/activitystreams#Public');

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
			'value' => 'schema:value',
		),
	);

	return \apply_filters( 'activitypub_json_context', $context );
}

function safe_remote_post( $url, $body, $user_id ) {
	$date = \gmdate( 'D, d M Y H:i:s T' );
	$signature = \Activitypub\Signature::generate_signature( $user_id, $url, $date );

	$wp_version = \get_bloginfo( 'version' );
	$user_agent = \apply_filters( 'http_headers_useragent', 'WordPress/' . $wp_version . '; ' . \get_bloginfo( 'url' ) );
	$args = array(
		'timeout' => 100,
		'limit_response_size' => 1048576,
		'redirection' => 3,
		'user-agent' => "$user_agent; ActivityPub",
		'headers' => array(
			'Accept' => 'application/activity+json',
			'Content-Type' => 'application/activity+json',
			'Signature' => $signature,
			'Date' => $date,
		),
		'body' => $body,
	);

	$response = \wp_safe_remote_post( $url, $args );

	\do_action( 'activitypub_safe_remote_post_response', $response, $url, $body, $user_id );

	return $response;
}

function forward_remote_post( $url, $body, $user_id ) {
	$date = \gmdate( 'D, d M Y H:i:s T' );
	$signature = \Activitypub\Signature::generate_signature( $user_id, $url, $date );

	$wp_version = \get_bloginfo( 'version' );
	$user_agent = \apply_filters( 'http_headers_useragent', 'WordPress/' . $wp_version . '; ' . \get_bloginfo( 'url' ) );
	$args = array(
		'timeout' => 100,
		'limit_response_size' => 1048576,
		'redirection' => 3,
		'user-agent' => "$user_agent; ActivityPub",
		'headers' => array(
			'Accept' => 'application/activity+json',
			'Content-Type' => 'application/activity+json',
			'Signature' => $signature,
			'Date' => $date,
		),
		'body' => $body,
	);

	$response = \wp_safe_remote_post( $url, $args );
	\error_log( 'forward_remote_post: wp_safe_remote_post: ' . print_r( $response, true ) );
	\do_action( 'activitypub_forward_remote_post_response', $response, $url, $body, $user_id );

	return $response;
}

function safe_remote_get( $url, $user_id ) {
	$date = \gmdate( 'D, d M Y H:i:s T' );
	$signature = \Activitypub\Signature::generate_signature( $user_id, $url, $date );

	$wp_version = \get_bloginfo( 'version' );
	$user_agent = \apply_filters( 'http_headers_useragent', 'WordPress/' . $wp_version . '; ' . \get_bloginfo( 'url' ) );
	$args = array(
		'timeout' => 100,
		'limit_response_size' => 1048576,
		'redirection' => 3,
		'user-agent' => "$user_agent; ActivityPub",
		'headers' => array(
			'Accept' => 'application/activity+json',
			'Content-Type' => 'application/activity+json',
			'Signature' => $signature,
			'Date' => $date,
		),
	);

	$response = \wp_safe_remote_get( $url, $args );

	\do_action( 'activitypub_safe_remote_get_response', $response, $url, $user_id );

	return $response;
}

/**
 * Returns a users WebFinger "resource"
 *
 * @param int $user_id
 *
 * @return string The user-resource
 */
function get_webfinger_resource( $user_id ) {
	// use WebFinger plugin if installed
	if ( \function_exists( '\get_webfinger_resource' ) ) {
		return \get_webfinger_resource( $user_id, false );
	}

	$user = \get_user_by( 'id', $user_id );

	return $user->user_login . '@' . \wp_parse_url( \home_url(), PHP_URL_HOST );
}

/**
 * [get_metadata_by_actor description]
 *
 * @param sting $actor
 *
 * @return array
 */
function get_remote_metadata_by_actor( $actor ) {
	$metadata = \get_transient( 'activitypub_' . $actor );

	if ( $metadata ) {
		return $metadata;
	}

	if ( ! \wp_http_validate_url( $actor ) ) {
		return new \WP_Error( 'activitypub_no_valid_actor_url', \__( 'The "actor" is no valid URL', 'activitypub' ), $actor );
	}

	$user = \get_users( array (
		'number' => 1,
		'who'    => 'authors',
		'fields' => 'ID',
	) );

	// we just need any user to generate a request signature
	$user_id = \reset( $user );

	$response = \Activitypub\safe_remote_get( $actor, $user_id );

	if ( \is_wp_error( $response ) ) {
		return $response;
	}

	$metadata = \wp_remote_retrieve_body( $response );
	$metadata = \json_decode( $metadata, true );

	if ( ! $metadata ) {
		return new \WP_Error( 'activitypub_invalid_json', \__( 'No valid JSON data', 'activitypub' ), $actor );
	}

	\set_transient( 'activitypub_' . $actor, $metadata, WEEK_IN_SECONDS );

	return $metadata;
}

/**
 * [get_inbox_by_actor description]
 * @param  [type] $actor [description]
 * @return [type]        [description]
 */
function get_inbox_by_actor( $actor ) {
	$metadata = \Activitypub\get_remote_metadata_by_actor( $actor );

	if ( \is_wp_error( $metadata ) ) {
		return $metadata;
	}

	if ( isset( $metadata['endpoints'] ) && isset( $metadata['endpoints']['sharedInbox'] ) ) {
		return $metadata['endpoints']['sharedInbox'];
	}

	if ( \array_key_exists( 'inbox', $metadata ) ) {
		return $metadata['inbox'];
	}

	return new \WP_Error( 'activitypub_no_inbox', __( 'No "Inbox" found', 'activitypub' ), $metadata );
}

/**
 * [get_inbox_by_actor description]
 * @param  [type] $actor [description]
 * @return [type]        [description]
 */
function get_publickey_by_actor( $actor, $key_id ) {
	$metadata = \Activitypub\get_remote_metadata_by_actor( $actor );

	if ( \is_wp_error( $metadata ) ) {
		return $metadata;
	}

	if (
		isset( $metadata['publicKey'] ) &&
		isset( $metadata['publicKey']['id'] ) &&
		isset( $metadata['publicKey']['owner'] ) &&
		isset( $metadata['publicKey']['publicKeyPem'] ) &&
		$key_id === $metadata['publicKey']['id'] &&
		$actor === $metadata['publicKey']['owner']
	) {
		return $metadata['publicKey']['publicKeyPem'];
	}

	return new \WP_Error( 'activitypub_no_public_key', \__( 'No "Public-Key" found', 'activitypub' ), $metadata );
}

function get_follower_inboxes( $user_id ) {
	$followers = \Activitypub\Peer\Followers::get_followers( $user_id );

	$inboxes = array();

	foreach ( $followers as $follower ) {
		$inbox = \Activitypub\get_inbox_by_actor( $follower );
		if ( ! $inbox || \is_wp_error( $inbox ) ) {
			continue;
		}
		// init array if empty
		if ( ! isset( $inboxes[ $inbox ] ) ) {
			$inboxes[ $inbox ] = array();
		}
		$inboxes[ $inbox ][] = $follower;
	}
	return $inboxes;
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
				<p class="description"><?php \printf( \esc_html__( 'Try to follow "@%s" in the Mastodon/Friendica search field.', 'activitypub' ), \esc_html( \Activitypub\get_webfinger_resource( $user_id ) ) ); ?></p>
			</td>
		</tr>
	</tbody>
</table>
	<?php
}

function get_followers( $user_id ) {
	$followers = \Activitypub\Peer\Followers::get_followers( $user_id );

	if ( ! $followers ) {
		return array();
	}

	return $followers;
}

function count_followers( $user_id ) {
	$followers = \Activitypub\get_followers( $user_id );

	return \count( $followers );
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
	if ( wp_parse_url( site_url(), PHP_URL_HOST ) !== wp_parse_url( $url, PHP_URL_HOST ) ) {
		return 0;
	}

	// first, check to see if there is a 'author=N' to match against
	if ( \preg_match( '/[?&]author=(\d+)/i', $url, $values ) ) {
		$id = absint( $values[1] );
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
		$user = get_user_by( 'slug', $match[2] );
		if ( $user ) {
			return $user->ID;
		}
	}

	return 0;
}

/**
 * Verify if url is a local comment, 
 * Or if it is a previously received remote comment
 * 
 * return int comment_id
 */
function url_to_commentid( $comment_url ) {
	if ( empty( $comment_url ) ) {
		return null;
	}
	$post_url = \url_to_postid( $comment_url );

	if ( $post_url ) {
		//for local comment parent
		$comment_id = explode( '#comment-', $comment_url );
		if ( isset( $comment_id[1] ) ){
			return $comment_id[1];
		} else {
			return null;
		}

	} else {
		//remote comment parent, assuming the parent was also recieved
		//Compare inReplyTo with source_url from meta, to determine if local comment_id exists for peer replied object
		$comment_args = array(
			'type' => 'activitypub',
			'meta_query' => array(
				array(
					'key' => 'source_url',
					'value' => $comment_url,
				)
			)
		);
		$comments_query = new \WP_Comment_Query;
		$comments = $comments_query->query( $comment_args );
		$found_comment_ids = array();
		if ( $comments ) {
			 foreach ( $comments as $comment ) {
				 $found_comment_ids[] = $comment->comment_ID;
			 }
			 return $found_comment_ids[0];
		} 
		return null;
	}	
}

//add recipients to CC 
function add_recipients( $recipient ) {
	$cc = array( AS_PUBLIC );
	$cc[] = $recipient;
	return $cc;
}

/**
 * Get tagged users from received AP object meta
 * @param string $object_id a comment_id to search 
 * @param boolean $post defaults to searching a comment_id
 *
 * @return array of tagged users 
 */
function get_recipients( $object_id, $post = null ) {
	$tagged_users_name = null;
	if ( $post ) {
		//post
		$ap_object = \unserialize( \get_post_meta( $object_id, '_ap_object' ) );
	} else {
		//comment
		$ap_object = \unserialize( \get_comment_meta( $object_id, 'ap_object', true ) );
	}

	if ( !empty( $ap_object ) ) {
		$tagged_users_name[] = \Activitypub\url_to_webfinger( $ap_object['actor'] );
		if ( !empty( $ap_object['object']['tag'] ) ) { 
			$author_post_url = \get_author_posts_url( $ap_object['user_id'] );
			foreach ( $ap_object['object']['tag'] as $tag ) {
				if ( $author_post_url == $tag['href'] ) {
					continue;
				} 
				if ( in_array( 'Mention', $tag ) ) {
					$tagged_users_name[] = $tag['name'];
				}
			}
		}
		return implode( ' ', $tagged_users_name );
	}	
}

/**
 * Add summary to reply 
 */
function get_summary( $comment_id ) {
	$ap_object = \unserialize( \get_comment_meta( $comment_id, 'ap_object', true ) );
	if ( !empty( $ap_object ) ) {
		if ( !empty( $ap_object['object']['summary'] ) ) {
			\error_log( 'summary: ' . $ap_object['object']['summary'] );
			return \esc_attr( $ap_object['object']['summary'] );
		}
	}	
}

/**
 * parse content for tags to transform
 */
function transform_tags( $content ) {
	//#tags

	//@Mentions
	$mentions = null;
	$webfinger_tags = \Activitypub\webfinger_extract( $content, true );
	if ( !empty( $webfinger_tags) ) {
		foreach ( $webfinger_tags[0] as $webfinger_tag ) {
			$ap_profile = \Activitypub\Rest\Webfinger::webfinger_lookup( $webfinger_tag );
			if ( ! empty( $ap_profile ) ) {
				$short_tag = \Activitypub\webfinger_short_tag( $webfinger_tag );
				$webfinger_link = "<span class='h-card'><a href=\"{$ap_profile['href']}\" class='u-url mention' rel='noopener noreferer' target='_blank'>{$short_tag}</a></span>";
				//$webfinger_link = "<span class='h-card'><a href=\"{$ap_profile['href']}\" class='u-url mention' rel='noopener noreferer' target='_blank' title='{$webfinger_tag}'>{$short_tag}</a></span>";//trips at title attribute
				$content = str_replace( $webfinger_tag, $webfinger_link, $content );
				$mentions[] = $ap_profile;
			}
		} 
	}
	// Return mentions separately to attach to comment/post meta
	$content_mentions['mentions'] = $mentions;
	$content_mentions['content'] = $content;
	return $content_mentions;
}

function tag_user( $recipient ) {
	$tagged_user = array(
		'type' => 'Mention',
		'href' => $recipient,
		'name' => \Activitypub\url_to_webfinger( $recipient ),
	);
	$tag[] = $tagged_user;
	return $tag;
}

function webfinger_extract( $string ) {
	preg_match_all("/@[\._a-zA-Z0-9-]+@[\._a-zA-Z0-9-]+/i", $string, $matches);
	//preg_match_all("/(?:(?<!\"))+@[\._a-zA-Z0-9-]+@[\._a-zA-Z0-9-]+(?:(?!\"))/i", $string, $matches);//trips on title attribute (recursion)
	return $matches;
}


function webfinger_short_tag( $webfinger ) {
	$short_tag = explode( '@', $webfinger );
	return '@' . $short_tag[1];
}

function url_to_webfinger( $user_url ) {
	$user_url = \untrailingslashit( $user_url );
	$user_url_array = explode( '/', $user_url );
	$user_name = end( $user_url_array );
	$url_host = parse_url( $user_url , PHP_URL_HOST );
	$webfinger = '@' . $user_name . '@' . $url_host;
	return $webfinger;
}

/**
 * Transform comment url, replace #fragment with ?query
 * 
 * AP Object ID must be unique
 * 
 * https://www.w3.org/TR/activitypub/#obj-id
 * https://github.com/tootsuite/mastodon/issues/13879
 */
function normalize_comment_url( $comment ) {
	$comment_id = explode( '#comment-', \get_comment_link( $comment ) );
	$comment_id = $comment_id[0] . '?comment-' . $comment_id[1];
	return $comment_id;
}

/**
 * Determine AP audience of incoming object
 */
function get_audience( $object ) {
	if ( in_array( AS_PUBLIC, $object['to'] ) ) {
		return 'public';
	}
	if ( in_array( AS_PUBLIC, $object['cc'] ) ) {
		return 'unlisted';//is unlisted even relevant?
	}
	if ( !in_array( AS_PUBLIC, $object['to'] ) && !in_array( AS_PUBLIC, $object['cc'] ) ) {
		$author_post_url = get_author_posts_url( $object['user_id'] );
		if ( in_array( $author_post_url, $object['cc'] ) ) {
			return 'followers_only';
		}
		if ( in_array( $author_post_url, $object['to'] ) ) {
			return 'private';
		}
	}
}

/**
 * Get the blacklist from the WordPress options table
 *
 * @return array the list of blacklisted hosts
 *
 * @uses apply_filters() Calls 'activitypub_blacklist' filter
 */
function get_blacklist() {
	$blacklist = \get_option( 'activitypub_blacklist' );
	$blacklist_hosts  = \explode( PHP_EOL, $blacklist );

	// if no values have been set, revert to the defaults
	if ( ! $blacklist || ! $blacklist_hosts || ! \is_array( $blacklist_hosts ) ) {
		$blacklist_hosts = array();
	}

	// clean out any blank values
	foreach ( $blacklist_hosts as $key => $value ) {
		if ( empty( $value ) ) {
			unset( $blacklist_hosts[ $key ] );
		} else {
			$blacklist_hosts[ $key ] = \trim( $blacklist_hosts[ $key ] );
		}
	}

	return \apply_filters( 'activitypub_blacklist', $blacklist_hosts );
}

/**
 * Check if an URL is blacklisted
 *
 * @param string $url an URL to check
 *
 * @return boolean
 */
function is_blacklisted( $url ) {
	foreach ( \ActivityPub\get_blacklist() as $blacklisted_host ) {
		if ( \strpos( $url, $blacklisted_host ) !== false ) {
			return true;
		}
	}

	return false;
}
