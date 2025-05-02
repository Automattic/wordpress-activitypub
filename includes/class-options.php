<?php
/**
 * Options file.
 *
 * @package ActivityPub
 */

namespace ActivityPub;

/**
 * Options class.
 *
 * @package ActivityPub
 */
class Options {

	/**
	 * Initialize the options.
	 */
	public static function init() {
		\add_filter( 'pre_option_activitypub_actor_mode', array( self::class, 'pre_option_activitypub_actor_mode' ) );
		\add_filter( 'pre_option_activitypub_authorized_fetch', array( self::class, 'pre_option_activitypub_authorized_fetch' ) );
		\add_filter( 'pre_option_activitypub_shared_inbox', array( self::class, 'pre_option_activitypub_shared_inbox' ) );
		\add_filter( 'pre_option_activitypub_vary_header', array( self::class, 'pre_option_activitypub_vary_header' ) );

		\add_filter( 'pre_option_activitypub_allow_likes', array( self::class, 'maybe_disable_interactions' ) );
		\add_filter( 'pre_option_activitypub_allow_replies', array( self::class, 'maybe_disable_interactions' ) );
	}


	/**
	 * Pre-get option filter for the Actor-Mode.
	 *
	 * @param string|false $pre The pre-get option value.
	 *
	 * @return string|false The actor mode or false if it should not be filtered.
	 */
	public static function pre_option_activitypub_actor_mode( $pre ) {
		if ( \defined( 'ACTIVITYPUB_SINGLE_USER_MODE' ) && ACTIVITYPUB_SINGLE_USER_MODE ) {
			return ACTIVITYPUB_BLOG_MODE;
		}

		if ( \defined( 'ACTIVITYPUB_DISABLE_USER' ) && ACTIVITYPUB_DISABLE_USER ) {
			return ACTIVITYPUB_BLOG_MODE;
		}

		if ( \defined( 'ACTIVITYPUB_DISABLE_BLOG_USER' ) && ACTIVITYPUB_DISABLE_BLOG_USER ) {
			return ACTIVITYPUB_ACTOR_MODE;
		}

		return $pre;
	}

	/**
	 * Pre-get option filter for the Authorized Fetch.
	 *
	 * @param string $pre The pre-get option value.
	 *
	 * @return string If the constant is defined, return the value, otherwise return the pre-get option value.
	 */
	public static function pre_option_activitypub_authorized_fetch( $pre ) {
		if ( ! \defined( 'ACTIVITYPUB_AUTHORIZED_FETCH' ) ) {
			return $pre;
		}

		if ( ACTIVITYPUB_AUTHORIZED_FETCH ) {
			return '1';
		}

		return '0';
	}

	/**
	 * Pre-get option filter for the Shared Inbox.
	 *
	 * @param string $pre The pre-get option value.
	 *
	 * @return string If the constant is defined, return the value, otherwise return the pre-get option value.
	 */
	public static function pre_option_activitypub_shared_inbox( $pre ) {
		if ( ! \defined( 'ACTIVITYPUB_SHARED_INBOX_FEATURE' ) ) {
			return $pre;
		}

		if ( ACTIVITYPUB_SHARED_INBOX_FEATURE ) {
			return '1';
		}

		return '0';
	}

	/**
	 * Pre-get option filter for the Vary Header.
	 *
	 * @param string $pre The pre-get option value.
	 *
	 * @return string If the constant is defined, return the value, otherwise return the pre-get option value.
	 */
	public static function pre_option_activitypub_vary_header( $pre ) {
		if ( ! \defined( 'ACTIVITYPUB_SEND_VARY_HEADER' ) ) {
			return $pre;
		}

		if ( ACTIVITYPUB_SEND_VARY_HEADER ) {
			return '1';
		}

		return '0';
	}

	/**
	 * Disallow interactions if the constant is set.
	 *
	 * @param bool $pre_option The value of the option.
	 * @return bool|string The value of the option.
	 */
	public static function maybe_disable_interactions( $pre_option ) {
		if ( ACTIVITYPUB_DISABLE_INCOMING_INTERACTIONS ) {
			return '0';
		}

		return $pre_option;
	}
}
