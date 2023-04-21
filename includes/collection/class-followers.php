<?php
namespace Activitypub\Collection;

use WP_Error;
use WP_Term_Query;
use Activitypub\Webfinger;
use Activitypub\Model\Activity;

use function Activitypub\safe_remote_get;
use function Activitypub\safe_remote_post;
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
			'user_id',
			array(
				'type'              => 'string',
				'single'            => true,
				//'sanitize_callback' => array( self::class, 'validate_username' ),
			)
		);

		register_term_meta(
			self::TAXONOMY,
			'name',
			array(
				'type'              => 'string',
				'single'            => true,
				//'sanitize_callback' => array( self::class, 'validate_displayname' ),
			)
		);

		register_term_meta(
			self::TAXONOMY,
			'username',
			array(
				'type'              => 'string',
				'single'            => true,
				//'sanitize_callback' => array( self::class, 'validate_username' ),
			)
		);

		register_term_meta(
			self::TAXONOMY,
			'avatar',
			array(
				'type'              => 'string',
				'single'            => true,
				//'sanitize_callback' => array( self::class, 'validate_avatar' ),
			)
		);

		register_term_meta(
			self::TAXONOMY,
			'created_at',
			array(
				'type'              => 'string',
				'single'            => true,
				//'sanitize_callback' => array( self::class, 'validate_created_at' ),
			)
		);

		register_term_meta(
			self::TAXONOMY,
			'inbox',
			array(
				'type'              => 'string',
				'single'            => true,
				//'sanitize_callback' => array( self::class, 'validate_created_at' ),
			)
		);

		do_action( 'activitypub_after_register_taxonomy' );
	}

	/**
	 * Handle the "Follow" Request
	 *
	 * @param array $object    The JSON "Follow" Activity
	 * @param int   $user_id The ID of the WordPress User
	 *
	 * @return void
	 */
	public static function handle_follow_request( $object, $user_id ) {
		// save follower
		self::add_follower( $user_id, $object['actor'] );
	}

	/**
	 * Handles "Unfollow" requests
	 *
	 * @param  array $object  The JSON "Undo" Activity
	 * @param  int   $user_id The ID of the WordPress User
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
	 * Add a new Follower
	 *
	 * @param int    $user_id The WordPress user
	 * @param string $actor   The Actor URL
	 * @return void
	 */
	public static function add_follower( $user_id, $actor ) {
		$remote_data = get_remote_metadata_by_actor( $actor );

		if ( ! $remote_data || is_wp_error( $remote_data ) || ! is_array( $remote_data ) ) {
			$remote_data = array();
		}

		$term = term_exists( $actor, self::TAXONOMY );

		if ( ! $term ) {
			$term = wp_insert_term(
				$actor,
				self::TAXONOMY,
				array(
					'slug'        => sanitize_title( $actor ),
					'description' => wp_json_encode( $remote_data ),
				)
			);
		}

		$term_id = $term['term_id'];

		$map_meta = array(
			'name'              => 'name',
			'preferredUsername' => 'username',
			'inbox'             => 'inbox',
		);

		foreach ( $map_meta as $remote => $internal ) {
			if ( ! empty( $remote_data[ $remote ] ) ) {
				update_term_meta( $term_id, $internal, esc_html( $remote_data[ $remote ] ), true );
			}
		}

		if ( ! empty( $remote_data['icon']['url'] ) ) {
			update_term_meta( $term_id, 'avatar', esc_url_raw( $remote_data['icon']['url'] ), true );
		}

		wp_set_object_terms( $user_id, $actor, self::TAXONOMY, true );
	}

	public static function remove_follower( $user_id, $actor ) {
		wp_remove_object_terms( $user_id, $actor, self::TAXONOMY );
	}

	/**
	 * Undocumented function
	 *
	 * @return void
	 */
	public static function send_ack() {
		// get inbox
		$inbox = \Activitypub\get_inbox_by_actor( $object['actor'] );

		// send "Accept" activity
		$activity = new Activity( 'Accept' );
		$activity->set_object( $object );
		$activity->set_actor( \get_author_posts_url( $user_id ) );
		$activity->set_to( $object['actor'] );
		$activity->set_id( \get_author_posts_url( $user_id ) . '#follow-' . \preg_replace( '~^https?://~', '', $object['actor'] ) );

		$activity = $activity->to_simple_json();
		$response = safe_remote_post( $inbox, $activity, $user_id );
	}

	/**
	 * Get the Followers of a given user
	 *
	 * @param  int   $user_id
	 * @param  int   $number
	 * @param  int   $offset
	 * @return array The Term list of Followers
	 */
	public static function get_followers( $user_id, $number = null, $offset = null ) {
		//self::migrate_followers( $user_id );

		$terms = new WP_Term_Query(
			array(
				'taxonomy'   => self::TAXONOMY,
				'hide_empty' => false,
				'object_ids' => $user_id,
				'number'     => $number,
				'offset'     => $offset,
			)
		);

		return $terms->get_terms();
	}

	/**
	 * Count the total number of followers
	 *
	 * @param  int $user_id The WordPress user
	 * @return int          The number of Followers
	 */
	public static function count_followers( $user_id ) {
		return count( self::get_followers( $user_id ) );
	}

	public static function migrate_followers( $user_id ) {
		$followes = get_user_meta( $user_id, 'activitypub_followers', true );

		if ( $followes ) {
			foreach ( $followes as $follower ) {
				self::add_follower( $user_id, $follower );
			}
		}
	}
}
