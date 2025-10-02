<?php
/**
 * Post Types class for consolidating all custom post type and related meta registrations.
 *
 * @package Activitypub
 */

namespace Activitypub;

use Activitypub\Activity\Activity;
use Activitypub\Collection\Extra_Fields;
use Activitypub\Collection\Followers;
use Activitypub\Collection\Inbox;
use Activitypub\Collection\Outbox;
use Activitypub\Collection\Remote_Actors;

/**
 * Post Types class.
 */
class Post_Types {
	/**
	 * Initialize the class, registering all custom post types and post meta.
	 */
	public static function init() {
		\add_action( 'init', array( self::class, 'register_remote_actors_post_type' ), 11 );
		\add_action( 'init', array( self::class, 'register_inbox_post_type' ), 11 );
		\add_action( 'init', array( self::class, 'register_outbox_post_type' ), 11 );
		\add_action( 'init', array( self::class, 'register_extra_fields_post_types' ), 11 );
		\add_action( 'init', array( self::class, 'register_activitypub_post_meta' ), 11 );

		\add_action( 'rest_api_init', array( self::class, 'register_ap_actor_rest_field' ) );

		\add_filter( 'activitypub_get_actor_extra_fields', array( Extra_Fields::class, 'default_actor_extra_fields' ), 10, 2 );

		\add_filter( 'add_post_metadata', array( self::class, 'prevent_empty_post_meta' ), 10, 4 );
		\add_filter( 'update_post_metadata', array( self::class, 'prevent_empty_post_meta' ), 10, 4 );
		\add_filter( 'default_post_metadata', array( self::class, 'default_post_meta_data' ), 10, 3 );

		// Add support for ActivityPub to custom post types.
		foreach ( \get_option( 'activitypub_support_post_types', array( 'post' ) ) as $post_type ) {
			\add_post_type_support( $post_type, 'activitypub' );
		}
	}

	/**
	 * Register the Remote Actors post type and its meta.
	 */
	public static function register_remote_actors_post_type() {
		\register_post_type(
			Remote_Actors::POST_TYPE,
			array(
				'labels'           => array(
					'name'          => \_x( 'Followers', 'post_type plural name', 'activitypub' ),
					'singular_name' => \_x( 'Follower', 'post_type single name', 'activitypub' ),
				),
				'public'           => false,
				'show_in_rest'     => true,
				'hierarchical'     => false,
				'rewrite'          => false,
				'query_var'        => false,
				'delete_with_user' => false,
				'can_export'       => true,
				'supports'         => array(),
			)
		);

		// Register meta for Remote Actors post type.
		\register_post_meta(
			Remote_Actors::POST_TYPE,
			'_activitypub_inbox',
			array(
				'type'              => 'string',
				'single'            => true,
				'sanitize_callback' => 'sanitize_url',
			)
		);

		\register_post_meta(
			Remote_Actors::POST_TYPE,
			'_activitypub_errors',
			array(
				'type'              => 'string',
				'single'            => false,
				'sanitize_callback' => 'sanitize_text_field',
			)
		);

		\register_post_meta(
			Remote_Actors::POST_TYPE,
			Followers::FOLLOWER_META_KEY,
			array(
				'type'              => 'string',
				'single'            => false,
				'sanitize_callback' => 'sanitize_text_field',
			)
		);
	}

