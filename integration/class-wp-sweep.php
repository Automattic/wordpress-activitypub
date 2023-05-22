<?php
namespace Activitypub\Integration;

use Activitypub\Collection\Followers;

/**
 * Manages the compatibility with WP Sweep.
 *
 * @link https://wordpress.org/plugins/wp-sweep/
 * @link https://github.com/polylang/polylang/tree/master/integrations/wp-sweep
 */
class Wp_Sweep {
	/**
	 * Setups actions.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'wp_sweep_excluded_taxonomies', array( self::class, 'excluded_taxonomies' ) );
		add_filter( 'wp_sweep_excluded_termids', array( self::class, 'excluded_termids' ), 0 );
	}

	/**
	 * Add 'activitypub-followers' to excluded taxonomies otherwise terms loose their language
	 * and translation group.
	 *
	 * @param array $excluded_taxonomies List of taxonomies excluded from sweeping.
	 *
	 * @return array The list of taxonomies excluded from sweeping.
	 */
	public static function excluded_taxonomies( $excluded_taxonomies ) {
		return array_merge( $excluded_taxonomies, array( Followers::TAXONOMY ) );
	}

	/**
	 * Add the translation of the default taxonomy terms and our language terms to the excluded terms.
	 *
	 * @param array $excluded_term_ids List of term ids excluded from sweeping.
	 *
	 * @return array The list of term ids excluded from sweeping.
	 */
	public static function excluded_termids( $excluded_term_ids ) {
		// We got a list of excluded terms (defaults and parents). Let exclude their translations too.
		$followers = Followers::get_all_followers( array( 'fields' => 'ids' ) );

		$excluded_term_ids = array_merge( $excluded_term_ids, $followers );

		return array_unique( $excluded_term_ids );
	}
}
