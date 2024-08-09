<?php
namespace Activitypub\Handler;

use Activitypub\Comment;
use Activitypub\Collection\Interactions;

use function Activitypub\object_to_uri;

/**
 * Handle Like requests
 */
class Like {
	/**
	 * Initialize the class, registering WordPress hooks
	 */
	public static function init() {
		\add_action(
			'activitypub_inbox_like',
			array( self::class, 'handle_like' ),
			10,
			3
		);
	}

	/**
	 * Handles "Like" requests
	 *
	 * @param array                 $array    The Activity array.
	 * @param int                   $user_id  The ID of the local blog user.
	 * @param \Activitypub\Activity $activity The Activity object.
	 *
	 * @return void
	 */
	public static function handle_like( $array, $user_id, $activity = null ) { // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.arrayFound,VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable,Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		if ( ACTIVITYPUB_DISABLE_INCOMING_INTERACTIONS ) {
			return;
		}

		$url = object_to_uri( $array['object'] );

		if ( empty( $url ) ) {
			return;
		}

		$exists = Comment::object_id_to_comment( esc_url_raw( $url ) );
		if ( $exists ) {
			return;
		}

		$state    = Interactions::add_reaction( $array );
		$reaction = null;

		if ( $state && ! is_wp_error( $state ) ) {
			$reaction = get_comment( $state );
		}

		do_action( 'activitypub_handled_like', $array, $user_id, $state, $reaction );
	}
}
