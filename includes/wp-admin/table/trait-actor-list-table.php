<?php
/**
 * Actor Table Trait file.
 *
 * @package Activitypub
 */

namespace Activitypub\WP_Admin\Table;

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
	public function normalize_search_term( $search ) {
		$search = \sanitize_text_field( $search );
		$search = \str_replace( array( 'acct:', 'http://', 'https://', 'www.' ), '', $search );
		$search = \str_replace( '@', ' ', $search );

		return \trim( $search );
	}

	/**
	 * Get the action URL for a follower.
	 *
	 * @param string $action   The action.
	 * @param string $follower The follower ID.
	 * @return string The action URL.
	 */
	private function get_action_url( $action, $follower ) {
		return \wp_nonce_url(
			\add_query_arg(
				array(
					'action'   => $action,
					'follower' => $follower,
				)
			),
			$action . '-follower_' . $follower
		);
	}
}
