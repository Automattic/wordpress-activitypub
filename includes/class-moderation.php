<?php
/**
 * Moderation class file.
 *
 * @package Activitypub
 */

namespace Activitypub;

use Activitypub\Activity\Activity;
use Activitypub\Collection\Actors;

/**
 * ActivityPub Moderation class.
 *
 * Handles user-specific blocking and site-wide moderation.
 */
class Moderation {
	/**
	 * Post meta key for blocked actors.
	 */
	const BLOCKED_ACTORS_META_KEY = '_activitypub_blocked_by';

	/**
	 * User meta key for blocked keywords.
	 */
	const USER_META_KEYS = array(
		'domain'  => 'activitypub_blocked_domains',
		'keyword' => 'activitypub_blocked_keywords',
	);

	/**
	 * Option key for site-wide blocked keywords.
	 */
	const OPTION_KEYS = array(
		'domain'  => 'activitypub_site_blocked_domains',
		'keyword' => 'activitypub_site_blocked_keywords',
	);

	/**
	 * Check if an activity should be blocked for a specific user.
	 *
	 * @param Activity $activity The activity.
	 * @param int|null $user_id  The user ID to check blocks for.
	 * @return bool True if blocked, false otherwise.
	 */
	public static function activity_is_blocked( $activity, $user_id = null ) {
		if ( ! $activity instanceof Activity ) {
			return false;
		}

		// First check site-wide blocks (admin moderation).
		if ( self::activity_is_blocked_site_wide( $activity ) ) {
			return true;
		}

		// Then check user-specific blocks.
		if ( $user_id && self::activity_is_blocked_for_user( $activity, $user_id ) ) {
			return true;
		}

		$remote_addr = \sanitize_text_field( \wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) );
		$user_agent  = \sanitize_text_field( \wp_unslash( $_SERVER['HTTP_USER_AGENT'] ?? '' ) );

