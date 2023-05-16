<?php
namespace Activitypub\Collection;

use WP_Error;
use Exception;
use WP_Term_Query;
use Activitypub\Http;
use Activitypub\Webfinger;
use Activitypub\Model\Activity;
use Activitypub\Model\Follower;

use function Activitypub\is_tombstone;
use function Activitypub\get_remote_metadata_by_actor;

/**
 * ActivityPub Followers Collection
 *
 * @author Matthias Pfefferle
 */
class Followers {
	const TAXONOMY = 'activitypub-followers';

	/**
	 * Register WordPress hooks/actions and register Taxonomy
	 *
	 * @return void
	 */
	public static function init() {
		// register "followers" taxonomy
		self::register_taxonomy();

		\add_action( 'activitypub_inbox_follow', array( self::class, 'handle_follow_request' ), 10, 2 );
		\add_action( 'activitypub_inbox_undo', array( self::class, 'handle_undo_request' ), 10, 2 );

		\add_action( 'activitypub_followers_post_follow', array( self::class, 'send_follow_response' ), 10, 4 );
	}

	/**
	 * Register the "Followers" Taxonomy
	 *
	 * @return void
	 */
	public static function register_taxonomy() {
		$args = array(
			'labels'            => array(
				'name'          => _x( 'Followers', 'taxonomy general name', 'activitypub' ),
				'singular_name' => _x( 'Followers', 'taxonomy singular name', 'activitypub' ),
				'menu_name'     => __( 'Followers', 'activitypub' ),
			),
			'hierarchical'      => false,
			'show_ui'           => false,
			'show_in_menu'      => false,
			'show_in_nav_menus' => false,
			'show_admin_column' => false,
			'query_var'         => false,
			'rewrite'           => false,
			'public'            => false,
			'capabilities'      => array(
				'edit_terms' => null,
			),
		);

		register_taxonomy( self::TAXONOMY, 'user', $args );
		register_taxonomy_for_object_type( self::TAXONOMY, 'user' );

		register_term_meta(
			self::TAXONOMY,
			'name',
			array(
				'type'              => 'string',
				'single'            => true,
				'sanitize_callback' => function( $value ) {
					return sanitize_user( $value );
				},
			)
		);

		register_term_meta(
			self::TAXONOMY,
			'username',
			array(
				'type'              => 'string',
				'single'            => true,
				'sanitize_callback' => function( $value ) {
					return sanitize_user( $value, true );
				},
			)
		);

		register_term_meta(
			self::TAXONOMY,
			'avatar',
			array(
				'type'              => 'string',
				'single'            => true,
				'sanitize_callback' => function( $value ) {
					if ( filter_var( $value, FILTER_VALIDATE_URL ) === false ) {
						return '';
					}

					return esc_url_raw( $value );
				},
			)
		);

		register_term_meta(
			self::TAXONOMY,
			'inbox',
			array(
				'type'              => 'string',
				'single'            => true,
				'sanitize_callback' => function( $value ) {
					if ( filter_var( $value, FILTER_VALIDATE_URL ) === false ) {
						throw new Exception( '"inbox" has to be a valid URL' );
					}

					return esc_url_raw( $value );
				},
			)
		);

		register_term_meta(
			self::TAXONOMY,
			'shared_inbox',
			array(
				'type'              => 'string',
				'single'            => true,
				'sanitize_callback' => function( $value ) {
					if ( filter_var( $value, FILTER_VALIDATE_URL ) === false ) {
						return null;
					}

					return esc_url_raw( $value );
				},
			)
		);

		register_term_meta(
			self::TAXONOMY,
			'updated_at',
			array(
				'type'              => 'string',
				'single'            => true,
				'sanitize_callback' => function( $value ) {
					if ( ! is_numeric( $value ) && (int) $value !== $value ) {
						$value = \time();
					}

					return $value;
				},
			)
		);

		register_term_meta(
			self::TAXONOMY,
			'errors',
			array(
				'type'              => 'string',
				'single'            => false,
				'sanitize_callback' => function( $value ) {
					if ( ! is_string( $value ) ) {
						throw new Exception( 'Error message is no valid string' );
					}

					return esc_sql( $value );
				},
			)
		);

		do_action( 'activitypub_after_register_taxonomy' );
	}

