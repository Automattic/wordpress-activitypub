<?php
/**
 * Actor Table Trait file.
 *
 * @package Activitypub
 */

namespace Activitypub\Table;

/**
 * Actor Table Trait.
 */
trait Actor_List_Table {

	/**
	 * Sanitize an actor search term.
	 *
	 * @param string $search The search term.
	 * @return string The sanitized search term.
	 */
	public static function sanitize_search_term( $search ) {
		$search = \sanitize_text_field( $search );
		$search = \str_replace( array( 'acct:', 'http://', 'https://', 'www.' ), '', $search );
		$search = \str_replace( '@', ' ', $search );

		return \trim( $search );
	}
}
