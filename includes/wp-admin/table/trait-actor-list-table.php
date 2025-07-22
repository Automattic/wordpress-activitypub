<?php
/**
 * Actor Table Trait file.
 *
 * @package Activitypub
 */

namespace Activitypub\WP_Admin\Table;

use Activitypub\Activity\Actor;
use Activitypub\Webfinger;

/**
 * Actor Table Trait.
 */
trait Actor_List_Table {

	/**
	 * Sanitizes and normalizes an actor search term.
	 *
	 * @param string $search The search term.
	 * @return string The normalized search term.
	 */
	public static function normalize_search_term( $search ) {
		$search = \sanitize_text_field( $search );
		$search = \str_replace( array( 'acct:', 'http://', 'https://', 'www.' ), '', $search );
		$search = \str_replace( '@', ' ', $search );

		return \trim( $search );
	}

	/**
	 * Returns the WebFinger of an actor.
	 *
	 * Falls back to the preferred username if the WebFinger lookup fails or
	 * tries to extract the username from the profile URL.
	 *
	 * @param Actor $actor The actor object.
	 *
	 * @return string The WebFinger of the actor.
	 */
	public static function get_webfinger( $actor ) {
		$webfinger = Webfinger::uri_to_acct( $actor->get_id() );

		if ( ! \is_wp_error( $webfinger ) ) {
			return $webfinger;
		}

		return Webfinger::guess( $actor );
	}
}
