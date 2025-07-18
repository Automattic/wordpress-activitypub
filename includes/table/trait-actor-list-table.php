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
}