		// Fall back to WordPress comment disallowed list.
		return \wp_check_comment_disallowed_list( $activity->to_json( false ), '', '', $activity->get_content(), $remote_addr, $user_agent );
	}

	/**
	 * Check if an activity is blocked site-wide.
	 *
	 * @param Activity $activity The activity.
	 * @return bool True if blocked, false otherwise.
	 */
	public static function activity_is_blocked_site_wide( $activity ) {
		$blocks = self::get_site_blocks();

		return self::check_activity_against_blocks( $activity, $blocks['actors'], $blocks['domains'], $blocks['keywords'] );
	}

	/**
	 * Check if an activity is blocked for a specific user.
	 *
	 * @param Activity $activity The activity.
	 * @param int      $user_id  The user ID.
	 * @return bool True if blocked, false otherwise.
	 */
	public static function activity_is_blocked_for_user( $activity, $user_id ) {
		$blocks = self::get_user_blocks( $user_id );

		return self::check_activity_against_blocks( $activity, $blocks['actors'], $blocks['domains'], $blocks['keywords'] );
	}

	/**
	 * Add a block for a user.
	 *
	 * @param int    $user_id The user ID.
	 * @param string $type    The block type (actor, domain, keyword).
	 * @param string $value   The value to block.
	 * @return bool True on success, false on failure.
	 */
	public static function add_user_block( $user_id, $type, $value ) {
		switch ( $type ) {
			case 'actor':
				// Find or create actor post.
				$actor_post = Actors::fetch_remote_by_uri( $value );
				if ( \is_wp_error( $actor_post ) ) {
					return false;
				}

				$blocked = \get_post_meta( $actor_post->ID, self::BLOCKED_ACTORS_META_KEY, false );
				if ( ! \in_array( (string) $user_id, $blocked, true ) ) {
					/**
					 * Fired when an actor is blocked.
					 *
					 * @param string $value   The blocked actor URI.
					 * @param string $type    The block type (actor, domain, keyword).
					 * @param int    $user_id The user ID.
					 */
					\do_action( 'activitypub_add_user_block', $value, $type, $user_id );

					return (bool) \add_post_meta( $actor_post->ID, self::BLOCKED_ACTORS_META_KEY, (string) $user_id );
				}
				break;

			case 'domain':
			case 'keyword':
				$blocks = \get_user_meta( $user_id, self::USER_META_KEYS[ $type ], true ) ?: array(); // phpcs:ignore Universal.Operators.DisallowShortTernary.Found

				if ( ! \in_array( $value, $blocks, true ) ) {
					/**
					 * Fired when a domain or keyword is blocked.
					 *
					 * @param string $value   The blocked domain or keyword.
					 * @param string $type    The block type (actor, domain, keyword).
					 * @param int    $user_id The user ID.
					 */
					\do_action( 'activitypub_add_user_block', $value, $type, $user_id );

					$blocks[] = $value;
					return (bool) \update_user_meta( $user_id, self::USER_META_KEYS[ $type ], $blocks );
				}
				break;
		}

		return true; // Already blocked.
	}

	/**
	 * Remove a block for a user.
	 *
	 * @param int    $user_id The user ID.
	 * @param string $type    The block type (actor, domain, keyword).
	 * @param string $value   The value to unblock.
	 * @return bool True on success, false on failure.
	 */
	public static function remove_user_block( $user_id, $type, $value ) {
		switch ( $type ) {
			case 'actor':
				// If value is numeric, treat it as a post ID for direct removal.
				if ( \is_numeric( $value ) ) {
					$post_id = (int) $value;
				} else {
					// Otherwise, find the actor post by actor ID.
					$actor_post = Actors::fetch_remote_by_uri( $value );
					if ( \is_wp_error( $actor_post ) ) {
						return false;
					}
					$post_id = $actor_post->ID;
				}

				/**
				 * Fired when an actor is unblocked.
				 *
				 * @param string $value   The unblocked actor URI.
				 * @param string $type    The block type (actor, domain, keyword).
				 * @param int    $user_id The user ID.
				 */
				\do_action( 'activitypub_remove_user_block', $value, $type, $user_id );

				return \delete_post_meta( $post_id, self::BLOCKED_ACTORS_META_KEY, $user_id );

			case 'domain':
			case 'keyword':
				$blocks = \get_user_meta( $user_id, self::USER_META_KEYS[ $type ], true ) ?: array(); // phpcs:ignore Universal.Operators.DisallowShortTernary.Found
				$key    = \array_search( $value, $blocks, true );

				if ( false !== $key ) {
					/**
					 * Fired when a domain or keyword is unblocked.
					 *
					 * @param string $value   The unblocked domain or keyword.
					 * @param string $type    The block type (actor, domain, keyword).
					 * @param int    $user_id The user ID.
					 */
					\do_action( 'activitypub_remove_user_block', $value, $type, $user_id );

					unset( $blocks[ $key ] );
					return \update_user_meta( $user_id, self::USER_META_KEYS[ $type ], \array_values( $blocks ) );
				}
				break;
		}

		return true; // Not blocked anyway.
	}

	/**
	 * Get all blocks for a user.
	 *
	 * @param int $user_id The user ID.
	 * @return array Array of blocks organized by type.
	 */
	public static function get_user_blocks( $user_id ) {
		return array(
			'actors'   => self::get_blocked_actors( $user_id ),
			'domains'  => \get_user_meta( $user_id, self::USER_META_KEYS['domain'], true ) ?: array(), // phpcs:ignore Universal.Operators.DisallowShortTernary.Found
			'keywords' => \get_user_meta( $user_id, self::USER_META_KEYS['keyword'], true ) ?: array(), // phpcs:ignore Universal.Operators.DisallowShortTernary.Found
		);
	}

	/**
	 * Add a site-wide block.
	 *
	 * @param string $type  The block type (actor, domain, keyword).
	 * @param string $value The value to block.
	 * @return bool True on success, false on failure.
	 */
	public static function add_site_block( $type, $value ) {
		switch ( $type ) {
			case 'actor':
				// Site-wide actor blocking uses the BLOG_USER_ID.
				return self::add_user_block( Actors::BLOG_USER_ID, 'actor', $value );

			case 'domain':
			case 'keyword':
				$blocks = \get_option( self::OPTION_KEYS[ $type ], array() );

				if ( ! \in_array( $value, $blocks, true ) ) {
					/**
					 * Fired when a domain or keyword is blocked site-wide.
					 *
					 * @param string $value The blocked domain or keyword.
					 * @param string $type  The block type (actor, domain, keyword).
					 */
					\do_action( 'activitypub_add_site_block', $value, $type );

					$blocks[] = $value;
					return \update_option( self::OPTION_KEYS[ $type ], $blocks );
				}
				break;
		}

		return true; // Already blocked.
	}

	/**
	 * Remove a site-wide block.
	 *
	 * @param string $type  The block type (actor, domain, keyword).
	 * @param string $value The value to unblock.
	 * @return bool True on success, false on failure.
	 */
	public static function remove_site_block( $type, $value ) {
		switch ( $type ) {
			case 'actor':
				// Site-wide actor unblocking uses the BLOG_USER_ID.
				return self::remove_user_block( Actors::BLOG_USER_ID, 'actor', $value );

			case 'domain':
			case 'keyword':
				$blocks = \get_option( self::OPTION_KEYS[ $type ], array() );
				$key    = \array_search( $value, $blocks, true );

				if ( false !== $key ) {
					/**
					 * Fired when a domain or keyword is unblocked site-wide.
					 *
					 * @param string $value The unblocked domain or keyword.
					 * @param string $type  The block type (actor, domain, keyword).
					 */
					\do_action( 'activitypub_remove_site_block', $value, $type );

					unset( $blocks[ $key ] );
					return \update_option( self::OPTION_KEYS[ $type ], \array_values( $blocks ) );
				}
				break;
		}

		return true; // Not blocked anyway.
	}

	/**
	 * Get all site-wide blocks.
	 *
	 * @return array Array of blocks organized by type.
	 */
	public static function get_site_blocks() {
		return array(
			'actors'   => self::get_blocked_actors( Actors::BLOG_USER_ID ),
			'domains'  => \get_option( self::OPTION_KEYS['domain'], array() ),
			'keywords' => \get_option( self::OPTION_KEYS['keyword'], array() ),
		);
	}

	/**
	 * Check activity against blocklists.
	 *
	 * @param Activity $activity         The activity.
	 * @param array    $blocked_actors   List of blocked actors.
	 * @param array    $blocked_domains  List of blocked domains.
	 * @param array    $blocked_keywords List of blocked keywords.
	 * @return bool True if blocked, false otherwise.
	 */
	private static function check_activity_against_blocks( $activity, $blocked_actors, $blocked_domains, $blocked_keywords ) {
		$has_object = \is_object( $activity->get_object() );

		// Extract actor information.
		$actor_id = object_to_uri( $activity->get_actor() );

		// Check blocked actors.
		if ( $actor_id && \in_array( $actor_id, $blocked_actors, true ) ) {
			return true;
		}

		// Check blocked domains.
		$urls = array(
			\wp_parse_url( $actor_id, PHP_URL_HOST ),
			\wp_parse_url( $activity->get_id(), PHP_URL_HOST ),
			\wp_parse_url( object_to_uri( $activity->get_object() ) ?? '', PHP_URL_HOST ),
		);
		foreach ( $blocked_domains as $domain ) {
			if ( \in_array( $domain, $urls, true ) ) {
				return true;
			}
		}

		// Check blocked keywords in activity content.
		if ( $has_object ) {
			$content = $activity->get_object()->get_content() . ' ' . $activity->get_object()->get_summary() . ' ' . $activity->get_object()->get_name();
			if ( is_actor( $activity->get_object() ) ) {
				$content .= ' ' . $activity->get_object()->get_preferred_username();
			}

			foreach ( $blocked_keywords as $keyword ) {
				if ( \stripos( $content, $keyword ) !== false ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Get the blocked actors of a given user, along with a total count for pagination purposes.
	 *
	 * @param int|null $user_id The ID of the WordPress User.
	 * @param int      $number  Maximum number of results to return.
	 * @param int      $page    Page number.
	 * @param array    $args    The WP_Query arguments.
	 *
	 * @return array {
	 *      Data about the blocked actors.
	 *
	 *      @type \WP_Post[] $blocked_actors List of blocked Actor WP_Post objects.
	 *      @type int        $total         Total number of blocked actors.
	 *  }
	 */
	public static function get_blocked_actors_with_count( $user_id, $number = -1, $page = null, $args = array() ) {
		$defaults = array(
			'post_type'      => Actors::POST_TYPE,
			'posts_per_page' => $number,
			'paged'          => $page,
			'orderby'        => 'ID',
			'order'          => 'DESC',
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			'meta_query'     => array(
				array(
					'key'   => self::BLOCKED_ACTORS_META_KEY,
					'value' => $user_id,
				),
			),
		);

		$args           = \wp_parse_args( $args, $defaults );
		$query          = new \WP_Query( $args );
		$total          = $query->found_posts;
		$blocked_actors = \array_filter( $query->posts );

		return \compact( 'blocked_actors', 'total' );
	}

	/**
	 * Get the blocked actors of a given user.
	 *
	 * @param int|null $user_id The ID of the WordPress User.
	 * @param int      $number  Maximum number of results to return.
	 * @param int      $page    Page number.
	 * @param array    $args    The WP_Query arguments.
	 *
	 * @return \WP_Post[] List of blocked Actors.
	 */
	public static function get_blocked_actors( $user_id, $number = -1, $page = null, $args = array() ) {
		return self::get_blocked_actors_with_count( $user_id, $number, $page, $args )['blocked_actors'];
	}
}
