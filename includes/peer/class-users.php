<?php
namespace Activitypub\Peer;

/**
 * ActivityPub Users DB-Class
 *
 * @author Matthias Pfefferle
 */
class Users {

	/**
	 * Undocumented function
	 *
	 * @return void
	 */
	public static function get_user_by_various( $data ) {

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
	public static function url_to_authorid( $url ) {
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
}