	/**
	 * Handle the "Follow" Request
	 *
	 * @param array $object    The JSON "Follow" Activity
	 * @param int   $user_id The ID of the ID of the WordPress User
	 *
	 * @return void
	 */
	public static function handle_follow_request( $object, $user_id ) {
		// save follower
		$follower = self::add_follower( $user_id, $object['actor'] );

		do_action( 'activitypub_followers_post_follow', $object['actor'], $object, $user_id, $follower );
	}

	/**
	 * Handle "Unfollow" requests
	 *
	 * @param array $object  The JSON "Undo" Activity
	 * @param int   $user_id The ID of the ID of the WordPress User
	 */
	public static function handle_undo_request( $object, $user_id ) {
		if (
			isset( $object['object'] ) &&
			isset( $object['object']['type'] ) &&
			'Follow' === $object['object']['type']
		) {
			self::remove_follower( $user_id, $object['actor'] );
		}
	}

	/**
	 * Add new Follower
	 *
	 * @param int    $user_id The ID of the WordPress User
	 * @param string $actor   The Actor URL
	 *
	 * @return array|WP_Error The Follower (WP_Term array) or an WP_Error
	 */
	public static function add_follower( $user_id, $actor ) {
		$meta = get_remote_metadata_by_actor( $actor );

		if ( empty( $meta ) || ! is_array( $meta ) || is_wp_error( $meta ) ) {
			return $meta;
		}

		$follower = new Follower( $actor );
		$follower->from_meta( $meta );
		$follower->upsert();

		$result = wp_set_object_terms( $user_id, $follower->get_actor(), self::TAXONOMY, true );

		if ( is_wp_error( $result ) ) {
			return $result;
		} else {
			return $follower;
		}
	}

	/**
	 * Remove a Follower
	 *
	 * @param int    $user_id The ID of the WordPress User
	 * @param string $actor   The Actor URL
	 *
	 * @return bool|WP_Error True on success, false or WP_Error on failure.
	 */
	public static function remove_follower( $user_id, $actor ) {
		return wp_remove_object_terms( $user_id, $actor, self::TAXONOMY );
	}

	/**
	 * Remove a Follower
	 *
	 * @param int   $user_id The ID of the WordPress User
	 * @param string $actor  The Actor URL
	 *
	 * @return \Activitypub\Model\Follower The Follower object
	 */
	public static function get_follower( $user_id, $actor ) {
		$terms = new WP_Term_Query(
			array(
				'name'       => $actor,
				'taxonomy'   => self::TAXONOMY,
				'hide_empty' => false,
				'object_ids' => $user_id,
				'number'     => 1,
			)
		);

		$term = $terms->get_terms();

		if ( is_array( $term ) && ! empty( $term ) ) {
			$term = reset( $term );
			return new Follower( $term->name );
		}

		return null;
	}

	/**
	 * Send Accept response
	 *
	 * @param string                     $actor    The Actor URL
	 * @param array                      $object   The Activity object
	 * @param int                        $user_id  The ID of the WordPress User
	 * @param Activitypub\Model\Follower $follower The Follower object
	 *
	 * @return void
	 */
	public static function send_follow_response( $actor, $object, $user_id, $follower ) {
		if ( is_wp_error( $follower ) ) {
			// it is not even possible to send a "Reject" because
			// we can not get the Remote-Inbox
			return;
		}

		if ( isset( $object['user_id'] ) ) {
			unset( $object['user_id'] );
			unset( $object['@context'] );
		}

		// get inbox
		$inbox = $follower->get_inbox();

		// send "Accept" activity
		$activity = new Activity( 'Accept' );
		$activity->set_object( $object );
		$activity->set_actor( \get_author_posts_url( $user_id ) );
		$activity->set_to( $actor );
		$activity->set_id( \get_author_posts_url( $user_id ) . '#follow-' . \preg_replace( '~^https?://~', '', $actor ) );

		$activity = $activity->to_simple_json();
		$response = Http::post( $inbox, $activity, $user_id );
	}