	/**
	 * Register the Inbox post type and its meta.
	 */
	public static function register_inbox_post_type() {
		\register_post_type(
			Inbox::POST_TYPE,
			array(
				'labels'              => array(
					'name'          => \_x( 'Inbox', 'post_type plural name', 'activitypub' ),
					'singular_name' => \_x( 'Inbox Item', 'post_type single name', 'activitypub' ),
				),
				'capabilities'        => array(
					'create_posts' => false,
				),
				'map_meta_cap'        => true,
				'public'              => false,
				'show_in_rest'        => true,
				'rewrite'             => false,
				'query_var'           => false,
				'supports'            => array( 'title', 'editor', 'author', 'custom-fields' ),
				'delete_with_user'    => true,
				'can_export'          => true,
				'exclude_from_search' => true,
			)
		);

		// Register meta for Inbox post type.
		\register_post_meta(
			Inbox::POST_TYPE,
			'_activitypub_object_id',
			array(
				'type'              => 'string',
				'single'            => true,
				'description'       => 'The ID (ActivityPub URI) of the object that the inbox item is about.',
				'sanitize_callback' => 'sanitize_url',
			)
		);

		\register_post_meta(
			Inbox::POST_TYPE,
			'_activitypub_activity_type',
			array(
				'type'              => 'string',
				'description'       => 'The type of the activity',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => function ( $value ) {
					$value  = ucfirst( strtolower( $value ) );
					$schema = array(
						'type'    => 'string',
						'enum'    => Activity::TYPES,
						'default' => 'Create',
					);

					if ( \is_wp_error( \rest_validate_enum( $value, $schema, '' ) ) ) {
						return $schema['default'];
					}

					return $value;
				},
			)
		);

		\register_post_meta(
			Inbox::POST_TYPE,
			'_activitypub_activity_actor',
			array(
				'type'              => 'string',
				'single'            => true,
				'description'       => 'The type of the local actor that received the activity.',
				'show_in_rest'      => true,
				'sanitize_callback' => function ( $value ) {
					$schema = array(
						'type'    => 'string',
						'enum'    => array( 'application', 'blog', 'user' ),
						'default' => 'user',
					);

					if ( \is_wp_error( \rest_validate_enum( $value, $schema, '' ) ) ) {
						return $schema['default'];
					}

					return $value;
				},
			)
		);

		\register_post_meta(
			Inbox::POST_TYPE,
			'_activitypub_activity_remote_actor',
			array(
				'type'              => 'string',
				'single'            => true,
				'description'       => 'The ID (ActivityPub URI) of the remote actor that sent the activity.',
				'sanitize_callback' => 'sanitize_url',
			)
		);

		\register_post_meta(
			Inbox::POST_TYPE,
			'activitypub_content_visibility',
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => function ( $value ) {
					$schema = array(
						'type'    => 'string',
						'enum'    => array( ACTIVITYPUB_CONTENT_VISIBILITY_PUBLIC, ACTIVITYPUB_CONTENT_VISIBILITY_QUIET_PUBLIC, ACTIVITYPUB_CONTENT_VISIBILITY_PRIVATE, ACTIVITYPUB_CONTENT_VISIBILITY_LOCAL ),
						'default' => ACTIVITYPUB_CONTENT_VISIBILITY_PUBLIC,
					);

					if ( \is_wp_error( \rest_validate_enum( $value, $schema, '' ) ) ) {
						return $schema['default'];
					}

					return $value;
				},
			)
		);
	}

	/**
	 * Register the Outbox post type and its meta.
	 */
	public static function register_outbox_post_type() {
		\register_post_type(
			Outbox::POST_TYPE,
			array(
				'labels'              => array(
					'name'          => \_x( 'Outbox', 'post_type plural name', 'activitypub' ),
					'singular_name' => \_x( 'Outbox Item', 'post_type single name', 'activitypub' ),
				),
				'capabilities'        => array(
					'create_posts' => false,
				),
				'map_meta_cap'        => true,
				'public'              => false,
				'show_in_rest'        => true,
				'rewrite'             => false,
				'query_var'           => false,
				'supports'            => array( 'title', 'editor', 'author', 'custom-fields' ),
				'delete_with_user'    => true,
				'can_export'          => true,
				'exclude_from_search' => true,
			)
		);

		// Register meta for Outbox post type.
		/**
		 * Register Activity Type meta for Outbox items.
		 *
		 * @see https://www.w3.org/TR/activitystreams-vocabulary/#activity-types
		 */
		\register_post_meta(
			Outbox::POST_TYPE,
			'_activitypub_activity_type',
			array(
				'type'              => 'string',
				'description'       => 'The type of the activity',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => function ( $value ) {
					$value  = ucfirst( strtolower( $value ) );
					$schema = array(
						'type'    => 'string',
						'enum'    => Activity::TYPES,
						'default' => 'Announce',
					);

					if ( \is_wp_error( \rest_validate_enum( $value, $schema, '' ) ) ) {
						return $schema['default'];
					}

					return $value;
				},
			)
		);

		\register_post_meta(
			Outbox::POST_TYPE,
			'_activitypub_activity_actor',
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => function ( $value ) {
					$schema = array(
						'type'    => 'string',
						'enum'    => array( 'application', 'blog', 'user' ),
						'default' => 'user',
					);

					if ( \is_wp_error( \rest_validate_enum( $value, $schema, '' ) ) ) {
						return $schema['default'];
					}

					return $value;
				},
			)
		);

		\register_post_meta(
			Outbox::POST_TYPE,
			'_activitypub_outbox_offset',
			array(
				'type'              => 'integer',
				'single'            => true,
				'description'       => 'Keeps track of the followers offset when processing outbox items.',
				'sanitize_callback' => 'absint',
				'default'           => 0,
			)
		);

		\register_post_meta(
			Outbox::POST_TYPE,
			'_activitypub_object_id',
			array(
				'type'              => 'string',
				'single'            => true,
				'description'       => 'The ID (ActivityPub URI) of the object that the outbox item is about.',
				'sanitize_callback' => 'sanitize_url',
			)
		);

		\register_post_meta(
			Outbox::POST_TYPE,
			'activitypub_content_visibility',
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => function ( $value ) {
					$schema = array(
						'type'    => 'string',
						'enum'    => array( ACTIVITYPUB_CONTENT_VISIBILITY_PUBLIC, ACTIVITYPUB_CONTENT_VISIBILITY_QUIET_PUBLIC, ACTIVITYPUB_CONTENT_VISIBILITY_PRIVATE, ACTIVITYPUB_CONTENT_VISIBILITY_LOCAL ),
						'default' => ACTIVITYPUB_CONTENT_VISIBILITY_PUBLIC,
					);

					if ( \is_wp_error( \rest_validate_enum( $value, $schema, '' ) ) ) {
						return $schema['default'];
					}

					return $value;
				},
			)
		);
	}

	/**
	 * Register the Extra Fields post types.
	 */
	public static function register_extra_fields_post_types() {
		$extra_field_args = array(
			'labels'              => array(
				'name'          => \_x( 'Extra fields', 'post_type plural name', 'activitypub' ),
				'singular_name' => \_x( 'Extra field', 'post_type single name', 'activitypub' ),
				'add_new'       => \__( 'Add new', 'activitypub' ),
				'add_new_item'  => \__( 'Add new extra field', 'activitypub' ),
				'new_item'      => \__( 'New extra field', 'activitypub' ),
				'edit_item'     => \__( 'Edit extra field', 'activitypub' ),
				'view_item'     => \__( 'View extra field', 'activitypub' ),
				'all_items'     => \__( 'All extra fields', 'activitypub' ),
			),
			'public'              => false,
			'hierarchical'        => false,
			'query_var'           => false,
			'has_archive'         => false,
			'publicly_queryable'  => false,
			'show_in_menu'        => false,
			'delete_with_user'    => true,
			'can_export'          => true,
			'exclude_from_search' => true,
			'show_in_rest'        => true,
			'map_meta_cap'        => true,
			'show_ui'             => true,
			'supports'            => array( 'title', 'editor', 'page-attributes' ),
		);

		\register_post_type( Extra_Fields::USER_POST_TYPE, $extra_field_args );
		\register_post_type( Extra_Fields::BLOG_POST_TYPE, $extra_field_args );

		/**
		 * Fires after ActivityPub custom post types have been registered.
		 */
		\do_action( 'activitypub_after_register_post_type' );
	}

	/**
	 * Register post meta for ActivityPub supported post types.
	 */
	public static function register_activitypub_post_meta() {
		$ap_post_types = \get_post_types_by_support( 'activitypub' );
		foreach ( $ap_post_types as $post_type ) {
			\register_post_meta(
				$post_type,
				'activitypub_content_warning',
				array(
					'show_in_rest'      => true,
					'single'            => true,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
				)
			);

			\register_post_meta(
				$post_type,
				'activitypub_content_visibility',
				array(
					'type'              => 'string',
					'single'            => true,
					'show_in_rest'      => true,
					'sanitize_callback' => function ( $value ) {
						$schema = array(
							'type'    => 'string',
							'enum'    => array( ACTIVITYPUB_CONTENT_VISIBILITY_PUBLIC, ACTIVITYPUB_CONTENT_VISIBILITY_QUIET_PUBLIC, ACTIVITYPUB_CONTENT_VISIBILITY_PRIVATE, ACTIVITYPUB_CONTENT_VISIBILITY_LOCAL ),
							'default' => ACTIVITYPUB_CONTENT_VISIBILITY_PUBLIC,
						);

						if ( \is_wp_error( \rest_validate_enum( $value, $schema, '' ) ) ) {
							return $schema['default'];
						}

						return $value;
					},
				)
			);

			\register_post_meta(
				$post_type,
				'activitypub_max_image_attachments',
				array(
					'type'              => 'integer',
					'single'            => true,
					'show_in_rest'      => true,
					'default'           => \get_option( 'activitypub_max_image_attachments', ACTIVITYPUB_MAX_IMAGE_ATTACHMENTS ),
					'sanitize_callback' => 'absint',
				)
			);

			\register_post_meta(
				$post_type,
				'activitypub_interaction_policy_quote',
				array(
					'type'              => 'string',
					'single'            => true,
					'show_in_rest'      => true,
					'sanitize_callback' => function ( $value ) {
						$schema = array(
							'type'    => 'string',
							'enum'    => array( ACTIVITYPUB_INTERACTION_POLICY_ANYONE, ACTIVITYPUB_INTERACTION_POLICY_FOLLOWERS, ACTIVITYPUB_INTERACTION_POLICY_ME ),
							'default' => ACTIVITYPUB_INTERACTION_POLICY_ANYONE,
						);

						if ( \is_wp_error( \rest_validate_enum( $value, $schema, '' ) ) ) {
							return $schema['default'];
						}

						return $value;
					},
				)
			);
		}
	}

	/**
	 * Register REST field for ap_actor posts.
	 */
	public static function register_ap_actor_rest_field() {
		\register_rest_field(
			Remote_Actors::POST_TYPE,
			'activitypub_json',
			array(
				/**
				 * Get the raw post content without WordPress content filtering.
				 *
				 * @param array $response Prepared response array.
				 * @return string The raw post content.
				 */
				'get_callback' => function ( $response ) {
					return \get_post_field( 'post_content', $response['id'] );
				},
				'schema'       => array(
					'description' => 'Raw ActivityPub JSON data without WordPress content filtering',
					'type'        => 'string',
					'context'     => array( 'view', 'edit' ),
				),
			)
		);
	}

	/**
	 * Prevent empty or default meta values.
	 *
	 * @param null|bool $check      Whether to allow updating metadata for the given type.
	 * @param int       $object_id  ID of the object metadata is for.
	 * @param string    $meta_key   Metadata key.
	 * @param mixed     $meta_value Metadata value. Must be serializable if non-scalar.
	 */
	public static function prevent_empty_post_meta( $check, $object_id, $meta_key, $meta_value ) {
		$post_metas = array(
			'activitypub_content_visibility'       => '',
			'activitypub_content_warning'          => '',
			'activitypub_interaction_policy_quote' => ACTIVITYPUB_INTERACTION_POLICY_ANYONE,
			'activitypub_max_image_attachments'    => (string) \get_option( 'activitypub_max_image_attachments', ACTIVITYPUB_MAX_IMAGE_ATTACHMENTS ),
		);

		if ( isset( $post_metas[ $meta_key ] ) && $post_metas[ $meta_key ] === (string) $meta_value ) {
			if ( 'update_post_metadata' === current_action() ) {
				\delete_post_meta( $object_id, $meta_key );
			}

			$check = true;
		}

		return $check;
	}

	/**
	 * Adjusts default post meta values.
	 *
	 * @param mixed  $meta_value The meta value.
	 * @param int    $object_id  ID of the object metadata is for.
	 * @param string $meta_key   Metadata key.
	 *
	 * @return string|null The meta value.
	 */
	public static function default_post_meta_data( $meta_value, $object_id, $meta_key ) {
		if ( 'activitypub_content_visibility' !== $meta_key ) {
			return $meta_value;
		}

		// If meta value is already explicitly set, respect the author's choice.
		if ( $meta_value ) {
			return $meta_value;
		}

		// If the post is federated, return the default visibility.
		if ( 'federated' === \get_post_meta( $object_id, 'activitypub_status', true ) ) {
			return $meta_value;
		}

		// If the post is not federated and older than a month, return local visibility.
		if ( \get_the_date( 'U', $object_id ) < \strtotime( '-1 month' ) ) {
			return ACTIVITYPUB_CONTENT_VISIBILITY_LOCAL;
		}

		return $meta_value;
	}
}