	/**
	 * Get the Followers of a given user
	 *
	 * @param int    $user_id The ID of the WordPress User
	 * @param string $output  The output format, supported ARRAY_N, OBJECT and ACTIVITYPUB_OBJECT
	 * @param int    $number  Limts the result
	 * @param int    $offset  Offset
	 *
	 * @return array The Term list of Followers, the format depends on $output
	 */
	public static function get_followers( $user_id, $number = null, $offset = null, $args = array() ) {
		$defaults = array(
			'taxonomy'   => self::TAXONOMY,
			'hide_empty' => false,
			'object_ids' => $user_id,
			'number'     => $number,
			'offset'     => $offset,
			'orderby'    => 'id',
			'order'      => 'ASC',
		);

		$args  = wp_parse_args( $args, $defaults );
		$terms = new WP_Term_Query( $args );
		$items = array();

		foreach ( $terms->get_terms() as $follower ) {
			$items[] = new Follower( $follower->name ); // phpcs:ignore
		}

		return $items;
	}

	/**
	 * Count the total number of followers
	 *
	 * @param int $user_id The ID of the WordPress User
	 *
	 * @return int The number of Followers
	 */
	public static function count_followers( $user_id ) {
		return count( self::get_followers( $user_id ) );
	}

	/**
	 * Returns all Inboxes fo a Users Followers
	 *
	 * @param int $user_id The ID of the WordPress User
	 *
	 * @return array The list of Inboxes
	 */
	public static function get_inboxes( $user_id ) {
		// get all Followers of a ID of the WordPress User
		$terms = new WP_Term_Query(
			array(
				'taxonomy'   => self::TAXONOMY,
				'hide_empty' => false,
				'object_ids' => $user_id,
				'fields'     => 'ids',
				'meta_query' => array(
					array(
						'key'     => 'inbox',
						'compare' => 'EXISTS',
					),
				),
			)
		);

		$terms = $terms->get_terms();

		if ( ! $terms ) {
			return array();
		}

		global $wpdb;
		$results = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT meta_value FROM {$wpdb->termmeta}
				WHERE term_id IN (" . implode( ', ', array_fill( 0, count( $terms ), '%d' ) ) . ")
				AND meta_key = 'shared_inbox'
				AND meta_value IS NOT NULL",
				$terms
			)
		);

		return array_filter( $results );
	}

	/**
	 * Get all Followers that have not been updated for a given time
	 *
	 * @param enum $output     The output format, supported ARRAY_N, OBJECT and ACTIVITYPUB_OBJECT.
	 * @param int  $number     Limits the result.
	 * @param int  $older_than The time in seconds.
	 *
	 * @return mixed The Term list of Followers, the format depends on $output.
	 */
	public static function get_outdated_followers( $number = 50, $older_than = 604800 ) {
		$args = array(
			'taxonomy'   => self::TAXONOMY,
			'number'     => $number,
			'meta_key'   => 'updated_at',
			'orderby'    => 'meta_value_num',
			'order'      => 'DESC',
			'meta_query' => array(
				array(
					'key'        => 'updated_at',
					'value'      => time() - $older_than,
					'type'       => 'numeric',
					'compare'    => '<=',
				),
			),
		);

		$terms = new WP_Term_Query( $args );
		$items = array();

		foreach ( $terms->get_terms() as $follower ) {
			$items[] = new Follower( $follower->name ); // phpcs:ignore
		}

		return $items;
	}

	/**
	 * Get all Followers that had errors
	 *
	 * @param enum    $output The output format, supported ARRAY_N, OBJECT and ACTIVITYPUB_OBJECT
	 * @param integer $number The number of Followers to return.
	 *
	 * @return mixed The Term list of Followers, the format depends on $output.
	 */
	public static function get_faulty_followers( $number = 10 ) {
		$args = array(
			'taxonomy'   => self::TAXONOMY,
			'number'     => $number,
			'meta_query' => array(
				array(
					'key'        => 'errors',
					'compare'    => 'EXISTS',
				),
			),
		);

		$terms = new WP_Term_Query( $args );
		$items = array();

		foreach ( $terms->get_terms() as $follower ) {
			$items[] = new Follower( $follower->name ); // phpcs:ignore
		}

		return $items;
	}
}
